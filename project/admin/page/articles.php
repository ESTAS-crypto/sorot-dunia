<?php
// admin/page/articles.php - COMPLETE FIXED VERSION WITH ALL FEATURES
require_once '../config/config.php';
require_once '../config/auth_check.php';

checkAdminRole();

$error_message = '';
$success_message = '';

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_article') {
    $delete_id = intval($_POST['article_id'] ?? 0);
    
    $check_query = "SELECT article_id, author_id, featured_image_id FROM articles WHERE article_id = ?";
    $stmt_check = mysqli_prepare($koneksi, $check_query);
    if ($stmt_check) {
        mysqli_stmt_bind_param($stmt_check, "i", $delete_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if ($row = mysqli_fetch_assoc($result_check)) {
            if (isAdmin() || $row['author_id'] == $_SESSION['user_id']) {
                $delete_query = "DELETE FROM articles WHERE article_id = ?";
                $stmt_delete = mysqli_prepare($koneksi, $delete_query);
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $delete_id);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        $success_message = 'Artikel berhasil dihapus';
                    } else {
                        $error_message = 'Gagal menghapus artikel: ' . mysqli_error($koneksi);
                    }
                    mysqli_stmt_close($stmt_delete);
                }
            } else {
                $error_message = 'Tidak memiliki izin untuk menghapus artikel ini';
            }
        } else {
            $error_message = 'Artikel tidak ditemukan';
        }
        mysqli_stmt_close($stmt_check);
    }
}

// Fetch all articles with tags
$query = "SELECT a.*, u.username as author_name, c.name as category_name,
          approver.username as approved_by_name, rejecter.username as rejected_by_name,
          i.id as image_id, i.filename as image_filename, i.url as image_url, i.is_external,
          GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as tags,
          s.slug as article_slug
          FROM articles a 
          LEFT JOIN users u ON a.author_id = u.id 
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN users approver ON a.approved_by = approver.id
          LEFT JOIN users rejecter ON a.rejected_by = rejecter.id
          LEFT JOIN images i ON a.featured_image_id = i.id
          LEFT JOIN article_tags at ON a.article_id = at.article_id
          LEFT JOIN tags t ON at.tag_id = t.id
          LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
          GROUP BY a.article_id
          ORDER BY a.publication_date DESC";
$result = mysqli_query($koneksi, $query);

if (!$result) {
    $error_message = 'Error mengambil data artikel: ' . mysqli_error($koneksi);
    $all_articles = [];
} else {
    $all_articles = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Fetch categories
$query = "SELECT category_id, name FROM categories ORDER BY name";
$result = mysqli_query($koneksi, $query);
$categories = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[$row['category_id']] = $row['name'];
    }
}

function formatDate($date) {
    return $date && $date != '0000-00-00 00:00:00' ? date('d M Y H:i', strtotime($date)) : '-';
}

function getStatusBadge($status) {
    $badges = [
        'published' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Published</span>',
        'draft' => '<span class="badge bg-warning text-dark"><i class="bi bi-pencil me-1"></i>Draft</span>',
        'pending' => '<span class="badge bg-info"><i class="bi bi-clock me-1"></i>Pending Review</span>',
        'rejected' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>',
        'archived' => '<span class="badge bg-secondary"><i class="bi bi-archive me-1"></i>Archived</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

function getPostStatusBadge($status) {
    if ($status === 'Premium') {
        return '<span class="badge" style="background: linear-gradient(135deg, #ffd700, #ffed4e); color: #000;"><i class="fas fa-crown me-1"></i>Premium</span>';
    }
    return '<span class="badge bg-secondary"><i class="bi bi-globe me-1"></i>Free</span>';
}
?>

<!-- Summernote CSS Bootstrap 5 - LOAD FIRST -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.css" rel="stylesheet">
<!-- Custom Article Styles - Load after Summernote -->
<link href="../style/articles.css" rel="stylesheet">
<style>
    .user-stats-card.secondary::before {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    }
    
    /* Tag Display Styles */
    .tag-display {
        min-height: 40px;
        padding: 0.75rem;
        background: var(--bg-tertiary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
        margin-top: 0.5rem;
    }
    
    .tag-item {
        display: inline-block;
        background: var(--primary-color);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        margin: 0.25rem;
        font-size: 0.875rem;
        background: #404040;
    }
    
    .tag-remove {
        background: none;
        border: none;
        color: white;
        margin-left: 0.5rem;
        cursor: pointer;
        font-weight: bold;
    }
    
    .tag-remove:hover {
        color: #ff6b6b;
    }
    
    /* Slug Display */
    .slug-container {
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: var(--bg-tertiary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    
    .slug-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }
    
    .slug-display {
        font-family: monospace;
        color: var(--text-primary);
        font-weight: 500;
        word-break: break-all;
    }
    
    /* Character Counter */
    .char-counter {
        font-size: 0.875rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
        text-align: right;
    }
    
    .char-counter.warning {
        color: #ffc107;
    }
    
    .char-counter.danger {
        color: #dc3545;
    }
    
    /* View Article Styles */
    .article-meta-item {
        margin-bottom: 0.5rem;
        padding: 0.5rem;
        background: var(--bg-tertiary);
        border-radius: 6px;
    }
    
    .article-summary-box {
        background: var(--bg-tertiary);
        border-left: 4px solid var(--accent);
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 4px;
    }
    
    .article-tags-box {
        margin-top: 1rem;
        padding: 1rem;
        background: var(--bg-tertiary);
        border-radius: 8px;
    }
</style>

<!-- Header -->
<div class="mb-3 mb-md-4">
    <div class="card bg-secondary text-white">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <div>
                    <h2 class="mb-1 h4 h-md-2">
                        <i class="bi bi-newspaper me-2"></i>
                        Kelola Artikel
                    </h2>
                    <small class="text-muted">Manajemen artikel dan konten website</small>
                </div>
                <button type="button" class="btn btn-primary mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#addArticleModal" style="color: black;">
                    <i class="bi bi-plus me-2" style="color: black;"></i>Tambah Artikel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo htmlspecialchars($success_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>
    <?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="user-stats-card primary">
            <div class="user-stat-icon primary">
                <i class="bi bi-newspaper"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo count($all_articles); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total</div>
        </div>
    </div>
    
    <div class="col-6 col-md-2">
        <div class="user-stats-card secondary">
            <div class="user-stat-icon secondary" style="background: #404040;">
                <i class="bi bi-file-earmark"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'draft')); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Draft</div>
        </div>
    </div>
    
    <div class="col-6 col-md-2">
        <div class="user-stats-card warning">
            <div class="user-stat-icon warning">
                <i class="bi bi-clock"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'pending')); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Pending</div>
        </div>
    </div>
    
    <div class="col-6 col-md-2">
        <div class="user-stats-card success">
            <div class="user-stat-icon success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'published')); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Published</div>
        </div>
    </div>
    
    <div class="col-6 col-md-2">
        <div class="user-stats-card danger">
            <div class="user-stat-icon danger">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'rejected')); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Rejected</div>
        </div>
    </div>
</div>

