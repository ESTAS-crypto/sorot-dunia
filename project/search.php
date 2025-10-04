<?php
// search.php - Complete Search Functionality
ob_start();

require 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'header.php';

// Get search query
$search_query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
$search_term = '%' . $search_query . '%';

// Pagination
$articles_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $articles_per_page;

$articles = [];
$total_articles = 0;
$total_pages = 1;
$categories = [];

try {
    if (!empty($search_query)) {
        // Count total matching articles
        $count_query = "SELECT COUNT(DISTINCT a.article_id) as total 
                       FROM articles a
                       LEFT JOIN categories c ON a.category_id = c.category_id
                       LEFT JOIN users u ON a.author_id = u.id
                       LEFT JOIN article_tags at ON a.article_id = at.article_id
                       LEFT JOIN tags t ON at.tag_id = t.id
                       WHERE a.article_status = 'published'
                       AND (a.title LIKE ? 
                            OR a.content LIKE ? 
                            OR a.meta_description LIKE ?
                            OR c.name LIKE ?
                            OR u.full_name LIKE ?
                            OR t.name LIKE ?)";
        
        $stmt = mysqli_prepare($koneksi, $count_query);
        mysqli_stmt_bind_param($stmt, "ssssss", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
        mysqli_stmt_execute($stmt);
        $count_result = mysqli_stmt_get_result($stmt);
        
        if ($count_result) {
            $count_row = mysqli_fetch_assoc($count_result);
            $total_articles = $count_row['total'];
            $total_pages = ceil($total_articles / $articles_per_page);
        }
        mysqli_stmt_close($stmt);
        
        // Get matching articles with full details
        $articles_query = "SELECT DISTINCT
                            a.article_id,
                            a.title,
                            a.content,
                            a.meta_description,
                            a.publication_date,
                            a.view_count,
                            u.full_name as author_name,
                            u.username as author_username,
                            c.name as category_name,
                            c.category_id,
                            i.filename as image_filename,
                            i.url as image_url,
                            i.is_external,
                            GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') as tags
                          FROM articles a
                          LEFT JOIN users u ON a.author_id = u.id
                          LEFT JOIN categories c ON a.category_id = c.category_id
                          LEFT JOIN images i ON a.featured_image_id = i.id
                          LEFT JOIN article_tags at ON a.article_id = at.article_id
                          LEFT JOIN tags t ON at.tag_id = t.id
                          WHERE a.article_status = 'published'
                          AND (a.title LIKE ? 
                               OR a.content LIKE ? 
                               OR a.meta_description LIKE ?
                               OR c.name LIKE ?
                               OR u.full_name LIKE ?
                               OR t.name LIKE ?)
                          GROUP BY a.article_id
                          ORDER BY a.publication_date DESC
                          LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($koneksi, $articles_query);
        mysqli_stmt_bind_param($stmt, "ssssssii", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $articles_per_page, $offset);
        mysqli_stmt_execute($stmt);
        $articles_result = mysqli_stmt_get_result($stmt);
        
        if ($articles_result) {
            while ($row = mysqli_fetch_assoc($articles_result)) {
                // Handle image URL
                if (!empty($row['image_filename']) && !$row['is_external']) {
                    $row['display_image'] = 'uploads/articles/' . $row['image_filename'];
                } elseif (!empty($row['image_url']) && $row['is_external']) {
                    $row['display_image'] = $row['image_url'];
                } else {
                    $row['display_image'] = null;
                }
                
                $articles[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get all categories for sidebar
    $categories_query = "SELECT 
                          c.category_id,
                          c.name,
                          COUNT(a.article_id) as article_count
                        FROM categories c
                        LEFT JOIN articles a ON c.category_id = a.category_id 
                        AND a.article_status = 'published'
                        GROUP BY c.category_id, c.name
                        ORDER BY article_count DESC, c.name ASC";
    
    $categories_result = mysqli_query($koneksi, $categories_query);
    if ($categories_result) {
        while ($row = mysqli_fetch_assoc($categories_result)) {
            $categories[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Error dalam search.php: " . $e->getMessage());
}

function truncateText($text, $length = 150) {
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function highlightSearchTerm($text, $search_query) {
    if (empty($search_query)) return $text;
    return preg_replace('/(' . preg_quote($search_query, '/') . ')/i', '<mark>$1</mark>', $text);
}

ob_end_flush();
?>

<div class="container main-content mt-3">
    <div class="row">
        <div class="col-lg-8">
            <!-- Search Header -->
            <div class="search-header mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> Beranda</a></li>
                        <li class="breadcrumb-item active">Pencarian</li>
                    </ol>
                </nav>
                
                <?php if (!empty($search_query)): ?>
                <h2 class="search-title">
                    <i class="fas fa-search me-2 text-primary"></i>
                    Hasil Pencarian: "<?php echo htmlspecialchars($search_query); ?>"
                </h2>
                <p class="text-muted">
                    Ditemukan <strong><?php echo number_format($total_articles); ?></strong> artikel
                </p>
                <?php else: ?>
                <h2 class="search-title">
                    <i class="fas fa-search me-2 text-primary"></i>
                    Pencarian Artikel
                </h2>
                <p class="text-muted">Masukkan kata kunci untuk mencari artikel</p>
                <?php endif; ?>
            </div>

            <!-- Search Form (Visible on search page) -->
            <div class="search-box mb-4">
                <form method="GET" action="search.php" class="search-form-large">
                    <div class="input-group input-group-lg">
                        <input type="search" 
                               name="q" 
                               class="form-control" 
                               placeholder="Cari berita, kategori, penulis, atau tag..." 
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               autofocus>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-1"></i> Cari
                        </button>
                    </div>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Tips: Cari berdasarkan judul, konten, kategori, nama penulis, atau tag
                    </small>
                </form>
            </div>

            <!-- Search Results -->
            <?php if (!empty($search_query)): ?>
                <?php if (!empty($articles)): ?>
                <div class="search-results">
                    <div class="row">
                        <?php foreach ($articles as $article): ?>
                        <div class="col-md-6 mb-4">
                            <article class="article-card h-100">
                                <div class="card h-100 shadow-sm hover-shadow">
                                    <?php if ($article['display_image']): ?>
                                    <div class="card-img-top-wrapper">
                                        <img src="<?php echo htmlspecialchars($article['display_image']); ?>" 
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
                                    <div class="card-img-top placeholder-image">
                                        <i class="fas fa-newspaper fa-3x text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <div class="article-meta mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d F Y', strtotime($article['publication_date'])); ?>
                                                <?php if ($article['category_name']): ?>
                                                <span class="mx-1">•</span>
                                                <i class="fas fa-folder me-1"></i>
                                                <a href="kategori.php?id=<?php echo $article['category_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($article['category_name']); ?>
                                                </a>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <h5 class="card-title">
                                            <a href="artikel.php?id=<?php echo $article['article_id']; ?>" 
                                               class="text-decoration-none text-dark article-title">
                                                <?php echo highlightSearchTerm(htmlspecialchars($article['title']), $search_query); ?>
                                            </a>
                                        </h5>
                                        
                                        <p class="card-text flex-grow-1">
                                            <?php echo highlightSearchTerm(truncateText($article['content']), $search_query); ?>
                                        </p>
                                        
                                        <?php if ($article['tags']): ?>
                                        <div class="article-tags mb-2">
                                            <i class="fas fa-tags text-muted me-1"></i>
                                            <?php 
                                            $tags = explode(', ', $article['tags']);
                                            foreach (array_slice($tags, 0, 3) as $tag): 
                                            ?>
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
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
                    <nav aria-label="Search pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $current_page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i> Sebelumnya
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=1">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $total_pages; ?>">
                                        <?php echo $total_pages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $current_page + 1; ?>">
                                    Selanjutnya <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>
                <?php else: ?>
                <!-- No Results -->
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-search fa-3x mb-3 text-info"></i>
                    <h4>Tidak Ada Hasil Ditemukan</h4>
                    <p class="mb-3">Pencarian untuk "<strong><?php echo htmlspecialchars($search_query); ?></strong>" tidak menemukan hasil.</p>
                    <div class="search-suggestions">
                        <h6>Saran Pencarian:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check-circle text-success"></i> Periksa ejaan kata kunci</li>
                            <li><i class="fas fa-check-circle text-success"></i> Gunakan kata kunci yang lebih umum</li>
                            <li><i class="fas fa-check-circle text-success"></i> Coba kata kunci yang berbeda</li>
                            <li><i class="fas fa-check-circle text-success"></i> Cari berdasarkan kategori di sidebar</li>
                        </ul>
                    </div>
                    <div class="mt-3">
                        <a href="berita.php" class="btn btn-primary me-2">
                            <i class="fas fa-newspaper me-1"></i>Lihat Semua Berita
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-1"></i>Kembali ke Beranda
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <!-- Empty Search State -->
            <div class="empty-search-state text-center py-5">
                <i class="fas fa-search fa-5x text-muted mb-4"></i>
                <h3>Temukan Berita yang Anda Cari</h3>
                <p class="text-muted mb-4">Gunakan form pencarian di atas untuk menemukan artikel berdasarkan judul, konten, kategori, atau penulis.</p>
                
                <div class="popular-searches mt-4">
                    <h5>Kategori Populer:</h5>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                        <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
                        <a href="kategori.php?id=<?php echo $cat['category_id']; ?>" 
                           class="btn btn-outline-primary">
                            <?php echo htmlspecialchars($cat['name']); ?> 
                            <span class="badge bg-primary"><?php echo $cat['article_count']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Categories Sidebar -->
            <?php if (!empty($categories)): ?>
            <div class="sidebar">
                <div class="sidebar-header">
                    <i class="fas fa-list me-2"></i>Kategori
                </div>
                <div class="p-3">
                    <?php foreach ($categories as $cat): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded hover-item">
                        <a href="kategori.php?id=<?php echo $cat['category_id']; ?>" 
                           class="text-decoration-none text-dark fw-bold flex-grow-1">
                            <i class="fas fa-folder me-2"></i>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                        <span class="badge bg-primary rounded-pill">
                            <?php echo number_format($cat['article_count']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Search Tips -->
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-lightbulb me-2"></i>Tips Pencarian
                </div>
                <div class="p-3">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Kata Kunci Spesifik:</strong> Gunakan kata kunci yang spesifik untuk hasil lebih akurat
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Nama Penulis:</strong> Cari artikel berdasarkan nama penulis
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Tag:</strong> Gunakan tag untuk menemukan artikel terkait
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Kategori:</strong> Filter berdasarkan kategori di sidebar
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="sidebar mt-3">
                <div class="sidebar-header">
                    <i class="fas fa-link me-2"></i>Link Cepat
                </div>
                <div class="p-3">
                    <a href="berita.php" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-newspaper me-2"></i>Semua Berita
                    </a>
                    <a href="kategori.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-th-large me-2"></i>Semua Kategori
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.search-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #007bff;
}

.search-title {
    color: #333;
    font-weight: 700;
    margin-bottom: 10px;
}

.search-box {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.search-form-large .input-group {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.search-form-large .form-control {
    border: 2px solid #e9ecef;
    padding: 12px 20px;
}

.search-form-large .form-control:focus {
    border-color: #007bff;
    box-shadow: none;
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
    display: flex;
    align-items: center;
    justify-content: center;
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

.article-tags .badge {
    font-size: 0.75em;
    font-weight: normal;
}

mark {
    background-color: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 600;
}

.search-suggestions ul li {
    padding: 5px 0;
    text-align: left;
}

.empty-search-state {
    background: white;
    border-radius: 10px;
    padding: 40px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.popular-searches .btn {
    margin: 5px;
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
    margin: 0 0 15px 0;
}

.breadcrumb-item a {
    color: #6c757d;
    text-decoration: none;
}

.breadcrumb-item a:hover {
    color: #007bff;
}

@media (max-width: 768px) {
    .search-header {
        padding: 15px;
    }
    
    .card-img-top-wrapper {
        height: 180px;
    }
    
    .search-title {
        font-size: 1.5rem;
    }
    
    .empty-search-state {
        padding: 30px 15px;
    }
}
</style>

<!-- JavaScript Enhancement -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Highlight search terms in results
    const searchQuery = "<?php echo addslashes($search_query); ?>";
    
    if (searchQuery) {
        console.log('Search performed for:', searchQuery);
    }
    
    // Add loading animation for images
    const images = document.querySelectorAll('.card-img-top');
    images.forEach(img => {
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
        
        img.style.opacity = '0';
        img.style.transition = 'opacity 0.3s ease';
    });
    
    // Smooth scroll for pagination
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
    
    console.log('Search page loaded successfully');
});
</script>

<?php
require 'footer.php';
?>