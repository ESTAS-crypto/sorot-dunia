<?php
// ajax/reset_password_handler.php - COMPLETE FIX
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
    error_log("=== RESET PASSWORD REQUEST START ===");
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method');
    }
    
    // Get data
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    error_log("📝 Token received: " . $token);
    error_log("📝 Token length: " . strlen($token));
    
    // Validasi input
    if (empty($token)) {
        jsonResponse(false, 'Token tidak valid');
    }
    
    if (empty($new_password)) {
        jsonResponse(false, 'Password baru tidak boleh kosong');
    }
    
    if (strlen($new_password) < 8) {
        jsonResponse(false, 'Password minimal 8 karakter');
    }
    
    if ($new_password !== $confirm_password) {
        jsonResponse(false, 'Password dan konfirmasi password tidak cocok');
    }
    
    // Validasi password strength
    if (!preg_match('/[A-Z]/', $new_password)) {
        jsonResponse(false, 'Password harus mengandung minimal 1 huruf besar');
    }
    
    if (!preg_match('/[a-z]/', $new_password)) {
        jsonResponse(false, 'Password harus mengandung minimal 1 huruf kecil');
    }
    
    if (!preg_match('/[0-9]/', $new_password)) {
        jsonResponse(false, 'Password harus mengandung minimal 1 angka');
    }
    
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $new_password)) {
        jsonResponse(false, 'Password harus mengandung minimal 1 karakter spesial');
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
    
    // PERBAIKAN UTAMA: Hash token dengan SHA-256
    $token_hash = hash('sha256', $token);
    error_log("🔐 Token hash for DB: " . $token_hash);
    
    // Cek token di database
    $query = "SELECT 
                prt.id,
                prt.user_id,
                prt.token_hash,
                prt.expires_at,
                prt.used_at,
                u.id as uid,
                u.username, 
                u.email 
              FROM password_reset_tokens prt
              JOIN users u ON prt.user_id = u.id
              WHERE prt.token_hash = ? 
              LIMIT 1";
    
    $stmt = mysqli_prepare($koneksi, $query);
    
    if (!$stmt) {
        error_log("❌ Prepare failed: " . mysqli_error($koneksi));
        jsonResponse(false, 'Kesalahan database');
    }
    
    mysqli_stmt_bind_param($stmt, "s", $token_hash);
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("❌ Execute failed: " . mysqli_error($koneksi));
        mysqli_stmt_close($stmt);
        jsonResponse(false, 'Kesalahan database');
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        error_log("⚠️ Token not found in database");
        error_log("Token hash searched: " . $token_hash);
        
        // Debug: Show recent tokens
        $debug_query = "SELECT id, LEFT(token_hash, 20) as hash_preview, expires_at, used_at 
                       FROM password_reset_tokens 
                       ORDER BY created_at DESC LIMIT 3";
        $debug_result = mysqli_query($koneksi, $debug_query);
        
        if ($debug_result) {
            error_log("Recent tokens in DB:");
            while ($row = mysqli_fetch_assoc($debug_result)) {
                error_log("  ID: {$row['id']}, Hash: {$row['hash_preview']}..., Expires: {$row['expires_at']}, Used: " . ($row['used_at'] ?? 'NULL'));
            }
        }
        
        jsonResponse(false, 'Token tidak valid atau sudah kadaluarsa. Silakan minta link reset password baru.');
    }
    
    $token_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$token_data || !isset($token_data['user_id'])) {
        error_log("❌ Invalid token data");
        jsonResponse(false, 'Data token tidak valid');
    }
    
    error_log("✅ Token found! User ID: " . $token_data['user_id']);
    error_log("Token ID: " . $token_data['id']);
    error_log("Expires at: " . $token_data['expires_at']);
    error_log("Used at: " . ($token_data['used_at'] ?? 'NULL'));
    
    // Cek apakah token sudah digunakan
    if (!empty($token_data['used_at'])) {
        error_log("⚠️ Token already used at: " . $token_data['used_at']);
        jsonResponse(false, 'Token ini sudah pernah digunakan. Silakan minta link reset password baru.');
    }
    
    // Cek apakah token sudah kadaluarsa
    if (strtotime($token_data['expires_at']) < time()) {
        error_log("⚠️ Token expired. Expires: " . $token_data['expires_at'] . ", Now: " . date('Y-m-d H:i:s'));
        jsonResponse(false, 'Token sudah kadaluarsa. Silakan minta link reset password baru.');
    }
    
    $user_id = $token_data['user_id'];
    
    // Hash password baru
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    error_log("🔐 New password hashed");
    
    // Begin transaction
    mysqli_begin_transaction($koneksi);
    
    try {
        // Update password user
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($koneksi, $update_query);
        
        if (!$update_stmt) {
            throw new Exception('Failed to prepare update statement: ' . mysqli_error($koneksi));
        }
        
        mysqli_stmt_bind_param($update_stmt, "si", $password_hash, $user_id);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception('Failed to update password: ' . mysqli_stmt_error($update_stmt));
        }
        
        $affected = mysqli_stmt_affected_rows($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        error_log("✅ Password updated. Affected rows: " . $affected);
        
        // Mark token as used
        $mark_used_query = "UPDATE password_reset_tokens 
                           SET used_at = NOW(), ip_address = ? 
                           WHERE id = ?";
        $mark_stmt = mysqli_prepare($koneksi, $mark_used_query);
        
        if (!$mark_stmt) {
            throw new Exception('Failed to mark token as used: ' . mysqli_error($koneksi));
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        mysqli_stmt_bind_param($mark_stmt, "si", $ip_address, $token_data['id']);
        
        if (!mysqli_stmt_execute($mark_stmt)) {
            throw new Exception('Failed to mark token: ' . mysqli_stmt_error($mark_stmt));
        }
        
        mysqli_stmt_close($mark_stmt);
        error_log("✅ Token marked as used");
        
        // Commit transaction
        mysqli_commit($koneksi);
        error_log("✅ Transaction committed");
        
        // Log activity
        error_log("✅ Password reset successful for user: " . $token_data['username'] . " (ID: " . $user_id . ") from IP: " . $ip_address);
        
        // Hapus semua token lama untuk user ini (cleanup)
        $cleanup_query = "DELETE FROM password_reset_tokens 
                         WHERE user_id = ? AND used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $cleanup_stmt = mysqli_prepare($koneksi, $cleanup_query);
        if ($cleanup_stmt) {
            mysqli_stmt_bind_param($cleanup_stmt, "i", $user_id);
            mysqli_stmt_execute($cleanup_stmt);
            $deleted = mysqli_stmt_affected_rows($cleanup_stmt);
            mysqli_stmt_close($cleanup_stmt);
            error_log("🗑️ Cleaned up $deleted old tokens");
        }
        
        error_log("=== RESET PASSWORD REQUEST SUCCESS ===");
        
        // Return success response
        jsonResponse(true, 'Password berhasil direset! Anda akan diarahkan ke halaman login...', [
            'redirect' => 'index.php?reset=success'
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($koneksi);
        error_log("❌ Transaction error: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("❌ Reset password error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    jsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage());
} catch (Error $e) {
    error_log("❌ Fatal error caught: " . $e->getMessage());
    jsonResponse(false, 'Terjadi kesalahan sistem.');
}

// Close connections if still open
if (isset($koneksi)) {
    mysqli_close($koneksi);
}

jsonResponse(false, 'Unknown error occurred');
?>