<!-- Bulk Delete Actions -->
<div class="card mb-4">
    <div class="card-header" style="color: white;">
        <h5 class="mb-0">
            <i class="bi bi-trash me-2"></i>Bulk Delete Actions
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Perhatian:</strong> Fitur ini akan menghapus artikel yang lebih dari 7 hari beserta gambarnya.
        </div>
        
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card bg-info bg-opacity-10 border-info">
                    <div class="card-body">
                        <h6 class="text-info">
                            <i class="bi bi-clock me-2"></i>Pending Lama
                        </h6>
                        <p class="small text-muted mb-3">
                            Hapus semua artikel pending yang sudah lebih dari 7 hari
                        </p>
                        <button class="btn btn-info w-100" onclick="showBulkDeleteModal('pending')">
                            <i class="bi bi-trash me-2"></i>Hapus Pending Lama
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-warning bg-opacity-10 border-warning">
                    <div class="card-body">
                        <h6 class="text-warning">
                            <i class="bi bi-pencil me-2"></i>Draft Lama
                        </h6>
                        <p class="small text-muted mb-3">
                            Hapus semua artikel draft yang sudah lebih dari 7 hari
                        </p>
                        <button class="btn btn-warning w-100" onclick="showBulkDeleteModal('draft')">
                            <i class="bi bi-trash me-2"></i>Hapus Draft Lama
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-danger bg-opacity-10 border-danger">
                    <div class="card-body">
                        <h6 class="text-danger">
                            <i class="bi bi-x-circle me-2"></i>Rejected Lama
                        </h6>
                        <p class="small text-muted mb-3">
                            Hapus semua artikel rejected yang sudah lebih dari 7 hari
                        </p>
                        <button class="btn btn-danger w-100" onclick="showBulkDeleteModal('rejected')">
                            <i class="bi bi-trash me-2"></i>Hapus Rejected Lama
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="card">
    <div class="card-body">
        <div class="mb-4">
            <div class="d-flex gap-2 overflow-auto pb-2" id="statusFilter" style="white-space: nowrap;">
                <a class="btn btn-sm btn-outline-light active flex-shrink-0" href="#" data-filter="all">
                    <i class="bi bi-list me-1"></i>Semua
                    <span class="badge bg-secondary ms-1"><?php echo count($all_articles); ?></span>
                </a>
                <a class="btn btn-sm btn-outline-secondary flex-shrink-0" href="#" data-filter="draft">
                    <i class="bi bi-file-earmark me-1"></i>Draft
                    <span class="badge bg-secondary ms-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'draft')); ?></span>
                </a>
                <a class="btn btn-sm btn-outline-info flex-shrink-0" href="#" data-filter="pending">
                    <i class="bi bi-clock me-1"></i>Pending
                    <span class="badge bg-info ms-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'pending')); ?></span>
                </a>
                <a class="btn btn-sm btn-outline-success flex-shrink-0" href="#" data-filter="published">
                    <i class="bi bi-check-circle me-1"></i>Published
                    <span class="badge bg-success ms-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'published')); ?></span>
                </a>
                <a class="btn btn-sm btn-outline-danger flex-shrink-0" href="#" data-filter="rejected">
                    <i class="bi bi-x-circle me-1"></i>Rejected
                    <span class="badge bg-danger ms-1"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'rejected')); ?></span>
                </a>
            </div>
        </div>

        <!-- Mobile Cards View -->
        <div class="d-md-none" id="mobileArticlesList">
            <?php if (empty($all_articles)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox display-1 mb-3 d-block opacity-50"></i>
                <h5>Belum Ada Artikel</h5>
                <p>Klik tombol "Tambah Artikel" untuk membuat artikel pertama</p>
            </div>
            <?php else: ?>
            <?php foreach ($all_articles as $article): ?>
            <div class="card mb-3" data-status="<?php echo $article['article_status']; ?>">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1">
                            <h6 class="card-title mb-1 text-truncate" style="max-width: 200px; color:white;" title="<?php echo htmlspecialchars($article['title']); ?>">
                                <?php echo htmlspecialchars($article['title']); ?>
                            </h6>
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary mb-1">#<?php echo $article['article_id']; ?></span>
                            <br><?php echo getStatusBadge($article['article_status']); ?>
                            <br><?php echo getPostStatusBadge($article['post_status']); ?>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <small class="text-muted">
                                <i class="bi bi-folder me-1"></i>
                                <?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                            </small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo date('d/m/y', strtotime($article['publication_date'])); ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-1">
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#viewArticleModal"
                            onclick="viewArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)">
                            <i class="bi bi-eye"></i>
                        </button>
                        
                        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#editArticleModal"
                            onclick="editArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <?php if ($article['article_status'] == 'pending'): ?>
                        <button class="btn btn-outline-info btn-sm"
                            onclick="showApprovalModal(<?php echo $article['article_id']; ?>)">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#rejectArticleModal"
                            onclick="rejectArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')">
                            <i class="bi bi-x"></i>
                        </button>
                        <?php endif; ?>

                        <button class="btn btn-outline-danger btn-sm"
                            onclick="deleteArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Desktop Table View -->
        <div class="d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash me-1"></i>ID</th>
                            <th><i class="bi bi-type me-1"></i>Judul</th>
                            <th><i class="bi bi-person me-1"></i>Penulis</th>
                            <th><i class="bi bi-folder me-1"></i>Kategori</th>
                            <th><i class="bi bi-flag me-1"></i>Status</th>
                            <th><i class="bi bi-star me-1"></i>Tipe</th>
                            <th><i class="bi bi-calendar me-1"></i>Tanggal</th>
                            <th class="text-center"><i class="bi bi-gear me-1"></i>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="articleTableBody">
                        <?php if (empty($all_articles)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fa-3x mb-3 d-block"></i>
                                <h5>Belum Ada Artikel</h5>
                                <p>Klik tombol "Tambah Artikel" untuk membuat artikel pertama</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($all_articles as $article): ?>
                        <tr data-status="<?php echo $article['article_status']; ?>">
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($article['article_id']); ?></span>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($article['title']); ?>">
                                    <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-circle me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td><?php echo getStatusBadge($article['article_status']); ?></td>
                            <td><?php echo getPostStatusBadge($article['post_status']); ?></td>
                            <td>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo formatDate($article['publication_date']); ?>
                                </small>
                            </td>
                            <td>
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewArticleModal"
                                        onclick="viewArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editArticleModal"
                                        onclick="editArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)" title="Edit Artikel">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <?php if ($article['article_status'] == 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-info"
                                        onclick="showApprovalModal(<?php echo $article['article_id']; ?>)" title="Approve">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#rejectArticleModal"
                                        onclick="rejectArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')" title="Reject">
                                        <i class="bi bi-x"></i>
                                    </button>
                                    <?php endif; ?>

                                    <button class="btn btn-sm btn-outline-danger"
                                        onclick="deleteArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Article Modal -->
<div class="modal fade" id="viewArticleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>Preview Artikel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewArticleContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-2"></i>Tutup
                </button>
                <button type="button" class="btn btn-primary" onclick="editFromView()">
                    <i class="bi bi-pencil me-2"></i>Edit Artikel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Article Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle me-2"></i>Approve Artikel
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Pilih Tipe Publikasi untuk Artikel</strong>
                </div>

                <div class="premium-option" data-value="Free" onclick="selectPostType('Free', this)">
                    <div class="d-flex align-items-center">
                        <div class="premium-icon">
                            <i class="bi bi-globe text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><strong>Free</strong></h6>
                            <small class="text-muted">Artikel dapat diakses oleh semua pengunjung</small>
                        </div>
                        <div>
                            <i class="bi bi-check-circle text-success" id="check-free" style="font-size: 1.5rem; display: none;"></i>
                        </div>
                    </div>
                </div>

                <div class="premium-option" data-value="Premium" onclick="selectPostType('Premium', this)">
                    <div class="d-flex align-items-center">
                        <div class="premium-icon">
                            <i class="fas fa-crown" style="color: #ffd700;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><strong>Premium</strong></h6>
                            <small class="text-muted">Hanya untuk pengguna premium/terdaftar</small>
                        </div>
                        <div>
                            <i class="bi bi-check-circle text-success" id="check-premium" style="font-size: 1.5rem; display: none;"></i>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="selectedPostStatus" value="Free">
                <input type="hidden" id="approveArticleId" value="">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-2"></i>Batal
                </button>
                <button type="button" class="btn btn-success" onclick="confirmApproval()">
                    <i class="bi bi-check me-2"></i>Approve & Publish
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Article Modal -->
<div class="modal fade" id="rejectArticleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle me-2"></i>Reject Artikel
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Konfirmasi Penolakan</strong>
                </div>
                <p>Reject artikel <strong id="reject_article_title"></strong>?</p>

                <div class="mb-3">
                    <label for="rejection_reason" class="form-label">
                        <i class="bi bi-chat me-2"></i>Alasan Penolakan <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control" id="rejection_reason" rows="4" required
                        placeholder="Jelaskan secara detail alasan penolakan artikel ini..."></textarea>
                    <div class="form-text">Alasan yang jelas akan membantu penulis untuk memperbaiki artikelnya.</div>
                </div>
                
                <input type="hidden" id="reject_article_id">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-2"></i>Batal
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmReject()">
                    <i class="bi bi-ban me-2"></i>Reject Article
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Article Modal -->
<div class="modal fade" id="addArticleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Artikel Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="addArticleForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="featured_image_id" id="add_featured_image_id">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-type me-2"></i>Judul Artikel <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="title" id="add_title" required maxlength="200">
                                <div class="char-counter" id="add_title_counter">0/200</div>
                            </div>

                            <!-- Slug Display -->
                            <div class="slug-container" id="add_slug_container" style="display: none;">
                                <div class="slug-label">URL Slug (Auto-generated)</div>
                                <div class="slug-display" id="add_slug_display"></div>
                            </div>

                            <!-- Ringkasan -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-file-text me-2"></i>Ringkasan Berita <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" name="summary" id="add_summary" rows="3" required maxlength="300"
                                    placeholder="Tulis ringkasan singkat artikel (maksimal 300 karakter)"></textarea>
                                <div class="char-counter" id="add_summary_counter">0/300</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-edit me-2"></i>Konten Artikel <span class="text-danger">*</span>
                                </label>
                                <textarea id="add_content" name="content" required></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Kategori *</label>
                                <select class="form-select" name="category" id="add_category" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status Artikel</label>
                                <select class="form-select" name="action" id="add_status">
                                    <option value="publish">Submit untuk Review</option>
                                    <option value="draft">Simpan sebagai Draft</option>
                                </select>
                            </div>

                            <!-- Tag Management -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-tags me-2"></i>Tag Artikel <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="add_tag_input" 
                                    placeholder="Ketik tag dan tekan Enter">
                                <small class="text-muted">Tekan Enter atau koma (,) untuk menambahkan tag</small>
                                <div class="tag-display" id="add_tag_display">
                                    <span class="text-muted">Tag akan muncul di sini</span>
                                </div>
                                <input type="hidden" name="tags" id="add_tags_hidden">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-image me-2"></i>Gambar Artikel <span class="text-danger">*</span>
                                </label>
                                <small class="text-muted d-block mb-2">
                                    Gambar wajib diupload untuk publish artikel
                                </small>
                                
                                <!-- File Input (Hidden) -->
                                <input type="file" 
                                    id="add_image_file" 
                                    accept="image/jpeg,image/png,image/webp,image/gif" 
                                    style="display: none;">
                                
                                <!-- Drop Zone -->
                                <div class="file-upload-area" id="addDropZone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h6>Klik atau Drag & Drop Gambar</h6>
                                    <p class="small mb-1">Format: JPG, PNG, GIF, WebP</p>
                                    <p class="small text-muted">Maksimal 5MB | Otomatis dioptimasi</p>
                                </div>

                                <!-- Uploaded Info -->
                                <div class="uploaded-info" id="add_uploaded_info">
                                    <div class="d-flex align-items-center">
                                        <img id="add_uploaded_thumbnail" 
                                            width="80" 
                                            height="80" 
                                            class="rounded me-3" 
                                            alt="Thumbnail"
                                            style="object-fit: cover;">
                                        <div class="flex-grow-1">
                                            <h6 id="add_uploaded_name" class="mb-1 text-white"></h6>
                                            <small id="add_uploaded_details" class="text-muted"></small>
                                            <div class="mt-2">
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>Upload Berhasil
                                                </span>
                                            </div>
                                        </div>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="removeAddImage()">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-2"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Artikel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Article Modal -->
<div class="modal fade" id="editArticleModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Edit Artikel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="editArticleForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="article_id" id="edit_article_id">
                <input type="hidden" name="featured_image_id" id="edit_featured_image_id">
                <input type="hidden" name="old_image_id" id="edit_old_image_id">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Judul *</label>
                                <input type="text" class="form-control" name="title" id="edit_title" required maxlength="200">
                                <div class="char-counter" id="edit_title_counter">0/200</div>
                            </div>

                            <!-- Slug Display (Read-only for edit) -->
                            <div class="slug-container" id="edit_slug_container" style="display: none;">
                                <div class="slug-label">URL Slug (Existing)</div>
                                <div class="slug-display" id="edit_slug_display"></div>
                            </div>

                            <!-- Ringkasan -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-file-text me-2"></i>Ringkasan Berita <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" name="summary" id="edit_summary" rows="3" required maxlength="300"
                                    placeholder="Tulis ringkasan singkat artikel (maksimal 300 karakter)"></textarea>
                                <div class="char-counter" id="edit_summary_counter">0/300</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Konten *</label>
                                <textarea id="edit_content" name="content" required></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Kategori *</label>
                                <select class="form-select" name="category" id="edit_category" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="action" id="edit_status">
                                    <option value="publish">Submit untuk Review</option>
                                    <option value="draft">Simpan sebagai Draft</option>
                                </select>
                            </div>

                            <!-- Tag Management -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-tags me-2"></i>Tag Artikel <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="edit_tag_input" 
                                    placeholder="Ketik tag dan tekan Enter">
                                <small class="text-muted">Tekan Enter atau koma (,) untuk menambahkan tag</small>
                                <div class="tag-display" id="edit_tag_display">
                                    <span class="text-muted">Tag akan muncul di sini</span>
                                </div>
                                <input type="hidden" name="tags" id="edit_tags_hidden">
                            </div>
                            <!-- Current Image Display -->
                            <div id="edit_current_image" class="mb-3"></div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-image me-2"></i>Gambar Baru (Opsional)
                                </label>
                                <small class="text-muted d-block mb-2">
                                    Upload file baru untuk mengganti gambar saat ini
                                </small>
                                
                                <!-- File Input (Hidden) -->
                                <input type="file" 
                                    id="edit_image_file" 
                                    accept="image/jpeg,image/png,image/webp,image/gif" 
                                    style="display: none;">
                                
                                <!-- Drop Zone -->
                                <div class="file-upload-area" id="editDropZone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h6>Klik atau Drag & Drop Gambar Baru</h6>
                                    <p class="small mb-1">Format: JPG, PNG, GIF, WebP</p>
                                    <p class="small text-muted">Maksimal 5MB | Otomatis dioptimasi</p>
                                </div>

                                <!-- Uploaded Info -->
                                <div class="uploaded-info" id="edit_uploaded_info">
                                    <div class="d-flex align-items-center">
                                        <img id="edit_uploaded_thumbnail" 
                                            width="80" 
                                            height="80" 
                                            class="rounded me-3" 
                                            alt="Thumbnail"
                                            style="object-fit: cover;">
                                        <div class="flex-grow-1">
                                            <h6 id="edit_uploaded_name" class="mb-1 text-white"></h6>
                                            <small id="edit_uploaded_details" class="text-muted"></small>
                                            <div class="mt-2">
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>Upload Berhasil
                                                </span>
                                            </div>
                                        </div>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="removeEditImage()">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update Artikel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Article Modal -->
<div class="modal fade" id="deleteArticleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deleteArticleForm">
                <input type="hidden" id="delete_article_id" value="">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-trash me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Peringatan!</strong> Tindakan ini tidak dapat dibatalkan.
                    </div>

                    <div class="text-center mb-3">
                        <i class="bi bi-trash fa-4x text-danger mb-3"></i>
                        <h6>Apakah Anda yakin ingin menghapus artikel ini?</h6>
                    </div>

                    <div class="bg-light p-3 rounded">
                        <strong class="text-dark">Artikel: <span id="delete_article_title"></span></strong>
                    </div>
                    
                    <div class="mt-3">
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Gambar artikel akan ikut dihapus dari server
                        </p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-2"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Ya, Hapus Artikel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Bulk Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>PERINGATAN!</strong> Tindakan ini tidak dapat dibatalkan.
                </div>
                
                <div class="text-center mb-3">
                    <i class="bi bi-trash fa-4x text-danger mb-3"></i>
                    <h6 class="text-white">Apakah Anda yakin ingin menghapus semua artikel <strong id="bulk_delete_status_text"></strong> yang lebih dari 7 hari?</h6>
                </div>
                
                <div class="bg-white p-3 rounded border">
                    <h6 class="mb-2 text-dark">Yang akan dihapus:</h6>
                    <ul class="mb-0 text-dark">
                        <li class="text-dark">Artikel dengan status <strong class="text-dark" id="bulk_delete_status_text2"></strong></li>
                        <li class="text-dark">Umur artikel lebih dari <strong class="text-dark">7 hari</strong></li>
                        <li class="text-dark">File gambar terkait</li>
                        <li class="text-dark">Komentar, tags, dan data terkait</li>
                    </ul>
                </div>
                
                <input type="hidden" id="bulk_delete_status" value="">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-2"></i>Batal
                </button>
                <button type="button" class="btn btn-danger" onclick="executeBulkDelete()">
                    <i class="bi bi-trash me-2"></i>Ya, Hapus Semua
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CRITICAL: Load Scripts in EXACT ORDER -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.js"></script>

<script>
'use strict';

// ===== NAMESPACE =====
window.ArticlesPage = window.ArticlesPage || {};

// Variables
ArticlesPage.currentArticleForView = null;
ArticlesPage.addUploadedImageId = null;
ArticlesPage.editUploadedImageId = null;
ArticlesPage.summernoteInitialized = false;
ArticlesPage.addTags = [];
ArticlesPage.editTags = [];

// ===== GENERATE SLUG =====
function generateSlug(text) {
    return text
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .substring(0, 100);
}

// ===== SUMMERNOTE INITIALIZATION =====
$(document).ready(function() {
    console.log('=== INITIALIZING ARTICLES PAGE ===');
    
    if (typeof $.fn.summernote === 'undefined') {
        console.error('✗ Summernote not loaded!');
        alert('Error: Summernote library not loaded');
        return;
    }
    
    console.log('✓ Summernote library loaded');
    
    const summernoteConfig = {
        height: 400,
        minHeight: 300,
        maxHeight: 600,
        placeholder: 'Tulis konten artikel lengkap di sini...',
        focus: false,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
        fontNamesIgnoreCheck: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
        fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '36'],
        styleTags: ['p', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
        dialogsInBody: true,
        callbacks: {
            onInit: function() {
                console.log('✓ Summernote initialized successfully');
                ArticlesPage.summernoteInitialized = true;
                
                setTimeout(function() {
                    $('.note-editor .dropdown-toggle').off('click').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const $dropdown = $(this).next('.dropdown-menu');
                        $('.note-editor .dropdown-menu').not($dropdown).removeClass('show');
                        $dropdown.toggleClass('show');
                        
                        return false;
                    });
                    
                    $(document).on('click', function(e) {
                        if (!$(e.target).closest('.note-editor .dropdown').length) {
                            $('.note-editor .dropdown-menu').removeClass('show');
                        }
                    });
                    
                    console.log('✓ Dropdown menus fixed');
                }, 500);
            }
        }
    };
    
    if ($('#add_content').data('summernote')) {
        $('#add_content').summernote('destroy');
    }
    if ($('#edit_content').data('summernote')) {
        $('#edit_content').summernote('destroy');
    }
    
    $('#add_content').summernote(summernoteConfig);
    $('#edit_content').summernote(summernoteConfig);
    
    console.log('✓ ALL SUMMERNOTE EDITORS INITIALIZED');
    
    // Initialize features
    ArticlesPage.initCharCounters();
    ArticlesPage.initSlugGeneration();
    ArticlesPage.initTagManagement();
    ArticlesPage.initDragDropAdd();
    ArticlesPage.initDragDropEdit();
    ArticlesPage.initializeStatusFilter();
    ArticlesPage.initModalResetHandlers();
    
    // Form submissions
    $('#addArticleForm').on('submit', function(e) {
        e.preventDefault();
        ArticlesPage.submitArticle('add');
    });
    
    $('#editArticleForm').on('submit', function(e) {
        e.preventDefault();
        ArticlesPage.submitArticle('edit');
    });
    
    console.log('✓ All features initialized');
});

// ===== CHARACTER COUNTERS =====
ArticlesPage.initCharCounters = function() {
    // Add Title Counter
    $('#add_title').on('input', function() {
        const length = $(this).val().length;
        const counter = $('#add_title_counter');
        counter.text(length + '/200');
        
        if (length > 180) {
            counter.removeClass('warning').addClass('danger');
        } else if (length > 150) {
            counter.removeClass('danger').addClass('warning');
        } else {
            counter.removeClass('warning danger');
        }
    });
    
    // Add Summary Counter
    $('#add_summary').on('input', function() {
        const length = $(this).val().length;
        const counter = $('#add_summary_counter');
        counter.text(length + '/300');
        
        if (length > 270) {
            counter.removeClass('warning').addClass('danger');
        } else if (length > 240) {
            counter.removeClass('danger').addClass('warning');
        } else {
            counter.removeClass('warning danger');
        }
    });
    
    // Edit Title Counter
    $('#edit_title').on('input', function() {
        const length = $(this).val().length;
        const counter = $('#edit_title_counter');
        counter.text(length + '/200');
        
        if (length > 180) {
            counter.removeClass('warning').addClass('danger');
        } else if (length > 150) {
            counter.removeClass('danger').addClass('warning');
        } else {
            counter.removeClass('warning danger');
        }
    });
    
    // Edit Summary Counter
    $('#edit_summary').on('input', function() {
        const length = $(this).val().length;
        const counter = $('#edit_summary_counter');
        counter.text(length + '/300');
        
        if (length > 270) {
            counter.removeClass('warning').addClass('danger');
        } else if (length > 240) {
            counter.removeClass('danger').addClass('warning');
        } else {
            counter.removeClass('warning danger');
        }
    });
    
    console.log('✓ Character counters initialized');
};

// ===== SLUG GENERATION =====
ArticlesPage.initSlugGeneration = function() {
    $('#add_title').on('input', function() {
        const title = $(this).val().trim();
        if (title.length > 0) {
            const slug = generateSlug(title);
            $('#add_slug_display').text(slug);
            $('#add_slug_container').show();
            
            console.log('Generated slug:', slug);
        } else {
            $('#add_slug_container').hide();
        }
    });
    
    console.log('✓ Slug generation initialized');
};

// ===== TAG MANAGEMENT =====
ArticlesPage.initTagManagement = function() {
    // Add Modal Tags
    $('#add_tag_input').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            ArticlesPage.addTagToList('add');
        }
    });
    
    $('#add_tag_input').on('blur', function() {
        if ($(this).val().trim()) {
            ArticlesPage.addTagToList('add');
        }
    });
    
    // Edit Modal Tags
    $('#edit_tag_input').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            ArticlesPage.addTagToList('edit');
        }
    });
    
    $('#edit_tag_input').on('blur', function() {
        if ($(this).val().trim()) {
            ArticlesPage.addTagToList('edit');
        }
    });
    
    console.log('✓ Tag management initialized');
};

