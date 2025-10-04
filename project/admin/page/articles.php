<?php
require_once '../config/config.php';
require_once '../config/auth_check.php';

checkAdminRole();

$error_message = '';
$success_message = '';

// Function untuk handle upload gambar dengan integrasi tabel images
function handleImageUpload($file) {
    global $koneksi;
    
    $upload_dir = '../uploads/articles/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $max_size = 300 * 1024; // 300KB
    
    // Validasi MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Format file tidak didukung. Hanya JPG, PNG, WEBP, dan GIF yang diizinkan.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 300KB.');
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'article_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    $url_path = '/uploads/articles/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal mengupload file.');
    }
    
    // Get image dimensions
    $image_info = getimagesize($filepath);
    $width = $image_info[0] ?? null;
    $height = $image_info[1] ?? null;
    $size_bytes = filesize($filepath);
    
    // Insert ke tabel images
    $insert_image_query = "INSERT INTO images (filename, url, mime, width, height, size_bytes, source_type, is_external, local_path) 
                          VALUES (?, ?, ?, ?, ?, ?, 'article', 0, ?)";
    $stmt = mysqli_prepare($koneksi, $insert_image_query);
    mysqli_stmt_bind_param($stmt, "sssiiis", $filename, $url_path, $mime_type, $width, $height, $size_bytes, $filepath);
    
    if (mysqli_stmt_execute($stmt)) {
        $image_id = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);
        return $image_id;
    } else {
        mysqli_stmt_close($stmt);
        unlink($filepath); // Hapus file jika gagal insert ke database
        throw new Exception('Gagal menyimpan data gambar ke database.');
    }
}

function deleteImageFile($image_id) {
    global $koneksi;
    
    if ($image_id) {
        // Get image info first
        $query = "SELECT filename, local_path, url FROM images WHERE id = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "i", $image_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Delete physical file
            if ($row['local_path'] && file_exists($row['local_path'])) {
                unlink($row['local_path']);
            } elseif (!$row['local_path'] && $row['filename']) {
                $old_path = '../uploads/articles/' . $row['filename'];
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }
            
            // Delete from database
            $delete_query = "DELETE FROM images WHERE id = ?";
            $stmt_delete = mysqli_prepare($koneksi, $delete_query);
            mysqli_stmt_bind_param($stmt_delete, "i", $image_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);
        }
        mysqli_stmt_close($stmt);
    }
}

function getArticleImageURL($article) {
    if ($article['image_url']) {
        return $article['image_url'];
    }
    return null;
}

