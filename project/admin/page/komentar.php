<?php
// admin/page/komentar.php - Halaman Kelola Komentar (FIXED VERSION)
// File ini dipanggil oleh admin/index.php, jadi tidak perlu include header/footer/sidebar

// Pastikan user sudah login dan adalah admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit_comment':
                $comment_id = sanitize_input($_POST['comment_id']);
                $content = sanitize_input($_POST['content']);
                
                if (empty($content)) {
                    $error = "Konten komentar harus diisi!";
                } else {
                    // Update comment content
                    $update_query = "UPDATE comments SET content = '$content' WHERE comment_id = '$comment_id'";
                    
                    if (mysqli_query($koneksi, $update_query)) {
                        $success = "Komentar berhasil diupdate!";
                        
                        // Log activity
                        error_log("Admin {$_SESSION['username']} edited comment ID: $comment_id");
                    } else {
                        $error = "Gagal mengupdate komentar: " . mysqli_error($koneksi);
                    }
                }
                break;
                 
            case 'delete_comment':
                $comment_id = sanitize_input($_POST['comment_id']);
                
                // Get comment info for logging
                $info_query = "SELECT c.*, u.username FROM comments c 
                              JOIN users u ON c.user_id = u.id 
                              WHERE c.comment_id = '$comment_id'";
                $info_result = mysqli_query($koneksi, $info_query);
                $comment_info = mysqli_fetch_assoc($info_result);
                
                // Delete comment
                $delete_query = "DELETE FROM comments WHERE comment_id = '$comment_id'";
                
                if (mysqli_query($koneksi, $delete_query)) {
                    $success = "Komentar dari user '{$comment_info['username']}' berhasil dihapus!";
                    
                    // Log activity
                    error_log("Admin {$_SESSION['username']} deleted comment ID: $comment_id from user: {$comment_info['username']}");
                } else {
                    $error = "Gagal menghapus komentar: " . mysqli_error($koneksi);
                }
                break;
        }
    }
}

// Get search parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$article_filter = isset($_GET['article']) ? sanitize_input($_GET['article']) : '';
$user_filter = isset($_GET['user']) ? sanitize_input($_GET['user']) : '';