ArticlesPage.addTagToList = function(mode) {
    const inputId = mode === 'add' ? '#add_tag_input' : '#edit_tag_input';
    const tagValue = $(inputId).val().trim();
    
    if (!tagValue || tagValue.length === 0) return;
    
    const normalizedTag = tagValue.toLowerCase();
    const tags = mode === 'add' ? ArticlesPage.addTags : ArticlesPage.editTags;
    
    if (tags.includes(normalizedTag)) {
        alert('Tag sudah ada');
        $(inputId).val('');
        return;
    }
    
    if (tags.length >= 10) {
        alert('Maksimal 10 tag');
        return;
    }
    
    if (normalizedTag.length > 50) {
        alert('Tag maksimal 50 karakter');
        return;
    }
    
    tags.push(normalizedTag);
    
    if (mode === 'add') {
        ArticlesPage.addTags = tags;
    } else {
        ArticlesPage.editTags = tags;
    }
    
    ArticlesPage.updateTagDisplay(mode);
    $(inputId).val('');
    
    console.log(`Tag added to ${mode}:`, normalizedTag);
    console.log(`Current tags (${mode}):`, tags);
};

ArticlesPage.updateTagDisplay = function(mode) {
    const displayId = mode === 'add' ? '#add_tag_display' : '#edit_tag_display';
    const hiddenId = mode === 'add' ? '#add_tags_hidden' : '#edit_tags_hidden';
    const tags = mode === 'add' ? ArticlesPage.addTags : ArticlesPage.editTags;
    
    if (tags.length === 0) {
        $(displayId).html('<span class="text-muted">Tag akan muncul di sini</span>');
    } else {
        const tagHtml = tags.map((tag, index) => `
            <span class="tag-item">
                ${tag}
                <button type="button" class="tag-remove" onclick="ArticlesPage.removeTag('${mode}', ${index})">×</button>
            </span>
        `).join('');
        $(displayId).html(tagHtml);
    }
    
    $(hiddenId).val(tags.join(','));
};

