<?php
require_once '../config/config.php';
require_once '../config/auth_check.php';
checkAdminRole();

$error_message = '';
$success_message = '';

// Function untuk handle upload gambar
function handleImageUpload($file) {
    $upload_dir = '../uploads/articles/';
    
    // Buat folder jika belum ada
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $max_size = 300 * 1024; // 300KB
    
    // Validasi file
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Format file tidak didukung. Hanya JPG, PNG, WEBP, dan GIF yang diizinkan.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 300KB.');
    }
    
    // Generate nama file unik
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'article_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal mengupload file.');
    }
    
    return $filename;
}

// Function untuk hapus file gambar
function deleteImageFile($filename) {
    if ($filename && file_exists('../uploads/articles/' . $filename)) {
        unlink('../uploads/articles/' . $filename);
    }
}

// Function untuk get URL gambar artikel (renamed to avoid conflict)
function getArticleImageURL($article) {
    if ($article['image_filename']) {
        return '../uploads/articles/' . $article['image_filename'];
    } elseif ($article['image_url']) {
        return $article['image_url'];
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_article') {
    $title = sanitize_input($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category_id = intval($_POST['category_id'] ?? 0);
    $article_status = sanitize_input($_POST['article_status'] ?? 'pending');
    $author_id = $_SESSION['user_id'] ?? 0;
    $image_url = sanitize_input($_POST['image_url'] ?? '');
    $image_filename = null;
    
    if (empty($title) || empty($content) || $category_id <= 0 || $author_id <= 0) {
        $error_message = 'Semua field wajib harus diisi';
    } else {
        try {
            // Handle image upload jika ada
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
                $image_filename = handleImageUpload($_FILES['image_file']);
                $image_url = ''; // Reset URL jika upload file
            }
            
            $query = "INSERT INTO articles (title, content, category_id, author_id, article_status, image_url, image_filename, publication_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($koneksi, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssiisss", $title, $content, $category_id, $author_id, $article_status, $image_url, $image_filename);
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Artikel berhasil ditambahkan';
                } else {
                    // Hapus file jika query gagal
                    if ($image_filename) deleteImageFile($image_filename);
                    $error_message = 'Gagal menambahkan artikel';
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_article') {
    $article_id = intval($_POST['article_id'] ?? 0);
    $title = sanitize_input($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category_id = intval($_POST['category_id'] ?? 0);
    $article_status = sanitize_input($_POST['article_status'] ?? 'pending');
    $image_url = sanitize_input($_POST['image_url'] ?? '');
    $image_filename = null;
    $old_image_filename = $_POST['old_image_filename'] ?? '';
    
    if (empty($title) || empty($content) || $category_id <= 0 || $article_id <= 0) {
        $error_message = 'Semua field wajib harus diisi';
    } else {
        try {
            // Handle image upload jika ada
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
                $image_filename = handleImageUpload($_FILES['image_file']);
                $image_url = ''; // Reset URL jika upload file
                
                // Hapus file lama jika ada
                if ($old_image_filename) {
                    deleteImageFile($old_image_filename);
                }
            } else {
                // Pertahankan file lama jika tidak ada upload baru
                $image_filename = $old_image_filename;
            }
            
            $query = "UPDATE articles SET title = ?, content = ?, category_id = ?, article_status = ?, image_url = ?, image_filename = ? WHERE article_id = ?";
            $stmt = mysqli_prepare($koneksi, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssisssi", $title, $content, $category_id, $article_status, $image_url, $image_filename, $article_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Artikel berhasil diupdate';
                } else {
                    $error_message = 'Gagal mengupdate artikel';
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'approve_article') {
    $article_id = intval($_POST['article_id'] ?? 0);
    $admin_notes = sanitize_input($_POST['admin_notes'] ?? '');
    
    $query = "UPDATE articles SET article_status = 'published', admin_notes = ?, approved_by = ?, approved_date = NOW() WHERE article_id = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sii", $admin_notes, $_SESSION['user_id'], $article_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_message = 'Artikel berhasil diapprove dan dipublikasikan';
        } else {
            $error_message = 'Gagal mengapprove artikel';
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reject_article') {
    $article_id = intval($_POST['article_id'] ?? 0);
    $rejection_reason = sanitize_input($_POST['rejection_reason'] ?? '');
    
    if (empty($rejection_reason)) {
        $error_message = 'Alasan penolakan harus diisi';
    } else {
        $query = "UPDATE articles SET article_status = 'rejected', rejection_reason = ?, rejected_by = ?, rejected_date = NOW() WHERE article_id = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sii", $rejection_reason, $_SESSION['user_id'], $article_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Artikel berhasil ditolak';
            } else {
                $error_message = 'Gagal menolak artikel';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_article') {
    $delete_id = intval($_POST['article_id'] ?? 0);
    
    $check_query = "SELECT article_id, author_id, image_filename FROM articles WHERE article_id = ?";
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
                        // Hapus file gambar jika ada
                        if ($row['image_filename']) {
                            deleteImageFile($row['image_filename']);
                        }
                        $success_message = 'Artikel berhasil dihapus';
                    } else {
                        $error_message = 'Gagal menghapus artikel';
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

// Fetch all articles (updated query tanpa tags)
$query = "SELECT a.*, u.username as author_name, c.name as category_name,
          approver.username as approved_by_name, rejecter.username as rejected_by_name
          FROM articles a 
          LEFT JOIN users u ON a.author_id = u.id 
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN users approver ON a.approved_by = approver.id
          LEFT JOIN users rejecter ON a.rejected_by = rejecter.id
          ORDER BY a.publication_date DESC";
$result = mysqli_query($koneksi, $query);
$all_articles = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Fetch categories
$query = "SELECT category_id, name FROM categories ORDER BY name";
$result = mysqli_query($koneksi, $query);
$categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    $categories[$row['category_id']] = $row['name'];
}

function formatDate($date) {
    return $date && $date != '0000-00-00 00:00:00' ? date('d M Y H:i', strtotime($date)) : '-';
}

function getStatusBadge($status) {
    $badges = [
        'published' => '<span class="badge bg-success">Published</span>',
        'draft' => '<span class="badge bg-warning text-dark">Draft</span>',
        'pending' => '<span class="badge bg-info">Pending Review</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'archived' => '<span class="badge bg-secondary">Archived</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

function truncateText($text, $length = 100) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Artikel - Admin Panel</title>
    <link rel="icon" href="../project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/project/style/artikel.css">
</head>

<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kelola Artikel</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#addArticleModal">
                                <i class="fas fa-plus me-2"></i>Tambah Artikel
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Status Filter Tabs -->
                        <ul class="nav nav-pills mb-3" id="statusFilter">
                            <li class="nav-item">
                                <a class="nav-link active" href="#" data-filter="all">
                                    Semua
                                    <span class="badge-count"><?php echo count($all_articles); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-filter="pending">
                                    Pending Review
                                    <span
                                        class="badge-count"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'pending')); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-filter="published">
                                    Published
                                    <span
                                        class="badge-count"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'published')); ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-filter="rejected">
                                    Rejected
                                    <span
                                        class="badge-count"><?php echo count(array_filter($all_articles, fn($a) => $a['article_status'] == 'rejected')); ?></span>
                                </a>
                            </li>
                        </ul>

                        <!-- Scroll hint for mobile -->
                        <div class="scroll-hint">
                            <i class="fas fa-arrows-alt-h"></i> Geser ke kiri/kanan untuk melihat seluruh tabel
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Judul</th>
                                        <th>Penulis</th>
                                        <th>Kategori</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="articleTableBody">
                                    <?php if (empty($all_articles)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Belum ada artikel</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($all_articles as $article): ?>
                                    <tr data-status="<?php echo $article['article_status']; ?>">
                                        <td><?php echo htmlspecialchars($article['article_id']); ?></td>
                                        <td class="text-truncate"><?php echo htmlspecialchars($article['title']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                                        </td>
                                        <td><?php echo getStatusBadge($article['article_status']); ?></td>
                                        <td><?php echo formatDate($article['publication_date']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" data-bs-toggle="modal"
                                                    data-bs-target="#viewArticleModal"
                                                    onclick="viewArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)"
                                                    title="Lihat">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action btn-edit" data-bs-toggle="modal"
                                                    data-bs-target="#editArticleModal"
                                                    onclick="editArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)"
                                                    title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($article['article_status'] == 'pending'): ?>
                                                <button class="btn-action btn-approve" data-bs-toggle="modal"
                                                    data-bs-target="#approveArticleModal"
                                                    onclick="approveArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')"
                                                    title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn-action btn-reject" data-bs-toggle="modal"
                                                    data-bs-target="#rejectArticleModal"
                                                    onclick="rejectArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')"
                                                    title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn-action btn-delete"
                                                    onclick="deleteArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')"
                                                    title="Hapus">
                                                    <i class="fas fa-trash"></i>
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
        </div>

        <!-- Modal View Artikel -->
        <div class="modal fade" id="viewArticleModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Review Artikel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="viewArticleContent">
                            <!-- Content will be loaded here -->
                        </div>
                        <div class="approval-actions" id="approvalActions" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <button type="button" class="btn btn-success w-100" onclick="quickApprove()">
                                        <i class="fas fa-check me-2"></i>Approve & Publish
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-danger w-100" onclick="quickReject()">
                                        <i class="fas fa-times me-2"></i>Reject Article
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="button" class="btn btn-primary" onclick="editFromView()">
                            <i class="fas fa-edit me-2"></i>Edit Artikel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Approve Artikel -->
        <div class="modal fade" id="approveArticleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="approve_article">
                        <input type="hidden" name="article_id" id="approve_article_id">
                        <div class="modal-header">
                            <h5 class="modal-title">Approve Artikel</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Approve artikel <strong id="approve_article_title"></strong> untuk dipublikasikan?</p>
                            <div class="mb-3">
                                <label class="form-label">Catatan Admin (Opsional)</label>
                                <textarea class="form-control" name="admin_notes" rows="3"
                                    placeholder="Catatan atau komentar untuk penulis..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success">Approve & Publish</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Reject Artikel -->
        <div class="modal fade" id="rejectArticleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="reject_article">
                        <input type="hidden" name="article_id" id="reject_article_id">
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Artikel</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Reject artikel <strong id="reject_article_title"></strong>?</p>
                            <div class="mb-3">
                                <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="rejection_reason" rows="4" required
                                    placeholder="Jelaskan alasan penolakan artikel ini..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Reject Article</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Artikel -->
        <div class="modal fade" id="addArticleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Artikel Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_article">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Judul Artikel <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $id => $name): ?>
                                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status Artikel</label>
                                    <select class="form-select" name="article_status">
                                        <option value="pending">Pending Review</option>
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Image Upload Section -->
                            <div class="mb-3">
                                <label class="form-label">Gambar Artikel</label>
                                <div class="image-option-tabs">
                                    <button type="button" class="image-option-tab active"
                                        onclick="switchImageOption('upload', this)">
                                        <i class="fas fa-upload me-2"></i>Upload File
                                    </button>
                                    <button type="button" class="image-option-tab"
                                        onclick="switchImageOption('url', this)">
                                        <i class="fas fa-link me-2"></i>URL Gambar
                                    </button>
                                </div>

                                <div id="image-upload-option" class="image-option-content active">
                                    <div class="image-upload-section"
                                        onclick="document.getElementById('add_image_file').click()">
                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                        <p>Klik untuk pilih gambar atau drag & drop</p>
                                        <small class="text-muted">Format: JPG, PNG, WEBP, GIF | Maksimal: 300KB</small>
                                        <input type="file" id="add_image_file" name="image_file" class="d-none"
                                            accept="image/jpeg,image/png,image/webp,image/gif"
                                            onchange="previewImage(this, 'add_image_preview')">
                                    </div>
                                    <img id="add_image_preview" class="image-preview d-none" alt="Preview">
                                </div>

                                <div id="image-url-option" class="image-option-content">
                                    <input type="url" class="form-control" name="image_url"
                                        placeholder="https://example.com/image.jpg">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Konten Artikel <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="content" rows="8" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Artikel -->
        <div class="modal fade" id="editArticleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Artikel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_article">
                        <input type="hidden" name="article_id" id="edit_article_id">
                        <input type="hidden" name="old_image_filename" id="edit_old_image_filename">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Judul Artikel <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" id="edit_title" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                    <select class="form-select" name="category_id" id="edit_category_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $id => $name): ?>
                                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status Artikel</label>
                                    <select class="form-select" name="article_status" id="edit_article_status">
                                        <option value="pending">Pending Review</option>
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Image Upload Section for Edit -->
                            <div class="mb-3">
                                <label class="form-label">Gambar Artikel</label>
                                <div class="image-option-tabs">
                                    <button type="button" class="image-option-tab active"
                                        onclick="switchImageOptionEdit('upload', this)">
                                        <i class="fas fa-upload me-2"></i>Upload File
                                    </button>
                                    <button type="button" class="image-option-tab"
                                        onclick="switchImageOptionEdit('url', this)">
                                        <i class="fas fa-link me-2"></i>URL Gambar
                                    </button>
                                </div>

                                <div id="edit-image-upload-option" class="image-option-content active">
                                    <div class="image-upload-section"
                                        onclick="document.getElementById('edit_image_file').click()">
                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                        <p>Klik untuk pilih gambar baru atau drag & drop</p>
                                        <small class="text-muted">Format: JPG, PNG, WEBP, GIF | Maksimal: 300KB</small>
                                        <input type="file" id="edit_image_file" name="image_file" class="d-none"
                                            accept="image/jpeg,image/png,image/webp,image/gif"
                                            onchange="previewImage(this, 'edit_image_preview')">
                                    </div>
                                    <img id="edit_image_preview" class="image-preview d-none" alt="Preview">
                                    <div id="edit_current_image" class="mt-2"></div>
                                </div>

                                <div id="edit-image-url-option" class="image-option-content">
                                    <input type="url" class="form-control" name="image_url" id="edit_image_url"
                                        placeholder="https://example.com/image.jpg">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Konten Artikel <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="content" id="edit_content" rows="8"
                                    required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Hapus -->
        <div class="modal fade" id="deleteArticleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="delete_article">
                        <input type="hidden" name="article_id" id="delete_article_id">
                        <div class="modal-header">
                            <h5 class="modal-title">Konfirmasi Hapus</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Apakah Anda yakin ingin menghapus artikel <strong id="delete_article_title"></strong>?
                            </p>
                            <p class="text-danger">Tindakan ini tidak dapat dibatalkan!</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentArticleForView = null;

    // Image upload functions
    function switchImageOption(option, button) {
        // Update tab appearance
        document.querySelectorAll('#addArticleModal .image-option-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        button.classList.add('active');

        // Show/hide content
        document.getElementById('image-upload-option').classList.toggle('active', option === 'upload');
        document.getElementById('image-url-option').classList.toggle('active', option === 'url');

        // Clear inputs
        if (option === 'upload') {
            document.querySelector('#addArticleModal input[name="image_url"]').value = '';
        } else {
            document.getElementById('add_image_file').value = '';
            document.getElementById('add_image_preview').classList.add('d-none');
        }
    }

    function switchImageOptionEdit(option, button) {
        // Update tab appearance
        document.querySelectorAll('#editArticleModal .image-option-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        button.classList.add('active');

        // Show/hide content
        document.getElementById('edit-image-upload-option').classList.toggle('active', option === 'upload');
        document.getElementById('edit-image-url-option').classList.toggle('active', option === 'url');

        // Clear inputs
        if (option === 'upload') {
            document.querySelector('#editArticleModal input[name="image_url"]').value = '';
        } else {
            document.getElementById('edit_image_file').value = '';
            document.getElementById('edit_image_preview').classList.add('d-none');
        }
    }

    function previewImage(input, previewId) {
        const file = input.files[0];
        const preview = document.getElementById(previewId);

        if (file) {
            // Validate file size
            if (file.size > 300 * 1024) {
                alert('Ukuran file terlalu besar. Maksimal 300KB.');
                input.value = '';
                preview.classList.add('d-none');
                return;
            }

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Format file tidak didukung. Hanya JPG, PNG, WEBP, dan GIF yang diizinkan.');
                input.value = '';
                preview.classList.add('d-none');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('d-none');
        }
    }

    // Filter functionality
    document.querySelectorAll('#statusFilter a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Update active tab
            document.querySelectorAll('#statusFilter a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            const rows = document.querySelectorAll('#articleTableBody tr[data-status]');

            rows.forEach(row => {
                if (filter === 'all' || row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    function viewArticle(article) {
        currentArticleForView = article;

        const statusBadges = {
            'published': '<span class="badge bg-success">Published</span>',
            'draft': '<span class="badge bg-warning text-dark">Draft</span>',
            'pending': '<span class="badge bg-info">Pending Review</span>',
            'rejected': '<span class="badge bg-danger">Rejected</span>',
            'archived': '<span class="badge bg-secondary">Archived</span>'
        };

        const statusBadge = statusBadges[article.article_status] ||
            `<span class="badge bg-secondary">${article.article_status}</span>`;

        // Get image URL (either from upload or URL)
        let imageHtml = '';
        if (article.image_filename) {
            imageHtml =
                `<img src="../uploads/articles/${article.image_filename}" class="img-fluid mb-3" style="max-height: 300px; object-fit: cover;">`;
        } else if (article.image_url) {
            imageHtml =
                `<img src="${article.image_url}" class="img-fluid mb-3" style="max-height: 300px; object-fit: cover;">`;
        }

        let contentHtml = `
            <div class="article-meta">
                <div class="row">
                    <div class="col-md-6">
                        <strong>ID:</strong> ${article.article_id}<br>
                        <strong>Penulis:</strong> ${article.author_name || 'Unknown'}<br>
                        <strong>Kategori:</strong> ${article.category_name || 'Uncategorized'}
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> ${statusBadge}<br>
                        <strong>Tanggal:</strong> ${formatDate(article.publication_date)}
                    </div>
                </div>
            </div>
            
            <h2>${article.title}</h2>
            
            ${imageHtml}
            
            <div class="article-content">
                ${article.content.replace(/\n/g, '<br>')}
            </div>
        `;

        // Add rejection reason if rejected
        if (article.article_status === 'rejected' && article.rejection_reason) {
            contentHtml += `
                <div class="alert alert-danger mt-3">
                    <strong>Alasan Penolakan:</strong><br>
                    ${article.rejection_reason}
                    <br><small class="text-muted">Ditolak oleh: ${article.rejected_by_name || 'Unknown'}</small>
                </div>
            `;
        }

        // Add admin notes if approved
        if (article.article_status === 'published' && article.admin_notes) {
            contentHtml += `
                <div class="alert alert-success mt-3">
                    <strong>Catatan Admin:</strong><br>
                    ${article.admin_notes}
                    <br><small class="text-muted">Diapprove oleh: ${article.approved_by_name || 'Unknown'}</small>
                </div>
            `;
        }

        document.getElementById('viewArticleContent').innerHTML = contentHtml;

        // Show approval actions only for pending articles
        const approvalActions = document.getElementById('approvalActions');
        if (article.article_status === 'pending') {
            approvalActions.style.display = 'block';
        } else {
            approvalActions.style.display = 'none';
        }
    }

    function editArticle(article) {
        document.getElementById('edit_article_id').value = article.article_id;
        document.getElementById('edit_title').value = article.title;
        document.getElementById('edit_category_id').value = article.category_id;
        document.getElementById('edit_article_status').value = article.article_status;
        document.getElementById('edit_content').value = article.content;
        document.getElementById('edit_image_url').value = article.image_url || '';
        document.getElementById('edit_old_image_filename').value = article.image_filename || '';

        // Show current image if exists
        const currentImageDiv = document.getElementById('edit_current_image');
        if (article.image_filename) {
            currentImageDiv.innerHTML = `
                <div class="alert alert-info">
                    <strong>Gambar saat ini:</strong><br>
                    <img src="../uploads/articles/${article.image_filename}" style="max-height: 100px; object-fit: cover;" class="rounded">
                    <br><small>Upload file baru untuk mengganti gambar ini</small>
                </div>
            `;
        } else if (article.image_url) {
            currentImageDiv.innerHTML = `
                <div class="alert alert-info">
                    <strong>Gambar saat ini (URL):</strong><br>
                    <img src="${article.image_url}" style="max-height: 100px; object-fit: cover;" class="rounded">
                </div>
            `;
        } else {
            currentImageDiv.innerHTML = '';
        }

        // Reset image preview
        document.getElementById('edit_image_preview').classList.add('d-none');
        document.getElementById('edit_image_file').value = '';
    }

    function editFromView() {
        if (currentArticleForView) {
            // Close view modal
            bootstrap.Modal.getInstance(document.getElementById('viewArticleModal')).hide();

            // Open edit modal with current article data
            setTimeout(() => {
                editArticle(currentArticleForView);
                new bootstrap.Modal(document.getElementById('editArticleModal')).show();
            }, 300);
        }
    }

    function approveArticle(articleId, title) {
        document.getElementById('approve_article_id').value = articleId;
        document.getElementById('approve_article_title').textContent = title;
    }

    function rejectArticle(articleId, title) {
        document.getElementById('reject_article_id').value = articleId;
        document.getElementById('reject_article_title').textContent = title;
    }

    function deleteArticle(articleId, title) {
        document.getElementById('delete_article_id').value = articleId;
        document.getElementById('delete_article_title').textContent = title;
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteArticleModal'));
        deleteModal.show();
    }

    function quickApprove() {
        if (currentArticleForView) {
            // Close view modal
            bootstrap.Modal.getInstance(document.getElementById('viewArticleModal')).hide();

            // Open approve modal
            setTimeout(() => {
                approveArticle(currentArticleForView.article_id, currentArticleForView.title);
                new bootstrap.Modal(document.getElementById('approveArticleModal')).show();
            }, 300);
        }
    }

    function quickReject() {
        if (currentArticleForView) {
            // Close view modal
            bootstrap.Modal.getInstance(document.getElementById('viewArticleModal')).hide();

            // Open reject modal
            setTimeout(() => {
                rejectArticle(currentArticleForView.article_id, currentArticleForView.title);
                new bootstrap.Modal(document.getElementById('rejectArticleModal')).show();
            }, 300);
        }
    }

    function formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00 00:00:00') return '-';

        const date = new Date(dateString);
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleDateString('id-ID', options);
    }

    // Drag and drop functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Add drag and drop for upload sections
        const uploadSections = document.querySelectorAll('.image-upload-section');

        uploadSections.forEach(section => {
            section.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            section.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            section.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const fileInput = this.querySelector('input[type="file"]');
                    fileInput.files = files;

                    // Trigger change event
                    const event = new Event('change', {
                        bubbles: true
                    });
                    fileInput.dispatchEvent(event);
                }
            });
        });
    });

    // Auto-refresh page every 5 minutes to show new articles
    setInterval(function() {
        // Only refresh if user is not in a modal
        if (!document.querySelector('.modal.show')) {
            location.reload();
        }
    }, 300000); // 5 minutes
    </script>
</body>

</html>