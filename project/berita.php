<?php

ob_start();

// Include konfigurasi database
require 'config/config.php';

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Mode detail artikel
if ($article_id > 0) {
    // Clean buffer before include
    ob_clean();
    require 'artikel.php';
    exit();
}

require 'header.php';

// Ambil berita terbaru dari database
$query = "SELECT article_id, title, image_url, image_filename, publication_date 
          FROM articles 
          WHERE article_status = 'published'
          ORDER BY publication_date DESC 
          LIMIT 10";

$news_result = mysqli_query($koneksi, $query);

// Hitung total artikel untuk auto-refresh
$count_query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
$count_result = mysqli_query($koneksi, $count_query);
$total_articles = 0;
if ($count_result) {
    $total_articles = mysqli_fetch_assoc($count_result)['total'];
}

// Ambil berita populer dari database
$popular_query = "SELECT article_id, title, publication_date 
                 FROM articles 
                 WHERE article_status = 'published'
                 ORDER BY publication_date DESC 
                 LIMIT 4";

$popular_result = mysqli_query($koneksi, $popular_query);

// Handle error messages
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

// Flush output buffer
ob_end_flush();
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
            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="../project/img/Frame 10.webp" class="d-block w-100" alt="Breaking News">
                        <div class="carousel-caption">
                            <h5>Pembunuhan Bos Sembako di Bekasi Ternyata</h5>
                            <p>Ternyata karyawanya sendiri yang melakukan pembunuhan tersebut</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="../project/img/hero1.webp" class="d-block w-100" alt="Latest News">
                        <div class="carousel-caption">
                            <h5>Ekonomi indonesia sekarang sedang menurun</h5>
                            <p>Ternyata banyak pejabat yang korupsi uang rakyat indonesia</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="../project/img/image 8.webp" class="d-block w-100" alt="Sorot Dunia">
                        <div class="carousel-caption">
                            <h5>Pembunuhan terhadap bosnya sendiri</h5>
                            <p>Karyawan yang membunuh bosnya sendiri karna dendam</p>
                        </div>
                    </div>
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

            <!-- News Feed -->
            <div class="news-feed mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Berita Terbaru</h4>
                    <span class="badge bg-secondary"><?php echo $total_articles; ?> artikel</span>
                </div>

                <?php if ($news_result && mysqli_num_rows($news_result) > 0): ?>
                <?php while ($news = mysqli_fetch_assoc($news_result)): ?>
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
                <?php endwhile; ?>
                <?php else: ?>
                <div class="alert alert-info">Belum ada berita tersedia</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Berita Terpopuler -->
            <div class="sidebar">
                <div class="sidebar-header">
                    Berita Terpopuler
                </div>

                <?php if ($popular_result && mysqli_num_rows($popular_result) > 0): ?>
                <?php $counter = 1; ?>
                <?php while ($popular = mysqli_fetch_assoc($popular_result)): ?>
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
                <?php endwhile; ?>
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

            <!-- Advertisement -->
            <div class="sidebar mt-3">
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
// STRICT SINGLE LOADING SYSTEM FOR BERITA.PHP
(function() {
    'use strict';

    if (window.banNotificationSystemLoaded) {
        console.log('🛡️ [BERITA] Ban system already loaded, skipping');
        return;
    }

    window.banNotificationSystemLoaded = true;
    console.log('🛡️ [BERITA] Loading ban notification for logged in user');

    document.body.classList.add('logged-in');
    document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0; ?>');
    document.body.setAttribute('data-username', '<?php echo $_SESSION['username'] ?? ''; ?>');
    document.body.setAttribute('data-page', 'berita');

    console.log('🔍 [BERITA] Session Info:', {
        logged_in: <?php echo json_encode($_SESSION['logged_in'] ?? false); ?>,
        user_id: <?php echo json_encode($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0); ?>,
        username: <?php echo json_encode($_SESSION['username'] ?? ''); ?>,
        user_role: <?php echo json_encode($_SESSION['user_role'] ?? ''); ?>
    });

    const script = document.createElement('script');
    script.src = 'js/notif-ban.js?v=<?php echo time(); ?>';
    script.onload = function() {
        console.log('✅ [BERITA] Ban notification script loaded');

        const checkInit = setInterval(() => {
            if (window.banNotificationManager && window.banNotificationManager.isInitialized) {
                console.log('✅ [BERITA] Ban notification system ready');
                clearInterval(checkInit);

                setTimeout(() => {
                    console.log('🔍 [BERITA] Initial ban check');
                    window.banNotificationManager.forceCheck();
                }, 5000);
            }
        }, 1000);
    };

    script.onerror = function() {
        console.error('❌ [BERITA] Failed to load ban notification script');
    };

    document.head.appendChild(script);
})();
</script>
<?php else: ?>
<script>
console.log('👥 [BERITA] User not logged in, ban notification skipped');
</script>
<?php endif; ?>

<!-- AUTO-REFRESH SCRIPT UNTUK BERITA.PHP -->
<script>
let currentArticleCount = <?php echo $total_articles; ?>;

function checkNewArticles() {
    console.log('🔍 [BERITA] Checking for new articles...');

    fetch('update_artikel.php')
        .then(response => response.json())
        .then(data => {
            console.log('📡 [BERITA] API Response:', data);

            if (data.success && data.count > currentArticleCount) {
                const newCount = data.count - currentArticleCount;
                console.log(`✨ [BERITA] Found ${newCount} new articles!`);
                showNotification(newCount);
                currentArticleCount = data.count;

                const badge = document.querySelector('.badge.bg-secondary');
                if (badge) {
                    badge.textContent = data.count + ' artikel';
                }
            } else {
                console.log('📰 [BERITA] No new articles');
            }
        })
        .catch(error => {
            console.log('🚫 [BERITA] Check failed:', error);
        });
}

function showNotification(newCount) {
    const oldNotif = document.querySelector('.new-article-alert');
    if (oldNotif) oldNotif.remove();

    const notification = document.createElement('div');
    notification.className = 'alert alert-info new-article-alert position-fixed fade show';
    notification.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 1050;
        min-width: 320px;
        max-width: 400px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border: none;
        border-left: 4px solid #0d6efd;
        border-radius: 8px;
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

// Hanya jalankan auto-refresh jika di halaman berita (bukan detail artikel)
if (!window.location.search.includes('id=')) {
    setInterval(checkNewArticles, 30000);
    setTimeout(checkNewArticles, 5000);
    console.log('🔄 [BERITA] Auto-refresh system initialized');
    console.log('📊 [BERITA] Current article count:', currentArticleCount);
}

// Background visitor tracking for berita page (silent)
<?php if ($koneksi && isset($_SESSION['logged_in'])): ?>
setTimeout(function() {
    if (typeof fetch !== 'undefined') {
        fetch('track.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                page: window.location.pathname,
                referrer: document.referrer,
                timestamp: Date.now(),
                action: 'page_view'
            })
        }).catch(() => {
            // Silent fail
        });
    }
}, 2000);
<?php endif; ?>
</script>

<!-- CSS untuk animasi notifikasi -->
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

/* Responsive untuk mobile */
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

<?php
require 'footer.php';
?>