ArticlesPage.removeTag = function(mode, index) {
    const tags = mode === 'add' ? ArticlesPage.addTags : ArticlesPage.editTags;
    
    if (index >= 0 && index < tags.length) {
        tags.splice(index, 1);
        
        if (mode === 'add') {
            ArticlesPage.addTags = tags;
        } else {
            ArticlesPage.editTags = tags;
        }
        
        ArticlesPage.updateTagDisplay(mode);
        console.log(`Tag removed from ${mode}. Remaining:`, tags);
    }
};

// ===== DRAG & DROP + CLICK FOR ADD MODAL =====
ArticlesPage.initDragDropAdd = function() {
    const dropZone = document.getElementById('addDropZone');
    const fileInput = document.getElementById('add_image_file');
    
    if (!dropZone || !fileInput) {
        console.error('❌ Add dropzone or file input not found');
        return;
    }
    
    console.log('🔧 Initializing Add modal upload...');
    
    // ===== CLICK TO SELECT FILE =====
    dropZone.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('📁 Drop zone clicked - opening file dialog');
        fileInput.click();
    }, false);
    
    // ===== FILE INPUT CHANGE =====
    fileInput.addEventListener('change', function(e) {
        console.log('📄 File input changed');
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            console.log('📎 File selected:', file.name, file.size, 'bytes');
            ArticlesPage.handleAddFile(file);
        }
    });
    
    // ===== DRAG & DROP EVENTS =====
    let dragCounter = 0;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });
    
    dropZone.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter++;
        dropZone.classList.add('dragover');
        console.log('🎯 Drag enter');
    }, false);
    
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'copy';
        dropZone.classList.add('dragover');
    }, false);
    
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter--;
        
        if (dragCounter === 0) {
            dropZone.classList.remove('dragover');
            console.log('🚫 Drag leave');
        }
    }, false);
    
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        dragCounter = 0;
        dropZone.classList.remove('dragover');
        
        const dt = e.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            const file = dt.files[0];
            console.log('📦 File dropped:', file.name, file.size, 'bytes');
            ArticlesPage.handleAddFile(file);
        }
    }, false);
    
    console.log('✅ Add modal drag & drop + click initialized');
};

