<?php
$page_title = "Kelola Berita";
require_once 'header.php';
require_once '../config/image_config.php';

// Get user articles with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query based on user role
if (isAdmin()) {
    $query = "SELECT a.*, u.full_name as author_name, c.name as category_name 
              FROM articles a 
              LEFT JOIN users u ON a.author_id = u.id 
              LEFT JOIN categories c ON a.category_id = c.category_id 
              ORDER BY a.publication_date DESC 
              LIMIT ? OFFSET ?";
    $count_query = "SELECT COUNT(*) FROM articles";
} else {
    $query = "SELECT a.*, u.full_name as author_name, c.name as category_name 
              FROM articles a 
              LEFT JOIN users u ON a.author_id = u.id 
              LEFT JOIN categories c ON a.category_id = c.category_id 
              WHERE a.author_id = ? 
              ORDER BY a.publication_date DESC 
              LIMIT ? OFFSET ?";
    $count_query = "SELECT COUNT(*) FROM articles WHERE author_id = ?";
}

// Get total count
if (isAdmin()) {
    $count_result = mysqli_query($koneksi, $count_query);
} else {
    $count_stmt = mysqli_prepare($koneksi, $count_query);
    mysqli_stmt_bind_param($count_stmt, "i", $current_user['id']);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
}
$total_articles = mysqli_fetch_row($count_result)[0];
$total_pages = ceil($total_articles / $limit);

// Get articles
if (isAdmin()) {
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
} else {
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "iii", $current_user['id'], $limit, $offset);
}

mysqli_stmt_execute($stmt);
$articles_result = mysqli_stmt_get_result($stmt);
$articles = [];
while ($row = mysqli_fetch_assoc($articles_result)) {
    $articles[] = $row;
}

// Get statistics for admin
if (isAdmin()) {
    $stats = [
        'total' => $total_articles,
        'published' => mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM articles WHERE article_status = 'published'"))[0],
        'pending' => mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM articles WHERE article_status = 'pending'"))[0],
        'rejected' => mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM articles WHERE article_status = 'rejected'"))[0]
    ];
} else {
    $stats = [
        'total' => $total_articles,
        'published' => mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM articles WHERE article_status = 'published' AND author_id = {$current_user['id']}"))[0],
        'pending' => mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM articles WHERE article_status = 'pending' AND author_id = {$current_user['id']}"))[0],
        'rejected' => mysqli_fetch_row(mysqli_query($koneksi, "SELECT COUNT(*) FROM articles WHERE article_status = 'rejected' AND author_id = {$current_user['id']}"))[0]
    ];
}
?>

<style>
/* Additional styles for manage page */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: var(--white);
    border: 2px solid var(--border-color);
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-color);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-heavy);
    border-color: var(--primary-color);
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--accent-color);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.875rem;
}

.stat-card.pending .stat-icon,
.stat-card.pending .stat-number {
    color: #ffc107;
}

.stat-card.published .stat-icon,
.stat-card.published .stat-number {
    color: #28a745;
}

.stat-card.rejected .stat-icon,
.stat-card.rejected .stat-number {
    color: #dc3545;
}

/* Article Cards */
.articles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.article-card {
    background: var(--white);
    border: 2px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.article-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-heavy);
    border-color: var(--primary-color);
}

.article-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: var(--light-gray);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-color);
    font-size: 3rem;
}

.article-content {
    padding: 1.5rem;
}

.article-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.75rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.article-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
    align-items: center;
}