// Handle form submissions - ADD ARTICLE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_article') {
    $title = sanitize_input($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category_id = intval($_POST['category_id'] ?? 0);
    $article_status = sanitize_input($_POST['article_status'] ?? 'pending');
    $author_id = $_SESSION['user_id'] ?? 0;
    $image_url = sanitize_input($_POST['image_url'] ?? '');
    $featured_image_id = null;
    
    if (empty($title) || empty($content) || $category_id <= 0 || $author_id <= 0) {
        $error_message = 'Semua field wajib harus diisi';
    } else {
        try {
            // Handle image upload
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
                $featured_image_id = handleImageUpload($_FILES['image_file']);
                $image_url = ''; // Clear URL if file uploaded
            } elseif (!empty($image_url)) {
                // Handle external URL image
                $filename = 'external_' . time() . '_' . uniqid();
                $insert_image_query = "INSERT INTO images (filename, url, mime, source_type, is_external) 
                                      VALUES (?, ?, 'image/jpeg', 'article', 1)";
                $stmt = mysqli_prepare($koneksi, $insert_image_query);
                mysqli_stmt_bind_param($stmt, "ss", $filename, $image_url);
                
                if (mysqli_stmt_execute($stmt)) {
                    $featured_image_id = mysqli_insert_id($koneksi);
                    mysqli_stmt_close($stmt);
                } else {
                    mysqli_stmt_close($stmt);
                }
            }
            
            // Insert article dengan featured_image_id
            $query = "INSERT INTO articles (title, content, category_id, author_id, article_status, featured_image_id, publication_date) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($stmt, "ssiisi", $title, $content, $category_id, $author_id, $article_status, $featured_image_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $article_id = mysqli_insert_id($koneksi);
                
                // Update source_id pada tabel images
                if ($featured_image_id) {
                    $update_image_query = "UPDATE images SET source_id = ? WHERE id = ?";
                    $stmt_update = mysqli_prepare($koneksi, $update_image_query);
                    mysqli_stmt_bind_param($stmt_update, "ii", $article_id, $featured_image_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                }
                
                $success_message = 'Artikel berhasil ditambahkan';
            } else {
                if ($featured_image_id) deleteImageFile($featured_image_id);
                $error_message = 'Gagal menambahkan artikel: ' . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Handle EDIT ARTICLE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_article') {
    $article_id = intval($_POST['article_id'] ?? 0);
    $title = sanitize_input($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category_id = intval($_POST['category_id'] ?? 0);
    $article_status = sanitize_input($_POST['article_status'] ?? 'pending');
    $image_url = sanitize_input($_POST['image_url'] ?? '');
    $old_image_id = intval($_POST['old_image_id'] ?? 0);
    $featured_image_id = $old_image_id;
    
    if (empty($title) || empty($content) || $category_id <= 0 || $article_id <= 0) {
        $error_message = 'Semua field wajib harus diisi';
    } else {
        try {
            // Handle new image upload
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
                $featured_image_id = handleImageUpload($_FILES['image_file']);
                
                // Update source_id
                $update_image_query = "UPDATE images SET source_id = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($koneksi, $update_image_query);
                mysqli_stmt_bind_param($stmt_update, "ii", $article_id, $featured_image_id);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
                
                // Delete old image
                if ($old_image_id && $old_image_id != $featured_image_id) {
                    deleteImageFile($old_image_id);
                }
                $image_url = ''; // Clear URL if file uploaded
            } elseif (!empty($image_url) && empty($old_image_id)) {
                // Handle new external URL
                $filename = 'external_' . $article_id . '_' . time();
                $insert_image_query = "INSERT INTO images (filename, url, mime, source_type, source_id, is_external) 
                                      VALUES (?, ?, 'image/jpeg', 'article', ?, 1)";
                $stmt = mysqli_prepare($koneksi, $insert_image_query);
                mysqli_stmt_bind_param($stmt, "ssi", $filename, $image_url, $article_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $featured_image_id = mysqli_insert_id($koneksi);
                    mysqli_stmt_close($stmt);
                }
            }
            
            // Update article
            $query = "UPDATE articles SET title = ?, content = ?, category_id = ?, article_status = ?, featured_image_id = ? WHERE article_id = ?";
            $stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($stmt, "ssiiii", $title, $content, $category_id, $article_status, $featured_image_id, $article_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Artikel berhasil diupdate';
            } else {
                $error_message = 'Gagal mengupdate artikel: ' . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Handle APPROVE ARTICLE
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
            $error_message = 'Gagal mengapprove artikel: ' . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle REJECT ARTICLE
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
                $error_message = 'Gagal menolak artikel: ' . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle DELETE ARTICLE
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
                        // Delete associated image
                        if ($row['featured_image_id']) {
                            deleteImageFile($row['featured_image_id']);
                        }
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

// Fetch all articles dengan JOIN ke tabel images
$query = "SELECT a.*, u.username as author_name, c.name as category_name,
          approver.username as approved_by_name, rejecter.username as rejected_by_name,
          i.id as image_id, i.filename as image_filename, i.url as image_url, i.is_external
          FROM articles a 
          LEFT JOIN users u ON a.author_id = u.id 
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN users approver ON a.approved_by = approver.id
          LEFT JOIN users rejecter ON a.rejected_by = rejecter.id
          LEFT JOIN images i ON a.featured_image_id = i.id
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
                <button type="button" class="btn btn-primary mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#addArticleModal">
                    <i class="bi bi-plus me-2"></i>Tambah Artikel
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

<!-- Main Content -->
<div class="card">
    <div class="card-body">
        <!-- Status Filter Pills - Mobile Scrollable -->
        <div class="mb-4">
            <div class="d-flex gap-2 overflow-auto pb-2" id="statusFilter" style="white-space: nowrap;">
                <a class="btn btn-sm btn-outline-light active flex-shrink-0" href="#" data-filter="all">
                    <i class="bi bi-list me-1"></i>Semua
                    <span class="badge bg-secondary ms-1"><?php echo count($all_articles); ?></span>
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
                    
                    <!-- Mobile Action Buttons -->
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
                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#approveArticleModal"
                            onclick="approveArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')">
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
                            <th><i class="bi bi-calendar me-1"></i>Tanggal</th>
                            <th class="text-center"><i class="bi bi-gear me-1"></i>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="articleTableBody">
                        <?php if (empty($all_articles)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
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
                            <td>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo formatDate($article['publication_date']); ?>
                                </small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-view" data-bs-toggle="modal" data-bs-target="#viewArticleModal"
                                        onclick="viewArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editArticleModal"
                                        onclick="editArticle(<?php echo htmlspecialchars(json_encode($article)); ?>)" title="Edit Artikel">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <?php if ($article['article_status'] == 'pending'): ?>
                                    <button class="btn-action btn-approve" data-bs-toggle="modal" data-bs-target="#approveArticleModal"
                                        onclick="approveArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')" title="Approve">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <button class="btn-action btn-reject" data-bs-toggle="modal" data-bs-target="#rejectArticleModal"
                                        onclick="rejectArticle(<?php echo $article['article_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')" title="Reject">
                                        <i class="bi bi-x"></i>
                                    </button>
                                    <?php endif; ?>

                                    <button class="btn-action btn-delete"
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
<div class="modal fade" id="viewArticleModal" tabindex="-1" aria-labelledby="viewArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewArticleModalLabel">
                    <i class="bi bi-eye me-2"></i>Preview Artikel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="viewArticleContent"></div>
                <div class="approval-actions" id="approvalActions" style="display: none;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <button type="button" class="btn btn-success w-100" onclick="quickApprove()">
                                <i class="bi bi-check me-2"></i>Approve & Publish
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-danger w-100" onclick="quickReject()">
                                <i class="bi bi-x me-2"></i>Reject Article
                            </button>
                        </div>
                    </div>
                </div>
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
<div class="modal fade" id="approveArticleModal" tabindex="-1" aria-labelledby="approveArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="approve_article">
                <input type="hidden" name="article_id" id="approve_article_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="approveArticleModalLabel">
                        <i class="bi bi-check-circle me-2"></i>Approve Artikel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Konfirmasi Approval</strong>
                    </div>
                    <p>Approve artikel <strong id="approve_article_title"></strong> untuk dipublikasikan?</p>

                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">
                            <i class="bi bi-sticky-note me-2"></i>Catatan Admin (Opsional)
                        </label>
                        <textarea class="form-control" name="admin_notes" id="admin_notes" rows="3"
                            placeholder="Berikan catatan atau komentar untuk penulis..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-2"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check me-2"></i>Approve & Publish
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Article Modal -->
<div class="modal fade" id="rejectArticleModal" tabindex="-1" aria-labelledby="rejectArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="reject_article">
                <input type="hidden" name="article_id" id="reject_article_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="rejectArticleModalLabel">
                        <i class="bi bi-x-circle me-2"></i>Reject Artikel
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                        <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="4" required
                            placeholder="Jelaskan secara detail alasan penolakan artikel ini..."></textarea>
                        <div class="form-text">Alasan yang jelas akan membantu penulis untuk memperbaiki artikelnya.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-2"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-ban me-2"></i>Reject Article
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Article Modal -->
<div class="modal fade" id="addArticleModal" tabindex="-1" aria-labelledby="addArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addArticleModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Artikel Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_article">

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_title" class="form-label">
                            <i class="bi bi-type me-2"></i>Judul Artikel <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="title" id="add_title" required
                            placeholder="Masukkan judul artikel yang menarik...">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_category_id" class="form-label">
                                <i class="bi bi-folder me-2"></i>Kategori <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="category_id" id="add_category_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_article_status" class="form-label">
                                <i class="bi bi-flag me-2"></i>Status Artikel
                            </label>
                            <select class="form-select" name="article_status" id="add_article_status">
                                <option value="pending">Pending Review</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                    </div>
                     <div class="col-md-6">
                            <label for="add_article_status" class="form-label">
                                <i class="bi bi-flag me-2"></i>Berlangganan
                            </label>
                            <select class="form-select" name="article_status" id="add_article_status">
                                <option value="pending">Free</option>
                                <option value="draft">Premium</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-image me-2"></i>Gambar Artikel
                        </label>

                        <div class="image-option-tabs">
                            <button type="button" class="image-option-tab active" onclick="switchImageOption('upload', this)">
                                <i class="bi bi-upload me-2"></i>Upload File
                            </button>
                            <button type="button" class="image-option-tab" onclick="switchImageOption('url', this)">
                                <i class="bi bi-link me-2"></i>URL Gambar
                            </button>
                        </div>

                        <div id="image-upload-option" class="image-option-content active">
                            <div class="image-upload-section" onclick="document.getElementById('add_image_file').click()">
                                <i class="bi bi-cloud-upload fa-3x mb-3"></i>
                                <p><strong>Klik untuk pilih gambar</strong> atau drag & drop</p>
                                <small class="text-muted">
                                    Format: JPG, PNG, WEBP, GIF | Maksimal: 300KB<br>
                                    Resolusi optimal: 800x600px
                                </small>
                                <input type="file" id="add_image_file" name="image_file" class="d-none"
                                    accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImage(this, 'add_image_preview')">
                            </div>
                            <img id="add_image_preview" class="image-preview d-none" alt="Preview">
                        </div>

                        <div id="image-url-option" class="image-option-content">
                            <input type="url" class="form-control" name="image_url" id="add_image_url"
                                placeholder="https://example.com/image.jpg">
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Pastikan URL gambar dapat diakses secara publik
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="add_content" class="form-label">
                            <i class="bi bi-text-left me-2"></i>Konten Artikel <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" name="content" id="add_content" rows="10" required
                            placeholder="Tulis konten artikel di sini...&#10;&#10;Tips:&#10;- Gunakan paragraf yang jelas&#10;- Berikan informasi yang berguna&#10;- Periksa tata bahasa sebelum submit"></textarea>
                        <div class="form-text">
                            <i class="bi bi-lightbulb me-1"></i>
                            Artikel berkualitas memiliki minimal 300 kata dan informasi yang berguna bagi pembaca.
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
<div class="modal fade" id="editArticleModal" tabindex="-1" aria-labelledby="editArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editArticleModalLabel">
                    <i class="bi bi-pencil me-2"></i>Edit Artikel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_article">
                <input type="hidden" name="article_id" id="edit_article_id">
                <input type="hidden" name="old_image_id" id="edit_old_image_id">

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">
                            <i class="bi bi-type me-2"></i>Judul Artikel <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="title" id="edit_title" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_category_id" class="form-label">
                                <i class="bi bi-folder me-2"></i>Kategori <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="category_id" id="edit_category_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $id => $name): ?>
                                <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_article_status" class="form-label">
                                <i class="bi bi-flag me-2"></i>Status Artikel
                            </label>
                            <select class="form-select" name="article_status" id="edit_article_status">
                                <option value="pending">Pending Review</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                <div class="col-md-6">
                            <label for="add_article_status" class="form-label">
                                <i class="bi bi-flag me-2"></i>Berlangganan
                            </label>
                            <select class="form-select" name="article_status" id="add_article_status">
                                <option value="pending">Free</option>
                                <option value="draft">Premium</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-image me-2"></i>Gambar Artikel
                        </label>

                        <div id="edit_current_image" class="mb-3"></div>

                        <div class="image-option-tabs">
                            <button type="button" class="image-option-tab active" onclick="switchImageOptionEdit('upload', this)">
                                <i class="bi bi-upload me-2"></i>Upload File
                            </button>
                            <button type="button" class="image-option-tab" onclick="switchImageOptionEdit('url', this)">
                                <i class="bi bi-link me-2"></i>URL Gambar
                            </button>
                        </div>

                        <div id="edit-image-upload-option" class="image-option-content active">
                            <div class="image-upload-section" onclick="document.getElementById('edit_image_file').click()">
                                <i class="bi bi-cloud-upload fa-3x mb-3"></i>
                                <p><strong>Klik untuk pilih gambar baru</strong> atau drag & drop</p>
                                <small class="text-muted">Format: JPG, PNG, WEBP, GIF | Maksimal: 300KB</small>
                                <input type="file" id="edit_image_file" name="image_file" class="d-none"
                                    accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImage(this, 'edit_image_preview')">
                            </div>
                            <img id="edit_image_preview" class="image-preview d-none" alt="Preview">
                        </div>

                        <div id="edit-image-url-option" class="image-option-content">
                            <input type="url" class="form-control" name="image_url" id="edit_image_url"
                                placeholder="https://example.com/image.jpg">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_content" class="form-label">
                            <i class="bi bi-text-left me-2"></i>Konten Artikel <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" name="content" id="edit_content" rows="10" required></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-2"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Article Modal -->
<div class="modal fade" id="deleteArticleModal" tabindex="-1" aria-labelledby="deleteArticleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_article">
                <input type="hidden" name="article_id" id="delete_article_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="deleteArticleModalLabel">
                        <i class="bi bi-trash me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Peringatan!</strong> Tindakan ini tidak dapat dibatalkan.
                    </div>

                    <div class="text-center mb-3">
                        <i class="bi bi-trash fa-4x text-danger mb-3"></i>
                        <h6>Apakah Anda yakin ingin menghapus artikel?</h6>
                    </div>

                    <div class="bg-light p-3 rounded">
                        <strong class="text-dark" id="delete_article_title"></strong>
                    </div>

                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Data artikel, gambar, dan semua informasi terkait akan dihapus permanen dari sistem.
                        </small>
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