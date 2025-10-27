<?php
// ajax/forgot_password_handler.php - FIXED VERSION WITH EMAIL CHECK
// ⚠️ CRITICAL: NO whitespace before <?php!

// Disable ALL output
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
@error_reporting(0);

// Start output buffering
@ob_start();

// Start session safely
try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
} catch (Exception $e) {
    error_log("Session error: " . $e->getMessage());
}

// Clear any existing buffer
while (@ob_get_level()) {
    @ob_end_clean();
}

// Start fresh buffer
@ob_start();

// Set JSON headers FIRST
@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-cache, must-revalidate');
@header('X-Content-Type-Options: nosniff');

// JSON response function
function jsonResponse($success, $message, $data = []) {
    // Clear all buffers
    while (@ob_get_level()) {
        @ob_end_clean();
    }
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    @error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    return true;
});

// Custom exception handler
set_exception_handler(function($exception) {
    @error_log("Exception: " . $exception->getMessage());
    jsonResponse(false, 'Terjadi kesalahan sistem. Silakan coba lagi.');
});

// Fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        @error_log("Fatal Error: " . print_r($error, true));
        
        while (@ob_get_level()) {
            @ob_end_clean();
        }
        
        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem. Silakan hubungi administrator.',
            'error_type' => 'fatal_error'
        ], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
});