// ===== DRAG & DROP + CLICK FOR EDIT MODAL =====
ArticlesPage.initDragDropEdit = function() {
    const dropZone = document.getElementById('editDropZone');
    const fileInput = document.getElementById('edit_image_file');
    
    if (!dropZone || !fileInput) {
        console.error('❌ Edit dropzone or file input not found');
        return;
    }
    
    console.log('🔧 Initializing Edit modal upload...');
    
    // ===== CLICK TO SELECT FILE =====
    dropZone.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('📁 Edit drop zone clicked - opening file dialog');
        fileInput.click();
    }, false);
    
    // ===== FILE INPUT CHANGE =====
    fileInput.addEventListener('change', function(e) {
        console.log('📄 Edit file input changed');
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            console.log('📎 File selected:', file.name, file.size, 'bytes');
            ArticlesPage.handleEditFile(file);
        }
    });
    
    // ===== DRAG & DROP EVENTS =====
    let dragCounter = 0;
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });
    
    dropZone.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter++;
        dropZone.classList.add('dragover');
        console.log('🎯 Edit drag enter');
    }, false);
    
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'copy';
        dropZone.classList.add('dragover');
    }, false);
    
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter--;
        
        if (dragCounter === 0) {
            dropZone.classList.remove('dragover');
            console.log('🚫 Edit drag leave');
        }
    }, false);
    
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        dragCounter = 0;
        dropZone.classList.remove('dragover');
        
        const dt = e.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            const file = dt.files[0];
            console.log('📦 File dropped on edit:', file.name, file.size, 'bytes');
            ArticlesPage.handleEditFile(file);
        }
    }, false);
    
    console.log('✅ Edit modal drag & drop + click initialized');
};

// ===== HANDLE FILE FOR ADD MODAL =====
ArticlesPage.handleAddFile = function(file) {
    console.log('=== HANDLE ADD FILE START ===');
    console.log('File name:', file.name);
    console.log('File size:', file.size, 'bytes');
    console.log('File type:', file.type);
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        alert('❌ Format file tidak didukung!\nHanya JPG, PNG, GIF, dan WebP yang diizinkan.');
        return;
    }
    
    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        alert('❌ Ukuran file terlalu besar!\nMaksimal 5MB.');
        return;
    }
    
    console.log('✅ File validation passed');
    ArticlesPage.uploadAddFile(file);
};

// ===== HANDLE FILE FOR EDIT MODAL =====
ArticlesPage.handleEditFile = function(file) {
    console.log('=== HANDLE EDIT FILE START ===');
    console.log('File name:', file.name);
    console.log('File size:', file.size, 'bytes');
    console.log('File type:', file.type);
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        alert('❌ Format file tidak didukung!\nHanya JPG, PNG, GIF, dan WebP yang diizinkan.');
        return;
    }
    
    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        alert('❌ Ukuran file terlalu besar!\nMaksimal 5MB.');
        return;
    }
    
    console.log('✅ File validation passed');
    ArticlesPage.uploadEditFile(file);
};

