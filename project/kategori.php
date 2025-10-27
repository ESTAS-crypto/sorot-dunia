<?php
// kategori.php - FIXED VERSION dengan Premium Badge di Artikel Terbaru
ob_start();

require 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'header.php';

// Get category dari URL
$category_slug = isset($_GET['slug']) ? sanitize_input($_GET['slug']) : '';
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pagination
$articles_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $articles_per_page;

// Variables
$category_info = null;
$articles = [];
$total_articles = 0;
$total_pages = 1;
$all_categories = [];
$latest_articles = []; // TAMBAHAN UNTUK ARTIKEL TERBARU

try {
    // Get category info
    if (!empty($category_slug)) {
        $category_query = "SELECT * FROM categories WHERE slug = ? LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $category_query);
        mysqli_stmt_bind_param($stmt, "s", $category_slug);
        mysqli_stmt_execute($stmt);
        $category_result = mysqli_stmt_get_result($stmt);
        
        if ($category_result && mysqli_num_rows($category_result) > 0) {
            $category_info = mysqli_fetch_assoc($category_result);
            $category_id = $category_info['category_id'];
        }
        mysqli_stmt_close($stmt);
    } else if ($category_id > 0) {
        $category_query = "SELECT * FROM categories WHERE category_id = ? LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $category_query);
        mysqli_stmt_bind_param($stmt, "i", $category_id);
        mysqli_stmt_execute($stmt);
        $category_result = mysqli_stmt_get_result($stmt);
        
        if ($category_result && mysqli_num_rows($category_result) > 0) {
            $category_info = mysqli_fetch_assoc($category_result);
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get articles jika kategori ditemukan
    if ($category_info) {
        // Count total articles
        $count_query = "SELECT COUNT(*) as total 
                       FROM articles a 
                       WHERE a.category_id = ? 
                       AND a.article_status = 'published'";
        
        $stmt = mysqli_prepare($koneksi, $count_query);
        mysqli_stmt_bind_param($stmt, "i", $category_id);
        mysqli_stmt_execute($stmt);
        $count_result = mysqli_stmt_get_result($stmt);
        
        if ($count_result) {
            $count_row = mysqli_fetch_assoc($count_result);
            $total_articles = $count_row['total'];
            $total_pages = ceil($total_articles / $articles_per_page);
        }
        mysqli_stmt_close($stmt);
        
        // Get articles
        $articles_query = "SELECT
                            a.article_id,
                            a.title,
                            a.content,
                            a.meta_description,
                            a.publication_date,
                            a.view_count,
                            a.article_status,
                            a.post_status,
                            u.full_name as author_name,
                            u.username as author_username,
                            c.name as category_name,
                            c.slug as category_slug,
                            i.filename as image_filename,
                            i.url as image_url,
                            i.is_external
                          FROM articles a
                          LEFT JOIN users u ON a.author_id = u.id
                          LEFT JOIN categories c ON a.category_id = c.category_id
                          LEFT JOIN images i ON a.featured_image_id = i.id
                          WHERE a.category_id = ? 
                          AND a.article_status = 'published'
                          ORDER BY a.publication_date DESC
                          LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($koneksi, $articles_query);
        mysqli_stmt_bind_param($stmt, "iii", $category_id, $articles_per_page, $offset);
        mysqli_stmt_execute($stmt);
        $articles_result = mysqli_stmt_get_result($stmt);
        
        if ($articles_result) {
            while ($row = mysqli_fetch_assoc($articles_result)) {
                // Handle image path
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
                
                $articles[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // ===== GET LATEST ARTICLES (ARTIKEL TERBARU) DENGAN POST_STATUS =====
    $latest_query = "SELECT 
                        a.article_id,
                        a.title,
                        a.publication_date,
                        a.post_status,
                        c.name as category_name,
                        c.slug as category_slug
                    FROM articles a
                    LEFT JOIN categories c ON a.category_id = c.category_id
                    WHERE a.article_status = 'published'
                    ORDER BY a.publication_date DESC
                    LIMIT 5";
    
    $latest_result = mysqli_query($koneksi, $latest_query);
    if ($latest_result && mysqli_num_rows($latest_result) > 0) {
        while ($latest = mysqli_fetch_assoc($latest_result)) {
            $latest_articles[] = $latest;
        }
    }
    
    // Get all categories
    $all_categories_query = "SELECT 
                              c.category_id,
                              c.name,
                              c.slug,
                              c.created_at,
                              COUNT(a.article_id) as article_count
                            FROM categories c
                            LEFT JOIN articles a ON c.category_id = a.category_id 
                            AND a.article_status = 'published'
                            GROUP BY c.category_id, c.name, c.slug, c.created_at
                            ORDER BY article_count DESC, c.name ASC";
    
    $all_categories_result = mysqli_query($koneksi, $all_categories_query);
    if ($all_categories_result) {
        while ($row = mysqli_fetch_assoc($all_categories_result)) {
            $all_categories[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Error dalam kategori.php: " . $e->getMessage());
}

function truncateText($text, $length = 150) {
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

ob_end_flush();
?>

<style>
.category-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #007bff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.category-title {
    color: #333;
    font-weight: 700;
    margin-bottom: 10px;
}

.article-card {
    transition: all 0.3s ease;
}

.article-card .card {
    transition: all 0.3s ease;
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.article-card .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.card-img-top-wrapper {
    position: relative;
    overflow: hidden;
    height: 200px;
    background: #f8f9fa;
}

.card-img-top {
    height: 100%;
    width: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.article-card:hover .card-img-top {
    transform: scale(1.05);
}

.placeholder-image {
    height: 200px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: #6c757d;
}

.image-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}

.article-title {
    font-size: 1.1rem;
    line-height: 1.4;
    margin-bottom: 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.article-title a:hover {
    color: #007bff !important;
    text-decoration: underline !important;
}

.article-meta {
    font-size: 0.85em;
}

.category-item .card {
    transition: all 0.3s ease;
    border: none;
    border-radius: 12px;
}

.category-item .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.category-icon {
    transition: transform 0.3s ease;
}

.category-item:hover .category-icon {
    transform: scale(1.1);
}

.hover-item {
    transition: all 0.2s ease;
}

.hover-item:hover {
    background-color: #e9ecef !important;
    transform: translateX(5px);
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
    background: white;
}

.trending-item {
    transition: background-color 0.2s ease;
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 10px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.trending-item:hover {
    background-color: #f8f9fa;
}

.trending-number {
    background-color: #007bff;
    color: #fff;
    min-width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border-radius: 50%;
    font-size: 12px;
    flex-shrink: 0;
}

.trending-content {
    font-size: 13px;
    font-weight: 500;
    flex: 1;
}

.trending-content a {
    color: #333;
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
}

.trending-content a:hover {
    color: #007bff;
}

/* Premium Badge Styling */
.premium-badge {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.premium-badge i {
    font-size: 11px;
}

/* Premium Badge in Trending Items */
.trending-content .premium-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 6px;
    vertical-align: middle;
}

.trending-content .premium-indicator i {
    font-size: 10px;
}

.pagination .page-link {
    color: #007bff;
    border-color: #dee2e6;
    padding: 8px 12px;
}

.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}

.pagination .page-link:hover {
    background-color: #e9ecef;
    border-color: #dee2e6;
    color: #0056b3;
}

.breadcrumb {
    background-color: transparent;
    padding: 0;
    margin: 0;
}

.breadcrumb-item a {
    color: #6c757d;
    text-decoration: none;
}

.breadcrumb-item a:hover {
    color: #007bff;
}

@media (max-width: 768px) {
    .category-header {
        padding: 15px;
    }
    
    .card-img-top-wrapper {
        height: 180px;
    }
    
    .category-title {
        font-size: 1.5rem;
    }
}
</style>

<div class="container main-content mt-3">
    <div class="row">
        <div class="col-lg-8">
            <!-- Category Header -->
            <div class="category-header mb-4">
                <?php if ($category_info): ?>
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="flex-grow-1">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Beranda</a></li>
                                <li class="breadcrumb-item"><a href="berita.php">Berita</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($category_info['name']); ?></li>
                            </ol>
                        </nav>
                        <h2 class="category-title">
                            <i class="fas fa-folder-open me-2 text-primary"></i>
                            Kategori: <?php echo htmlspecialchars($category_info['name']); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="fas fa-calendar me-1"></i>
                            Dibuat: <?php echo date('d F Y', strtotime($category_info['created_at'])); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-newspaper me-1"></i>
                            <?php echo number_format($total_articles); ?> artikel
                        </p>
                    </div>
                    <div class="category-stats mt-3 mt-md-0">
                        <div class="bg-primary text-white rounded p-3 text-center">
                            <div class="fs-3 fw-bold"><?php echo number_format($total_articles); ?></div>
                            <small>Total Artikel</small>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- All Categories View -->
                <div class="text-center">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Beranda</a></li>
                            <li class="breadcrumb-item"><a href="berita.php">Berita</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Semua Kategori</li>
                        </ol>
                    </nav>
                    <h2 class="category-title">
                        <i class="fas fa-th-large me-2 text-primary"></i>
                        Semua Kategori Berita
                    </h2>
                    <p class="text-muted">Jelajahi berita berdasarkan kategori yang Anda minati</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($category_info): ?>
            <!-- Articles Grid -->
            <?php if (!empty($articles)): ?>
            <div class="articles-grid">
                <div class="row">
                    <?php foreach ($articles as $article): ?>
                    <div class="col-md-6 col-lg-6 mb-4">
                        <article class="article-card h-100">
                            <div class="card h-100 shadow-sm hover-shadow">
                                <!-- Image Display -->
                                <?php if (!empty($article['display_image'])): ?>
                                <div class="card-img-top-wrapper">
                                    <img src="<?php echo htmlspecialchars($article['display_image']); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($article['title']); ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="image-overlay">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-eye me-1"></i>
                                            <?php echo number_format($article['view_count']); ?>
                                        </span>
                                        <?php if ($article['post_status'] === 'Premium'): ?>
                                        <span class="premium-badge">
                                            <i class="fas fa-crown"></i> PREMIUM
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Placeholder -->
                                <div class="placeholder-image <?php echo empty($article['display_image']) ? 'd-flex' : 'd-none'; ?>">
                                    <i class="fas fa-newspaper fa-3x text-muted mb-2"></i>
                                    <small>Gambar tidak tersedia</small>
                                </div>
                                
                                <div class="card-body d-flex flex-column">
                                    <div class="article-meta mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d F Y', strtotime($article['publication_date'])); ?>
                                            <?php if ($article['author_name']): ?>
                                            <span class="mx-1">•</span>
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($article['author_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <h5 class="card-title article-title">
                                        <a href="artikel.php?id=<?php echo $article['article_id']; ?>" 
                                           class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <p class="card-text flex-grow-1">
                                        <?php echo htmlspecialchars(truncateText($article['content'])); ?>
                                    </p>
                                    
                                    <div class="mt-auto">
                                        <a href="artikel.php?id=<?php echo $article['article_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-arrow-right me-1"></i>
                                            Baca Selengkapnya
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Category pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?slug=<?php echo urlencode($category_info['slug']); ?>&page=<?php echo $current_page - 1; ?>">
                                <i class="fas fa-chevron-left"></i> Sebelumnya
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?slug=<?php echo urlencode($category_info['slug']); ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?slug=<?php echo urlencode($category_info['slug']); ?>&page=<?php echo $current_page + 1; ?>">
                                Selanjutnya <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
            <?php else: ?>
            <!-- No Articles -->
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-info-circle fa-3x mb-3 text-info"></i>
                <h4>Belum Ada Artikel</h4>
                <p class="mb-0">Belum ada artikel dalam kategori <strong><?php echo htmlspecialchars($category_info['name']); ?></strong>.</p>
                <div class="mt-3">
                    <a href="berita.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke Berita
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- All Categories Grid -->
            <?php if (!empty($all_categories)): ?>
            <div class="categories-grid">
                <div class="row">
                    <?php foreach ($all_categories as $cat): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="category-item">
                            <div class="card h-100 shadow-sm hover-shadow">
                                <div class="card-body text-center">
                                    <div class="category-icon mb-3">
                                        <i class="fas fa-folder fa-3x text-primary"></i>
                                    </div>
                                    <h5 class="card-title"><?php echo htmlspecialchars($cat['name']); ?></h5>
                                    <p class="text-muted mb-3">
                                        <i class="fas fa-newspaper me-1"></i>
                                        <?php echo number_format($cat['article_count']); ?> artikel
                                    </p>
                                    <a href="kategori.php?slug=<?php echo urlencode($cat['slug']); ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-eye me-2"></i>Lihat Artikel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- All Categories Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-list me-2"></i>Semua Kategori
                </div>
                <div class="p-3">
                    <?php if (!empty($all_categories)): ?>
                        <?php foreach ($all_categories as $cat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded hover-item">
                            <a href="kategori.php?slug=<?php echo urlencode($cat['slug']); ?>" 
                               class="text-decoration-none text-dark fw-bold flex-grow-1 <?php echo ($category_info && $category_info['category_id'] == $cat['category_id']) ? 'text-primary' : ''; ?>">
                                <i class="fas fa-folder me-2"></i>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo number_format($cat['article_count']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-folder-open fa-2x mb-2"></i>
                        <p>Belum ada kategori</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== ARTIKEL TERBARU DENGAN PREMIUM BADGE ===== -->
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-newspaper me-2"></i>Artikel Terbaru
                </div>
                <div class="p-3">
                    <?php if (!empty($latest_articles)): ?>
                        <?php $counter = 1; ?>
                        <?php foreach ($latest_articles as $latest): ?>
                        <div class="trending-item">
                            <div class="trending-number"><?php echo $counter; ?></div>
                            <div class="trending-content">
                                <a href="artikel.php?id=<?php echo $latest['article_id']; ?>">
                                    <?php echo htmlspecialchars($latest['title']); ?>
                                    <?php if ($latest['post_status'] === 'Premium'): ?>
                                    <span class="premium-indicator">
                                        <i class="fas fa-crown"></i> PREMIUM
                                    </span>
                                    <?php endif; ?>
                                </a>
                                <div class="text-muted small mt-1">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d M Y', strtotime($latest['publication_date'])); ?>
                                    <?php if ($latest['category_name']): ?>
                                    • <i class="fas fa-folder me-1"></i>
                                    <a href="kategori.php?slug=<?php echo urlencode($latest['category_slug']); ?>" 
                                       class="text-decoration-none text-muted">
                                        <?php echo htmlspecialchars($latest['category_name']); ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php $counter++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-newspaper fa-2x mb-2"></i>
                        <p>Belum ada artikel</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-chart-bar me-2"></i>Statistik Kategori
                </div>
                <div class="p-3">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <div class="fw-bold fs-4 text-primary"><?php echo count($all_categories); ?></div>
                            <small class="text-muted">Total Kategori</small>
                        </div>
                        <div class="col-6">
                            <?php
                            $total_all_articles = 0;
                            foreach ($all_categories as $cat) {
                                $total_all_articles += $cat['article_count'];
                            }
                            ?>
                            <div class="fw-bold fs-4 text-success"><?php echo number_format($total_all_articles); ?></div>
                            <small class="text-muted">Total Artikel</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Category page loaded with Latest Articles widget and Premium Badge');
    
    <?php if ($category_info): ?>
    console.log('Category:', '<?php echo addslashes($category_info['name']); ?>');
    console.log('Slug:', '<?php echo addslashes($category_info['slug']); ?>');
    console.log('Articles found:', <?php echo $total_articles; ?>);
    <?php endif; ?>
    
    console.log('Latest articles:', <?php echo count($latest_articles); ?>);
    
    // Count premium articles in latest
    let premiumCount = 0;
    <?php foreach ($latest_articles as $latest): ?>
        <?php if ($latest['post_status'] === 'Premium'): ?>
        premiumCount++;
        <?php endif; ?>
    <?php endforeach; ?>
    
    console.log('Premium articles in latest:', premiumCount);
    
    // Handle image loading
    const images = document.querySelectorAll('.card-img-top');
    images.forEach((img, index) => {
        img.addEventListener('error', function() {
            console.warn(`Image ${index + 1} failed to load:`, this.src);
            this.style.display = 'none';
            const placeholder = this.nextElementSibling;
            if (placeholder && placeholder.classList.contains('placeholder-image')) {
                placeholder.style.display = 'flex';
            }
        });
    });
    
    // Add hover effect for premium badges
    const premiumBadges = document.querySelectorAll('.premium-badge, .premium-indicator');
    premiumBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
});
</script>

<?php require 'footer.php'; ?>