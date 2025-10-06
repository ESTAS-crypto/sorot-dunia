<?php
// berita.php - FIXED VERSION dengan Hero Carousel yang diperbaiki
ob_start();

// Include konfigurasi database
require_once 'config/config.php';

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PERBAIKAN: Cek koneksi database
if (!isset($koneksi) || !$koneksi) {
    die('<div class="container mt-5">
        <div class="alert alert-danger">
            <h4>Koneksi Database Gagal</h4>
            <p>Tidak dapat terhubung ke database. Silakan periksa konfigurasi.</p>
        </div>
    </div>');
}

// Cek apakah ini request untuk detail artikel
$article_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Mode detail artikel - redirect ke artikel.php
if ($article_id > 0) {
    ob_clean();
    header("Location: artikel.php?id=" . $article_id);
    exit();
}

// Include header
require_once 'header.php';

// PERBAIKAN: Query database dengan error handling yang lebih baik
$news_result = false;
$popular_result = false;
$categories_result = false;
$total_articles = 0;
$error_occurred = false;

try {
    // Query berita terbaru dengan JOIN yang benar
    $query = "SELECT 
                a.article_id, 
                a.title, 
                a.publication_date,
                a.view_count,
                i.filename as image_filename,
                i.url as image_url,
                i.is_external,
                c.name as category_name,
                u.full_name as author_name
              FROM articles a
              LEFT JOIN images i ON a.featured_image_id = i.id
              LEFT JOIN categories c ON a.category_id = c.category_id  
              LEFT JOIN users u ON a.author_id = u.id
              WHERE a.article_status = 'published'
              ORDER BY a.publication_date DESC 
              LIMIT 20";

    $news_result = mysqli_query($koneksi, $query);
    
    if (!$news_result) {
        throw new Exception("Query berita gagal: " . mysqli_error($koneksi));
    }

    // Hitung total artikel
    $count_query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
    $count_result = mysqli_query($koneksi, $count_query);
    
    if (!$count_result) {
        throw new Exception("Query count gagal: " . mysqli_error($koneksi));
    }
    
    $count_row = mysqli_fetch_assoc($count_result);
    $total_articles = $count_row ? intval($count_row['total']) : 0;

    // Query berita populer
    $popular_query = "SELECT 
                        a.article_id, 
                        a.title, 
                        a.publication_date,
                        a.view_count,
                        c.name as category_name
                     FROM articles a
                     LEFT JOIN categories c ON a.category_id = c.category_id
                     WHERE a.article_status = 'published'
                     ORDER BY a.view_count DESC, a.publication_date DESC 
                     LIMIT 5";

    $popular_result = mysqli_query($koneksi, $popular_query);
    
    if (!$popular_result) {
        throw new Exception("Query populer gagal: " . mysqli_error($koneksi));
    }

    // Query kategori dengan jumlah artikel
    $categories_query = "SELECT 
                          c.category_id, 
                          c.name, 
                          COUNT(a.article_id) as article_count 
                        FROM categories c 
                        LEFT JOIN articles a ON c.category_id = a.category_id 
                          AND a.article_status = 'published'
                        GROUP BY c.category_id, c.name 
                        ORDER BY article_count DESC, c.name ASC
                        LIMIT 10";
    
    $categories_result = mysqli_query($koneksi, $categories_query);
    
    if (!$categories_result) {
        throw new Exception("Query kategori gagal: " . mysqli_error($koneksi));
    }

} catch (Exception $e) {
    error_log("Error dalam berita.php: " . $e->getMessage());
    $error_occurred = true;
    $error_message_detail = $e->getMessage();
}

// Handle error messages dari URL parameter
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
            $error_message = 'Kesalahan database. Silakan coba lagi nanti.';
            break;
        default:
            $error_message = 'Terjadi kesalahan';
    }
}