.meta-item {
    font-size: 0.875rem;
    color: var(--accent-color);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-published {
    background: #d4edda;
    color: #155724;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.article-excerpt {
    color: var(--accent-color);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.article-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-action {
    padding: 0.5rem;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: var(--white);
    color: var(--accent-color);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
}

.btn-action:hover {
    background: var(--hover-color);
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.btn-action.btn-view {
    border-color: #17a2b8;
    color: #17a2b8;
}

.btn-action.btn-view:hover {
    background: #17a2b8;
    color: var(--white);
}

.btn-action.btn-edit {
    border-color: #6c757d;
    color: #6c757d;
}

.btn-action.btn-edit:hover {
    background: #6c757d;
    color: var(--white);
}

.btn-action.btn-approve {
    border-color: #28a745;
    color: #28a745;
}

.btn-action.btn-approve:hover {
    background: #28a745;
    color: var(--white);
}

.btn-action.btn-reject {
    border-color: #ffc107;
    color: #ffc107;
}

.btn-action.btn-reject:hover {
    background: #ffc107;
    color: var(--primary-color);
}

.btn-action.btn-delete {
    border-color: #dc3545;
    color: #dc3545;
}

.btn-action.btn-delete:hover {
    background: #dc3545;
    color: var(--white);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--accent-color);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 2rem;
    color: var(--border-color);
}

.empty-state h3 {
    color: var(--secondary-color);
    margin-bottom: 1rem;
}

.empty-state p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

/* Pagination */
.pagination {
    justify-content: center;
    margin-top: 3rem;
}

.page-link {
    border: 2px solid var(--border-color);
    color: var(--secondary-color);
    background: var(--white);
    border-radius: 8px !important;
    margin: 0 0.25rem;
    padding: 0.75rem 1rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.page-link:hover {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--white);
}

.page-item.active .page-link {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--white);
}

.page-item.disabled .page-link {
    background: var(--light-gray);
    border-color: var(--border-color);
    color: var(--accent-color);
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .stat-card {
        padding: 1.5rem 1rem;
    }

    .stat-number {
        font-size: 2rem;
    }

    .stat-icon {
        font-size: 2rem;
    }

    .articles-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .article-actions {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Main Content -->
<div class="container" style="margin-top: 100px; margin-bottom: 50px;">
    <div class="upload-container">
        <div class="upload-header">
            <h2><i class="fas fa-cog me-3"></i>Kelola Berita</h2>
            <p>
                <?php if (isAdmin()): ?>
                Kelola semua artikel di sistem
                <?php else: ?>
                Kelola artikel yang Anda tulis
                <?php endif; ?>
            </p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Artikel</div>
            </div>

            <div class="stat-card published">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['published']; ?></div>
                <div class="stat-label">Published</div>
            </div>

            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card rejected">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Articles -->
        <?php if (empty($articles)): ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <h3>Belum ada artikel</h3>
            <p>Mulai dengan mengupload artikel pertama Anda.</p>
            <a href="uplod.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Upload Artikel Baru
            </a>
        </div>
        <?php else: ?>
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
            <div class="article-card">
                <?php if (!empty($article['image_filename'])): ?>
                <img src="<?php echo getArticleImageUrl($article['image_filename']); ?>"
                    alt="<?php echo htmlspecialchars($article['title']); ?>" class="article-image">
                <?php else: ?>
                <div class="article-image">
                    <i class="fas fa-image"></i>
                </div>
                <?php endif; ?>

                <div class="article-content">
                    <h3 class="article-title">
                        <?php echo htmlspecialchars($article['title']); ?>
                    </h3>

                    <div class="article-meta">
                        <?php if (isAdmin()): ?>
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($article['author_name']); ?>
                        </div>
                        <?php endif; ?>

                        <div class="meta-item">
                            <i class="fas fa-folder"></i>
                            <?php echo ucfirst(htmlspecialchars($article['category_name'])); ?>
                        </div>

                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <?php echo date('d/m/Y', strtotime($article['publication_date'])); ?>
                        </div>

                        <span class="status-badge status-<?php echo $article['article_status']; ?>">
                            <?php echo ucfirst($article['article_status']); ?>
                        </span>
                    </div>

                    <div class="article-excerpt">
                        <?php echo htmlspecialchars(substr(strip_tags($article['content']), 0, 150)) . '...'; ?>
                    </div>

                    <div class="article-actions">
                        <?php if ($article['article_status'] === 'published' && !empty($article['title_slug'])): ?>
                        <a href="/project/article.php?slug=<?php echo $article['title_slug']; ?>"
                            class="btn-action btn-view" title="Lihat Artikel" target="_blank">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php endif; ?>

                        <button class="btn-action btn-edit" onclick="editArticle(<?php echo $article['article_id']; ?>)"
                            title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>

                        <?php if (isAdmin() && $article['article_status'] === 'pending'): ?>
                        <button class="btn-action btn-approve"
                            onclick="approveArticle(<?php echo $article['article_id']; ?>)" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>

                        <button class="btn-action btn-reject"
                            onclick="rejectArticle(<?php echo $article['article_id']; ?>)" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>

                        <button class="btn-action btn-delete"
                            onclick="deleteArticle(<?php echo $article['article_id']; ?>)" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left me-1"></i>Previous
                    </a>
                </li>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>

                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                        Next<i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>