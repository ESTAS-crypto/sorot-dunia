<?php

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database dan settings manager - PERBAIKAN UTAMA
require_once '../config/config.php';

// PERBAIKAN: Pastikan settingsManager tersedia sebelum digunakan
if (!isset($settingsManager) || !$settingsManager) {
    try {
        $settingsManager = SettingsManager::getInstance($koneksi);
    } catch (Exception $e) {
        error_log("Error initializing SettingsManager: " . $e->getMessage());
        die("Error loading settings. Please contact administrator.");
    }
}

// Cek autentikasi
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../login.php");
    exit();
}

// Cek role admin
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: ../../login.php?error=access_denied");
    exit();
}

// CSRF Token Generation
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$csrf_token = generateCSRFToken();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Token keamanan tidak valid!";
        $message_type = "danger";
    } else {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_general':
                    $site_name = sanitize_input($_POST['site_name'] ?? '');
                    $site_description = sanitize_input($_POST['site_description'] ?? '');
                    $site_keywords = sanitize_input($_POST['site_keywords'] ?? '');
                    $admin_email = sanitize_input($_POST['admin_email'] ?? '');
                    
                    if (empty($site_name) || empty($admin_email)) {
                        $message = "Nama website dan email admin harus diisi!";
                        $message_type = "danger";
                    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                        $message = "Format email admin tidak valid!";
                        $message_type = "danger";
                    } else {
                        // Update menggunakan SettingsManager
                        $settingsManager->set('site_name', $site_name);
                        $settingsManager->set('site_description', $site_description);
                        $settingsManager->set('site_keywords', $site_keywords);
                        $settingsManager->set('admin_email', $admin_email);
                        
                        $message = "Pengaturan umum berhasil diperbarui!";
                        $message_type = "success";
                    }
                    break;
                    
                case 'update_display':
                    $articles_per_page = (int)sanitize_input($_POST['articles_per_page'] ?? '10');
                    $show_author = isset($_POST['show_author']) ? '1' : '0';
                    $show_date = isset($_POST['show_date']) ? '1' : '0';
                    $show_category = isset($_POST['show_category']) ? '1' : '0';
                    $enable_comments = isset($_POST['enable_comments']) ? '1' : '0';
                    
                    if ($articles_per_page < 1 || $articles_per_page > 50) {
                        $message = "Jumlah artikel per halaman harus antara 1-50!";
                        $message_type = "danger";
                    } else {
                        // Update menggunakan SettingsManager
                        $settingsManager->set('articles_per_page', $articles_per_page);
                        $settingsManager->set('show_author', $show_author);
                        $settingsManager->set('show_date', $show_date);
                        $settingsManager->set('show_category', $show_category);
                        $settingsManager->set('enable_comments', $enable_comments);
                        
                        $message = "Pengaturan tampilan berhasil diperbarui!";
                        $message_type = "success";
                    }
                    break;
                    
                case 'update_maintenance':
                    $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
                    $maintenance_message = sanitize_input($_POST['maintenance_message'] ?? '');
                    
                    if (empty($maintenance_message)) {
                        $maintenance_message = 'Website sedang dalam maintenance. Silakan kembali lagi nanti.';
                    }
                    
                    // Update menggunakan SettingsManager
                    $settingsManager->set('maintenance_mode', $maintenance_mode);
                    $settingsManager->set('maintenance_message', $maintenance_message);
                    
                    $message = "Pengaturan maintenance berhasil diperbarui!";
                    $message_type = "success";
                    break;
            }
        }
    }
}

// PERBAIKAN BARIS 106: Get current settings dengan proper error handling
$current_settings = [];
try {
    if ($settingsManager && method_exists($settingsManager, 'getAllSettings')) {
        $current_settings = $settingsManager->getAllSettings();
    }
} catch (Exception $e) {
    error_log("Error getting settings: " . $e->getMessage());
    // Fallback settings jika error
    $current_settings = [
        'site_name' => 'Sorot Dunia',
        'site_description' => 'Portal berita terpercaya',
        'site_keywords' => 'berita, news, artikel',
        'admin_email' => 'eatharasya@gmail.com',
        'articles_per_page' => '10',
        'show_author' => '1',
        'show_date' => '1',
        'show_category' => '1',
        'enable_comments' => '1',
        'maintenance_mode' => '0',
        'maintenance_message' => 'Website sedang dalam maintenance. Silakan kembali lagi nanti.'
    ];
}

// Get statistics
$total_articles = 0;
$total_users = 0;
$total_categories = 0;