// Function helper untuk mendapatkan URL gambar yang benar
function getArticleImageUrl($image_filename, $image_url, $is_external = false) {
    if (empty($image_filename) && empty($image_url)) {
        return null;
    }
    
    // Jika gambar eksternal dan ada URL
    if ($is_external && !empty($image_url)) {
        return $image_url;
    }
    
    // Jika ada filename lokal
    if (!empty($image_filename)) {
        // Cek berbagai kemungkinan path
        $possible_paths = [
            'uploads/articles/published/' . $image_filename,
            'uploads/articles/' . $image_filename,
            'ajax/../uploads/articles/' . $image_filename,
            $image_filename
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Jika file lokal tidak ditemukan, coba gunakan URL jika ada
        if (!empty($image_url)) {
            return $image_url;
        }
        
        // Return path default
        return 'uploads/articles/' . $image_filename;
    }
    
    // Fallback ke URL jika ada
    return $image_url;
}

// Flush output buffer
ob_end_flush();
?>

<!-- Main Content -->
<div class="container main-content mt-2">
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_occurred): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Peringatan:</strong> Terjadi masalah saat memuat data.
        <?php if (isset($error_message_detail)): ?>
        <br><small>Detail: <?php echo htmlspecialchars($error_message_detail); ?></small>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <link rel="stylesheet" href="style/berita.css">

    <div class="row">
        <div class="col-lg-8">
            <!-- Hero Carousel - PERBAIKAN LENGKAP -->
            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                <div class="carousel-inner">
                    <?php 
                    // Reset pointer untuk carousel
                    $carousel_count = 0;
                    if ($news_result && mysqli_num_rows($news_result) > 0) {
                        mysqli_data_seek($news_result, 0);
                        while ($carousel_article = mysqli_fetch_assoc($news_result) and $carousel_count < 3): 
                            $carousel_count++;
                            $carousel_active = ($carousel_count == 1) ? 'active' : '';
                            $carousel_image = getArticleImageUrl(
                                $carousel_article['image_filename'], 
                                $carousel_article['image_url'], 
                                $carousel_article['is_external']
                            );
                    ?>
                    <div class="carousel-item <?php echo $carousel_active; ?>"
                         onclick="window.location.href='artikel.php?id=<?php echo $carousel_article['article_id']; ?>'"
                         style="cursor: pointer;">
                        <?php if ($carousel_image): ?>
                        <img src="<?php echo htmlspecialchars($carousel_image); ?>" 
                             class="d-block w-100" 
                             alt="<?php echo htmlspecialchars($carousel_article['title']); ?>"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>
                        
                        <div class="d-block w-100 bg-secondary align-items-center justify-content-center <?php echo $carousel_image ? 'd-none' : 'd-flex'; ?>" 
                             style="height: 400px;">
                            <i class="fas fa-newspaper fa-4x text-white"></i>
                        </div>
                        
                        <div class="carousel-caption">
                            <h5><?php echo htmlspecialchars(mb_substr($carousel_article['title'], 0, 100)); ?><?php echo mb_strlen($carousel_article['title']) > 100 ? '...' : ''; ?></h5>
                            <p><?php echo htmlspecialchars($carousel_article['category_name'] ?? 'Berita'); ?> • 
                               <?php echo date('d M Y', strtotime($carousel_article['publication_date'])); ?></p>
                        </div>
                    </div>
                    <?php endwhile; } ?>
                    
                    <!-- Fallback jika tidak ada artikel -->
                    <?php if (!$news_result || $carousel_count == 0): ?>
                    <div class="carousel-item active">
                        <div class="d-block w-100 bg-secondary d-flex align-items-center justify-content-center" 
                             style="height: 400px;">
                            <div class="text-center text-white">
                                <i class="fas fa-newspaper fa-4x mb-3"></i>
                                <h5>Portal Berita Terpercaya</h5>
                                <p>Menghadirkan berita terkini dan terpercaya</p>
                            </div>
                        </div>
                        <div class="carousel-caption">
                            <h5>Selamat Datang di Portal Berita</h5>
                            <p>Dapatkan informasi terkini dan terpercaya</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Carousel Controls -->
                <?php if ($carousel_count > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" onclick="event.stopPropagation();">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Kembali</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" onclick="event.stopPropagation();">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Lanjut</span>
                </button>
                <?php endif; ?>
            </div>

            <!-- News Feed -->
            <div class="news-feed mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-newspaper me-2 text-primary"></i>Berita Terbaru</h4>
                    <span class="badge bg-secondary fs-6"><?php echo number_format($total_articles); ?> artikel</span>
                </div>

                <?php if ($news_result && mysqli_num_rows($news_result) > 0): ?>
                    <?php 
                    // Reset pointer untuk daftar berita
                    mysqli_data_seek($news_result, 0);
                    $news_count = 0;
                    ?>
                    <?php while ($news = mysqli_fetch_assoc($news_result)): ?>
                    <?php 
                    $news_count++; 
                    $news_image = getArticleImageUrl($news['image_filename'], $news['image_url'], $news['is_external']);
                    ?>
                    <article class="news-item">
                        <?php if ($news_image): ?>
                        <img src="<?php echo htmlspecialchars($news_image); ?>"
                             alt="<?php echo htmlspecialchars($news['title']); ?>" 
                             class="news-image"
                             loading="lazy"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>
                        
                        <div class="news-image bg-secondary <?php echo $news_image ? 'd-none' : 'd-flex'; ?> align-items-center justify-content-center">
                            <i class="fas fa-newspaper fa-2x text-white"></i>
                        </div>

                        <div class="news-content">
                            <div class="news-meta">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d F Y', strtotime($news['publication_date'])); ?>
                                <?php if ($news['category_name']): ?>
                                • <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($news['category_name']); ?>
                                <?php endif; ?>
                                <?php if ($news['author_name']): ?>
                                • <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($news['author_name']); ?>
                                <?php endif; ?>
                                <?php if ($news['view_count'] > 0): ?>
                                • <i class="fas fa-eye me-1"></i><?php echo number_format($news['view_count']); ?> views
                                <?php endif; ?>
                            </div>
                            <h6 class="news-title">
                                <a href="artikel.php?id=<?php echo $news['article_id']; ?>" 
                                   class="text-decoration-none text-dark fw-bold">
                                    <?php echo htmlspecialchars($news['title']); ?>
                                </a>
                            </h6>
                        </div>
                    </article>
                    <?php endwhile; ?>
                    
                    <?php if ($news_count == 0): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                        <h5>Belum Ada Berita</h5>
                        <p>Saat ini belum ada berita yang tersedia. Silakan kembali lagi nanti.</p>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h5>Gagal Memuat Berita</h5>
                    <p>Terjadi masalah saat memuat berita. Silakan refresh halaman ini.</p>
                    <button class="btn btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Berita Terpopuler -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-fire me-2 text-danger"></i>Berita Terpopuler
                </div>

                <?php if ($popular_result && mysqli_num_rows($popular_result) > 0): ?>
                    <?php $counter = 1; ?>
                    <?php while ($popular = mysqli_fetch_assoc($popular_result)): ?>
                    <div class="trending-item">
                        <div class="trending-number"><?php echo $counter; ?></div>
                        <div class="trending-content">
                            <a href="artikel.php?id=<?php echo $popular['article_id']; ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($popular['title']); ?>
                            </a>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d M Y', strtotime($popular['publication_date'])); ?>
                                <?php if ($popular['view_count'] > 0): ?>
                                • <i class="fas fa-eye me-1"></i><?php echo number_format($popular['view_count']); ?>
                                <?php endif; ?>
                                <?php if ($popular['category_name']): ?>
                                • <?php echo htmlspecialchars($popular['category_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php $counter++; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <p>Belum ada berita populer</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Categories Widget -->
            <?php if ($categories_result && mysqli_num_rows($categories_result) > 0): ?>
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-list me-2 text-primary"></i>Kategori Berita
                </div>
                <div class="p-3">
                    <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded hover-shadow">
                        <a href="kategori.php?id=<?php echo $category['category_id']; ?>"
                            class="text-decoration-none text-dark fw-bold">
                            <i class="fas fa-folder me-2"></i>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                        <span class="badge bg-primary rounded-pill">
                            <?php echo number_format($category['article_count']); ?>
                        </span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistik Website -->
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-chart-bar me-2 text-success"></i>Statistik
                </div>
                <div class="p-3">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <div class="fw-bold fs-4 text-primary"><?php echo number_format($total_articles); ?></div>
                            <small class="text-muted">Total Artikel</small>
                        </div>
                        <div class="col-6">
                            <?php 
                            // Hitung kategori aktif
                            $active_categories = 0;
                            if ($categories_result) {
                                mysqli_data_seek($categories_result, 0);
                                while ($cat = mysqli_fetch_assoc($categories_result)) {
                                    if ($cat['article_count'] > 0) $active_categories++;
                                }
                            }
                            ?>
                            <div class="fw-bold fs-4 text-success"><?php echo $active_categories; ?></div>
                            <small class="text-muted">Kategori Aktif</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<hr class="border-3 border-dark my-4">

<!-- CSS Tambahan untuk styling yang lebih baik -->
<style>
.news-item {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 20px;
    cursor: pointer;
}

.news-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    background-color: #f8f9fa;
}

.news-title a:hover {
    color: #0d6efd !important;
}

.trending-item {
    transition: background-color 0.2s ease;
    border-radius: 6px;
    padding: 8px;
}

.trending-item:hover {
    background-color: #f8f9fa;
}

.hover-shadow:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.badge {
    font-size: 0.75em;
}

.news-meta {
    font-size: 0.85em;
    color: #6c757d;
}

.sidebar-header {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    padding: 12px 15px;
    border-radius: 8px 8px 0 0;
    font-weight: bold;
}

.sidebar {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}
</style>

<!-- JavaScript untuk enhancement -->
<script>
// Image lazy loading error handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('Berita page loaded successfully');
    
    // Debug: Cek koneksi database
    <?php if ($error_occurred): ?>
    console.error('Database error occurred');
    <?php endif; ?>
    
    <?php if ($news_result): ?>
    console.log('Total berita: <?php echo mysqli_num_rows($news_result); ?>');
    <?php endif; ?>
});

