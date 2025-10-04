<?php
// index.php - Tidak perlu perubahan, sudah menggunakan header.php dan footer.php yang baru
ob_start();

// Include header (yang sudah include config.php dan modal login/register)
require_once 'header.php';

// PERBAIKAN: Pastikan koneksi database tersedia
if (!isset($koneksi) || !$koneksi) {
    die('<div class="alert alert-danger">Koneksi database gagal. Silakan periksa konfigurasi.</div>');
}

// Get articles data dengan error handling yang lebih baik
$articlesPerPage = getSetting('articles_per_page', 10);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $articlesPerPage;

// Handle search redirect
if (isset($_GET['q']) && !empty($_GET['q'])) {
    header("Location: search.php?q=" . urlencode($_GET['q']));
    exit();
}

$articles = [];
$total_articles = 0;
$popular_articles = [];

try {
    // Count total published articles
    $count_query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
    $count_result = mysqli_query($koneksi, $count_query);
    
    if (!$count_result) {
        throw new Exception("Query count gagal: " . mysqli_error($koneksi));
    }
    
    $count_row = mysqli_fetch_assoc($count_result);
    $total_articles = $count_row ? intval($count_row['total']) : 0;

    // Get articles dengan JOIN yang benar
    $query = "SELECT 
                a.article_id, 
                a.title, 
                a.content, 
                a.publication_date,
                a.view_count,
                u.full_name as author_name,
                u.username as author_username,
                c.name as category_name,
                i.filename as image_filename,
                i.url as image_url,
                a.post_status as langganan,
                i.is_external
              FROM articles a
              LEFT JOIN users u ON a.author_id = u.id
              LEFT JOIN categories c ON a.category_id = c.category_id
              LEFT JOIN images i ON a.featured_image_id = i.id
              WHERE a.article_status = 'published' AND a.post_status = 'Free'
              ORDER BY a.publication_date DESC 
              LIMIT $articlesPerPage OFFSET $offset";

    $result = mysqli_query($koneksi, $query);
    
    if (!$result) {
        throw new Exception("Query articles gagal: " . mysqli_error($koneksi));
    }
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Handle image path
        if (!empty($row['image_filename'])) {
            if ($row['is_external'] && !empty($row['image_url'])) {
                $row['display_image'] = $row['image_url'];
            } else {
                // Cek berbagai kemungkinan lokasi file
                $possible_paths = [
                    'uploads/articles/published/' . $row['image_filename'],
                    'uploads/articles/' . $row['image_filename'],
                    $row['image_filename']
                ];
                
                $row['display_image'] = null;
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $row['display_image'] = $path;
                        break;
                    }
                }
                
                // Fallback jika file tidak ditemukan
                if (!$row['display_image'] && !empty($row['image_url'])) {
                    $row['display_image'] = $row['image_url'];
                }
            }
        } else {
            $row['display_image'] = null;
        }
        
        $articles[] = $row;
    }

    // Get popular articles
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
        throw new Exception("Query popular gagal: " . mysqli_error($koneksi));
    }
    
    while ($row = mysqli_fetch_assoc($popular_result)) {
        $popular_articles[] = $row;
    }

} catch (Exception $e) {
    error_log("Error in index.php: " . $e->getMessage());
    $error_display = '<div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Gagal memuat data: ' . htmlspecialchars($e->getMessage()) . '
    </div>';
}

$total_pages = $total_articles > 0 ? ceil($total_articles / $articlesPerPage) : 1;

// Handle error messages dari URL
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

ob_end_flush();
?>
<link rel="stylesheet" href="style/berita.css">