// ===== UPLOAD FILE FOR ADD MODAL =====
ArticlesPage.uploadAddFile = function(file) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('csrf_token', '<?php echo $csrf_token; ?>');
    formData.append('action', document.getElementById('add_status').value === 'draft' ? 'draft' : 'pending');
    
    console.log('📤 Uploading file for Add modal...');
    
    // Show loading state
    const dropZone = document.getElementById('addDropZone');
    const originalHTML = dropZone.innerHTML;
    dropZone.innerHTML = `
        <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h6 class="text-white">Mengupload...</h6>
        <p class="small text-muted">Mohon tunggu, gambar sedang dioptimasi</p>
    `;
    
    fetch('/project/ajax/upload_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Upload response:', data);
        
        // Restore drop zone
        dropZone.innerHTML = originalHTML;
        
        if (data.success) {
            console.log('✅ Upload success:', data.data);
            
            // Store image ID
            ArticlesPage.addUploadedImageId = data.data.image_id;
            document.getElementById('add_featured_image_id').value = data.data.image_id;
            
            // Update thumbnail and info
            document.getElementById('add_uploaded_thumbnail').src = data.data.url;
            document.getElementById('add_uploaded_name').textContent = data.data.filename;
            document.getElementById('add_uploaded_details').innerHTML = `
                <i class="bi bi-file-image me-1"></i>${data.data.size} | 
                <i class="bi bi-aspect-ratio me-1"></i>${data.data.dimensions}
            `;
            
            // Show uploaded info with animation
            const uploadedInfo = document.getElementById('add_uploaded_info');
            uploadedInfo.style.display = 'block';
            uploadedInfo.classList.add('show');
            
            // Hide drop zone
            dropZone.style.display = 'none';
            
            // Show success alert
            showAlert('success', '✅ Gambar berhasil diupload dan dioptimasi!', 3000);
            
        } else {
            console.error('❌ Upload failed:', data.message);
            showAlert('danger', '❌ Upload gagal: ' + data.message);
        }
    })
    .catch(error => {
        console.error('❌ Network error:', error);
        
        // Restore drop zone
        dropZone.innerHTML = originalHTML;
        
        showAlert('danger', '❌ Terjadi kesalahan saat upload: ' + error.message);
    });
};

// ===== UPLOAD FILE FOR EDIT MODAL =====
ArticlesPage.uploadEditFile = function(file) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('csrf_token', '<?php echo $csrf_token; ?>');
    formData.append('action', document.getElementById('edit_status').value === 'draft' ? 'draft' : 'pending');
    
    console.log('📤 Uploading file for Edit modal...');
    
    // Show loading state
    const dropZone = document.getElementById('editDropZone');
    const originalHTML = dropZone.innerHTML;
    dropZone.innerHTML = `
        <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h6 class="text-white">Mengupload...</h6>
        <p class="small text-muted">Mohon tunggu, gambar sedang dioptimasi</p>
    `;
    
    fetch('/project/ajax/upload_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Upload response:', data);
        
        // Restore drop zone
        dropZone.innerHTML = originalHTML;
        
        if (data.success) {
            console.log('✅ Upload success:', data.data);
            
            // Store image ID
            ArticlesPage.editUploadedImageId = data.data.image_id;
            document.getElementById('edit_featured_image_id').value = data.data.image_id;
            
            // Update thumbnail and info
            document.getElementById('edit_uploaded_thumbnail').src = data.data.url;
            document.getElementById('edit_uploaded_name').textContent = data.data.filename;
            document.getElementById('edit_uploaded_details').innerHTML = `
                <i class="bi bi-file-image me-1"></i>${data.data.size} | 
                <i class="bi bi-aspect-ratio me-1"></i>${data.data.dimensions}
            `;
            
            // Show uploaded info with animation
            const uploadedInfo = document.getElementById('edit_uploaded_info');
            uploadedInfo.style.display = 'block';
            uploadedInfo.classList.add('show');
            
            // Hide drop zone
            dropZone.style.display = 'none';
            
            // Show success alert
            showAlert('success', '✅ Gambar berhasil diupload dan dioptimasi!', 3000);
            
        } else {
            console.error('❌ Upload failed:', data.message);
            showAlert('danger', '❌ Upload gagal: ' + data.message);
        }
    })
    .catch(error => {
        console.error('❌ Network error:', error);
        
        // Restore drop zone
        dropZone.innerHTML = originalHTML;
        
        showAlert('danger', '❌ Terjadi kesalahan saat upload: ' + error.message);
    });
};

// ===== REMOVE IMAGE FUNCTIONS =====
function removeAddImage() {
    if (confirm('❓ Hapus gambar yang diupload?')) {
        console.log('🗑️ Removing Add image...');
        
        // Clear data
        ArticlesPage.addUploadedImageId = null;
        document.getElementById('add_featured_image_id').value = '';
        document.getElementById('add_image_file').value = '';
        
        // Hide uploaded info
        const uploadedInfo = document.getElementById('add_uploaded_info');
        uploadedInfo.style.display = 'none';
        uploadedInfo.classList.remove('show');
        
        // Show drop zone again
        document.getElementById('addDropZone').style.display = 'flex';
        
        console.log('✅ Add image removed');
        showAlert('info', 'Gambar berhasil dihapus', 2000);
    }
}

function removeEditImage() {
    if (confirm('❓ Hapus gambar yang diupload?')) {
        console.log('🗑️ Removing Edit image...');
        
        // Clear data
        ArticlesPage.editUploadedImageId = null;
        document.getElementById('edit_featured_image_id').value = '';
        document.getElementById('edit_image_file').value = '';
        
        // Hide uploaded info
        const uploadedInfo = document.getElementById('edit_uploaded_info');
        uploadedInfo.style.display = 'none';
        uploadedInfo.classList.remove('show');
        
        // Show drop zone again
        document.getElementById('editDropZone').style.display = 'flex';
        
        console.log('✅ Edit image removed');
        showAlert('info', 'Gambar berhasil dihapus', 2000);
    }
}