// Global error handler
window.addEventListener('error', function(event) {
    if (event.message.includes('Could not establish connection') || 
        event.message.includes('Extension context invalidated')) {
        console.warn('Browser extension error suppressed');
        event.preventDefault();
        return false;
    }
});
</script>

<!-- Ban Notification System (hanya untuk user yang login) -->
<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
<script>
// Ban notification system - single load only
(function() {
    'use strict';

    if (window.banNotificationSystemLoaded) {
        console.log('Ban system already loaded, skipping');
        return;
    }

    window.banNotificationSystemLoaded = true;
    console.log('Loading ban notification for logged in user');

    // Set body attributes for user identification
    document.body.classList.add('logged-in');
    document.body.setAttribute('data-user-id', '<?php echo intval($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0); ?>');
    document.body.setAttribute('data-username', '<?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES); ?>');
    document.body.setAttribute('data-page', 'berita');

    // Load ban notification script jika ada
    const script = document.createElement('script');
    script.src = 'js/notif-ban.js?v=' + Date.now();
    script.onload = function() {
        console.log('Ban notification script loaded');

        // Wait for initialization
        const checkInit = setInterval(() => {
            if (window.banNotificationManager && window.banNotificationManager.isInitialized) {
                console.log('Ban notification system ready');
                clearInterval(checkInit);

                // Initial check after 5 seconds
                setTimeout(() => {
                    console.log('Initial ban check');
                    window.banNotificationManager.forceCheck();
                }, 5000);
            }
        }, 1000);
    };

    script.onerror = function() {
        console.log('Ban notification script not available');
    };

    document.head.appendChild(script);
})();
</script>
<?php else: ?>
<script>
console.log('User not logged in, ban notification skipped');
</script>
<?php endif; ?>

<?php
// Include footer
require_once 'footer.php';
?>