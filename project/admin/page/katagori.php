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

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Admin Sorot Dunia</title>
    <link rel="icon" href="../img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Dark theme colors - Enhanced version */
    :root {
        --bg-primary: #1a1a1a;
        --bg-secondary: #2d2d2d;
        --bg-tertiary: #404040;
        --border-color: #555555;
        --text-primary: #ffffff;
        --text-secondary: #adb5bd;
        --accent-primary: #6c757d;
        --accent-hover: #5a6268;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
    }

    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Enhanced Card Styling */
    .card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }

    .card-header {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 12px 12px 0 0 !important;
        padding: 1.5rem;
    }

    .card-body {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        padding: 1.5rem;
    }

    /* Stats Cards */
    .stats-card {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 1.5rem;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }

    .stats-card .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: var(--accent-primary);
        margin-bottom: 0.5rem;
    }

    .stats-card .stats-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Enhanced Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 8px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-action::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .btn-action:hover::before {
        left: 100%;
    }

    .btn-edit {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .btn-edit:hover {
        background: linear-gradient(135deg, #5a6268, #495057);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-delete {
        background: linear-gradient(135deg, #495057, #343a40);
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .btn-delete:hover {
        background: linear-gradient(135deg, #343a40, #23272b);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-view {
        background: linear-gradient(135deg, var(--info-color), #138496);
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .btn-view:hover {
        background: linear-gradient(135deg, #138496, #0f6674);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    /* Enhanced Table */
    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
        background-color: var(--bg-secondary);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .table {
        margin-bottom: 0;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        min-width: 800px;
    }

    .table th {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        border-bottom: 2px solid var(--border-color);
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
    }

    .table td {
        vertical-align: middle;
        padding: 15px 12px;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        transition: background-color 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: var(--bg-tertiary);
        transform: scale(1.01);
        transition: all 0.3s ease;
    }

    .table tbody tr:hover td {
        background-color: var(--bg-tertiary);
    }

    /* Enhanced Badges */
    .badge {
        font-size: 11px;
        font-weight: 600;
        padding: 6px 10px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover));
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .badge-success {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
    }

    .badge-warning {
        background: linear-gradient(135deg, var(--warning-color), #e0a800);
        color: #000;
    }

    .badge-info {
        background: linear-gradient(135deg, var(--info-color), #117a8b);
    }

    /* Enhanced Modal */
    .modal-content {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        border-bottom: 1px solid var(--border-color);
        border-radius: 12px 12px 0 0;
    }

    .modal-title {
        color: var(--text-primary);
        font-weight: 600;
    }

    .modal-footer {
        background-color: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
        border-radius: 0 0 12px 12px;
    }

    /* Enhanced Form Controls */
    .form-control,
    .form-select {
        background-color: var(--bg-tertiary);
        border: 2px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 8px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
        background-color: var(--bg-tertiary);
        border-color: var(--accent-primary);
        color: var(--text-primary);
        box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        transform: translateY(-2px);
    }

    .form-label {
        color: var(--text-primary);
        font-weight: 500;
        margin-bottom: 8px;
    }

    /* Enhanced Alerts */
    .alert {
        border-radius: 8px;
        border: none;
        font-weight: 500;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .alert-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .alert-success {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }

    /* Enhanced Buttons */
    .btn {
        border-radius: 8px;
        font-weight: 500;
        padding: 10px 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover));
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--accent-hover), #495057);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #495057, #343a40);
        border: none;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        border: none;
    }

    /* Date formatting */
    .date-text {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-style: italic;
    }

    /* Scroll hint enhancement */
    .scroll-hint {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        color: var(--text-primary);
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 12px;
        text-align: center;
        margin-bottom: 15px;
        border: 1px solid var(--border-color);
    }

    /* Mobile responsive enhancements */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 10px;
        }

        .card-header {
            padding: 1rem;
            flex-direction: column;
            gap: 1rem;
        }

        .stats-card {
            margin-bottom: 1rem;
        }

        .stats-card .stats-number {
            font-size: 2rem;
        }
    }

    /* Loading animation */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, .3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        height: 10px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background-color: var(--bg-tertiary);
        border-radius: 5px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover));
        border-radius: 5px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--accent-hover), #495057);
    }
    </style>
</head>

<body>
    <div class="container-fluid p-4">
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_categories']; ?></div>
                    <div class="stats-label">Total Kategori</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['used_categories']; ?></div>
                    <div class="stats-label">Kategori Terpakai</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['unused_categories']; ?></div>
                    <div class="stats-label">Kategori Kosong</div>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title mb-0">
                                <i class="fas fa-tags me-2"></i>Kelola Kategori
                            </h4>
                            <small class="text-muted">Manage artikel categories</small>
                        </div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addCategoryModal">
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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchInput"
                                        placeholder="Cari kategori...">
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
                        <div class="scroll-hint d-md-none">
                            <i class="fas fa-arrows-alt-h me-2"></i>Geser ke kiri/kanan untuk melihat seluruh tabel
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="categoriesTable">
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
                                            <span class="badge">
                                                <?php echo $category['article_count']; ?> total
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($category['published_count'] > 0): ?>
                                            <span class="badge badge-success">
                                                <?php echo $category['published_count']; ?> published
                                            </span>
                                            <?php else: ?>
                                            <span class="badge">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($category['pending_count'] > 0): ?>
                                            <span class="badge badge-warning">
                                                <?php echo $category['pending_count']; ?> pending
                                            </span>
                                            <?php else: ?>
                                            <span class="badge">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="date-text">
                                                <?php echo date('d M Y', strtotime($category['created_at'])); ?>
                                            </div>
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
                                                <button type="button" class="btn-action btn-delete"
                                                    title="Hapus Kategori"
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
                            <div class="form-text text-muted">
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
                            <div class="form-text text-muted">
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
                            <strong>Peringatan!</strong> Kategori ini masih digunakan oleh beberapa artikel dan tidak
                            dapat dihapus!
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
                        <i class="fas fa-newspaper me-2"></i>Artikel dalam Kategori: <span
                            id="view_category_name"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="articles_loading" class="text-center">
                        <div class="loading"></div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Enhanced JavaScript functionality

    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#categoriesTable tbody tr');

        rows.forEach(row => {
            const categoryName = row.getAttribute('data-category-name');
            if (categoryName && categoryName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        updateEmptyState();
    });

    // Filter functionality
    document.getElementById('filterSelect').addEventListener('change', function() {
        const filterValue = this.value;
        const rows = document.querySelectorAll('#categoriesTable tbody tr');

        rows.forEach(row => {
            const articleCount = parseInt(row.getAttribute('data-article-count'));
            let show = true;

            if (filterValue === 'used' && articleCount === 0) {
                show = false;
            } else if (filterValue === 'unused' && articleCount > 0) {
                show = false;
            }

            row.style.display = show ? '' : 'none';
        });

        updateEmptyState();
    });

    function updateEmptyState() {
        const visibleRows = document.querySelectorAll(
            '#categoriesTable tbody tr[style=""], #categoriesTable tbody tr:not([style])');
        const emptyRow = document.querySelector('#categoriesTable tbody tr td[colspan="7"]');

        if (visibleRows.length === 0 && !emptyRow) {
            const tbody = document.querySelector('#categoriesTable tbody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        <i class="fas fa-search fa-2x mb-2"></i>
                        <br>Tidak ada kategori yang sesuai dengan pencarian
                    </td>
                </tr>
            `;
        }
    }

    function editCategory(category) {
        document.getElementById('edit_category_id').value = category.category_id;
        document.getElementById('edit_name').value = category.name;

        var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        editModal.show();
    }

    function deleteCategory(categoryId, categoryName, articleCount) {
        document.getElementById('delete_category_id').value = categoryId;
        document.getElementById('delete_category_name').textContent = categoryName;

        const deleteWarning = document.getElementById('delete_warning');
        const deleteSafe = document.getElementById('delete_safe');
        const deleteBtn = document.getElementById('delete_confirm_btn');

        if (articleCount > 0) {
            deleteWarning.style.display = 'block';
            deleteSafe.style.display = 'none';
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-ban me-1"></i>Tidak dapat dihapus';
            deleteBtn.className = 'btn btn-secondary';
        } else {
            deleteWarning.style.display = 'none';
            deleteSafe.style.display = 'block';
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i>Hapus Kategori';
            deleteBtn.className = 'btn btn-danger';
        }

        var deleteModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
        deleteModal.show();
    }

    function viewCategoryArticles(categoryId, categoryName) {
        document.getElementById('view_category_name').textContent = categoryName;
        document.getElementById('view_all_articles_btn').href = `../kategori.php?id=${categoryId}`;

        const modal = new bootstrap.Modal(document.getElementById('viewArticlesModal'));
        modal.show();

        // Show loading
        document.getElementById('articles_loading').style.display = 'block';
        document.getElementById('articles_content').innerHTML = '';

        // Fetch articles (simulated - you can implement actual AJAX call)
        setTimeout(() => {
            fetchCategoryArticles(categoryId);
        }, 1000);
    }

    function fetchCategoryArticles(categoryId) {
        // This is a placeholder - implement actual AJAX call to fetch articles
        const mockArticles = [{
                id: 1,
                title: 'Artikel Sample 1',
                status: 'published',
                date: '2025-01-20'
            },
            {
                id: 2,
                title: 'Artikel Sample 2',
                status: 'pending',
                date: '2025-01-19'
            },
            {
                id: 3,
                title: 'Artikel Sample 3',
                status: 'published',
                date: '2025-01-18'
            }
        ];

        document.getElementById('articles_loading').style.display = 'none';

        let articlesHtml = '';
        if (mockArticles.length > 0) {
            articlesHtml = '<div class="list-group">';
            mockArticles.forEach(article => {
                const statusBadge = article.status === 'published' ?
                    '<span class="badge badge-success">Published</span>' :
                    '<span class="badge badge-warning">Pending</span>';

                articlesHtml += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${article.title}</h6>
                            <small class="text-muted">${article.date}</small>
                        </div>
                        <div>
                            ${statusBadge}
                            <a href="../artikel.php?id=${article.id}" class="btn btn-sm btn-outline-primary ms-2" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                `;
            });
            articlesHtml += '</div>';
        } else {
            articlesHtml = `
                <div class="text-center text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada artikel dalam kategori ini</p>
                </div>
            `;
        }

        document.getElementById('articles_content').innerHTML = articlesHtml;
    }

    // Form validation
    document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
        const nameInput = document.getElementById('name');
        const name = nameInput.value.trim();

        if (name.length < 2) {
            e.preventDefault();
            alert('Nama kategori harus minimal 2 karakter');
            nameInput.focus();
            return false;
        }

        if (name.length > 50) {
            e.preventDefault();
            alert('Nama kategori maksimal 50 karakter');
            nameInput.focus();
            return false;
        }
    });

    document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
        const nameInput = document.getElementById('edit_name');
        const name = nameInput.value.trim();

        if (name.length < 2) {
            e.preventDefault();
            alert('Nama kategori harus minimal 2 karakter');
            nameInput.focus();
            return false;
        }

        if (name.length > 50) {
            e.preventDefault();
            alert('Nama kategori maksimal 50 karakter');
            nameInput.focus();
            return false;
        }
    });

    // Auto-hide alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
                const bsAlert = new bootstrap.Alert(alert);
                setTimeout(() => bsAlert.close(), 5000);
            }
        });
    }, 100);

    // Tooltip initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
</body>

</html>