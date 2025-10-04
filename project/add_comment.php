<?php
// add_comment.php - Support AJAX dan redirect biasa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config/config.php';

// Cek apakah request AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Set header untuk AJAX
if ($is_ajax) {
    header('Content-Type: application/json');
}

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', $is_ajax ? 0 : 1);

// Log session data untuk debugging
error_log("add_comment.php - Session data: " . print_r($_SESSION, true));
error_log("add_comment.php - Is AJAX: " . ($is_ajax ? 'yes' : 'no'));

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
    } else {
        header("Location: index.php");
    }
    exit();
}

// Fungsi untuk validasi dan perbaikan session user
function validateAndFixUserSession() {
    global $koneksi;
    
    // Cek apakah user sudah login
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Ambil user_id dari session dengan berbagai kemungkinan
    $user_id = 0;
    if (isset($_SESSION['id'])) {
        $user_id = (int)$_SESSION['id'];
    } elseif (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
    }
    
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    
    // Jika user_id tidak ada tapi username ada, coba ambil dari database
    if ($user_id <= 0 && !empty($username)) {
        $user_query = "SELECT id, username, full_name, role FROM users WHERE username = '" . sanitize_input($username) . "'";
        $user_result = mysqli_query($koneksi, $user_query);
        
        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $user_data = mysqli_fetch_assoc($user_result);
            $user_id = (int)$user_data['id'];
            
            // Update session dengan data yang benar
            $_SESSION['id'] = $user_id;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_role'] = $user_data['role'];
            $_SESSION['full_name'] = $user_data['full_name'];
            
            error_log("Fixed session data in add_comment.php for user: " . $username);
            return $user_id;
        }
    }
    
    // Jika masih tidak ada user_id, coba cari berdasarkan email
    if ($user_id <= 0 && isset($_SESSION['email'])) {
        $email = sanitize_input($_SESSION['email']);
        $user_query = "SELECT id, username, full_name, role FROM users WHERE email = '$email'";
        $user_result = mysqli_query($koneksi, $user_query);
        
        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $user_data = mysqli_fetch_assoc($user_result);
            $user_id = (int)$user_data['id'];
            
            // Update session
            $_SESSION['id'] = $user_id;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['user_role'] = $user_data['role'];
            $_SESSION['full_name'] = $user_data['full_name'];
            
            error_log("Fixed session data using email in add_comment.php for user: " . $user_data['username']);
            return $user_id;
        }
    }
    
    return $user_id > 0 ? $user_id : false;
}

// Validasi dan perbaiki session user
$user_id = validateAndFixUserSession();

if (!$user_id) {
    error_log("User not logged in or session invalid in add_comment.php");
    
    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'message' => 'Anda harus login untuk mengirim komentar',
            'redirect' => 'login.php'
        ]);
    } else {
        header("Location: login.php?error=session_expired");
    }
    exit();
}

// Get and validate input
$article_id = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
$comment_content = isset($_POST['comment_content']) ? trim($_POST['comment_content']) : '';

// Log input data untuk debugging
error_log("add_comment.php - Input data: article_id=$article_id, user_id=$user_id, content_length=" . strlen($comment_content));

// Validate inputs
if ($article_id <= 0 || empty($comment_content)) {
    error_log("Invalid input in add_comment.php: article_id=$article_id, content_empty=" . empty($comment_content));
    
    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak lengkap. Pastikan komentar tidak kosong.'
        ]);
    } else {
        header("Location: artikel.php?id=$article_id&error=invalid_input");
    }
    exit();
}

// Batasi panjang komentar
if (strlen($comment_content) > 1000) {
    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'message' => 'Komentar terlalu panjang. Maksimal 1000 karakter.'
        ]);
    } else {
        header("Location: artikel.php?id=$article_id&error=comment_too_long");
    }
    exit();
}

// Check if article exists and is published
$check_article = "SELECT article_id FROM articles WHERE article_id = $article_id AND article_status = 'published'";
$check_result = mysqli_query($koneksi, $check_article);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    error_log("Article not found or not published: article_id=$article_id");
    
    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'message' => 'Artikel tidak ditemukan atau sudah tidak tersedia.'
        ]);
    } else {
        header("Location: index.php?error=article_not_found");
    }
    exit();
}

// Sanitize comment content
$comment_content = sanitize_input($comment_content);

// Cek apakah user sudah berkomentar dalam 1 menit terakhir (anti-spam)
$spam_check = "SELECT COUNT(*) as count FROM comments 
               WHERE user_id = $user_id 
               AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
$spam_result = mysqli_query($koneksi, $spam_check);

if ($spam_result) {
    $spam_data = mysqli_fetch_assoc($spam_result);
    if ($spam_data['count'] > 0) {
        error_log("Spam detected: user_id=$user_id trying to comment too frequently");
        
        if ($is_ajax) {
            echo json_encode([
                'success' => false,
                'message' => 'Mohon tunggu sebentar sebelum mengirim komentar lagi.'
            ]);
        } else {
            header("Location: artikel.php?id=$article_id&error=comment_spam");
        }
        exit();
    }
}

// Insert comment into database
$insert_query = "INSERT INTO comments (article_id, user_id, content, created_at) 
                VALUES ($article_id, $user_id, '$comment_content', NOW())";

if (mysqli_query($koneksi, $insert_query)) {
    $comment_id = mysqli_insert_id($koneksi);
    error_log("Comment added successfully: article_id=$article_id, user_id=$user_id, comment_id=$comment_id");
    
    if ($is_ajax) {
        // Get user data untuk response AJAX
        $user_query = "SELECT full_name, username FROM users WHERE id = $user_id";
        $user_result = mysqli_query($koneksi, $user_query);
        $user_data = mysqli_fetch_assoc($user_result);
        
        // Format tanggal komentar
        $created_at = date('d M Y H:i');
        
        echo json_encode([
            'success' => true,
            'message' => 'Komentar berhasil ditambahkan!',
            'comment' => [
                'id' => $comment_id,
                'user_name' => $user_data['full_name'] ?: $user_data['username'],
                'content' => htmlspecialchars($comment_content),
                'created_at' => $created_at,
                'created_at_full' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        // Redirect untuk non-AJAX
        header("Location: artikel.php?id=$article_id&comment=success");
    }
} else {
    error_log("Error inserting comment: " . mysqli_error($koneksi));
    
    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menambahkan komentar. Silakan coba lagi.'
        ]);
    } else {
        header("Location: artikel.php?id=$article_id&error=comment_failed");
    }
}

exit();
?>