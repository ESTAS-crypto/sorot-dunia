<?php
// admin/kategori.php - Halaman Kelola Kategori
require_once '../config/config.php';

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                $name = sanitize_input($_POST['name']);
                
                if (empty($name)) {
                    $error = "Nama kategori harus diisi!";
                } else {
                    // Cek apakah nama kategori sudah ada
                    $check_query = "SELECT category_id FROM categories WHERE name = '$name'";
                    $check_result = mysqli_query($koneksi, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $error = "Nama kategori sudah digunakan!";
                    } else {
                        // Insert kategori baru
                        $insert_query = "INSERT INTO categories (name, created_at) VALUES ('$name', NOW())";
                        
                        if (mysqli_query($koneksi, $insert_query)) {
                            $success = "Kategori '$name' berhasil ditambahkan!";
                            
                            // Log activity
                            error_log("Admin {$_SESSION['username']} added new category: $name");
                        } else {
                            $error = "Gagal menambahkan kategori: " . mysqli_error($koneksi);
                        }
                    }
                }
                break;
                
            case 'edit_category':
                $category_id = sanitize_input($_POST['category_id']);
                $name = sanitize_input($_POST['name']);
                
                if (empty($name)) {
                    $error = "Nama kategori harus diisi!";
                } else {
                    // Get old category name for logging
                    $old_name_query = "SELECT name FROM categories WHERE category_id = '$category_id'";
                    $old_name_result = mysqli_query($koneksi, $old_name_query);
                    $old_name = mysqli_fetch_assoc($old_name_result)['name'] ?? 'Unknown';
                    
                    // Cek apakah nama kategori sudah ada (kecuali untuk kategori yang sedang diedit)
                    $check_query = "SELECT category_id FROM categories WHERE name = '$name' AND category_id != '$category_id'";
                    $check_result = mysqli_query($koneksi, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $error = "Nama kategori sudah digunakan!";
                    } else {
                        // Update kategori
                        $update_query = "UPDATE categories SET name = '$name' WHERE category_id = '$category_id'";
                        
                        if (mysqli_query($koneksi, $update_query)) {
                            $success = "Kategori berhasil diupdate dari '$old_name' menjadi '$name'!";
                            
                            // Log activity
                            error_log("Admin {$_SESSION['username']} updated category: $old_name -> $name");
                        } else {
                            $error = "Gagal mengupdate kategori: " . mysqli_error($koneksi);
                        }
                    }
                }
                break;
                 
            case 'delete_category':
                $category_id = sanitize_input($_POST['category_id']);
                
                // Get category name for logging
                $name_query = "SELECT name FROM categories WHERE category_id = '$category_id'";
                $name_result = mysqli_query($koneksi, $name_query);
                $category_name = mysqli_fetch_assoc($name_result)['name'] ?? 'Unknown';
                
                // Cek apakah kategori masih digunakan oleh artikel
                $check_articles_query = "SELECT COUNT(*) as total FROM articles WHERE category_id = '$category_id'";
                $check_articles_result = mysqli_query($koneksi, $check_articles_query);
                $articles_count = mysqli_fetch_assoc($check_articles_result)['total'];
                
                if ($articles_count > 0) {
                    $error = "Kategori '$category_name' tidak dapat dihapus karena masih digunakan oleh $articles_count artikel!";
                } else {
                    $delete_query = "DELETE FROM categories WHERE category_id = '$category_id'";
                    
                    if (mysqli_query($koneksi, $delete_query)) {
                        $success = "Kategori '$category_name' berhasil dihapus!";
                        
                        // Log activity
                        error_log("Admin {$_SESSION['username']} deleted category: $category_name");
                    } else {
                        $error = "Gagal menghapus kategori: " . mysqli_error($koneksi);
                    }
                }
                break;
        }
    }
}

// Get all categories with article count and creation date
$categories_query = "SELECT c.category_id, c.name, c.created_at,
                           COUNT(a.article_id) as article_count,
                           COUNT(CASE WHEN a.article_status = 'published' THEN 1 END) as published_count,
                           COUNT(CASE WHEN a.article_status = 'pending' THEN 1 END) as pending_count
                    FROM categories c 
                    LEFT JOIN articles a ON c.category_id = a.category_id 
                    GROUP BY c.category_id, c.name, c.created_at
                    ORDER BY c.created_at DESC, c.name ASC";
$categories_result = mysqli_query($koneksi, $categories_query);

// Get category statistics
$stats_query = "SELECT 
                    COUNT(*) as total_categories,
                    COUNT(CASE WHEN article_count > 0 THEN 1 END) as used_categories,
                    COUNT(CASE WHEN article_count = 0 THEN 1 END) as unused_categories
                FROM (
                    SELECT c.category_id, COUNT(a.article_id) as article_count
                    FROM categories c 
                    LEFT JOIN articles a ON c.category_id = a.category_id 
                    GROUP BY c.category_id
                ) as category_stats";

