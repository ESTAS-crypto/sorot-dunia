<?php
ob_start();
require_once 'header.php';

if (!isset($koneksi) || !$koneksi) {
    die('<div class="alert alert-danger">Koneksi database gagal. Silakan periksa konfigurasi.</div>');
}

$articlesPerPage = getSetting('articles_per_page', 10);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $articlesPerPage;

if (isset($_GET['q']) && !empty($_GET['q'])) {
    header("Location: search.php?q=" . urlencode($_GET['q']));
    exit();
}

$free_articles = [];
$premium_articles = [];
$total_articles = 0;
$popular_articles = [];
$categories_data = [];

try {
    // Count total published articles
    $count_query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
    $count_result = mysqli_query($koneksi, $count_query);
    
    if (!$count_result) {
        throw new Exception("Query count gagal: " . mysqli_error($koneksi));
    }
    
    $count_row = mysqli_fetch_assoc($count_result);
    $total_articles = $count_row ? intval($count_row['total']) : 0;

    // Get FREE articles
    $free_query = "SELECT 
                    a.article_id, 
                    a.title, 
                    a.content, 
                    a.publication_date,
                    a.view_count,
                    a.post_status,
                    u.full_name as author_name,
                    u.username as author_username,
                    c.name as category_name,
                    i.filename as image_filename,
                    i.url as image_url,
                    i.is_external
                  FROM articles a
                  LEFT JOIN users u ON a.author_id = u.id
                  LEFT JOIN categories c ON a.category_id = c.category_id
                  LEFT JOIN images i ON a.featured_image_id = i.id
                  WHERE a.article_status = 'published' AND a.post_status = 'Free'
                  ORDER BY a.publication_date DESC 
                  LIMIT 15";

    $free_result = mysqli_query($koneksi, $free_query);
    
    if (!$free_result) {
        throw new Exception("Query free articles gagal: " . mysqli_error($koneksi));
    }
    
    while ($row = mysqli_fetch_assoc($free_result)) {
        if (!empty($row['image_filename'])) {
            if ($row['is_external'] && !empty($row['image_url'])) {
                $row['display_image'] = $row['image_url'];
            } else {
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
                
                if (!$row['display_image'] && !empty($row['image_url'])) {
                    $row['display_image'] = $row['image_url'];
                }
            }
        } else {
            $row['display_image'] = null;
        }
        
        $free_articles[] = $row;
    }

    // Get PREMIUM articles
    $premium_query = "SELECT 
                        a.article_id, 
                        a.title, 
                        a.content, 
                        a.publication_date,
                        a.view_count,
                        a.post_status,
                        u.full_name as author_name,
                        u.username as author_username,
                        c.name as category_name,
                        i.filename as image_filename,
                        i.url as image_url,
                        i.is_external
                      FROM articles a
                      LEFT JOIN users u ON a.author_id = u.id
                      LEFT JOIN categories c ON a.category_id = c.category_id
                      LEFT JOIN images i ON a.featured_image_id = i.id
                      WHERE a.article_status = 'published' AND a.post_status = 'Premium'
                      ORDER BY a.publication_date DESC 
                      LIMIT 15";

    $premium_result = mysqli_query($koneksi, $premium_query);
    
    if (!$premium_result) {
        throw new Exception("Query premium articles gagal: " . mysqli_error($koneksi));
    }
    
    while ($row = mysqli_fetch_assoc($premium_result)) {
        if (!empty($row['image_filename'])) {
            if ($row['is_external'] && !empty($row['image_url'])) {
                $row['display_image'] = $row['image_url'];
            } else {
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
                
                if (!$row['display_image'] && !empty($row['image_url'])) {
                    $row['display_image'] = $row['image_url'];
                }
            }
        } else {
            $row['display_image'] = null;
        }
        
        $premium_articles[] = $row;
    }

    // Get popular articles
    $popular_query = "SELECT 
                        a.article_id, 
                        a.title, 
                        a.publication_date,
                        a.view_count,
                        a.post_status,
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

    // Get categories
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
    
    if ($categories_result) {
        while ($row = mysqli_fetch_assoc($categories_result)) {
            $categories_data[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Error in index.php: " . $e->getMessage());
    $error_display = '<div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Gagal memuat data: ' . htmlspecialchars($e->getMessage()) . '
    </div>';
}

// Gabungkan artikel dengan pola bergantian
$merged_articles = [];
$free_index = 0;
$premium_index = 0;
$batch_size_free = 4; // 4 artikel free per batch
$batch_size_premium = 3; // 3 artikel premium per batch

while ($free_index < count($free_articles) || $premium_index < count($premium_articles)) {
    // Tambahkan batch FREE
    for ($i = 0; $i < $batch_size_free && $free_index < count($free_articles); $i++) {
        $merged_articles[] = [
            'type' => 'free',
            'data' => $free_articles[$free_index++]
        ];
    }
    
    // Tambahkan divider jika ada premium articles
    if ($premium_index < count($premium_articles) && count($merged_articles) > 0) {
        $merged_articles[] = ['type' => 'divider_premium'];
    }
    
    // Tambahkan batch PREMIUM
    for ($i = 0; $i < $batch_size_premium && $premium_index < count($premium_articles); $i++) {
        $merged_articles[] = [
            'type' => 'premium',
            'data' => $premium_articles[$premium_index++]
        ];
    }
    
    // Tambahkan divider jika masih ada free articles
    if ($free_index < count($free_articles) && count($merged_articles) > 0) {
        $merged_articles[] = ['type' => 'divider_free'];
    }
}

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
            <?php if (!empty($free_articles) || !empty($premium_articles)): ?>
            <?php 
            $carousel_items = array_merge(
                array_slice($free_articles, 0, 2),
                array_slice($premium_articles, 0, 1)
            );
            ?>
            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                <div class="carousel-inner">
                    <?php foreach ($carousel_items as $idx => $article): ?>
                    <div class="carousel-item <?php echo $idx === 0 ? 'active' : ''; ?>" 
                         onclick="window.location.href='artikel.php?id=<?php echo $article['article_id']; ?>'"
                         style="cursor: pointer;">
                        <?php if (!empty($article['display_image'])): ?>
                        <img src="<?php echo htmlspecialchars($article['display_image']); ?>" 
                             class="d-block w-100" 
                             alt="<?php echo htmlspecialchars($article['title']); ?>"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php else: ?>
                        <div class="d-block w-100 bg-secondary d-flex align-items-center justify-content-center" 
                             style="height: 400px;">
                            <i class="fas fa-newspaper fa-4x text-white"></i>
                        </div>
                        <?php endif; ?>
                        <div class="carousel-caption">
                            <?php if ($article['post_status'] === 'Premium'): ?>
                            <span class="premium-badge mb-2">
                                <i class="fas fa-crown"></i> PREMIUM
                            </span>
                            <?php endif; ?>
                            <h5><?php echo htmlspecialchars(mb_substr($article['title'], 0, 100)); ?><?php echo mb_strlen($article['title']) > 100 ? '...' : ''; ?></h5>
                            <p><?php echo htmlspecialchars(mb_substr(strip_tags($article['content']), 0, 120)); ?>...</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($carousel_items) > 1): ?>
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
            <?php endif; ?>

            <!-- News Feed dengan Pola Bergantian -->
            <div class="news-feed mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-newspaper me-2 text-primary"></i>Berita Terbaru</h4>
                    <div class="d-flex gap-2">
                        <span class="badge bg-secondary fs-6">
                            <i class="fas fa-file-alt"></i> <?php echo count($free_articles); ?> Free
                        </span>
                        <span class="badge fs-6" style="background: linear-gradient(135deg, #FFD700, #FFA500); color: #000;">
                            <i class="fas fa-crown"></i> <?php echo count($premium_articles); ?> Premium
                        </span>
                    </div>
                </div>

                <?php if (!empty($merged_articles)): ?>
                <?php foreach ($merged_articles as $item): ?>
                    <?php if ($item['type'] === 'divider_premium'): ?>
                    <div class="section-divider premium-section">
                        <h5>
                            <i class="fas fa-crown"></i>
                            <span>BERITA PREMIUM</span>
                            <i class="fas fa-crown"></i>
                        </h5>
                    </div>
                    
                    <?php elseif ($item['type'] === 'divider_free'): ?>
                    <div class="section-divider">
                        <h5>
                            <i class="fas fa-newspaper"></i>
                            <span>BERITA FREE</span>
                            <i class="fas fa-newspaper"></i>
                        </h5>
                    </div>
                    
                    <?php else: ?>
                    <?php $news = $item['data']; ?>
                    <article class="news-item <?php echo $item['type']; ?>">
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
                            <?php if ($news['post_status'] === 'Premium'): ?>
                            <span class="premium-badge mb-2">
                                <i class="fas fa-crown"></i> PREMIUM
                            </span>
                            <?php endif; ?>
                            
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
                                • <i class="fas fa-eye me-1"></i><?php echo number_format($news['view_count']); ?>
                                <?php endif; ?>
                                <?php if ($news['post_status'] === 'Premium'): ?>
                                • <span class="premium-indicator"><i class="fas fa-star"></i> Premium</span>
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
                    <?php endif; ?>
                <?php endforeach; ?>

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
                    <div class="trending-number bg-danger"><?php echo $counter; ?></div>
                    <div class="trending-content">
                        <?php if ($popular['post_status'] === 'Premium'): ?>
                        <span class="premium-badge mb-1" style="font-size: 9px; padding: 2px 8px;">
                            <i class="fas fa-crown"></i> PREMIUM
                        </span>
                        <?php endif; ?>
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

<?php require_once 'footer.php'; ?>