// Build search query
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "c.content LIKE '%$search%'";
}
if (!empty($article_filter)) {
    $where_conditions[] = "c.article_id = '$article_filter'";
}
if (!empty($user_filter)) {
    $where_conditions[] = "c.user_id = '$user_filter'";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get all comments with user and article info
$comments_query = "SELECT c.comment_id, c.content, c.created_at, c.article_id, c.user_id,
                          u.username, u.full_name, u.email,
                          a.title as article_title
                   FROM comments c
                   JOIN users u ON c.user_id = u.id
                   JOIN articles a ON c.article_id = a.article_id
                   $where_clause
                   ORDER BY c.created_at DESC";
$comments_result = mysqli_query($koneksi, $comments_query);

// Get comment count
$comment_count_query = "SELECT COUNT(*) as total FROM comments c
                       JOIN users u ON c.user_id = u.id
                       JOIN articles a ON c.article_id = a.article_id
                       $where_clause";
$comment_count_result = mysqli_query($koneksi, $comment_count_query);
$comment_count = mysqli_fetch_assoc($comment_count_result)['total'];

// Get total comments (for stats)
$total_comments_query = "SELECT COUNT(*) as total FROM comments";
$total_comments_result = mysqli_query($koneksi, $total_comments_query);
$total_comments = mysqli_fetch_assoc($total_comments_result)['total'];

// Get comments today
$today_comments_query = "SELECT COUNT(*) as total FROM comments WHERE DATE(created_at) = CURDATE()";
$today_comments_result = mysqli_query($koneksi, $today_comments_query);
$today_comments = mysqli_fetch_assoc($today_comments_result)['total'];

// Get comments this week
$week_comments_query = "SELECT COUNT(*) as total FROM comments WHERE YEARWEEK(created_at) = YEARWEEK(NOW())";
$week_comments_result = mysqli_query($koneksi, $week_comments_query);
$week_comments = mysqli_fetch_assoc($week_comments_result)['total'];

// Get articles for filter dropdown
$articles_query = "SELECT article_id, title FROM articles ORDER BY title ASC";
$articles_result = mysqli_query($koneksi, $articles_query);

// Get users for filter dropdown
$users_query = "SELECT id, username, full_name FROM users ORDER BY username ASC";
$users_result = mysqli_query($koneksi, $users_query);
?>

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-6 col-md-4 mb-3">
        <div class="card stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-center text-white p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Total Komentar</h5>
                    <i class="bi bi-chat-dots-fill" style="font-size: 1.5rem;"></i>
                </div>
                <p class="card-text display-6 mb-1"><?php echo number_format($total_comments); ?></p>
                <small class="text-white-50">Semua komentar</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 mb-3">
        <div class="card stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="card-body text-center text-white p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Hari Ini</h5>
                    <i class="bi bi-calendar-day-fill" style="font-size: 1.5rem;"></i>
                </div>
                <p class="card-text display-6 mb-1"><?php echo number_format($today_comments); ?></p>
                <small class="text-white-50">Komentar baru</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 mb-3">
        <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="card-body text-center text-white p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Minggu Ini</h5>
                    <i class="bi bi-calendar-week-fill" style="font-size: 1.5rem;"></i>
                </div>
                <p class="card-text display-6 mb-1"><?php echo number_format($week_comments); ?></p>
                <small class="text-white-50">Total mingguan</small>
            </div>
        </div>
    </div>
</div>

<!-- Main Card -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <div class="mb-2 mb-md-0">
                    <h4 class="card-title mb-0">
                        <i class="bi bi-chat-dots me-2"></i>Kelola Komentar
                    </h4>
                    <small class="text-muted">Menampilkan <?php echo number_format($comment_count); ?> dari <?php echo number_format($total_comments); ?> komentar</small>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="card mb-3" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <input type="hidden" name="page" value="comments">
                            <div class="col-12 col-md-4">
                                <label class="form-label">
                                    <i class="bi bi-search me-1"></i>Cari Komentar
                                </label>
                                <input type="text" class="form-control" name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Cari konten komentar...">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">
                                    <i class="bi bi-newspaper me-1"></i>Filter Artikel
                                </label>
                                <select class="form-select" name="article">
                                    <option value="">Semua Artikel</option>
                                    <?php 
                                    if ($articles_result && mysqli_num_rows($articles_result) > 0) {
                                        mysqli_data_seek($articles_result, 0);
                                        while ($article = mysqli_fetch_assoc($articles_result)): 
                                    ?>
                                    <option value="<?php echo $article['article_id']; ?>"
                                        <?php echo $article_filter == $article['article_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(substr($article['title'], 0, 50)); ?>
                                        <?php if (strlen($article['title']) > 50) echo '...'; ?>
                                    </option>
                                    <?php 
                                        endwhile; 
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">
                                    <i class="bi bi-person me-1"></i>Filter User
                                </label>
                                <select class="form-select" name="user">
                                    <option value="">Semua User</option>
                                    <?php 
                                    if ($users_result && mysqli_num_rows($users_result) > 0) {
                                        mysqli_data_seek($users_result, 0);
                                        while ($user = mysqli_fetch_assoc($users_result)): 
                                    ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if (!empty($user['full_name'])): ?>
                                        - <?php echo htmlspecialchars($user['full_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">
                                        <i class="fas fa-search me-1"></i>Cari
                                    </button>
                                    <a href="?page=comments" class="btn btn-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Scroll hint for mobile -->
                <div class="scroll-hint">
                    <i class="fas fa-arrows-alt-h me-2"></i>Geser ke kiri/kanan untuk melihat seluruh tabel
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="min-width: 150px;">User</th>
                                <th style="min-width: 200px;">Artikel</th>
                                <th style="min-width: 300px;">Komentar</th>
                                <th style="min-width: 120px;">Tanggal</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($comments_result && mysqli_num_rows($comments_result) > 0): ?>
                            <?php while ($comment = mysqli_fetch_assoc($comments_result)): ?>
                            <tr>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($comment['comment_id']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2"
                                             style="width: 35px; height: 35px; font-size: 0.9rem;">
                                            <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong class="d-block"><?php echo htmlspecialchars($comment['full_name'] ?: $comment['username']); ?></strong>
                                            <small class="text-muted">@<?php echo htmlspecialchars($comment['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="max-width: 250px;">
                                        <a href="../artikel.php?id=<?php echo $comment['article_id']; ?>" 
                                           target="_blank" 
                                           class="text-decoration-none"
                                           title="<?php echo htmlspecialchars($comment['article_title']); ?>">
                                            <?php 
                                            $title = $comment['article_title'];
                                            echo htmlspecialchars(strlen($title) > 50 ? substr($title, 0, 47) . '...' : $title); 
                                            ?>
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div style="max-width: 350px; word-wrap: break-word; line-height: 1.5;">
                                        <?php 
                                        $content = $comment['content'];
                                        if (strlen($content) > 150) {
                                            echo htmlspecialchars(substr($content, 0, 147)) . '...';
                                        } else {
                                            echo htmlspecialchars($content);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($comment['created_at'])); ?>
                                        <br>
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($comment['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" 
                                                class="btn-action btn-view" 
                                                title="Lihat Detail"
                                                onclick="viewComment(<?php echo htmlspecialchars(json_encode($comment)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn-action btn-edit" 
                                                title="Edit Komentar"
                                                onclick="editComment(<?php echo htmlspecialchars(json_encode($comment)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn-action btn-delete" 
                                                title="Hapus Komentar"
                                                onclick="deleteComment(<?php echo $comment['comment_id']; ?>, '<?php echo htmlspecialchars($comment['username']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    <p class="mb-0">
                                        <?php if (!empty($search) || !empty($article_filter) || !empty($user_filter)): ?>
                                        Tidak ada komentar yang sesuai dengan filter
                                        <?php else: ?>
                                        Belum ada komentar
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Comment -->
<div class="modal fade" id="viewCommentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>Detail Komentar
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewCommentContent">
                <!-- Content will be loaded by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Tutup
                </button>
                <button type="button" class="btn btn-primary" onclick="editFromView()">
                    <i class="fas fa-edit me-1"></i>Edit Komentar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Komentar -->
<div class="modal fade" id="editCommentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editCommentForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Komentar
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_comment">
                    <input type="hidden" name="comment_id" id="edit_comment_id">

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-person me-1"></i>User
                        </label>
                        <input type="text" class="form-control" id="edit_user_info" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-newspaper me-1"></i>Artikel
                        </label>
                        <input type="text" class="form-control" id="edit_article_title" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="edit_content" class="form-label">
                            <i class="bi bi-chat-text me-1"></i>Konten Komentar <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="edit_content" name="content" rows="6" required></textarea>
                        <div class="form-text">
                            <span id="charCount">0</span> karakter
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-calendar3 me-1"></i>Tanggal Dibuat
                        </label>
                        <input type="text" class="form-control" id="edit_created_at" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update Komentar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirm Delete -->
<div class="modal fade" id="deleteCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteCommentForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="comment_id" id="delete_comment_id">
                    
                    <div class="text-center mb-4">
                        <i class="fas fa-trash-alt fa-4x text-danger mb-3"></i>
                        <h5>Apakah Anda yakin ingin menghapus komentar ini?</h5>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>User: <span id="delete_user_name"></span></strong>
                    </div>
                    
                    <p class="text-center text-danger mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Tindakan ini tidak dapat dibatalkan!</strong>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Hapus Komentar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store current comment for view/edit
let currentCommentForView = null;

// View comment details
function viewComment(comment) {
    currentCommentForView = comment;
    
    const contentHtml = `
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">
                            <i class="bi bi-person me-1"></i>Informasi User
                        </h6>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3"
                                 style="width: 50px; height: 50px; font-size: 1.5rem;">
                                ${comment.username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <strong class="d-block">${comment.full_name || comment.username}</strong>
                                <small class="text-muted">@${comment.username}</small>
                                <br>
                                <small class="text-muted">${comment.email || 'No email'}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">
                            <i class="bi bi-newspaper me-1"></i>Artikel
                        </h6>
                        <p class="mb-2">
                            <a href="../artikel.php?id=${comment.article_id}" target="_blank" class="text-decoration-none">
                                ${comment.article_title}
                                <i class="bi bi-box-arrow-up-right ms-1"></i>
                            </a>
                        </p>
                        <small class="text-muted">
                            <i class="bi bi-calendar3 me-1"></i>
                            ${new Date(comment.created_at).toLocaleDateString('id-ID', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3" style="background: var(--bg-tertiary); border: 1px solid var(--border-color);">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted">
                    <i class="bi bi-chat-text me-1"></i>Konten Komentar
                </h6>
                <p class="mb-0" style="white-space: pre-wrap; line-height: 1.8;">${comment.content}</p>
            </div>
        </div>
    `;
    
    document.getElementById('viewCommentContent').innerHTML = contentHtml;
    
    const viewModal = new bootstrap.Modal(document.getElementById('viewCommentModal'));
    viewModal.show();
}

// Edit comment from view modal
function editFromView() {
    if (currentCommentForView) {
        bootstrap.Modal.getInstance(document.getElementById('viewCommentModal')).hide();
        setTimeout(() => {
            editComment(currentCommentForView);
        }, 300);
    }
}

// Edit comment
function editComment(comment) {
    document.getElementById('edit_comment_id').value = comment.comment_id;
    document.getElementById('edit_content').value = comment.content;
    document.getElementById('edit_user_info').value = (comment.full_name || comment.username) + ' (@' + comment.username + ')';
    document.getElementById('edit_article_title').value = comment.article_title;
    document.getElementById('edit_created_at').value = new Date(comment.created_at).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Update character count
    updateCharCount();
    
    var editModal = new bootstrap.Modal(document.getElementById('editCommentModal'));
    editModal.show();
}

// Delete comment
function deleteComment(commentId, username) {
    document.getElementById('delete_comment_id').value = commentId;
    document.getElementById('delete_user_name').textContent = username;

    var deleteModal = new bootstrap.Modal(document.getElementById('deleteCommentModal'));
    deleteModal.show();
}

// Update character count
function updateCharCount() {
    const textarea = document.getElementById('edit_content');
    const charCount = document.getElementById('charCount');
    
    if (textarea && charCount) {
        charCount.textContent = textarea.value.length;
    }
}

// Auto-resize textarea
const editContentTextarea = document.getElementById('edit_content');
if (editContentTextarea) {
    editContentTextarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        updateCharCount();
    });
}

// Form validation
document.getElementById('editCommentForm').addEventListener('submit', function(e) {
    const content = document.getElementById('edit_content').value.trim();
    
    if (content.length < 3) {
        e.preventDefault();
        showAlert('danger', 'Komentar harus minimal 3 karakter');
        document.getElementById('edit_content').focus();
        return false;
    }
    
    if (content.length > 1000) {
        e.preventDefault();
        showAlert('danger', 'Komentar maksimal 1000 karakter');
        document.getElementById('edit_content').focus();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    setLoadingState(submitBtn, true);
});

// Prevent double submission
document.getElementById('deleteCommentForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    setLoadingState(submitBtn, true);
});

// Add confirmation on page leave if form is dirty
let formChanged = false;
if (editContentTextarea) {
    editContentTextarea.addEventListener('input', function() {
        formChanged = true;
    });
}

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Reset form changed flag on submit
document.getElementById('editCommentForm').addEventListener('submit', function() {
    formChanged = false;
});

// Keyboard shortcuts for modals
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit edit form
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const editModal = document.getElementById('editCommentModal');
        if (editModal.classList.contains('show')) {
            e.preventDefault();
            document.getElementById('editCommentForm').submit();
        }
    }
});

// Auto-focus on modal open
document.getElementById('editCommentModal').addEventListener('shown.bs.modal', function() {
    document.getElementById('edit_content').focus();
});

// Tooltips initialization
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

</script>

<style>
/* Hover effect for table rows */
.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    transform: scale(1.01);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Loading spinner in buttons */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}

/* Better modal animations */
.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
}

/* Responsive improvements */
@media (max-width: 576px) {
    .stats-card .display-6 {
        font-size: 2rem;
    }
    
    .stats-card h5 {
        font-size: 0.9rem;
    }
    
    .table {
        font-size: 0.85rem;
    }
    
    .btn-action {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
}

/* Custom scrollbar for table */
.table-responsive::-webkit-scrollbar {
    height: 10px;
}

.table-responsive::-webkit-scrollbar-track {
    background: var(--bg-tertiary);
    border-radius: 5px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 5px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: var(--accent);
}

/* Better badge styling */
.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}

/* Improved card hover effect */
.stats-card {
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

/* Better focus states */
.form-control:focus,
.form-select:focus,
.btn:focus {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
}

/* Smooth transitions for all interactive elements */
a, button, .btn-action {
    transition: all 0.2s ease;
}

/* Loading state for forms */
form.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Fade in animation for stats cards */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stats-card {
    animation: fadeInUp 0.5s ease-out;
}

.stats-card:nth-child(1) { animation-delay: 0s; }
.stats-card:nth-child(2) { animation-delay: 0.1s; }
.stats-card:nth-child(3) { animation-delay: 0.2s; }
</style>