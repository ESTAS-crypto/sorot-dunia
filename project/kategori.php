<?php
// kategori.php - Halaman kategori berita
ob_start();

// Include konfigurasi database
require 'config/config.php';

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include header
require 'header.php';

// Get category ID dari URL
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category_name = isset($_GET['name']) ? sanitize_input($_GET['name']) : '';

// Pagination setup
$articles_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $articles_per_page;

// Variables untuk data
$category_info = null;
$articles = [];
$total_articles = 0;
$total_pages = 1;
$all_categories = [];

try {
    // Jika ada category_id, ambil info kategori
    if ($category_id > 0) {
        $category_query = "SELECT * FROM categories WHERE category_id = $category_id LIMIT 1";
        $category_result = mysqli_query($koneksi, $category_query);
        
        if ($category_result && mysqli_num_rows($category_result) > 0) {
            $category_info = mysqli_fetch_assoc($category_result);
        }
        
        // Count total articles in this category
        $count_query = "SELECT COUNT(*) as total 
                       FROM articles a 
                       WHERE a.category_id = $category_id 
                       AND a.article_status = 'published'";
        
        $count_result = mysqli_query($koneksi, $count_query);
        if ($count_result) {
            $count_row = mysqli_fetch_assoc($count_result);
            $total_articles = $count_row['total'];
            $total_pages = ceil($total_articles / $articles_per_page);
        }
        
        // Get articles for this category
        $articles_query = "SELECT 
                            a.article_id,
                            a.title,
                            a.slug,
                            a.content,
                            a.publication_date,
                            a.view_count,
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
                          WHERE a.category_id = $category_id 
                          AND a.article_status = 'published'
                          ORDER BY a.publication_date DESC
                          LIMIT $articles_per_page OFFSET $offset";
        
        $articles_result = mysqli_query($koneksi, $articles_query);
        if ($articles_result) {
            while ($row = mysqli_fetch_assoc($articles_result)) {
                $articles[] = $row;
            }
        }
    }
    
    // Get all categories with article count untuk sidebar
    $all_categories_query = "SELECT 
                              c.category_id,
                              c.name,
                              c.created_at,
                              COUNT(a.article_id) as article_count
                            FROM categories c
                            LEFT JOIN articles a ON c.category_id = a.category_id 
                            AND a.article_status = 'published'
                            GROUP BY c.category_id, c.name, c.created_at
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

// Function helper untuk mendapatkan URL gambar
function getArticleImageUrl($image_filename, $image_url, $is_external = false) {
    if (empty($image_filename) && empty($image_url)) {
        return null;
    }
    
    if ($is_external && !empty($image_url)) {
        return $image_url;
    }
    
    if (!empty($image_filename)) {
        if (strpos($image_filename, '/') !== false) {
            return $image_filename;
        } else {
            return 'uploads/articles/' . $image_filename;
        }
    }
    
    return $image_url;
}

// Function untuk truncate text
function truncateText($text, $length = 150) {
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

ob_end_flush();
?>

<!-- Main Content -->
<div class="container main-content mt-3">
    <div class="row">
        <div class="col-lg-8">
            <!-- Category Header -->
            <div class="category-header mb-4">
                <?php if ($category_info): ?>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
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
                    <div class="category-stats">
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
            <!-- Articles Grid for Specific Category -->
            <?php if (!empty($articles)): ?>
            <div class="articles-grid">
                <div class="row">
                    <?php foreach ($articles as $article): ?>
                    <?php $article_image = getArticleImageUrl($article['image_filename'], $article['image_url'], $article['is_external']); ?>
                    <div class="col-md-6 col-lg-6 mb-4">
                        <article class="article-card h-100">
                            <div class="card h-100 shadow-sm hover-shadow">
                                <?php if ($article_image): ?>
                                <div class="card-img-top-wrapper">
                                    <img src="<?php echo htmlspecialchars($article_image); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($article['title']); ?>"
                                         loading="lazy"
                                         onerror="this.src='img/placeholder-news.jpg';">
                                    <div class="image-overlay">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-eye me-1"></i>
                                            <?php echo number_format($article['view_count']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="card-img-top placeholder-image d-flex align-items-center justify-content-center bg-light">
                                    <i class="fas fa-newspaper fa-3x text-muted"></i>
                                </div>
                                <?php endif; ?>
                                
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
                                    
                                    <h5 class="card-title">
                                        <a href="artikel.php?id=<?php echo $article['article_id']; ?>" 
                                           class="text-decoration-none text-dark article-title">
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
                        <!-- Previous Page -->
                        <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $current_page - 1; ?>">
                                <i class="fas fa-chevron-left"></i> Sebelumnya
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?php echo $category_id; ?>&page=1">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $total_pages; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Next Page -->
                        <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $current_page + 1; ?>">
                                Selanjutnya <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
            <?php else: ?>
            <!-- No Articles Found -->
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-info-circle fa-3x mb-3 text-info"></i>
                <h4>Belum Ada Artikel</h4>
                <p class="mb-0">Belum ada artikel yang dipublikasikan dalam kategori <strong><?php echo htmlspecialchars($category_info['name']); ?></strong>.</p>
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
                                    <p class="text-muted small mb-3">
                                        <i class="fas fa-calendar me-1"></i>
                                        Dibuat: <?php echo date('d M Y', strtotime($cat['created_at'])); ?>
                                    </p>
                                    <a href="kategori.php?id=<?php echo $cat['category_id']; ?>" 
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
            <?php else: ?>
            <!-- No Categories -->
            <div class="alert alert-warning text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                <h4>Belum Ada Kategori</h4>
                <p class="mb-0">Belum ada kategori yang tersedia saat ini.</p>
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
                            <a href="kategori.php?id=<?php echo $cat['category_id']; ?>" 
                               class="text-decoration-none text-dark fw-bold flex-grow-1 <?php echo ($category_id == $cat['category_id']) ? 'text-primary' : ''; ?>">
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

            <!-- Latest Articles Across All Categories -->
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-newspaper me-2"></i>Artikel Terbaru
                </div>
                <div class="p-3">
                    <?php
                    // Get latest articles across all categories
                    $latest_query = "SELECT 
                                        a.article_id,
                                        a.title,
                                        a.publication_date,
                                        c.name as category_name
                                    FROM articles a
                                    LEFT JOIN categories c ON a.category_id = c.category_id
                                    WHERE a.article_status = 'published'
                                    ORDER BY a.publication_date DESC
                                    LIMIT 5";
                    
                    $latest_result = mysqli_query($koneksi, $latest_query);
                    if ($latest_result && mysqli_num_rows($latest_result) > 0):
                        $counter = 1;
                        while ($latest = mysqli_fetch_assoc($latest_result)):
                    ?>
                    <div class="trending-item">
                        <div class="trending-number"><?php echo $counter; ?></div>
                        <div class="trending-content">
                            <a href="artikel.php?id=<?php echo $latest['article_id']; ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($latest['title']); ?>
                            </a>
                            <div class="text-muted small mt-1">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d M Y', strtotime($latest['publication_date'])); ?>
                                <?php if ($latest['category_name']): ?>
                                • <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($latest['category_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php $counter++; endwhile; ?>
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

<!-- Custom CSS untuk kategori -->
<style>
.category-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #007bff;
}

.category-title {
    color: #333;
    font-weight: 700;
    margin-bottom: 10px;
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
}

.card-img-top {
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.article-card:hover .card-img-top {
    transform: scale(1.05);
}

.placeholder-image {
    height: 200px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.image-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
}

.article-title:hover {
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

.hover-shadow {
    transition: box-shadow 0.3s ease;
}

.hover-shadow:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.hover-item:hover {
    background-color: #e9ecef !important;
    transform: translateX(5px);
    transition: all 0.2s ease;
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

.trending-item {
    transition: background-color 0.2s ease;
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 10px;
}

.trending-item:hover {
    background-color: #f8f9fa;
}

.trending-number {
    background-color: #007bff;
    color: #fff;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border-radius: 50%;
    margin-right: 10px;
    font-size: 12px;
    flex-shrink: 0;
}

.trending-content {
    font-size: 13px;
    font-weight: 500;
    flex: 1;
}

@media (max-width: 768px) {
    .category-header {
        padding: 15px;
    }
    
    .category-stats {
        margin-top: 15px;
    }
    
    .card-img-top-wrapper {
        height: 180px;
    }
    
    .category-title {
        font-size: 1.5rem;
    }
}
</style>

<!-- JavaScript untuk enhance interactivity -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add loading animation untuk images
    const images = document.querySelectorAll('.card-img-top');
    images.forEach(img => {
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
        
        img.style.opacity = '0';
        img.style.transition = 'opacity 0.3s ease';
    });
    
    // Add smooth scroll untuk pagination
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add loading state
            const icon = this.querySelector('i');
            if (icon && (icon.classList.contains('fa-chevron-left') || icon.classList.contains('fa-chevron-right'))) {
                icon.className = 'fas fa-spinner fa-spin';
            }
        });
    });
    
    console.log('Kategori page loaded successfully');
});
</script>

<?php
// Include footer
require 'footer.php';
?>