<!-- Main Content -->
<div class="container main-content mt-2">
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_display)): ?>
    <?php echo $error_display; ?>
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
                        <img src="<?php echo htmlspecialchars($article['display_image']); ?>" 
                             class="d-block w-100" 
                             alt="<?php echo htmlspecialchars($article['title']); ?>"
                             onerror="this.onerror=null; this.src='img/placeholder-news.jpg'; this.style.objectFit='cover';">
                        <?php else: ?>
                        <div class="d-block w-100 bg-secondary d-flex align-items-center justify-content-center" 
                             style="height: 400px;">
                            <i class="fas fa-newspaper fa-4x text-white"></i>
                        </div>
                        <?php endif; ?>
                        <div class="carousel-caption">
                            <h5><?php echo htmlspecialchars(mb_substr($article['title'], 0, 100)); ?><?php echo mb_strlen($article['title']) > 100 ? '...' : ''; ?></h5>
                            <p><?php echo htmlspecialchars(mb_substr(strip_tags($article['content']), 0, 150)); ?>...</p>
                            <a href="artikel.php?id=<?php echo $article['article_id']; ?>" 
                               class="btn btn-primary btn-sm">
                                Baca Selengkapnya
                            </a>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                    <span class="visually-hidden">Kembali</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                    <span class="visually-hidden">Lanjut</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- News Feed -->
            <div class="news-feed mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-newspaper me-2 text-primary"></i>Berita Terbaru</h4>
                    <span class="badge bg-secondary fs-6"><?php echo number_format($total_articles); ?> artikel</span>
                </div>

                <?php if (!empty($articles)): ?>
                <?php foreach ($articles as $news): ?>
                <article class="news-item">
                    <?php if (!empty($news['display_image'])): ?>
                    <img src="<?php echo htmlspecialchars($news['display_image']); ?>"
                         alt="<?php echo htmlspecialchars($news['title']); ?>" 
                         class="news-image"
                         loading="lazy"
                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <?php endif; ?>
                    
                    <div class="news-image bg-secondary <?php echo !empty($news['display_image']) ? 'd-none' : 'd-flex'; ?> align-items-center justify-content-center">
                        <i class="fas fa-newspaper fa-2x text-white"></i>
                    </div>

                    <div class="news-content">
                        <div class="news-meta">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('d F Y', strtotime($news['publication_date'])); ?>
                            <?php if (!empty($news['category_name'])): ?>
                            • <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($news['category_name']); ?>
                            <?php endif; ?>
                            <?php if (!empty($news['author_name'])): ?>
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
                        <p class="news-excerpt text-muted">
                            <?php echo htmlspecialchars(mb_substr(strip_tags($news['content']), 0, 120)); ?>...
                        </p>
                    </div>
                </article>
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
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h5>Belum Ada Berita</h5>
                    <p>Saat ini belum ada berita yang tersedia. Silakan kembali lagi nanti.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-fire me-2 text-danger"></i>Berita Terpopuler
                </div>

                <?php if (!empty($popular_articles)): ?>
                <?php $counter = 1; ?>
                <?php foreach ($popular_articles as $popular): ?>
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
                            <?php if (!empty($popular['category_name'])): ?>
                            • <?php echo htmlspecialchars($popular['category_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php $counter++; ?>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <p>Belum ada berita populer</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Categories Widget -->
            <?php
            try {
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
                $categories_data = [];
                
                if ($categories_result) {
                    while ($row = mysqli_fetch_assoc($categories_result)) {
                        $categories_data[] = $row;
                    }
                }
            } catch (Exception $e) {
                error_log("Error loading categories: " . $e->getMessage());
                $categories_data = [];
            }
            ?>
            
            <?php if (!empty($categories_data)): ?>
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-list me-2 text-primary"></i>Kategori Berita
                </div>
                <div class="p-3">
                    <?php foreach ($categories_data as $category): ?>
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
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Widget -->
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
                            $active_categories = 0;
                            foreach ($categories_data as $cat) {
                                if ($cat['article_count'] > 0) $active_categories++;
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

<!-- CSS Enhancement -->
<style>
.news-item {
    transition: all 0.3s ease;
    cursor: pointer;
}

.news-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.news-title a {
    transition: color 0.2s ease;
}

.news-title a:hover {
    color: #0d6efd !important;
}

.news-excerpt {
    font-size: 0.9rem;
    line-height: 1.5;
}

.hover-shadow {
    transition: all 0.2s ease;
}

.hover-shadow:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}
</style>

<?php require_once 'footer.php'; ?>