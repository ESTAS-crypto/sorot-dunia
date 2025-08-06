<?php
$baseUrl = 'https://inievan.my.id/project';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'config/config.php';

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

// Define $can_access_features (assumed from original code context)
$can_access_features = true; // Ganti dengan logika aktual jika diperlukan
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorot Dunia</title>
    <link rel="icon" href="/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style/berita.css">
    <link rel="stylesheet" href="style/reactions.css">
    <style>
    a {
        text-decoration: none;
        color: #333;
    }

    body {
        overflow-y: auto;
        scroll-behavior: smooth;
        background-color: #f8f9fa;
    }

    /* Add logged-in class if user is logged in */
    body.logged-in {
        /* Additional styles for logged-in users if needed */
    }

    /* Navigation */
    .top-nav {
        background-color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 0;
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

    /* User Dropdown - PERBAIKAN UTAMA */
    .user-dropdown .dropdown-toggle {
        border: none !important;
        background: #6c757d !important;
        cursor: pointer !important;
        color: white !important;
        padding: 8px 20px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
    }

    .user-dropdown .dropdown-toggle:hover {
        background-color: #e9ecef !important;
        color: #333 !important;
    }

    .user-dropdown .dropdown-toggle:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        background-color: #6c757d !important;
        color: white !important;
    }

    .user-dropdown .dropdown-toggle:active,
    .user-dropdown .dropdown-toggle.show {
        background-color: #5a6268 !important;
        color: white !important;
        border-color: #545b62 !important;
    }

    .user-dropdown .dropdown-menu {
        min-width: 200px !important;
        border: 1px solid #dee2e6 !important;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    /* Mobile Toggle Button - PERBAIKAN */
    .navbar-toggler {
        margin-left: auto;
        border: 1px solid #dee2e6;
        padding: 4px 8px;
        background: transparent !important;
        border-radius: 0.375rem;
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

    /* Carousel */
    .carousel-item img {
        height: 400px;
        object-fit: cover;
        width: 100%;
        border-radius: 10px;
    }

    .carousel-caption {
        background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
        border-radius: 0 0 10px 10px;
        left: 0;
        right: 0;
        bottom: 0;
        padding: 20px;
    }

    .carousel-caption h5 {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 10px;
    }

    /* News Feed */
    .news-feed {
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .news-item {
        display: flex;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }

    .news-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .news-image {
        width: 120px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        margin-right: 15px;
    }

    .news-content h6 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
        line-height: 1.4;
    }

    .news-meta {
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }

    /* Sidebar */
    .sidebar {
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .sidebar h5 {
        color: #fffafa;
        margin-bottom: 15px;
        font-weight: bold;
    }

    .trending-item {
        display: flex;
        flex-wrap: wrap;
        margin-bottom: 15px;
        align-items: flex-start;
    }

    .trending-number {
        background-color: #333;
        color: #fff;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border-radius: 50%;
        margin-right: 10px;
        font-size: 14px;
    }

    .trending-content {
        font-size: 13px;
        font-weight: bold;
        flex: 1;
        word-break: break-word;
        overflow: hidden;
        text-overflow: ellipsis;

    }

    /* Footer */
    .footer {
        background-color: #333;
        color: #fff;
        padding: 40px 0 20px;
        margin-top: 40px;
    }

    .footer h5 {
        color: #fff;
        margin-bottom: 20px;
        font-weight: bold;
    }

    .footer p {
        color: #ccc;
        margin-bottom: 10px;
    }

    .footer ul {
        list-style: none;
        padding: 0;
    }

    .footer ul li {
        margin-bottom: 8px;
    }

    .footer ul li a {
        color: #ccc;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer ul li a:hover {
        color: #fff;
    }

    .social-icons {
        display: flex;
        gap: 15px;
        justify-content: center;
        align-items: center;
        margin: 20px 0;
    }

    .social-icons a {
        color: #fff;
        font-size: 24px;
        transition: all 0.3s ease;
        text-decoration: none;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.1);
    }

    .social-icons a:hover {
        background-color: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    .footer-bottom {
        border-top: 1px solid #555;
        padding-top: 20px;
        margin-top: 30px;
        text-align: center;
    }

    .footer-bottom p {
        margin: 5px 0;
        font-size: 14px;
    }

    /* Ban/Warning Notification Styles */
    .notification-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(5px);
    }

    .notification-box {
        background-color: #2d2d2d;
        border: 2px solid #dc3545;
        border-radius: 12px;
        padding: 30px;
        max-width: 500px;
        max-height: 80vh;
        overflow-y: auto;
        text-align: center;
        color: #ffffff;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        animation: slideInScale 0.4s ease-out;
    }

    .notification-box.warning {
        border-color: #ffc107;
    }

    .notification-icon {
        font-size: 48px;
        margin-bottom: 20px;
        animation: pulse 2s infinite;
    }

    .notification-icon.ban {
        color: #dc3545;
    }

    .notification-icon.warning {
        color: #ffc107;
    }

    .notification-title {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 15px;
    }

    .notification-message {
        font-size: 16px;
        line-height: 1.5;
        margin-bottom: 20px;
        text-align: left;
    }

    .notification-message hr {
        border-color: #555;
        margin: 15px 0;
    }

    .notification-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn-understand {
        background-color: #6c757d;
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-understand:hover {
        background-color: #5a6268;
        transform: translateY(-1px);
    }

    /* Animations */
    @keyframes slideInScale {
        from {
            opacity: 0;
            transform: scale(0.8) translateY(-20px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    /* Responsive Design */
    @media (max-width: 767px) {
        .navbar-content {
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .trending-number {
            width: 20px;
            height: 20px;
            font-size: 12px;
        }

        .sidebar {
            padding: 15px;
        }

        .trending-content {
            font-size: 12px;
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

        .carousel-item img {
            height: 200px;
        }

        .news-image {
            width: 80px;
            height: 60px;
        }

        /* Mobile navbar fix - PERBAIKAN UTAMA */
        .navbar-collapse {
            background-color: #fff !important;
            border-top: 1px solid #dee2e6 !important;
            margin-top: 10px !important;
            padding: 10px 0 !important;
            border-radius: 0.375rem !important;
        }

        .navbar-nav .dropdown-menu {
            position: static !important;
            float: none !important;
            width: 100% !important;
            margin-top: 0 !important;
            background-color: #f8f9fa !important;
            border: none !important;
            box-shadow: none !important;
        }

        .notification-box {
            margin: 20px;
            padding: 20px;
            max-width: none;
        }

        .notification-title {
            font-size: 20px;
        }

        .notification-message {
            font-size: 14px;
        }

        .notification-icon {
            font-size: 36px;
        }
    }

    @media (min-width: 768px) and (max-width: 991px) {
        .search-form {
            max-width: 300px;
        }
    }

    @media (min-width: 992px) and (max-width: 1199px) {
        .search-form {
            max-width: 350px;
        }
    }

    @media (min-width: 1200px) and (max-width: 1399px) {
        .search-form {
            max-width: 400px;
        }
    }

    @media (min-width: 1400px) {
        .search-form {
            max-width: 450px;
        }

        .carousel-item img {
            height: 450px;
        }
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .new-article-alert {
        backdrop-filter: blur(10px);
        background-color: rgba(255, 255, 255, 0.95) !important;
    }

    .new-article-alert:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease;
    }

    /* Responsive untuk mobile */
    @media (max-width: 768px) {
        .new-article-alert {
            top: 10px !important;
            right: 10px !important;
            left: 10px !important;
            min-width: auto !important;
            max-width: none !important;
        }
    }
    </style>
</head>

<body <?php echo $is_logged_in ? 'class="logged-in"' : ''; ?>>
    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg top-nav">
        <div class="container">
            <div class="navbar-content">
                <!-- Brand Logo -->
                <div>
                    <a class="brand-logo" href="<?php echo $baseUrl; ?>">
                        <img src="<?php echo $baseUrl; ?>/img/NewLogo.webp" alt="Sorot Dunia Logo">
                    </a>
                </div>

                <!-- Mobile Toggle Button -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
                    aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Search Form -->
                <form class="search-form" role="search">
                    <div class="input-group">
                        <input class="form-control" type="search" placeholder="Cari Berita Terbaru" aria-label="Search">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Desktop Auth Navigation -->
                <div class="auth-buttons d-none d-lg-flex align-items-center">
                    <?php if (!$is_logged_in): ?>
                    <a href="<?php echo $baseUrl; ?>/login.php" class="btn btn-outline-primary">Masuk</a>
                    <a href="register.php" class="btn btn-outline-primary">Daftar</a>
                    <?php else: ?>
                    <div class="user-dropdown">
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($user['username'] ?? 'User'); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if (isset($user['role']) && in_array($user['role'], ['admin'])): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>/admin/index.php">
                                        <i class="fas fa-check-circle me-2"></i> Admin Dashboard
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php if (isset($user['role']) && in_array($user['role'], ['admin', 'penulis'])): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo $baseUrl; ?>/Uplod_berita/uplod.php">
                                        <i class="fas fa-upload me-2"></i> Upload Berita
                                    </a>
                                </li>
                                <?php endif; ?>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>
                                        Logout</a></li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Collapsible Content -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Mobile Menu Navigation -->
                <ul class="navbar-nav w-100 d-lg-none">
                    <?php if (!$is_logged_in): ?>
                    <li class="nav-item d-flex gap-2 p-2">
                        <a href="<?php echo $baseUrl; ?>/login.php" class="btn btn-outline-primary flex-fill">MASUK</a>
                        <a href="register.php" class="btn btn-outline-primary flex-fill">DAFTAR</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item p-2">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-user me-2"></i>
                            <strong><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></strong>
                        </div>
                    </li>

                    <?php if (isset($user['role']) && strtolower($user['role']) === 'admin'): ?>
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
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                    </li>
                    <?php endif; ?>

                    <!-- Menu Items -->
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                            data-bs-toggle="dropdown">Nasional</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Politik</a></li>
                            <li><a class="dropdown-item" href="#">Hukum dan Kriminal</a></li>
                            <li><a class="dropdown-item" href="#">Info Politik</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                            data-bs-toggle="dropdown">Internasional</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">ASEAN</a></li>
                            <li><a class="dropdown-item" href="#">ASEAN Pasifik</a></li>
                            <li><a class="dropdown-item" href="#">Timur Tengah</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Ekonomi</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Keuangan</a></li>
                            <li><a class="dropdown-item" href="#">Energi</a></li>
                            <li><a class="dropdown-item" href="#">Bisnis</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                            data-bs-toggle="dropdown">Olahraga</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Sepak Bola</a></li>
                            <li><a class="dropdown-item" href="#">Motor GP</a></li>
                            <li><a class="dropdown-item" href="#">F1</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                            data-bs-toggle="dropdown">Otomotif</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Motor listrik</a></li>
                            <li><a class="dropdown-item" href="#">Mobil china</a></li>
                            <li><a class="dropdown-item" href="#">Mobil air</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Hiburan</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Film</a></li>
                            <li><a class="dropdown-item" href="#">Musik</a></li>
                            <li><a class="dropdown-item" href="#">Selebriti</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Gaya
                            Hidup</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Kesehatan</a></li>
                            <li><a class="dropdown-item" href="#">Fashion</a></li>
                            <li><a class="dropdown-item" href="#">Travel</a></li>
                        </ul>
                    </li>
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
                        <a class="nav-link active m-2" href="<?php echo $baseUrl; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link m-2" href="berita.php">Berita</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link m-2" href="#">Nasional</a>
                    </li>
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle m-2" href="#" role="button"
                            data-bs-toggle="dropdown">Internasional</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">ASEAN</a></li>
                            <li><a class="dropdown-item" href="#">ASEAN Pasifik</a></li>
                            <li><a class="dropdown-item" href="#">Timur Tengah</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle m-2" href="#" role="button"
                            data-bs-toggle="dropdown">Ekonomi</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Keuangan</a></li>
                            <li><a class="dropdown-item" href="#">Energi</a></li>
                            <li><a class="dropdown-item" href="#">Bisnis</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle m-2" href="#" role="button"
                            data-bs-toggle="dropdown">Olahraga</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Sepak Bola</a></li>
                            <li><a class="dropdown-item" href="#">Motor GP</a></li>
                            <li><a class="dropdown-item" href="#">F1</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle m-2" href="#" role="button"
                            data-bs-toggle="dropdown">Otomotif</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Motor listrik</a></li>
                            <li><a class="dropdown-item" href="#">Mobil china</a></li>
                            <li><a class="dropdown-item" href="#">Mobil air</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle m-2" href="#" role="button"
                            data-bs-toggle="dropdown">Hiburan</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Film</a></li>
                            <li><a class="dropdown-item" href="#">Musik</a></li>
                            <li><a class="dropdown-item" href="#">Selebriti</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle m-2" href="#" role="button" data-bs-toggle="dropdown">Gaya
                            Hidup</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Kesehatan</a></li>
                            <li><a class="dropdown-item" href="#">Fashion</a></li>
                            <li><a class="dropdown-item" href="#">Travel</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Bootstrap JS - PENTING: Letakkan sebelum closing body -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Ban Notification System - ONLY for logged in users -->
    <?php if ($is_logged_in): ?>
    <script src="js/notif-ban.js"></script>
    <?php endif; ?>

    <!-- PERBAIKAN JAVASCRIPT - FOKUS MOBILE NAVBAR CLOSE -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // Initialize semua dropdown dengan proper Bootstrap
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });

        // PERBAIKAN UTAMA MOBILE: Auto close navbar mobile
        var navLinks = document.querySelectorAll('.navbar-nav .nav-link:not(.dropdown-toggle)');
        var navbarCollapse = document.querySelector('#navbarContent');
        var navbarToggler = document.querySelector('.navbar-toggler');

        // Close mobile navbar ketika klik menu item
        navLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                    var bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse) ||
                        new bootstrap.Collapse(navbarCollapse);
                    bsCollapse.hide();
                }
            });
        });

        // PERBAIKAN UTAMA: Close mobile navbar ketika klik di luar
        document.addEventListener('click', function(event) {
            var isClickInsideNavbar = document.querySelector('.navbar').contains(event.target);

            if (!isClickInsideNavbar && navbarCollapse && navbarCollapse.classList.contains('show')) {
                console.log('Clicked outside navbar, closing menu');
                var bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse) || new bootstrap
                    .Collapse(navbarCollapse);
                bsCollapse.hide();
            }
        });

        // PERBAIKAN: Close mobile navbar ketika klik dropdown item di mobile
        document.querySelectorAll('.navbar-collapse .dropdown-item').forEach(function(item) {
            item.addEventListener('click', function() {
                console.log('Dropdown item clicked');
                if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                    setTimeout(function() {
                        var bsCollapse = bootstrap.Collapse.getInstance(
                            navbarCollapse) || new bootstrap.Collapse(
                            navbarCollapse);
                        bsCollapse.hide();
                    }, 100);
                }
            });
        });

        // Fix user dropdown desktop
        var userDropdown = document.querySelector('#userDropdown');
        if (userDropdown) {
            userDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var dropdownInstance = bootstrap.Dropdown.getOrCreateInstance(userDropdown);
                dropdownInstance.toggle();
            });
        }

        // TAMBAHAN: Force close dengan tombol toggle
        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', function() {
                // Jika sudah terbuka, pastikan bisa tertutup
                if (navbarCollapse.classList.contains('show')) {
                    setTimeout(function() {
                        var bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse) ||
                            new bootstrap.Collapse(navbarCollapse);
                        bsCollapse.hide();
                    }, 50);
                }
            });
        }

        // TAMBAHAN: Close dengan ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && navbarCollapse && navbarCollapse.classList.contains('show')) {
                console.log('ESC pressed, closing navbar');
                var bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse) || new bootstrap
                    .Collapse(navbarCollapse);
                bsCollapse.hide();
            }
        });

    });
    </script>