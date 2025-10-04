<?php
$baseUrl = 'https://inievan.my.id/project';

// Include database configuration and auth check
require_once 'config/config.php';
require_once 'config/auth_check.php';

// Initialize visitor tracking for public pages only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') === false) {
        initVisitorTracking($koneksi);
    }
}

// Fetch user data if logged in
$user = [];
$is_logged_in = false;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    $user_id = sanitize_input($_SESSION['user_id']);
    $query = "SELECT * FROM users WHERE id = '$user_id' LIMIT 1";
    $result = mysqli_query($koneksi, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $is_logged_in = true;
    }
}

// Menentukan halaman aktif untuk navigasi
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_mapping = [
    'index' => 'Home',
    'berita' => 'Berita',
    'artikel' => 'Berita',
    'search' => 'Search'
];

$can_access_features = true;
$siteInfo = getSiteInfo();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteInfo['name']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($siteInfo['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($siteInfo['keywords']); ?>">
    <meta name="robots" content="index, follow">
    <link rel="icon" href="<?php echo $baseUrl; ?>/img/icon.webp" type="image/webp" />
    <link rel="canonical" href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/style/berita.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/style/modal.css">
    
    <style>
    /* Reset and base styles */
    * {
        box-sizing: border-box;
    }

    a {
        text-decoration: none;
        color: #333;
    }

    body {
        overflow-y: auto;
        scroll-behavior: smooth;
        background-color: #f8f9fa;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    
    body.logged-in {
        /* Styles for logged in users */
    }

    /* Navigation */
    .top-nav {
        background-color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 0;
        position: relative;
        z-index: 1000;
    }

    .navbar-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .brand-logo {
        display: inline-flex;
        align-items: center;
        color: black;
        text-decoration: none;
        font-weight: bold;
    }

    .brand-logo img {
        height: 40px;
        margin-right: 10px;
    }

    /* Search Form */
    .search-form {
        max-width: 400px;
        flex-grow: 1;
        margin: 0 10px;
    }

    .search-form .form-control {
        border-radius: 20px 0 0 20px;
        border-right: none;
        padding: 8px 15px;
        border-color: #ddd;
    }

    .search-form .btn {
        border-radius: 0 20px 20px 0;
        border-color: #ddd;
        background-color: #f8f9fa;
        color: #666;
        border-left: none;
        padding: 8px 15px;
    }

    .search-form .btn:hover {
        background-color: #e9ecef;
        border-color: #ddd;
        color: #333;
    }

    /* Auth Buttons */
    .auth-buttons .btn {
        margin: 0 5px;
        padding: 8px 20px;
        font-size: 14px;
        color: #333;
        font-weight: 500;
    }

    /* User Dropdown */
    .user-dropdown {
        position: relative;
    }

    .user-dropdown .dropdown {
        position: relative;
    }

    .user-dropdown .dropdown-toggle {
        border: 1px solid #6c757d !important;
        background: #6c757d !important;
        cursor: pointer !important;
        color: white !important;
        padding: 8px 20px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        outline: none !important;
        box-shadow: none !important;
        border-radius: 0.375rem !important;
        display: inline-flex !important;
        align-items: center !important;
        text-decoration: none !important;
        position: relative !important;
    }

    .user-dropdown .dropdown-toggle:hover,
    .user-dropdown .dropdown-toggle:focus,
    .user-dropdown .dropdown-toggle:active,
    .user-dropdown .dropdown-toggle.show {
        background-color: #5a6268 !important;
        border-color: #545b62 !important;
        color: white !important;
        outline: none !important;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25) !important;
    }

    .user-dropdown .dropdown-toggle::after {
        margin-left: 0.5em !important;
        border-top: 0.3em solid !important;
        border-right: 0.3em solid transparent !important;
        border-left: 0.3em solid transparent !important;
        border-bottom: 0 !important;
    }

    .user-dropdown .dropdown-menu {
        position: absolute !important;
        top: 100% !important;
        left: auto !important;
        right: 0 !important;
        z-index: 1000 !important;
        display: none !important;
        min-width: 200px !important;
        padding: 0.5rem 0 !important;
        margin: 0.125rem 0 0 !important;
        font-size: 0.875rem !important;
        color: #212529 !important;
        text-align: left !important;
        background-color: #fff !important;
        background-clip: padding-box !important;
        border: 1px solid rgba(0, 0, 0, 0.15) !important;
        border-radius: 0.5rem !important;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175) !important;
    }

    .user-dropdown .dropdown-menu.show {
        display: block !important;
    }

    .user-dropdown .dropdown-item {
        display: flex !important;
        width: 100% !important;
        padding: 0.5rem 1rem !important;
        clear: both !important;
        font-weight: 400 !important;
        color: #212529 !important;
        text-align: inherit !important;
        text-decoration: none !important;
        white-space: nowrap !important;
        background-color: transparent !important;
        border: 0 !important;
        align-items: center !important;
        font-size: 14px !important;
    }

    .user-dropdown .dropdown-item:hover,
    .user-dropdown .dropdown-item:focus {
        color: #1e2125 !important;
        background-color: #e9ecef !important;
    }

    .user-dropdown .dropdown-item i {
        width: 16px !important;
        text-align: center !important;
        margin-right: 8px !important;
    }

    .user-dropdown .dropdown-divider {
        height: 0 !important;
        margin: 0.5rem 0 !important;
        overflow: hidden !important;
        border-top: 1px solid rgba(0, 0, 0, 0.15) !important;
    }

    /* Mobile Toggle Button */
    .navbar-toggler {
        margin-left: auto;
        border: 1px solid #dee2e6;
        padding: 4px 8px;
        background: transparent !important;
        border-radius: 0.375rem;
        outline: none !important;
        box-shadow: none !important;
    }

    .navbar-toggler:focus {
        text-decoration: none;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2833, 37, 41, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        display: inline-block;
        width: 1.5em;
        height: 1.5em;
        vertical-align: middle;
        background-repeat: no-repeat;
        background-position: center;
        background-size: 100%;
    }

    /* Category Navigation */
    .category-nav {
        padding: 10px 0;
    }

    .category-nav .nav-link {
        color: #fff !important;
        padding: 8px 15px;
        border-radius: 15px;
        margin: 0 2px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .category-nav .nav-link:hover,
    .category-nav .nav-link.active {
        background-color: #fff;
        color: #333 !important;
    }

    /* Password Strength Indicator */
    .password-strength-meter {
        margin-top: 8px;
        height: 4px;
        background-color: #e0e0e0;
        border-radius: 2px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
    }

    .password-strength-text {
        margin-top: 8px;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .strength-weak .password-strength-bar {
        width: 33.33%;
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    .strength-weak .password-strength-text {
        color: #ef4444;
    }

    .strength-medium .password-strength-bar {
        width: 66.66%;
        background: linear-gradient(90deg, #f59e0b, #d97706);
    }

    .strength-medium .password-strength-text {
        color: #f59e0b;
    }

    .strength-strong .password-strength-bar {
        width: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
    }

    .strength-strong .password-strength-text {
        color: #10b981;
    }

    .password-requirements {
        margin-top: 10px;
        padding: 12px;
        background-color: #f8f9fa;
        border-radius: 8px;
        border-left: 3px solid #667eea;
    }

    .password-requirements h6 {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #495057;
    }

    .password-requirements ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .password-requirements li {
        font-size: 12px;
        padding: 4px 0;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .password-requirements li i {
        font-size: 14px;
        width: 16px;
    }

    .password-requirements li.valid {
        color: #10b981;
    }

    .password-requirements li.valid i {
        color: #10b981;
    }

    .password-requirements li.invalid {
        color: #6c757d;
    }

    .password-requirements li.invalid i {
        color: #dc3545;
    }

    /* Password mismatch alert */
    .password-mismatch {
        color: #dc3545;
        font-size: 12px;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .password-match {
        color: #10b981;
        font-size: 12px;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Responsive Design */
    @media (max-width: 767px) {
        .navbar-content {
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .brand-logo {
            order: 1;
        }

        .navbar-toggler {
            order: 2;
            margin-left: 0;
        }

        .search-form {
            order: 3;
            width: 100%;
            margin: 10px 0;
        }

        .auth-buttons {
            order: 4;
            width: 100%;
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }

        .user-dropdown {
            width: 100%;
            text-align: center;
        }

        .navbar-collapse {
            background-color: #fff !important;
            border-top: 1px solid #dee2e6 !important;
            margin-top: 10px !important;
            padding: 10px 0 !important;
            border-radius: 0.375rem !important;
        }
    }

    @media (min-width: 992px) {
        .login-container {
            justify-content: flex-start;
            padding-left: 8%;
        }
    }
    </style>
</head>
<body <?php echo $is_logged_in ? 'class="logged-in" data-user-id="' . htmlspecialchars($user['id'] ?? '') . '"' : ''; ?>>
    
    <!-- Error Suppression -->
    <script>
    (function() {
        'use strict';
        const suppressedPatterns = [
            'Could not establish connection',
            'Extension context invalidated',
            'chrome-extension'
        ];
        function shouldSuppress(message) {
            return suppressedPatterns.some(pattern => 
                message && message.toLowerCase().includes(pattern.toLowerCase())
            );
        }
        window.addEventListener('error', function(event) {
            if (shouldSuppress(event.message || '')) {
                console.log('Extension error suppressed');
                event.preventDefault();
                return false;
            }
        }, true);
        window.addEventListener('unhandledrejection', function(event) {
            const message = (event.reason && event.reason.message) || '';
            if (shouldSuppress(message.toString())) {
                console.log('Extension promise rejection suppressed');
                event.preventDefault();
                return false;
            }
        }, true);
    })();
    </script>
    
    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg top-nav">
        <div class="container">
            <div class="navbar-content">
                <!-- Brand Logo -->
                <div>
                    <a class="brand-logo" href="<?php echo $baseUrl; ?>">
                        <img src="<?php echo $baseUrl; ?>/img/NewLogo.webp" alt="<?php echo htmlspecialchars($siteInfo['name']); ?>" loading="lazy">
                    </a>
                </div>

                <!-- Mobile Toggle Button -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Search Form -->
                <form class="search-form" role="search" method="GET" action="<?php echo $baseUrl; ?>/search.php">
                    <div class="input-group">
                        <input class="form-control" type="search" name="q" placeholder="Cari Berita..." aria-label="Search" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Desktop Auth Navigation -->
                <div class="auth-buttons d-none d-lg-flex align-items-center">
                    <?php if (!$is_logged_in): ?>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Masuk</button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Daftar</button>
                    <?php else: ?>
                    <div class="user-dropdown">
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="userDropdown"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($user['username'] ?? 'User'); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>/admin/index.php">
                                        <i class="fas fa-check-circle"></i> Admin Dashboard
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php if (isset($user['role']) && in_array($user['role'], ['admin', 'penulis'])): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>/Uplod_berita/uplod.php">
                                        <i class="fas fa-upload"></i> Upload Berita
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>/profile/profile.php">
                                        <i class="fas fa-user-circle"></i> Profile
                                    </a>
                                </li>
                                
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav w-100 d-lg-none">
                    <?php if (!$is_logged_in): ?>
                    <li class="nav-item d-flex gap-2 p-2">
                        <button type="button" class="btn btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#loginModal">MASUK</button>
                        <button type="button" class="btn btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#registerModal">DAFTAR</button>
                    </li>
                    <?php else: ?>
                    <li class="nav-item p-2">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-user me-2"></i>
                            <strong><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></strong>
                        </div>
                    </li>

                    <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                    <li class="nav-item p-2">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/admin/index.php">
                            <i class="fas fa-check-circle me-2"></i> Admin Dashboard
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (isset($user['role']) && in_array($user['role'], ['admin', 'penulis'])): ?>
                    <li class="nav-item p-2">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/Uplod_berita/uplod.php">
                            <i class="fas fa-upload me-2"></i> Upload Berita
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item p-2">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/profile/profile.php">
                            <i class="fas fa-user-circle me-2"></i> Profile
                        </a>
                    </li>
                    
                    <li class="nav-item p-2">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Category Navigation -->
    <div class="container-fluid text-white bg-secondary">
        <div class="d-flex flex-wrap justify-content-center">
            <nav class="category-nav">
                <ul class="navbar-nav d-flex flex-row justify-content-center">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index') ? 'active' : ''; ?> m-2" href="<?php echo $baseUrl; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (in_array($current_page, ['berita', 'artikel'])) ? 'active' : ''; ?> m-2" href="<?php echo $baseUrl; ?>/berita.php">Berita</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link m-2" href="#">Nasional</a>
                    </li>
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle m-2" href="#" role="button" data-bs-toggle="dropdown">Internasional</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">ASEAN</a></li>
                            <li><a class="dropdown-item" href="#">ASEAN Pasifik</a></li>
                            <li><a class="dropdown-item" href="#">Timur Tengah</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel"><i class="fas fa-sign-in-alt me-2"></i>Masuk ke Akun</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="loginAlert" class="alert d-none" role="alert"></div>
                    <form id="loginForm">
                        <div class="mb-3">
                            <label for="loginUsername" class="form-label">Username atau Email</label>
                            <input type="text" class="form-control" id="loginUsername" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label">Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="loginPassword" name="password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('loginPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Masuk
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="w-100 text-center">
                        <p class="mb-0">Belum punya akun? <a href="#" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#registerModal">Daftar disini</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalLabel"><i class="fas fa-user-plus me-2"></i>Daftar Akun Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="registerAlert" class="alert d-none" role="alert"></div>
                    <form id="registerForm">
                        <div class="mb-3">
                            <label for="registerUsername" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="registerUsername" name="username" required 
                                   minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+"
                                   title="Username hanya boleh mengandung huruf, angka, dan underscore">
                            <small class="text-muted">3-50 karakter, hanya huruf, angka, dan underscore</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="registerFullName" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="registerFullName" name="full_name" required
                                   minlength="3" maxlength="100">
                            <small class="text-muted">Minimal 3 karakter</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="registerEmail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="registerEmail" name="email" required>
                            <small class="text-muted">Email valid yang dapat digunakan untuk login</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="registerPassword" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="registerPassword" name="password" required
                                       minlength="8" maxlength="100">
                                <button type="button" class="password-toggle" onclick="togglePassword('registerPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <!-- Password Strength Indicator -->
                            <div class="password-strength-meter" id="strengthMeter">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="password-strength-text" id="strengthText"></div>
                            
                            <!-- Password Requirements -->
                            <div class="password-requirements">
                                <h6><i class="fas fa-shield-alt me-1"></i>Persyaratan Password:</h6>
                                <ul id="passwordRequirements">
                                    <li id="req-length" class="invalid">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Minimal 8 karakter</span>
                                    </li>
                                    <li id="req-uppercase" class="invalid">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Minimal 1 huruf besar (A-Z)</span>
                                    </li>
                                    <li id="req-lowercase" class="invalid">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Minimal 1 huruf kecil (a-z)</span>
                                    </li>
                                    <li id="req-number" class="invalid">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Minimal 1 angka (0-9)</span>
                                    </li>
                                    <li id="req-special" class="invalid">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Minimal 1 karakter spesial (!@#$%^&*)</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="registerConfirmPassword" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="registerConfirmPassword" name="confirm_password" required
                                       minlength="8" maxlength="100">
                                <button type="button" class="password-toggle" onclick="togglePassword('registerConfirmPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatchMessage"></div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="registerSubmitBtn" disabled>
                                <i class="fas fa-user-plus me-2"></i>Daftar
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="w-100 text-center">
                        <p class="mb-0">Sudah punya akun? <a href="#" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#loginModal">Masuk disini</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    <?php if ($is_logged_in): ?>
    window.userData = {
        id: <?php echo json_encode(intval($user['id'] ?? 0)); ?>,
        username: <?php echo json_encode($user['username'] ?? ''); ?>,
        role: <?php echo json_encode($user['role'] ?? ''); ?>,
        isLoggedIn: true
    };
    <?php else: ?>
    window.userData = { isLoggedIn: false };
    <?php endif; ?>

    // Password toggle function
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = event.currentTarget.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html>