// ===== SUBMIT ARTICLE =====
ArticlesPage.submitArticle = function(mode) {
    console.log('=== SUBMIT ARTICLE ===');
    console.log('Mode:', mode);
    
    const form = mode === 'add' ? document.getElementById('addArticleForm') : document.getElementById('editArticleForm');
    const formData = new FormData(form);
    
    // Get Summernote content
    const contentEditor = mode === 'add' ? '#add_content' : '#edit_content';
    const content = $(contentEditor).summernote('code');
    formData.set('content', content);
    
    // Log data untuk debugging
    const title = formData.get('title');
    const summary = formData.get('summary');
    const tags = formData.get('tags');
    const slug = mode === 'add' ? generateSlug(title) : null;
    
    console.log('=== FORM DATA ===');
    console.log('Title:', title);
    console.log('Summary:', summary);
    console.log('Content length:', content.length);
    console.log('Tags:', tags);
    if (slug) console.log('Generated Slug:', slug);
    console.log('===============');
    
    // Validate
    if (!title || !summary || !content || !formData.get('category')) {
        alert('Mohon lengkapi semua field yang wajib diisi');
        return;
    }
    
    if (!tags || tags.trim() === '') {
        alert('Minimal 1 tag harus diisi');
        return;
    }
    
    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
    
    console.log('Submitting to /project/ajax/submit_article.php...');
    
    fetch('/project/ajax/submit_article.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data);
        
        if (data.success) {
            alert('✓ ' + data.message);
            
            // Close modal & reload
            const modalId = mode === 'add' ? 'addArticleModal' : 'editArticleModal';
            const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (modal) modal.hide();
            
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            alert('✗ ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Submit error:', error);
        alert('✗ Terjadi kesalahan saat menyimpan artikel');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
};

// ===== VIEW ARTICLE =====
function viewArticle(article) {
    ArticlesPage.currentArticleForView = article;

    const statusBadges = {
        'published': '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Published</span>',
        'draft': '<span class="badge bg-warning text-dark"><i class="bi bi-pencil me-1"></i>Draft</span>',
        'pending': '<span class="badge bg-info"><i class="bi bi-clock me-1"></i>Pending Review</span>',
        'rejected': '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>'
    };

    const statusBadge = statusBadges[article.article_status] || article.article_status;
    const postStatusBadge = article.post_status === 'Premium' ? 
        '<span class="badge" style="background: linear-gradient(135deg, #ffd700, #ffed4e); color: #000;"><i class="fas fa-crown me-1"></i>Premium</span>' :
        '<span class="badge bg-secondary"><i class="bi bi-globe me-1"></i>Free</span>';

    let imageHtml = '';
    if (article.image_url) {
        imageHtml = `<div class="text-center mb-4">
                <img src="${article.image_url}" 
                     class="img-fluid rounded shadow" 
                     style="max-height: 400px; object-fit: cover;" 
                     alt="Article Image">
            </div>`;
    }

    let contentHtml = `
        <div class="article-meta">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="article-meta-item">
                        <i class="bi bi-hash text-muted me-2"></i>
                        <strong>ID:</strong>
                        <span class="badge bg-secondary ms-2">${article.article_id}</span>
                    </div>
                    <div class="article-meta-item">
                        <i class="bi bi-person text-muted me-2"></i>
                        <strong>Penulis:</strong>
                        <span class="ms-2">${article.author_name || 'Unknown'}</span>
                    </div>
                    <div class="article-meta-item">
                        <i class="bi bi-folder text-muted me-2"></i>
                        <strong>Kategori:</strong>
                        <span class="badge bg-info ms-2">${article.category_name || 'Uncategorized'}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="article-meta-item">
                        <i class="bi bi-flag text-muted me-2"></i>
                        <strong>Status:</strong>
                        <span class="ms-2">${statusBadge}</span>
                    </div>
                    <div class="article-meta-item">
                        <i class="bi bi-star text-muted me-2"></i>
                        <strong>Tipe:</strong>
                        <span class="ms-2">${postStatusBadge}</span>
                    </div>
                    <div class="article-meta-item">
                        <i class="bi bi-clock text-muted me-2"></i>
                        <strong>Tanggal:</strong>
                        <span class="ms-2">${new Date(article.publication_date).toLocaleDateString('id-ID')}</span>
                    </div>
                </div>
            </div>
        </div>
        
        ${article.article_slug ? `
        <div class="article-meta-item mb-3">
            <i class="bi bi-link-45deg text-muted me-2"></i>
            <strong>URL Slug:</strong>
            <code class="ms-2">${article.article_slug}</code>
        </div>
        ` : ''}
        
        <div class="article-preview">
            <h2 class="article-title mb-3">${article.title}</h2>
            
            ${article.meta_description ? `
            <div class="article-summary-box">
                <h6 class="mb-2"><i class="bi bi-file-text me-2"></i>Ringkasan Berita</h6>
                <p class="mb-0">${article.meta_description}</p>
            </div>
            ` : ''}
            
            ${imageHtml}
            
            <div class="article-content">
                ${article.content}
            </div>
            
            ${article.tags ? `
            <div class="article-tags-box">
                <h6 class="mb-2"><i class="bi bi-tags me-2"></i>Tag Artikel</h6>
                <div>
                    ${article.tags.split(', ').map(tag => `<span class="tag-item">${tag}</span>`).join('')}
                </div>
            </div>
            ` : ''}
        </div>
    `;

    document.getElementById('viewArticleContent').innerHTML = contentHtml;
}

// ===== EDIT ARTICLE =====
function editArticle(article) {
    console.log('=== EDIT ARTICLE ===');
    console.log('Article data:', article);
    
    document.getElementById('edit_article_id').value = article.article_id;
    document.getElementById('edit_title').value = article.title;
    document.getElementById('edit_summary').value = article.meta_description || '';
    document.getElementById('edit_category').value = article.category_id;
    document.getElementById('edit_old_image_id').value = article.image_id || '';
    
    // Update counters
    $('#edit_title').trigger('input');
    $('#edit_summary').trigger('input');
    
    // Show slug if exists
    if (article.article_slug) {
        $('#edit_slug_display').text(article.article_slug);
        $('#edit_slug_container').show();
    } else {
        $('#edit_slug_container').hide();
    }
    
    // Set Summernote content
    $('#edit_content').summernote('code', article.content);
    
    // Load tags
    ArticlesPage.editTags = article.tags ? article.tags.split(', ').map(t => t.toLowerCase().trim()) : [];
    ArticlesPage.updateTagDisplay('edit');
    
    console.log('Loaded tags:', ArticlesPage.editTags);

    // Display current image
    const currentImageDiv = document.getElementById('edit_current_image');
    if (article.image_url) {
        currentImageDiv.innerHTML = `
            <div class="current-image-display">
                <label class="form-label">
                    <i class="bi bi-image me-2"></i>Gambar Saat Ini
                </label>
                <div class="d-flex align-items-center p-3 bg-secondary rounded">
                    <img src="${article.image_url}" 
                         style="max-height: 100px; max-width: 150px; object-fit: cover;" 
                         class="rounded me-3 border border-secondary" 
                         alt="Current Image">
                    <div>
                        <h6 class="mb-1 text-white">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Gambar yang digunakan
                        </h6>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Upload file baru di bawah untuk mengganti gambar ini
                        </small>
                    </div>
                </div>
            </div>
        `;
    } else {
        currentImageDiv.innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Tidak ada gambar</strong><br>
                <small>Artikel ini belum memiliki gambar. Upload gambar di bawah.</small>
            </div>
        `;
    }
    
    // Reset upload section
    document.getElementById('edit_uploaded_info').style.display = 'none';
    document.getElementById('edit_uploaded_info').classList.remove('show');
    document.getElementById('editDropZone').style.display = 'flex';
    document.getElementById('edit_image_file').value = '';
    ArticlesPage.editUploadedImageId = null;
    
    console.log('✅ Edit article form populated');
}

// ===== EDIT FROM VIEW =====
function editFromView() {
    if (ArticlesPage.currentArticleForView) {
        bootstrap.Modal.getInstance(document.getElementById('viewArticleModal')).hide();
        setTimeout(() => {
            editArticle(ArticlesPage.currentArticleForView);
            new bootstrap.Modal(document.getElementById('editArticleModal')).show();
        }, 300);
    }
}

// ===== MODAL RESET HANDLERS =====
ArticlesPage.initModalResetHandlers = function() {
    // Add Modal Reset
    $('#addArticleModal').on('hidden.bs.modal', function () {
        console.log('🔄 Resetting Add modal...');
        
        // Reset form
        document.getElementById('addArticleForm').reset();
        
        // Clear Summernote
        $('#add_content').summernote('code', '');
        
        // Reset image upload
        ArticlesPage.addUploadedImageId = null;
        document.getElementById('add_featured_image_id').value = '';
        document.getElementById('add_image_file').value = '';
        document.getElementById('add_uploaded_info').style.display = 'none';
        document.getElementById('add_uploaded_info').classList.remove('show');
        document.getElementById('addDropZone').style.display = 'flex';
        
        // Reset tags
        ArticlesPage.addTags = [];
        ArticlesPage.updateTagDisplay('add');
        
        // Reset counters
        $('#add_title_counter').text('0/200');
        $('#add_summary_counter').text('0/300');
        $('#add_slug_container').hide();
        
        console.log('✅ Add modal reset complete');
    });

    // Edit Modal Reset
    $('#editArticleModal').on('hidden.bs.modal', function () {
        console.log('🔄 Resetting Edit modal...');
        
        // Reset form
        document.getElementById('editArticleForm').reset();
        
        // Clear Summernote
        $('#edit_content').summernote('code', '');
        
        // Reset image upload
        ArticlesPage.editUploadedImageId = null;
        document.getElementById('edit_featured_image_id').value = '';
        document.getElementById('edit_image_file').value = '';
        document.getElementById('edit_uploaded_info').style.display = 'none';
        document.getElementById('edit_uploaded_info').classList.remove('show');
        document.getElementById('editDropZone').style.display = 'flex';
        document.getElementById('edit_current_image').innerHTML = '';
        
        // Reset tags
        ArticlesPage.editTags = [];
        ArticlesPage.updateTagDisplay('edit');
        
        // Reset counters
        $('#edit_title_counter').text('0/200');
        $('#edit_summary_counter').text('0/300');
        $('#edit_slug_container').hide();
        
        console.log('✅ Edit modal reset complete');
    });
    
    console.log('✓ Modal reset handlers initialized');
};

// ===== APPROVAL FUNCTIONS =====
ArticlesPage.selectedArticleId = null;
ArticlesPage.selectedPostType = 'Free';

function showApprovalModal(articleId) {
    ArticlesPage.selectedArticleId = articleId;
    document.getElementById('approveArticleId').value = articleId;
    
    document.querySelectorAll('.premium-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.querySelectorAll('.bi-check-circle').forEach(icon => {
        icon.style.display = 'none';
    });
    
    selectPostType('Free', document.querySelector('[data-value="Free"]'));
    
    const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
    modal.show();
}

function selectPostType(type, element) {
    ArticlesPage.selectedPostType = type;
    document.getElementById('selectedPostStatus').value = type;
    
    document.querySelectorAll('.premium-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.querySelectorAll('.bi-check-circle').forEach(icon => {
        icon.style.display = 'none';
    });
    
    element.classList.add('selected');
    const checkIcon = type === 'Free' ? 'check-free' : 'check-premium';
    document.getElementById(checkIcon).style.display = 'block';
}

function confirmApproval() {
    if (!ArticlesPage.selectedArticleId) {
        alert('Error: Article ID not found');
        return;
    }
    
    const postType = document.getElementById('selectedPostStatus').value;
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('approvalModal'));
    modal.hide();
    
    fetch('/project/ajax/admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'approve',
            article_id: ArticlesPage.selectedArticleId,
            post_status: postType,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✓ Artikel berhasil diapprove sebagai ${postType}!`);
            location.reload();
        } else {
            alert('✗ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('✗ Network error occurred');
        console.error('Approval error:', error);
    });
}

// ===== REJECT FUNCTIONS =====
function rejectArticle(articleId, title) {
    document.getElementById('reject_article_id').value = articleId;
    document.getElementById('reject_article_title').textContent = title;
}

function confirmReject() {
    const articleId = document.getElementById('reject_article_id').value;
    const reason = document.getElementById('rejection_reason').value.trim();
    
    if (!reason) {
        alert('Alasan penolakan harus diisi');
        return;
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('rejectArticleModal'));
    modal.hide();
    
    fetch('/project/ajax/admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'reject',
            article_id: articleId,
            reason: reason,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Artikel berhasil ditolak!');
            location.reload();
        } else {
            alert('✗ Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('✗ Network error occurred');
        console.error('Reject error:', error);
    });
}

// ===== DELETE ARTICLE =====
function deleteArticle(articleId, title) {
    document.getElementById('delete_article_id').value = articleId;
    document.getElementById('delete_article_title').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteArticleModal')).show();
}

// Handle delete form submission
document.addEventListener('DOMContentLoaded', function() {
    const deleteForm = document.querySelector('#deleteArticleModal form');
    
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const articleId = document.getElementById('delete_article_id').value;
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteArticleModal'));
            if (modal) modal.hide();
            
            fetch('/project/ajax/admin_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    article_id: parseInt(articleId),
                    csrf_token: '<?php echo $csrf_token; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ ' + data.message);
                    location.reload();
                } else {
                    alert('✗ ' + data.message);
                }
            })
            .catch(error => {
                alert('✗ Network error');
                console.error(error);
            });
        });
    }
});