// Main execution
try {
    error_log("=== FORGOT PASSWORD REQUEST START ===");
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method');
    }
    
    // Get email
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    error_log("📧 Email received: $email");
    
    // Validate email
    if (empty($email)) {
        jsonResponse(false, 'Email tidak boleh kosong');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Format email tidak valid');
    }
    
    // Include config
    $config_paths = [
        __DIR__ . '/../config/config.php',
        dirname(__DIR__) . '/config/config.php',
        '../config/config.php'
    ];
    
    $config_loaded = false;
    foreach ($config_paths as $config_path) {
        if (file_exists($config_path)) {
            require_once $config_path;
            $config_loaded = true;
            error_log("✅ Config loaded from: $config_path");
            break;
        }
    }
    
    if (!$config_loaded) {
        error_log("❌ Config file not found");
        jsonResponse(false, 'Konfigurasi sistem tidak ditemukan');
    }
    
    // Check database connection
    if (!isset($koneksi) || !$koneksi) {
        error_log("❌ Database connection not available");
        jsonResponse(false, 'Koneksi database gagal');
    }
    
    if (!@mysqli_ping($koneksi)) {
        error_log("❌ Database ping failed");
        jsonResponse(false, 'Koneksi database terputus');
    }
    
    error_log("✅ Database connection OK");
    
    // ============================================
    // PERBAIKAN UTAMA: CEK EMAIL TERDAFTAR
    // ============================================
    
    $check_query = "SELECT id, username, email, full_name FROM users WHERE email = ? LIMIT 1";
    
    if (!($stmt = @mysqli_prepare($koneksi, $check_query))) {
        error_log("❌ Prepare failed: " . mysqli_error($koneksi));
        jsonResponse(false, 'Kesalahan database');
    }
    
    @mysqli_stmt_bind_param($stmt, "s", $email);
    
    if (!@mysqli_stmt_execute($stmt)) {
        error_log("❌ Execute failed: " . mysqli_error($koneksi));
        @mysqli_stmt_close($stmt);
        jsonResponse(false, 'Kesalahan database');
    }
    
    $result = @mysqli_stmt_get_result($stmt);
    
    // PERBAIKAN: Jika email tidak terdaftar, beri tahu user untuk registrasi
    if (!$result || @mysqli_num_rows($result) === 0) {
        @mysqli_stmt_close($stmt);
        error_log("⚠️ Email not found: $email");
        
        // Return response yang jelas bahwa email tidak terdaftar
        jsonResponse(false, 'Email tidak terdaftar. Silakan daftar terlebih dahulu.', [
            'email_not_found' => true,
            'action' => 'register'
        ]);
    }
    
    $user = @mysqli_fetch_assoc($result);
    @mysqli_stmt_close($stmt);
    
    if (!$user || !isset($user['id'])) {
        error_log("❌ Invalid user data");
        jsonResponse(false, 'Data user tidak valid');
    }
    
    error_log("✅ User found: " . $user['username'] . " (ID: " . $user['id'] . ")");
    
    // Delete old unused tokens for this user
    $delete_query = "DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL";
    if ($del_stmt = @mysqli_prepare($koneksi, $delete_query)) {
        @mysqli_stmt_bind_param($del_stmt, "i", $user['id']);
        @mysqli_stmt_execute($del_stmt);
        $deleted = @mysqli_stmt_affected_rows($del_stmt);
        @mysqli_stmt_close($del_stmt);
        error_log("🗑️ Deleted $deleted old tokens");
    }
    
    // Generate token RAW (untuk URL) dan HASH (untuk DB)
    $raw_token = bin2hex(random_bytes(32)); // 64 karakter hex (token untuk URL)
    $token_hash = hash('sha256', $raw_token); // Hash SHA-256 untuk database
    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 jam dari sekarang
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    error_log("=== TOKEN GENERATION ===");
    error_log("🔑 Raw token (for URL): " . $raw_token);
    error_log("🔑 Raw token length: " . strlen($raw_token));
    error_log("🔐 Token hash (for DB): " . $token_hash);
    error_log("🔐 Token hash length: " . strlen($token_hash));
    error_log("⏰ Expires at: " . $expires_at);
    error_log("🌐 IP Address: " . $ip_address);
    
    // Ensure table exists
    $table_check = "SHOW TABLES LIKE 'password_reset_tokens'";
    $table_result = @mysqli_query($koneksi, $table_check);
    
    if (!$table_result || @mysqli_num_rows($table_result) === 0) {
        error_log("⚠️ Creating password_reset_tokens table");
        
        $create_table = "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `token_hash` VARCHAR(64) NOT NULL UNIQUE,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `ip_address` VARCHAR(45),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!@mysqli_query($koneksi, $create_table)) {
            error_log("❌ Failed to create table: " . mysqli_error($koneksi));
            jsonResponse(false, 'Kesalahan sistem. Silakan hubungi administrator.');
        }
        error_log("✅ Table created successfully");
    }
    
    // Insert token
    $insert_query = "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address, created_at) 
                     VALUES (?, ?, ?, ?, NOW())";
    
    if (!($insert_stmt = @mysqli_prepare($koneksi, $insert_query))) {
        error_log("❌ Insert prepare failed: " . mysqli_error($koneksi));
        jsonResponse(false, 'Kesalahan saat membuat token');
    }
    
    @mysqli_stmt_bind_param($insert_stmt, "isss", $user['id'], $token_hash, $expires_at, $ip_address);
    
    if (!@mysqli_stmt_execute($insert_stmt)) {
        error_log("❌ Insert execute failed: " . mysqli_error($koneksi));
        error_log("❌ MySQL Error: " . mysqli_stmt_error($insert_stmt));
        @mysqli_stmt_close($insert_stmt);
        jsonResponse(false, 'Gagal menyimpan token');
    }
    
    $insert_id = @mysqli_stmt_insert_id($insert_stmt);
    @mysqli_stmt_close($insert_stmt);
    
    error_log("✅ Token saved to database with ID: $insert_id");
    
    // VERIFY: Check if token really saved
    $verify_query = "SELECT id, token_hash, expires_at FROM password_reset_tokens WHERE id = ? LIMIT 1";
    if ($verify_stmt = @mysqli_prepare($koneksi, $verify_query)) {
        @mysqli_stmt_bind_param($verify_stmt, "i", $insert_id);
        @mysqli_stmt_execute($verify_stmt);
        $verify_result = @mysqli_stmt_get_result($verify_stmt);
        
        if ($verify_row = @mysqli_fetch_assoc($verify_result)) {
            error_log("✅ VERIFIED: Token exists in database");
            error_log("   ID: " . $verify_row['id']);
            error_log("   Hash (first 20): " . substr($verify_row['token_hash'], 0, 20) . "...");
            error_log("   Expires: " . $verify_row['expires_at']);
        } else {
            error_log("❌ VERIFY FAILED: Token not found in database after insert!");
        }
        @mysqli_stmt_close($verify_stmt);
    }
    
    // Generate reset link - PENTING: Gunakan RAW TOKEN (bukan hash!)
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    $base_path = dirname(dirname($script_name));
    $base_path = str_replace('\\', '/', $base_path);
    $base_path = rtrim($base_path, '/');
    
    // GUNAKAN RAW TOKEN untuk URL!
    $reset_link = "{$protocol}://{$host}{$base_path}/reset_password.php?token={$raw_token}";
    
    error_log("🔗 Reset link: $reset_link");
    error_log("🔗 Token in URL: $raw_token");
    
    // Load email config
    $email_loaded = false;
    $email_paths = [
        __DIR__ . '/../config/email.php',
        dirname(__DIR__) . '/config/email.php',
        '../config/email.php'
    ];
    
    foreach ($email_paths as $email_path) {
        if (file_exists($email_path)) {
            try {
                require_once $email_path;
                $email_loaded = true;
                error_log("✅ Email config loaded from: $email_path");
                break;
            } catch (Exception $e) {
                error_log("❌ Failed to load email config: " . $e->getMessage());
            }
        }
    }
    
    // Send email
    $email_sent = false;
    if ($email_loaded && function_exists('sendResetEmailSMTP')) {
        try {
            error_log("📧 Attempting to send email to: $email");
            
            // GUNAKAN RAW TOKEN untuk email!
            $email_sent = sendResetEmailSMTP(
                $user['email'],
                $raw_token,  // RAW TOKEN!
                $user['username'],
                $user['full_name'] ?? ''
            );
            
            if ($email_sent) {
                error_log("✅ Email sent successfully");
            } else {
                error_log("❌ Email sending failed");
            }
        } catch (Exception $e) {
            error_log("❌ Email exception: " . $e->getMessage());
        }
    } else {
        error_log("⚠️ Email function not available");
    }
    
    error_log("=== FORGOT PASSWORD REQUEST END ===");
    
    // Return success dengan informasi yang jelas
    jsonResponse(true, 'Link reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam. Link akan kadaluarsa dalam 1 jam.', [
        'email_sent' => $email_sent,
        'email_found' => true,
        'expires_in' => '1 hour',
        'email' => $email,
        'username' => $user['username']
    ]);
    
} catch (Exception $e) {
    error_log("❌ Main exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    jsonResponse(false, 'Terjadi kesalahan. Silakan coba lagi.');
} catch (Error $e) {
    error_log("❌ Fatal error caught: " . $e->getMessage());
    jsonResponse(false, 'Terjadi kesalahan sistem.');
}

jsonResponse(false, 'Unknown error occurred');
?>