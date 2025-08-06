<?php
require_once 'config/config.php';

global $koneksi, $settingsManager;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($koneksi) {
    initVisitorTracking($koneksi);
}

$articlesPerPage = getSetting('articles_per_page', 10);
$showAuthor = getSetting('show_author', '1') == '1';
$showDate = getSetting('show_date', '1') == '1';
$showCategory = getSetting('show_category', '1') == '1';

$siteInfo = getSiteInfo();

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $articlesPerPage;

$articles = [];
$total_articles = 0;

if ($koneksi) {
    try {
        $count_query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
        $count_result = mysqli_query($koneksi, $count_query);
        if ($count_result) {
            $total_articles = mysqli_fetch_assoc($count_result)['total'];
        }

        $query = "SELECT a.article_id, a.title, a.content, a.image_url, a.image_filename, a.publication_date, 
                         u.username as author_name, c.name as category_name
                  FROM articles a
                  LEFT JOIN users u ON a.author_id = u.id
                  LEFT JOIN categories c ON a.category_id = c.category_id
                  WHERE a.article_status = 'published'
                  ORDER BY a.publication_date DESC 
                  LIMIT $articlesPerPage OFFSET $offset";

        $news_result = mysqli_query($koneksi, $query);
        
        if ($news_result) {
            while ($row = mysqli_fetch_assoc($news_result)) {
                if (!empty($row['image_filename'])) {
                    $row['display_image'] = 'uploads/articles/' . $row['image_filename'];
                } elseif (!empty($row['image_url'])) {
                    $row['display_image'] = $row['image_url'];
                } else {
                    $row['display_image'] = null;
                }
                
                $articles[] = $row;
            }
        }

    } catch (Exception $e) {
        error_log("Error fetching articles: " . $e->getMessage());
    }
}