$stats_result = mysqli_query($koneksi, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-center text-white">
                <h5 class="card-title mb-3">Total Kategori</h5>
                <div class="display-4 fw-bold"><?php echo $stats['total_categories']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="card-body text-center text-white">
                <h5 class="card-title mb-3">Kategori Terpakai</h5>
                <div class="display-4 fw-bold"><?php echo $stats['used_categories']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="card-body text-center text-white">
                <h5 class="card-title mb-3">Kategori Kosong</h5>
                <div class="display-4 fw-bold"><?php echo $stats['unused_categories']; ?></div>
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
                        <i class="fas fa-tags me-2"></i>Kelola Kategori
                    </h4>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-2"></i>Tambah Kategori
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="row mb-3 g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari kategori...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="filterSelect">
                            <option value="">Semua Kategori</option>
                            <option value="used">Kategori Terpakai</option>
                            <option value="unused">Kategori Kosong</option>
                        </select>
                    </div>
                </div>

                <!-- Scroll hint for mobile -->
                <div class="scroll-hint">
                    <i class="fas fa-arrows-alt-h me-2"></i>Geser ke kiri/kanan untuk melihat seluruh tabel
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-striped" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Kategori</th>
                                <th>Total Artikel</th>
                                <th>Published</th>
                                <th>Pending</th>
                                <th>Dibuat</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($categories_result && mysqli_num_rows($categories_result) > 0): ?>
                            <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                            <tr data-category-name="<?php echo strtolower($category['name']); ?>" 
                                data-article-count="<?php echo $category['article_count']; ?>">
                                <td><?php echo htmlspecialchars($category['category_id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo $category['article_count']; ?> total
                                    </span>
                                </td>
                                <td>
                                    <?php if ($category['published_count'] > 0): ?>
                                    <span class="badge bg-success">
                                        <?php echo $category['published_count']; ?> published
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($category['pending_count'] > 0): ?>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo $category['pending_count']; ?> pending
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('d M Y', strtotime($category['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action btn-view" title="Lihat Artikel"
                                            onclick="viewCategoryArticles(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-edit" title="Edit Kategori"
                                            onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-delete" title="Hapus Kategori"
                                            onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['article_count']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <br>Tidak ada kategori ditemukan
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

<!-- Modal Tambah Kategori -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Tambah Kategori Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_category">
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-tag me-1"></i>Nama Kategori
                        </label>
                        <input type="text" class="form-control" id="name" name="name"
                            placeholder="Masukkan nama kategori..." required>
                        <div class="form-text">
                            Nama kategori harus unik dan akan digunakan untuk mengelompokkan artikel.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Tambah Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Kategori -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Kategori
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">
                            <i class="fas fa-tag me-1"></i>Nama Kategori
                        </label>
                        <input type="text" class="form-control" id="edit_name" name="name"
                            placeholder="Masukkan nama kategori..." required>
                        <div class="form-text">
                            Perubahan nama kategori akan berlaku untuk semua artikel yang menggunakan kategori ini.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirm Delete -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" id="delete_category_id">

                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                        <h6>Apakah Anda yakin ingin menghapus kategori?</h6>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Kategori: <span id="delete_category_name"></span></strong>
                    </div>

                    <div id="delete_warning" class="alert alert-danger" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Peringatan!</strong> Kategori ini masih digunakan oleh beberapa artikel dan tidak dapat dihapus!
                        <br><small>Hapus atau pindahkan artikel terlebih dahulu sebelum menghapus kategori.</small>
                    </div>

                    <div id="delete_safe" class="alert alert-info" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        Kategori ini tidak digunakan oleh artikel manapun dan aman untuk dihapus.
                    </div>

                    <p class="text-center text-muted">
                        <small>Tindakan ini tidak dapat dibatalkan!</small>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger" id="delete_confirm_btn">
                        <i class="fas fa-trash me-1"></i>Hapus Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal View Articles -->
<div class="modal fade" id="viewArticlesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-newspaper me-2"></i>Artikel dalam Kategori: <span id="view_category_name"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="articles_loading" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat artikel...</p>
                </div>
                <div id="articles_content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Tutup
                </button>
                <a href="#" id="view_all_articles_btn" class="btn btn-primary" target="_blank">
                    <i class="fas fa-external-link-alt me-1"></i>Lihat Semua di Kategori
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation for add category
document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
    const nameInput = document.getElementById('name');
    const name = nameInput.value.trim();

    if (name.length < 2) {
        e.preventDefault();
        showAlert('danger', 'Nama kategori harus minimal 2 karakter');
        nameInput.focus();
        return false;
    }

    if (name.length > 50) {
        e.preventDefault();
        showAlert('danger', 'Nama kategori maksimal 50 karakter');
        nameInput.focus();
        return false;
    }
});

// Form validation for edit category
document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
    const nameInput = document.getElementById('edit_name');
    const name = nameInput.value.trim();

    if (name.length < 2) {
        e.preventDefault();
        showAlert('danger', 'Nama kategori harus minimal 2 karakter');
        nameInput.focus();
        return false;
    }

    if (name.length > 50) {
        e.preventDefault();
        showAlert('danger', 'Nama kategori maksimal 50 karakter');
        nameInput.focus();
        return false;
    }
});
</script>