try {
    if ($koneksi) {
        $total_articles_query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
        $total_articles_result = mysqli_query($koneksi, $total_articles_query);
        if ($total_articles_result) {
            $total_articles = mysqli_fetch_assoc($total_articles_result)['total'] ?? 0;
        }

        $total_users_query = "SELECT COUNT(*) as total FROM users";
        $total_users_result = mysqli_query($koneksi, $total_users_query);
        if ($total_users_result) {
            $total_users = mysqli_fetch_assoc($total_users_result)['total'] ?? 0;
        }

        $total_categories_query = "SELECT COUNT(*) as total FROM categories";
        $total_categories_result = mysqli_query($koneksi, $total_categories_query);
        if ($total_categories_result) {
            $total_categories = mysqli_fetch_assoc($total_categories_result)['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error getting statistics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Website - <?php echo htmlspecialchars($current_settings['site_name'] ?? 'Sorot Dunia'); ?></title>
    <link rel="icon" href="../../img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Dark theme styling yang diperbaiki */
    body {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: #ffffff;
        min-height: 100vh;
    }

    .navbar {
        background: rgba(45, 45, 45, 0.95) !important;
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .card {
        background: rgba(45, 45, 45, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        margin-bottom: 25px;
        backdrop-filter: blur(10px);
    }

    .card-header {
        background: rgba(64, 64, 64, 0.8);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-radius: 15px 15px 0 0 !important;
    }

    .card-body {
        background: rgba(45, 45, 45, 0.9);
        color: #ffffff;
    }

    .form-control,
    .form-select {
        background: rgba(64, 64, 64, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff;
        border-radius: 10px;
    }

    .form-control:focus,
    .form-select:focus {
        background: rgba(64, 64, 64, 0.9);
        border-color: #6c757d;
        color: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    }

    .form-label {
        color: #ffffff;
        font-weight: 500;
    }

    .form-check-input {
        background: rgba(64, 64, 64, 0.8);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .form-check-input:checked {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .form-check-label {
        color: #ffffff;
    }

    .btn-primary {
        background: linear-gradient(45deg, #6c757d, #5a6268);
        border: none;
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: linear-gradient(45deg, #5a6268, #495057);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .btn-secondary {
        background: rgba(73, 80, 87, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
    }

    .btn-secondary:hover {
        background: rgba(52, 58, 64, 0.9);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .btn-warning {
        background: linear-gradient(45deg, #ffc107, #e0a800);
        border: none;
        color: #000;
        border-radius: 10px;
        font-weight: 500;
    }

    .btn-warning:hover {
        background: linear-gradient(45deg, #e0a800, #d39e00);
        color: #000;
        transform: translateY(-2px);
    }

    .stats-card {
        background: linear-gradient(135deg, rgba(64, 64, 64, 0.8), rgba(85, 85, 85, 0.8));
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }

    .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        background: linear-gradient(45deg, #ffc107, #ffeb3b);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stats-label {
        color: #adb5bd;
        font-size: 1rem;
        font-weight: 500;
    }

    .maintenance-warning {
        background: linear-gradient(45deg, rgba(220, 53, 69, 0.2), rgba(185, 27, 47, 0.2));
        border: 1px solid rgba(220, 53, 69, 0.5);
        color: #ffffff;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        backdrop-filter: blur(10px);
    }

    .nav-tabs {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .nav-tabs .nav-link {
        background: rgba(45, 45, 45, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #adb5bd;
        margin-right: 10px;
        border-radius: 10px 10px 0 0;
        padding: 12px 20px;
    }

    .nav-tabs .nav-link:hover {
        background: rgba(64, 64, 64, 0.9);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.2);
    }

    .nav-tabs .nav-link.active {
        background: rgba(64, 64, 64, 0.9);
        border-color: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    .alert-success {
        background: linear-gradient(45deg, rgba(25, 135, 84, 0.2), rgba(20, 108, 67, 0.2));
        border: 1px solid rgba(25, 135, 84, 0.5);
        color: #ffffff;
        border-radius: 10px;
    }

    .alert-danger {
        background: linear-gradient(45deg, rgba(220, 53, 69, 0.2), rgba(185, 27, 47, 0.2));
        border: 1px solid rgba(220, 53, 69, 0.5);
        color: #ffffff;
        border-radius: 10px;
    }

    .container-fluid {
        padding: 30px;
    }

    .page-header {
        background: rgba(45, 45, 45, 0.9);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 15px;
        }

        .stats-card {
            margin-bottom: 15px;
            padding: 20px;
        }

        .stats-number {
            font-size: 2rem;
        }

        .card {
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            margin-right: 5px;
            padding: 10px 15px;
            font-size: 0.9rem;
        }
    }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-newspaper me-2"></i>
                <?php echo htmlspecialchars($current_settings['site_name'] ?? 'Sorot Dunia'); ?> Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../../index.php" target="_blank">
                    <i class="fas fa-external-link-alt me-1"></i> Lihat Website
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1">
                        <i class="fas fa-cog me-2"></i> Pengaturan Website
                    </h1>
                    <p class="mb-0 text-muted">Kelola pengaturan dan konfigurasi website Anda</p>
                </div>
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i
                class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Maintenance Warning -->
        <?php if (($current_settings['maintenance_mode'] ?? '0') == '1'): ?>
        <div class="maintenance-warning">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div class="flex-grow-1">
                    <h5 class="mb-1">Mode Maintenance Aktif!</h5>
                    <p class="mb-0">Website sedang dalam mode maintenance dan tidak dapat diakses oleh pengunjung umum.
                    </p>
                </div>
                <a href="../" class="btn btn-warning" target="_blank">
                    <i class="fas fa-eye me-2"></i> Preview Website
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_articles; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-newspaper me-1"></i> Total Artikel
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_users; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-users me-1"></i> Total Pengguna
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_categories; ?></div>
                    <div class="stats-label">
                        <i class="fas fa-tags me-1"></i> Total Kategori
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number">
                        <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'ON' : 'OFF'; ?>
                    </div>
                    <div class="stats-label">
                        <i class="fas fa-tools me-1"></i> Mode Maintenance
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tabs -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-sliders-h me-2"></i> Pengaturan Website
                </h4>
            </div>
            <div class="card-body">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general"
                            type="button" role="tab" aria-controls="general" aria-selected="true">
                            <i class="fas fa-globe me-2"></i> Pengaturan Umum
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="display-tab" data-bs-toggle="tab" data-bs-target="#display"
                            type="button" role="tab" aria-controls="display" aria-selected="false">
                            <i class="fas fa-desktop me-2"></i> Tampilan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance"
                            type="button" role="tab" aria-controls="maintenance" aria-selected="false">
                            <i class="fas fa-tools me-2"></i> Maintenance
                        </button>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content mt-4" id="settingsTabContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_general">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">
                                            <i class="fas fa-globe me-2"></i> Nama Website *
                                        </label>
                                        <input type="text" class="form-control" id="site_name" name="site_name"
                                            value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>"
                                            required maxlength="100" placeholder="Masukkan nama website">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="admin_email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i> Email Admin *
                                        </label>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email"
                                            value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>"
                                            required maxlength="100" placeholder="eatharasya@gmail.com">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="site_description" class="form-label">
                                    <i class="fas fa-info-circle me-2"></i> Deskripsi Website
                                </label>
                                <textarea class="form-control" id="site_description" name="site_description" rows="3"
                                    maxlength="500"
                                    placeholder="Deskripsi singkat tentang website Anda"><?php echo htmlspecialchars($current_settings['site_description'] ?? ''); ?></textarea>
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    Deskripsi ini akan muncul di meta description untuk SEO
                                </small>
                            </div>

                            <div class="mb-4">
                                <label for="site_keywords" class="form-label">
                                    <i class="fas fa-tags me-2"></i> Keywords SEO
                                </label>
                                <input type="text" class="form-control" id="site_keywords" name="site_keywords"
                                    value="<?php echo htmlspecialchars($current_settings['site_keywords'] ?? ''); ?>"
                                    maxlength="500" placeholder="berita, news, artikel, indonesia">
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    Kata kunci untuk SEO, pisahkan dengan koma
                                </small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Simpan Pengaturan Umum
                            </button>
                        </form>
                    </div>

                    <!-- Display Settings -->
                    <div class="tab-pane fade" id="display" role="tabpanel" aria-labelledby="display-tab">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_display">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="articles_per_page" class="form-label">
                                            <i class="fas fa-list me-2"></i> Artikel per Halaman
                                        </label>
                                        <select class="form-select" id="articles_per_page" name="articles_per_page">
                                            <option value="5"
                                                <?php echo ($current_settings['articles_per_page'] ?? '10') == '5' ? 'selected' : ''; ?>>
                                                5 Artikel</option>
                                            <option value="10"
                                                <?php echo ($current_settings['articles_per_page'] ?? '10') == '10' ? 'selected' : ''; ?>>
                                                10 Artikel</option>
                                            <option value="15"
                                                <?php echo ($current_settings['articles_per_page'] ?? '10') == '15' ? 'selected' : ''; ?>>
                                                15 Artikel</option>
                                            <option value="20"
                                                <?php echo ($current_settings['articles_per_page'] ?? '10') == '20' ? 'selected' : ''; ?>>
                                                20 Artikel</option>
                                            <option value="25"
                                                <?php echo ($current_settings['articles_per_page'] ?? '10') == '25' ? 'selected' : ''; ?>>
                                                25 Artikel</option>
                                        </select>
                                        <small class="text-muted">Jumlah artikel yang ditampilkan per halaman di
                                            homepage</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-eye me-2"></i> Opsi Tampilan Artikel
                                    </label>
                                    <div class="mb-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="show_author"
                                                name="show_author"
                                                <?php echo ($current_settings['show_author'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="show_author">
                                                <i class="fas fa-user me-1"></i> Tampilkan Nama Penulis
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="show_date"
                                                name="show_date"
                                                <?php echo ($current_settings['show_date'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="show_date">
                                                <i class="fas fa-calendar me-1"></i> Tampilkan Tanggal Publikasi
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="show_category"
                                                name="show_category"
                                                <?php echo ($current_settings['show_category'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="show_category">
                                                <i class="fas fa-folder me-1"></i> Tampilkan Kategori Artikel
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_comments"
                                                name="enable_comments"
                                                <?php echo ($current_settings['enable_comments'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_comments">
                                                <i class="fas fa-comments me-1"></i> Aktifkan Sistem Komentar
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Simpan Pengaturan Tampilan
                            </button>
                        </form>
                    </div>

                    <!-- Maintenance Settings -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
                        <form method="POST" action="" id="maintenanceForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_maintenance">

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode"
                                        name="maintenance_mode" style="transform: scale(1.5);"
                                        <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label ms-2" for="maintenance_mode">
                                        <strong><i class="fas fa-tools me-2"></i> Aktifkan Mode Maintenance</strong>
                                    </label>
                                </div>
                                <small class="text-muted ms-4">
                                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                    Jika diaktifkan, pengunjung tidak akan dapat mengakses website dan akan melihat
                                    pesan maintenance. Admin tetap dapat mengakses website.
                                </small>
                            </div>

                            <div class="mb-4">
                                <label for="maintenance_message" class="form-label">
                                    <i class="fas fa-comment-alt me-2"></i> Pesan Maintenance
                                </label>
                                <textarea class="form-control" id="maintenance_message" name="maintenance_message"
                                    rows="4" maxlength="1000"
                                    placeholder="Pesan yang akan ditampilkan saat mode maintenance aktif"><?php echo htmlspecialchars($current_settings['maintenance_message'] ?? ''); ?></textarea>
                                <small class="text-muted">Pesan yang akan ditampilkan kepada pengunjung saat mode
                                    maintenance aktif.</small>
                            </div>

                            <div class="alert alert-warning">
                                <h6><i class="fas fa-info-circle me-2"></i> Informasi Mode Maintenance:</h6>
                                <ul class="mb-0 mt-2">
                                    <li><i class="fas fa-check text-success me-1"></i> Admin dapat tetap mengakses
                                        website secara normal</li>
                                    <li><i class="fas fa-check text-success me-1"></i> Gunakan URL dengan parameter
                                        <code>?bypass=evan123</code> untuk akses sementara
                                    </li>
                                    <li><i class="fas fa-check text-success me-1"></i> Pengunjung akan dialihkan ke
                                        halaman maintenance otomatis</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="fas fa-tools me-2"></i> Simpan Pengaturan Maintenance
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-server me-2"></i> Informasi Sistem
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <p><strong><i class="fas fa-database me-1"></i> Database Host:</strong></p>
                                <p class="text-muted">64.235.41.175</p>
                            </div>
                            <div class="col-6">
                                <p><strong><i class="fas fa-code me-1"></i> PHP Version:</strong></p>
                                <p class="text-muted"><?php echo phpversion(); ?></p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <p><strong><i class="fas fa-plug me-1"></i> Status Koneksi:</strong></p>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i> Terhubung
                                </span>
                            </div>
                            <div class="col-6">
                                <p><strong><i class="fas fa-cogs me-1"></i> Settings Manager:</strong></p>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i> Aktif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i> Ringkasan Pengaturan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong><i class="fas fa-globe me-2"></i> Nama Website:</strong>
                            <span
                                class="text-muted"><?php echo htmlspecialchars($current_settings['site_name'] ?? 'Belum diset'); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong><i class="fas fa-list me-2"></i> Artikel per Halaman:</strong>
                            <span
                                class="badge bg-primary"><?php echo ($current_settings['articles_per_page'] ?? '10'); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong><i class="fas fa-tools me-2"></i> Mode Maintenance:</strong>
                            <span
                                class="badge bg-<?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'danger' : 'success'; ?>">
                                <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        </div>
                        <div>
                            <strong><i class="fas fa-clock me-2"></i> Terakhir Diperbarui:</strong>
                            <span class="text-muted"><?php echo date('d M Y H:i'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-save indication
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML =
                    '<i class="fas fa-spinner fa-spin me-2"></i> Menyimpan...';
                submitBtn.disabled = true;

                // Re-enable after 3 seconds (in case form doesn't redirect)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        });
    });

    // Maintenance mode warning
    document.getElementById('maintenance_mode').addEventListener('change', function() {
        if (this.checked) {
            if (!confirm(
                    'Apakah Anda yakin ingin mengaktifkan mode maintenance?\n\n' +
                    'Pengunjung tidak akan dapat mengakses website, tetapi admin tetap dapat mengakses.\n' +
                    'Gunakan parameter ?bypass=evan123 untuk akses sementara.'
                )) {
                this.checked = false;
            }
        }
    });

    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    field.style.borderColor = '#dc3545';
                } else {
                    field.classList.remove('is-invalid');
                    field.style.borderColor = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Mohon lengkapi semua field yang wajib diisi!');
            }
        });
    });

    // Auto-dismiss success alerts
    setTimeout(function() {
        const successAlerts = document.querySelectorAll('.alert-success');
        successAlerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Character counter for textareas
    document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        const counter = document.createElement('small');
        counter.className = 'text-muted float-end';
        textarea.parentNode.appendChild(counter);

        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${textarea.value.length}/${maxLength} karakter`;
            counter.style.color = remaining < 50 ? '#dc3545' : '#6c757d';
        }

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });

    // Smooth tab transitions
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            // Add animation class
            const targetPane = document.querySelector(e.target.getAttribute('data-bs-target'));
            if (targetPane) {
                targetPane.style.opacity = '0';
                setTimeout(() => {
                    targetPane.style.transition = 'opacity 0.3s ease';
                    targetPane.style.opacity = '1';
                }, 10);
            }
        });
    });

    // Settings preview update
    function updatePreview() {
        const siteName = document.getElementById('site_name')?.value || 'Website';
        const previewElements = document.querySelectorAll('.site-name-preview');
        previewElements.forEach(el => {
            el.textContent = siteName;
        });
    }

    document.getElementById('site_name')?.addEventListener('input', updatePreview);

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S to save current form
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const activeTab = document.querySelector('.tab-pane.active');
            if (activeTab) {
                const form = activeTab.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        }

        // Ctrl+1, Ctrl+2, Ctrl+3 to switch tabs
        if (e.ctrlKey && ['1', '2', '3'].includes(e.key)) {
            e.preventDefault();
            const tabIndex = parseInt(e.key) - 1;
            const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
            if (tabs[tabIndex]) {
                const tab = new bootstrap.Tab(tabs[tabIndex]);
                tab.show();
            }
        }
    });

    // Auto-save draft functionality
    function saveDraft() {
        const formData = new FormData();
        document.querySelectorAll('input, textarea, select').forEach(field => {
            if (field.name && field.type !== 'hidden') {
                if (field.type === 'checkbox') {
                    formData.append(field.name, field.checked ? '1' : '0');
                } else {
                    formData.append(field.name, field.value);
                }
            }
        });

        localStorage.setItem('settings_draft', JSON.stringify(Object.fromEntries(formData)));
    }

    function loadDraft() {
        const draft = localStorage.getItem('settings_draft');
        if (draft) {
            try {
                const data = JSON.parse(draft);
                Object.keys(data).forEach(key => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = data[key] === '1';
                        } else {
                            field.value = data[key];
                        }
                    }
                });
            } catch (e) {
                console.log('Error loading draft:', e);
            }
        }
    }

    // Auto-save every 30 seconds
    setInterval(saveDraft, 30000);

    // Clear draft on successful save
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            localStorage.removeItem('settings_draft');
        });
    });

    // Tooltip initialization
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Add tooltips to info icons
    document.querySelectorAll('.fas.fa-lightbulb').forEach(icon => {
        icon.setAttribute('data-bs-toggle', 'tooltip');
        icon.setAttribute('title', 'Tips untuk optimasi SEO');
        new bootstrap.Tooltip(icon);
    });
    </script>
</body>

</html>