$popular_articles = [];
if ($koneksi) {
    try {
        $popular_query = "SELECT article_id, title, publication_date 
                         FROM articles 
                         WHERE article_status = 'published'
                         ORDER BY publication_date DESC 
                         LIMIT 5";

        $popular_result = mysqli_query($koneksi, $popular_query);
        
        if ($popular_result) {
            while ($row = mysqli_fetch_assoc($popular_result)) {
                $popular_articles[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching popular articles: " . $e->getMessage());
    }
}

$total_pages = $total_articles > 0 ? ceil($total_articles / $articlesPerPage) : 1;

$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_article_id':
            $error_message = 'ID Artikel tidak valid';
            break;
        case 'article_not_found':
            $error_message = 'Artikel tidak ditemukan';
            break;
        case 'database_error':
            $error_message = 'Kesalahan database';
            break;
    }
}

require_once 'header.php';
?>

<!-- Main Content -->
<div class="container main-content mt-2">
    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Hero Carousel -->
            <?php if (!empty($articles)): ?>
            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                <div class="carousel-inner">
                    <?php for ($i = 0; $i < min(3, count($articles)); $i++): ?>
                    <?php $article = $articles[$i]; ?>
                    <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                        <?php if (!empty($article['display_image'])): ?>
                        <img src="<?php echo htmlspecialchars($article['display_image']); ?>" class="d-block w-100"
                            alt="<?php echo htmlspecialchars($article['title']); ?>">
                        <?php else: ?>
                        <img src="img/placeholder-news.jpg" class="d-block w-100" alt="No Image">
                        <?php endif; ?>
                        <div class="carousel-caption">
                            <h5><?php echo htmlspecialchars($article['title']); ?></h5>
                            <p><?php echo htmlspecialchars(substr(strip_tags($article['content']), 0, 100)); ?>...</p>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Kembali</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Lanjut</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- News Feed -->
            <div class="news-feed mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Berita Terbaru</h4>
                    <span class="badge bg-secondary"><?php echo $total_articles; ?> artikel</span>
                </div>

                <?php if (!empty($articles)): ?>
                <?php foreach ($articles as $news): ?>
                <div class="news-item">
                    <?php if (!empty($news['image_filename'])): ?>
                    <img src="uploads/articles/<?php echo htmlspecialchars($news['image_filename']); ?>"
                        alt="<?php echo htmlspecialchars($news['title']); ?>" class="news-image">
                    <?php elseif (!empty($news['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($news['image_url']); ?>"
                        alt="<?php echo htmlspecialchars($news['title']); ?>" class="news-image">
                    <?php else: ?>
                    <div class="news-image bg-secondary d-flex align-items-center justify-content-center">
                        <i class="fas fa-newspaper fa-2x text-white"></i>
                    </div>
                    <?php endif; ?>

                    <div class="news-content">
                        <div class="news-meta">
                            <?php echo date('d F Y', strtotime($news['publication_date'])); ?>
                        </div>
                        <h6>
                            <a href="artikel.php?id=<?php echo $news['article_id']; ?>">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </a>
                        </h6>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

                <?php else: ?>
                <div class="alert alert-info">Belum ada berita tersedia</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="sidebar">
                <div class="sidebar-header">
                    Berita Terpopuler
                </div>

                <?php if (!empty($popular_articles)): ?>
                <?php $counter = 1; ?>
                <?php foreach ($popular_articles as $popular): ?>
                <div class="trending-item">
                    <div class="trending-number"><?php echo $counter; ?></div>
                    <div class="trending-content">
                        <a href="artikel.php?id=<?php echo $popular['article_id']; ?>">
                            <?php echo htmlspecialchars($popular['title']); ?>
                        </a>
                        <div class="text-muted small mt-1">
                            <?php echo date('d M Y', strtotime($popular['publication_date'])); ?>
                        </div>
                    </div>
                </div>
                <?php $counter++; ?>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="alert alert-info">Belum ada berita populer</div>
                <?php endif; ?>
            </div>

            <!-- Categories Widget -->
            <?php if ($koneksi): ?>
            <?php
                $categories_query = "SELECT c.category_id, c.name, COUNT(a.article_id) as article_count 
                                   FROM categories c 
                                   LEFT JOIN articles a ON c.category_id = a.category_id AND a.article_status = 'published'
                                   GROUP BY c.category_id, c.name 
                                   ORDER BY article_count DESC, c.name ASC";
                $categories_result = mysqli_query($koneksi, $categories_query);
                $categories_data = [];
                if ($categories_result) {
                    while ($row = mysqli_fetch_assoc($categories_result)) {
                        $categories_data[] = $row;
                    }
                }
                ?>
            <?php if (!empty($categories_data)): ?>
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <h5><i class="fas fa-list me-2"></i> Kategori</h5>
                </div>
                <div class="p-3">
                    <?php foreach ($categories_data as $category): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                        <a href="kategori.php?id=<?php echo $category['category_id']; ?>"
                            class="text-decoration-none text-dark fw-bold">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                        <span class="badge bg-primary"><?php echo $category['article_count']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="sidebar">
                <div class="sidebar-header">
                    Iklan
                </div>
                <div class="text-center">
                    <div class="bg-light p-4 rounded">
                        <i class="fas fa-ad fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Ruang untuk iklan</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<hr class="border-4 border-dark my-3">

<!-- === BAN NOTIFICATION SYSTEM - SINGLE LOAD ONLY === -->
<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
<script>
// STRICT SINGLE LOADING SYSTEM
(function() {
    'use strict';

    // Prevent multiple loads on same page
    if (window.banNotificationSystemLoaded) {
        console.log('🛡️ [INDEX] Ban system already loaded, skipping');
        return;
    }

    window.banNotificationSystemLoaded = true;
    console.log('🛡️ [INDEX] Loading ban notification for logged in user');

    // Set user data attributes
    document.body.classList.add('logged-in');
    document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0; ?>');
    document.body.setAttribute('data-username', '<?php echo $_SESSION['username'] ?? ''; ?>');
    document.body.setAttribute('data-page', 'index');

    // Debug info
    console.log('🔍 [INDEX] Session Info:', {
        logged_in: <?php echo json_encode($_SESSION['logged_in'] ?? false); ?>,
        user_id: <?php echo json_encode($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0); ?>,
        username: <?php echo json_encode($_SESSION['username'] ?? ''); ?>,
        user_role: <?php echo json_encode($_SESSION['user_role'] ?? ''); ?>
    });

    // Load script only once
    const script = document.createElement('script');
    script.src = 'js/notif-ban.js?v=<?php echo time(); ?>';
    script.onload = function() {
        console.log('✅ [INDEX] Ban notification script loaded');

        // Wait for system to initialize
        const checkInit = setInterval(() => {
            if (window.banNotificationManager && window.banNotificationManager.isInitialized) {
                console.log('✅ [INDEX] Ban notification system ready');
                clearInterval(checkInit);

                // Initial check after delay
                setTimeout(() => {
                    console.log('🔍 [INDEX] Initial ban check');
                    window.banNotificationManager.forceCheck();
                }, 5000);
            }
        }, 1000);
    };

    script.onerror = function() {
        console.error('❌ [INDEX] Failed to load ban notification script');
    };

    document.head.appendChild(script);
})();
</script>
<?php else: ?>
<script>
console.log('👥 [INDEX] User not logged in, ban notification skipped');
</script>
<?php endif; ?>

<!-- Auto-refresh for new articles -->
<script>
let currentArticleCount = <?php echo $total_articles; ?>;

function checkNewArticles() {
    console.log('🔍 [INDEX] Checking for new articles...');

    fetch('config/upload.php')
        .then(response => response.json())
        .then(data => {
            console.log('📡 [INDEX] API Response:', data);

            if (data.success && data.count > currentArticleCount) {
                const newCount = data.count - currentArticleCount;
                console.log(`✨ [INDEX] Found ${newCount} new articles!`);
                showNotification(newCount);
                currentArticleCount = data.count;

                const badge = document.querySelector('.badge.bg-secondary');
                if (badge) {
                    badge.textContent = data.count + ' artikel';
                }
            }
        })
        .catch(error => {
            console.log('🚫 [INDEX] Check failed:', error);
        });
}

function showNotification(newCount) {
    const oldNotif = document.querySelector('.new-article-alert');
    if (oldNotif) oldNotif.remove();

    const notification = document.createElement('div');
    notification.className = 'alert alert-info new-article-alert position-fixed fade show';
    notification.style.cssText = `
        top: 20px; right: 20px; z-index: 1050; min-width: 320px; max-width: 400px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15); border: none;
        border-left: 4px solid #0d6efd; border-radius: 8px;
        animation: slideInRight 0.5s ease-out;
    `;

    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="me-3">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 40px; height: 40px;">
                    <i class="fas fa-newspaper"></i>
                </div>
            </div>
            <div class="flex-grow-1">
                <h6 class="mb-1">
                    <i class="fas fa-sparkles text-warning me-1"></i>
                    Berita Baru Tersedia!
                </h6>
                <p class="mb-0">${newCount} artikel baru telah dipublikasikan</p>
            </div>
            <div class="ms-2">
                <button class="btn btn-primary btn-sm mb-1 d-block" onclick="refreshPage()" style="font-size: 12px;">
                    <i class="fas fa-sync-alt me-1"></i> Muat
                </button>
                <button class="btn btn-outline-secondary btn-sm d-block" onclick="dismissNotification()" style="font-size: 11px;">
                    Nanti
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification && notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 150);
        }
    }, 12000);
}

function refreshPage() {
    const notification = document.querySelector('.new-article-alert');
    if (notification) {
        notification.innerHTML = `
            <div class="text-center py-2">
                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                <span>Memuat berita terbaru...</span>
            </div>
        `;
    }

    setTimeout(() => {
        window.location.reload();
    }, 800);
}

function dismissNotification() {
    const notification = document.querySelector('.new-article-alert');
    if (notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 150);
    }
}

// Start auto-check
setInterval(checkNewArticles, 30000);
setTimeout(checkNewArticles, 5000);

console.log('🔄 [INDEX] Auto-refresh initialized');
console.log('📊 [INDEX] Current article count:', currentArticleCount);
</script>

<!-- CSS Animations -->
<style>
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }

    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.new-article-alert {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95) !important;
}

.new-article-alert:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
}

@media (max-width: 768px) {
    .new-article-alert {
        top: 10px !important;
        right: 10px !important;
        left: 10px !important;
        min-width: auto !important;
        max-width: none !important;
    }
}
</style>

<?php require_once 'footer.php'; ?>