// ===== STATUS FILTER =====
ArticlesPage.initializeStatusFilter = function() {
    document.querySelectorAll('#statusFilter a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            document.querySelectorAll('#statusFilter a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            
            const tableRows = document.querySelectorAll('#articleTableBody tr[data-status]');
            tableRows.forEach(row => {
                if (filter === 'all' || row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            const mobileCards = document.querySelectorAll('#mobileArticlesList .card[data-status]');
            mobileCards.forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    console.log('✓ Status filter initialized');
};

// ===== BULK DELETE =====
function showBulkDeleteModal(status) {
    const statusLabels = {
        'pending': 'Pending',
        'draft': 'Draft',
        'rejected': 'Rejected'
    };
    
    const statusLabel = statusLabels[status] || status;
    
    document.getElementById('bulk_delete_status').value = status;
    document.getElementById('bulk_delete_status_text').textContent = statusLabel;
    document.getElementById('bulk_delete_status_text2').textContent = statusLabel;
    
    const modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
    modal.show();
}

function executeBulkDelete() {
    const status = document.getElementById('bulk_delete_status').value;
    
    if (!status) {
        alert('Status tidak valid');
        return;
    }
    
    console.log('=== BULK DELETE START ===');
    console.log('Status:', status);
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal'));
    if (modal) modal.hide();
    
    showAlert('info', 'Sedang memproses bulk delete...', 0);
    
    fetch('/project/ajax/bulk_delete_articles.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            status: status,
            days_old: 7,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        document.querySelectorAll('.alert').forEach(alert => {
            if (alert.textContent.includes('Sedang memproses')) {
                alert.remove();
            }
        });
        
        if (data.success) {
            console.log('✓ Bulk delete successful');
            showAlert('success', data.message, 5000);
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            console.error('✗ Bulk delete failed:', data.message);
            showAlert('danger', data.message || 'Gagal menghapus artikel');
        }
    })
    .catch(error => {
        console.error('✗ Network error:', error);
        
        document.querySelectorAll('.alert').forEach(alert => {
            if (alert.textContent.includes('Sedang memproses')) {
                alert.remove();
            }
        });
        
        showAlert('danger', 'Terjadi kesalahan jaringan: ' + error.message);
    });
}

// ===== SHOW ALERT FUNCTION =====
function showAlert(type, message, duration = 5000) {
    const alertId = 'alert-' + Date.now();
    const iconMap = {
        'success': 'bi-check-circle',
        'danger': 'bi-exclamation-circle',
        'warning': 'bi-exclamation-triangle',
        'info': 'bi-info-circle'
    };

    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" 
             role="alert" 
             id="${alertId}" 
             style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
            <i class="bi ${iconMap[type] || 'bi-info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', alertHtml);

    if (duration > 0) {
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, duration);
    }
}

// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert && document.contains(alert)) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, 5000);
    });
});

console.log('✅ Articles page fully loaded with all features');
</script>

<?php require_once 'footer.php'; ?>