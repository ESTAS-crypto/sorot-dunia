<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../config/login.php");
    exit();
}

if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_comment':
                $comment_id = sanitize_input($_POST['comment_id']);
                
                $success = "Komentar berhasil disetujui!";
                break;
                
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
                    } else {
                        $error = "Gagal mengupdate komentar: " . mysqli_error($koneksi);
                    }
                }
                break;
                 
            case 'delete_comment':
                $comment_id = sanitize_input($_POST['comment_id']);
                
                $delete_query = "DELETE FROM comments WHERE comment_id = '$comment_id'";
                
                if (mysqli_query($koneksi, $delete_query)) {
                    $success = "Komentar berhasil dihapus!";
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
$comments_query = "SELECT c.comment_id, c.content, c.created_at, c.article_id,
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

// Get articles for filter dropdown
$articles_query = "SELECT article_id, title FROM articles ORDER BY title ASC";
$articles_result = mysqli_query($koneksi, $articles_query);

// Get users for filter dropdown
$users_query = "SELECT id, username, full_name FROM users ORDER BY username ASC";
$users_result = mysqli_query($koneksi, $users_query);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Komentar</title>
    <link rel="icon" href="../project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
    /* Dark theme colors - Same as kategori.php */
    body {
        background-color: #1a1a1a;
        color: #ffffff;
    }

    .card {
        background-color: #2d2d2d;
        border: 1px solid #404040;
        border-radius: 8px;
    }

    .card-header {
        background-color: #404040;
        border-bottom: 1px solid #555555;
        color: #ffffff;
    }

    .card-body {
        background-color: #2d2d2d;
        color: #ffffff;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 5px 10px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: bold;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        transition: all 0.2s ease;
    }

    .btn-edit {
        background-color: #6c757d;
        color: #ffffff;
        border: 1px solid #6c757d;
    }

    .btn-edit:hover {
        background-color: #5a6268;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .btn-delete {
        background-color: #495057;
        color: #ffffff;
        border: 1px solid #495057;
    }

    .btn-delete:hover {
        background-color: #343a40;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .btn-action i {
        font-size: 14px;
    }

    /* Fixed table responsive styling */
    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        background-color: #2d2d2d;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        margin-bottom: 0;
        background-color: #2d2d2d;
        color: #ffffff;
        min-width: 800px;
    }

    .table th {
        background-color: #404040;
        border-bottom: 2px solid #555555;
        font-weight: 600;
        color: #ffffff;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table td {
        vertical-align: middle;
        padding: 12px;
        border-bottom: 1px solid #555555;
        background-color: #2d2d2d;
        color: #ffffff;
    }

    .table tbody tr:hover {
        background-color: #3d3d3d;
    }

    .table tbody tr:hover td {
        background-color: #3d3d3d;
    }

    .badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
        background-color: #495057 !important;
        color: #ffffff;
    }

    .badge-success {
        background-color: #28a745 !important;
    }

    .badge-warning {
        background-color: #ffc107 !important;
        color: #000000 !important;
    }

    /* Modal styling */
    .modal-content {
        background-color: #2d2d2d;
        border: 1px solid #404040;
        color: #ffffff;
    }

    .modal-header {
        background-color: #404040;
        border-bottom: 1px solid #555555;
    }

    .modal-title {
        color: #ffffff;
    }

    .modal-footer {
        background-color: #2d2d2d;
        border-top: 1px solid #555555;
    }

    .form-control {
        background-color: #404040;
        border: 1px solid #555555;
        color: #ffffff;
    }

    .form-control:focus {
        background-color: #404040;
        border-color: #6c757d;
        color: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    }

    .form-select {
        background-color: #404040;
        border: 1px solid #555555;
        color: #ffffff;
    }

    .form-select:focus {
        background-color: #404040;
        border-color: #6c757d;
        color: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    }

    .form-label {
        color: #ffffff;
    }

    .alert-danger {
        background-color: #3d3d3d;
        border-color: #555555;
        color: #ffffff;
    }

    .alert-success {
        background-color: #3d3d3d;
        border-color: #555555;
        color: #ffffff;
    }

    .text-muted {
        color: #adb5bd !important;
    }

    .btn-primary {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .btn-primary:hover {
        background-color: #5a6268;
        border-color: #5a6268;
    }

    .btn-secondary {
        background-color: #495057;
        border-color: #495057;
    }

    .btn-secondary:hover {
        background-color: #343a40;
        border-color: #343a40;
    }

    .btn-danger {
        background-color: #495057;
        border-color: #495057;
    }

    .btn-danger:hover {
        background-color: #343a40;
        border-color: #343a40;
    }

    .btn-close {
        filter: invert(1);
    }

    .text-danger {
        color: #adb5bd !important;
    }

    .no-data {
        color: #adb5bd !important;
    }

    /* Comment content styling */
    .comment-content {
        max-width: 300px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-height: 100px;
        overflow-y: auto;
        line-height: 1.4;
    }

    .comment-content::-webkit-scrollbar {
        width: 4px;
    }

    .comment-content::-webkit-scrollbar-track {
        background-color: #404040;
        border-radius: 2px;
    }

    .comment-content::-webkit-scrollbar-thumb {
        background-color: #6c757d;
        border-radius: 2px;
    }

    /* Filter section */
    .filter-section {
        background-color: #404040;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .filter-section .form-control,
    .filter-section .form-select {
        background-color: #2d2d2d;
        border: 1px solid #555555;
        color: #ffffff;
    }

    /* Scroll hint untuk mobile */
    .scroll-hint {
        display: none;
        background-color: #404040;
        color: #adb5bd;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        text-align: center;
        margin-bottom: 10px;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 15px;
        }

        .scroll-hint {
            display: block;
        }

        .table-responsive {
            font-size: 13px;
            border: 1px solid #555555;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .table {
            min-width: 1000px;
        }

        .table th,
        .table td {
            padding: 10px 8px;
            font-size: 12px;
        }

        .btn-action {
            min-width: 30px;
            height: 30px;
            padding: 5px 8px;
        }

        .btn-action i {
            font-size: 12px;
        }

        .filter-section {
            padding: 10px;
        }

        .filter-section .row {
            --bs-gutter-x: 0.5rem;
        }
    }

    @media (max-width: 576px) {
        .table-responsive {
            font-size: 12px;
            overflow-x: scroll;
        }

        .table {
            min-width: 1200px;
        }

        .table th,
        .table td {
            padding: 8px 6px;
            font-size: 11px;
        }

        .action-buttons {
            flex-direction: row;
            gap: 3px;
        }

        .btn-action {
            min-width: 28px;
            height: 28px;
            padding: 4px 6px;
        }

        .btn-action i {
            font-size: 11px;
        }

        .badge {
            font-size: 10px;
            padding: 3px 6px;
        }
    }

    /* Scrollbar styling for webkit browsers */
    .table-responsive::-webkit-scrollbar {
        height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background-color: #404040;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background-color: #6c757d;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background-color: #5a6268;
    }

    /* User info styling */
    .user-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .user-name {
        font-weight: 600;
        color: #ffffff;
    }

    .user-username {
        font-size: 12px;
        color: #adb5bd;
    }

    .article-title {
        max-width: 200px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        line-height: 1.3;
    }

    .date-info {
        font-size: 12px;
        color: #adb5bd;
    }
    </style>
</head>

<body>
    <div class="container-fluid p-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Kelola Komentar</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Filter Section -->
                        <div class="filter-section">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Cari Komentar</label>
                                    <input type="text" class="form-control" name="search"
                                        value="<?php echo htmlspecialchars($search); ?>"
                                        placeholder="Cari konten komentar...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Filter Artikel</label>
                                    <select class="form-select" name="article">
                                        <option value="">Semua Artikel</option>
                                        <?php 
                                        mysqli_data_seek($articles_result, 0);
                                        while ($article = mysqli_fetch_assoc($articles_result)): 
                                        ?>
                                        <option value="<?php echo $article['article_id']; ?>"
                                            <?php echo $article_filter == $article['article_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Filter User</label>
                                    <select class="form-select" name="user">
                                        <option value="">Semua User</option>
                                        <?php 
                                        mysqli_data_seek($users_result, 0);
                                        while ($user = mysqli_fetch_assoc($users_result)): 
                                        ?>
                                        <option value="<?php echo $user['id']; ?>"
                                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill">
                                            <i class="fas fa-search"></i> Cari
                                        </button>
                                        <a href="?page=komentar" class="btn btn-secondary">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">Total Komentar: <?php echo $comment_count; ?></small>
                        </div>

                        <!-- Scroll hint for mobile -->
                        <div class="scroll-hint">
                            <i class="fas fa-arrows-alt-h"></i> Geser ke kiri/kanan untuk melihat seluruh tabel
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Artikel</th>
                                        <th>Komentar</th>
                                        <th>Tanggal</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($comments_result && mysqli_num_rows($comments_result) > 0): ?>
                                    <?php while ($comment = mysqli_fetch_assoc($comments_result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($comment['comment_id']); ?></td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($comment['full_name'] ?: $comment['username']); ?>
                                                </div>
                                                <div class="user-username">
                                                    @<?php echo htmlspecialchars($comment['username']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="article-title">
                                                <?php echo htmlspecialchars($comment['article_title']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="comment-content">
                                                <?php echo htmlspecialchars($comment['content']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action btn-edit" title="Edit Komentar"
                                                    onclick="editComment(<?php echo htmlspecialchars(json_encode($comment)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-delete"
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
                                        <td colspan="6" class="text-center no-data">Tidak ada komentar ditemukan</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Komentar -->
    <div class="modal fade" id="editCommentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Komentar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_comment">
                        <input type="hidden" name="comment_id" id="edit_comment_id">

                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <input type="text" class="form-control" id="edit_user_info" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Artikel</label>
                            <input type="text" class="form-control" id="edit_article_title" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="edit_content" class="form-label">Konten Komentar</label>
                            <textarea class="form-control" id="edit_content" name="content" rows="5"
                                required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Dibuat</label>
                            <input type="text" class="form-control" id="edit_created_at" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update Komentar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Confirm Delete -->
    <div class="modal fade" id="deleteCommentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_comment">
                        <input type="hidden" name="comment_id" id="delete_comment_id">
                        <p>Apakah Anda yakin ingin menghapus komentar dari user <strong id="delete_user_name"></strong>?
                        </p>
                        <p class="text-danger">Tindakan ini tidak dapat dibatalkan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus Komentar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editComment(comment) {
        document.getElementById('edit_comment_id').value = comment.comment_id;
        document.getElementById('edit_content').value = comment.content;
        document.getElementById('edit_user_info').value = comment.full_name + ' (@' + comment.username + ')';
        document.getElementById('edit_article_title').value = comment.article_title;
        document.getElementById('edit_created_at').value = new Date(comment.created_at).toLocaleString('id-ID');

        var editModal = new bootstrap.Modal(document.getElementById('editCommentModal'));
        editModal.show();
    }

    function deleteComment(commentId, username) {
        document.getElementById('delete_comment_id').value = commentId;
        document.getElementById('delete_user_name').textContent = username;

        var deleteModal = new bootstrap.Modal(document.getElementById('deleteCommentModal'));
        deleteModal.show();
    }

    // Auto-resize textarea
    document.getElementById('edit_content').addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    </script>
</body>

</html>