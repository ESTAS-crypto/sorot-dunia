<?php
/**
 * Authentication Handler
 * Handles login and registration requests
 */

session_start();
require_once 'config/config.php';

// Set response header to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
    exit;
}

// ========================================
// LOGIN HANDLER
// ========================================
if (isset($_POST['email']) && isset($_POST['password']) && !isset($_POST['username'])) {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email dan password harus diisi'
        ]);
        exit;
    }
    
    // Prepare query to find user by email or username
    $query = "SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $query);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Kesalahan sistem. Silakan coba lagi.'
        ]);
        error_log("Login prepare error: " . mysqli_error($koneksi));
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        // Check if user is banned
        if ($user['is_banned'] == 1) {
            $ban_message = 'Akun Anda telah diblokir.';
            if ($user['ban_until'] && strtotime($user['ban_until']) > time()) {
                $ban_message .= ' Hingga: ' . date('d F Y H:i', strtotime($user['ban_until']));
            }
            if ($user['ban_reason']) {
                $ban_message .= '<br>Alasan: ' . htmlspecialchars($user['ban_reason']);
            }
            
            echo json_encode([
                'success' => false,
                'message' => $ban_message
            ]);
            exit;
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['last_activity'] = time();
            
            // Log successful login
            error_log("User logged in: " . $user['username'] . " (ID: " . $user['id'] . ")");
            
            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil! Selamat datang, ' . htmlspecialchars($user['full_name'])
            ]);
        } else {echo json_encode([
                'success' => false,
                'message' => 'Password salah'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Email atau username tidak ditemukan'
        ]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// ========================================
// REGISTER HANDLER
// ========================================
if (isset($_POST['username']) && isset($_POST['email']) && isset($_POST['password']) && isset($_POST['full_name'])) {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $full_name = sanitize_input($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // ========================================
    // VALIDATE INPUT
    // ========================================
    
    // Check if all fields are filled
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Semua field harus diisi'
        ]);
        exit;
    }
    
    // Validate username
    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode([
            'success' => false,
            'message' => 'Username harus antara 3-50 karakter'
        ]);
        exit;
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        echo json_encode([
            'success' => false,
            'message' => 'Username hanya boleh mengandung huruf, angka, dan underscore'
        ]);
        exit;
    }
    
    // Validate full name
    if (strlen($full_name) < 3 || strlen($full_name) > 100) {
        echo json_encode([
            'success' => false,
            'message' => 'Nama lengkap harus antara 3-100 karakter'
        ]);
        exit;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Format email tidak valid'
        ]);
        exit;
    }
    
    // Validate password match
    if ($password !== $confirm_password) {
        echo json_encode([
            'success' => false,
            'message' => 'Password dan konfirmasi password tidak cocok'
        ]);
        exit;
    }
    
    // ========================================
    // VALIDATE PASSWORD STRENGTH
    // ========================================
    
    // Check minimum length
    if (strlen($password) < 8) {
        echo json_encode([
            'success' => false,
            'message' => 'Password harus minimal 8 karakter'
        ]);
        exit;
    }
    
    // Check maximum length
    if (strlen($password) > 100) {
        echo json_encode([
            'success' => false,
            'message' => 'Password terlalu panjang (maksimal 100 karakter)'
        ]);
        exit;
    }
    
    // Check for uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password harus mengandung minimal 1 huruf besar (A-Z)'
        ]);
        exit;
    }
    
    // Check for lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password harus mengandung minimal 1 huruf kecil (a-z)'
        ]);
        exit;
    }
    
    // Check for number
    if (!preg_match('/[0-9]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password harus mengandung minimal 1 angka (0-9)'
        ]);
        exit;
    }
    
    // Check for special character
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password harus mengandung minimal 1 karakter spesial (!@#$%^&*)'
        ]);
        exit;
    }
    
    // ========================================
    // CHECK IF USERNAME EXISTS
    // ========================================
    $check_query = "SELECT id FROM users WHERE username = ? LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $check_query);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Kesalahan sistem. Silakan coba lagi.'
        ]);
        error_log("Register username check error: " . mysqli_error($koneksi));
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Username sudah digunakan. Silakan pilih username lain.'
        ]);
        mysqli_stmt_close($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // ========================================
    // CHECK IF EMAIL EXISTS
    // ========================================
    $check_query = "SELECT id FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $check_query);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Kesalahan sistem. Silakan coba lagi.'
        ]);
        error_log("Register email check error: " . mysqli_error($koneksi));
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email sudah terdaftar. Silakan gunakan email lain atau login.'
        ]);
        mysqli_stmt_close($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // ========================================
    // INSERT NEW USER
    // ========================================
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user into database
    $insert_query = "INSERT INTO users (username, email, full_name, password, role, created_at) VALUES (?, ?, ?, ?, 'pembaca', NOW())";
    $stmt = mysqli_prepare($koneksi, $insert_query);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Kesalahan sistem. Silakan coba lagi.'
        ]);
        error_log("Register insert error: " . mysqli_error($koneksi));
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $full_name, $hashed_password);
    
    if (mysqli_stmt_execute($stmt)) {
        $new_user_id = mysqli_insert_id($koneksi);
        
        // Log successful registration
        error_log("New user registered: " . $username . " (ID: " . $new_user_id . ") Email: " . $email);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registrasi berhasil! Silakan login dengan akun Anda.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mendaftar. Silakan coba lagi.'
        ]);
        error_log("Register execute error: " . mysqli_error($koneksi));
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// ========================================
// INVALID REQUEST
// ========================================
echo json_encode([
    'success' => false,
    'message' => 'Invalid request'
]);
?>