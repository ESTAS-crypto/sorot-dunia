<?php
// admin/index.php - Main Controller
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include config
include '../config/config.php';

// ========== CEK LOGIN - REDIRECT KE admin-login.php ==========
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

// If user_role is not set in session, fetch it from database
if (!isset($_SESSION['user_role']) && isset($_SESSION['user_id'])) {
    $user_id = sanitize_input($_SESSION['user_id']);
    $query = "SELECT role FROM users WHERE id = '$user_id' LIMIT 1";
    $result = mysqli_query($koneksi, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_role'] = $user['role'];
    } else {
        error_log("Failed to fetch user role: " . mysqli_error($koneksi));
    }
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    error_log("Access denied for user: " . ($_SESSION['username'] ?? 'unknown'));
    
    // Logout dan redirect ke login
    session_destroy();
    header("Location: admin-login.php?error=not_admin");
    exit();
}

// Get the page parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Define allowed pages
$allowed_pages = ['dashboard', 'articles', 'users', 'categories', 'comments', 'settings', 'analytics','token'];

// Validate page
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Include the header
include 'include/header.php';

// Include the sidebar
include 'include/sidebar.php';

// Main content wrapper
echo '<div class="main-content" id="mainContent">';
echo '<div class="container-fluid">';

// Include the requested page
switch ($page) {
    case 'dashboard':
        include 'page/dashboard.php';
        break;
    case 'articles':
        include 'page/articles.php';
        break;
    case 'users':
        include 'page/user.php';
        break;
    case 'categories':
        include 'page/katagori.php';
        break;
    case 'comments':
        include 'page/komentar.php';
        break;
    case 'settings':
        include 'page/pengaturan.php';
        break;
    case 'analytics':
        include 'page/analisis.php';
        break;
    case 'token':
        include 'page/token_test.php';
        break;
    default:
        include 'page/dashboard.php';
        break;
}

echo '</div>';
echo '</div>';

// Include the footer
include 'include/footer.php';
?>