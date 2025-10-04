<?php
// auth_check.php - FIXED VERSION untuk halaman publik

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable error display untuk production (tapi tetap log)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Function untuk cek apakah headers sudah dikirim
function canSendHeaders() {
    return !headers_sent();
}

// Function untuk logout dengan error handling
function logout() {
    // Clear all session data
    $_SESSION = array();
    
    // Destroy session cookie if exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        if (canSendHeaders()) {
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login dengan JavaScript fallback
    if (canSendHeaders()) {
        header("Location: ../project/login.php?logout=success");
        exit();
    } else {
        // JavaScript fallback jika headers sudah dikirim
        echo '<script>window.location.href = "../project/login.php?logout=success";</script>';
        exit();
    }
}

// Function untuk cek role admin
function checkAdminRole() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        error_log("Access denied for user: " . ($_SESSION['username'] ?? 'unknown') . " with role: " . ($_SESSION['user_role'] ?? 'none'));
        
        // Set error message
        $_SESSION['error_message'] = 'Akses ditolak. Anda harus login sebagai admin.';
        
        // Clear session for security
        logout();
    }
}

// Function untuk cek apakah user adalah admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function untuk cek apakah user adalah penulis
function isPenulis() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'penulis';
}

// Function untuk cek multiple roles
function hasRole($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    return in_array($userRole, $roles);
}

// Function untuk generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function untuk validasi CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function untuk get current user info
function getCurrentUser() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return [
            'id' => $_SESSION['user_id'] ?? 0,
            'username' => $_SESSION['username'] ?? 'Unknown',
            'role' => $_SESSION['user_role'] ?? 'user',
            'email' => $_SESSION['user_email'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? ''
        ];
    }
    return null;
}

// Function untuk update last activity
function updateLastActivity() {
    $_SESSION['last_activity'] = time();
}

// Function untuk cek session timeout
function checkSessionTimeout($timeout = 1800) { // Default 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Session expired
        session_unset();
        session_destroy();
        
        if (canSendHeaders()) {
            header("Location: ../project/login.php?error=session_timeout");
            exit();
        } else {
            echo '<script>window.location.href = "../project/login.php?error=session_timeout";</script>';
            exit();
        }
    }
    
    // Update last activity
    updateLastActivity();
}

// Function untuk security headers dengan error handling
function setSecurityHeaders() {
    if (canSendHeaders()) {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

// Function untuk log user activity
function logUserActivity($action, $details = '') {
    $user = getCurrentUser();
    if ($user) {
        error_log("User Activity - ID: {$user['id']}, Username: {$user['username']}, Action: $action, Details: $details");
    }
}

// PERBAIKAN UTAMA: Tentukan halaman mana yang butuh login dan yang tidak
function isPublicPage() {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    
    // Halaman yang TIDAK memerlukan login
    $public_pages = [
        'index.php',           // Homepage
        'berita.php',          // Berita list
        'artikel.php',         // Detail artikel
        'search.php',          // Pencarian
        'kategori.php',        // Kategori
        'header.php',          // Header file
        'footer.php',          // Footer file
        'config.php'           // Config file
    ];
    
    return in_array($current_script, $public_pages);
}

// Function untuk cek halaman yang membutuhkan login
function requiresLogin() {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    
    // Halaman yang WAJIB login
    $login_required_pages = [
        'profile.php',
        'edit_profile.php',
        'change_password.php',
        'user_dashboard.php'
    ];
    
    return in_array($current_script, $login_required_pages);
}

// Function untuk cek halaman admin only
function isAdminOnlyPage() {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    
    // Halaman khusus admin
    $admin_only_pages = [
        'dashboard.php',
        'users.php', 
        'user.php',
        'categories.php',
        'kategori.php',
        'settings.php',
        'articles.php',
        'manage_articles.php'
    ];
    
    return in_array($current_script, $admin_only_pages);
}

// Function untuk cek halaman debug/utility
function isDebugOrUtilityFile() {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $debug_files = [
        'token_test.php',
        'debug.php',
        'test.php',
        'utility.php',
        'maintenance.php',
        'backup.php',
        'log_viewer.php',
        'system_info.php',
        'database_test.php'
    ];
    
    return in_array($current_script, $debug_files);
}

// Function untuk cek apakah skip auth check
function skipAuthCheck() {
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    
    // File-file yang menggunakan custom auth check sendiri
    $custom_auth_files = [
        'token_test.php',
        'debug.php',
        'system_info.php'
    ];
    
    return in_array($current_script, $custom_auth_files);
}

// Set security headers untuk semua halaman
setSecurityHeaders();

// Generate CSRF token untuk forms
$csrf_token = generateCSRFToken();
$current_user = getCurrentUser();

// PERBAIKAN UTAMA: Cek berdasarkan jenis halaman
if (skipAuthCheck()) {
    // File dengan custom auth, hanya return
    return;
}

// Untuk halaman publik, tidak perlu cek login
if (isPublicPage()) {
    // Halaman publik: hanya cek session timeout jika user sedang login
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        try {
            checkSessionTimeout();
            
            // Validate session data integrity untuk user yang login
            if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_role'])) {
                error_log("Invalid session data detected");
                logout();
            }
            
            // Log page access untuk user yang login
            logUserActivity('page_access', $_SERVER['REQUEST_URI']);
        } catch (Exception $e) {
            error_log("Session check error: " . $e->getMessage());
            // Untuk halaman publik, tidak perlu logout jika ada error
        }
    }
    
    // Untuk halaman publik, selalu izinkan akses
    return;
}

// Untuk halaman yang membutuhkan login
if (requiresLogin() || isAdminOnlyPage() || isDebugOrUtilityFile()) {
    // Cek apakah user sudah login
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        if (canSendHeaders()) {
            header("Location: ../project/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
            exit();
        } else {
            echo '<script>window.location.href = "../project/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']) . '";</script>';
            exit();
        }
    }
    
    // Main security checks untuk halaman yang butuh login
    try {
        // Check session timeout
        checkSessionTimeout();
        
        // Validate session data integrity
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['user_role'])) {
            error_log("Invalid session data detected");
            logout();
        }
        
        // Cek role untuk halaman admin
        if (isAdminOnlyPage()) {
            checkAdminRole();
        }
        
        // Cek role untuk debug/utility files
        if (isDebugOrUtilityFile() && !isAdmin()) {
            error_log("Access denied to debug file for user: " . ($_SESSION['username'] ?? 'unknown'));
            
            if (canSendHeaders()) {
                header("Location: ../project/login.php?error=admin_required");
                exit();
            } else {
                echo '<script>window.location.href = "../project/login.php?error=admin_required";</script>';
                exit();
            }
        }
        
        // Log page access
        logUserActivity('page_access', $_SERVER['REQUEST_URI']);
        
    } catch (Exception $e) {
        error_log("Auth check error: " . $e->getMessage());
        
        // Secure fallback
        session_destroy();
        if (canSendHeaders()) {
            header("Location: ../project/login.php?error=security_error");
            exit();
        } else {
            echo '<script>window.location.href = "../project/login.php?error=security_error";</script>';
            exit();
        }
    }
}

// Helper function for permission checks in views
function canAccess($required_roles) {
    return hasRole($required_roles);
}

// Helper function to display user-friendly role names
function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'Administrator',
        'penulis' => 'Penulis',
        'pembaca' => 'Pembaca'
    ];
    
    return $roles[$role] ?? ucfirst($role);
}

// Function untuk bypass auth untuk specific files
function bypassAuthForFile($filename) {
    $bypass_files = [
        'token_test.php' => 'admin',
        'debug.php' => 'admin',
        'system_info.php' => 'admin',
        'log_viewer.php' => 'admin'
    ];
    
    if (isset($bypass_files[$filename])) {
        $required_role = $bypass_files[$filename];
        return hasRole($required_role);
    }
    
    return false;
}

// Additional security: Rate limiting untuk sensitive operations
function checkRateLimit($action, $limit = 10, $window = 3600) {
    $key = "rate_limit_{$action}_" . ($_SESSION['user_id'] ?? 'anonymous');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    // Reset jika window sudah berlalu
    if (time() - $data['start_time'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
        return true;
    }
    
    // Cek apakah masih dalam limit
    if ($data['count'] >= $limit) {
        return false;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

// Function untuk log security events
function logSecurityEvent($event_type, $details = '') {
    $user = getCurrentUser();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'user_id' => $user['id'] ?? 0,
        'username' => $user['username'] ?? 'anonymous',
        'ip_address' => $ip,
        'user_agent' => $user_agent,
        'details' => $details,
        'script' => $_SERVER['SCRIPT_NAME'] ?? 'unknown'
    ];
    
    error_log("SECURITY_EVENT: " . json_encode($log_entry));
}

// Function untuk detect suspicious activity
function detectSuspiciousActivity() {
    $user = getCurrentUser();
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Hanya cek jika user login
    if (!$user) {
        return;
    }
    
    // Cek jika IP berubah dalam session yang sama
    if (isset($_SESSION['last_ip']) && $_SESSION['last_ip'] !== $current_ip) {
        logSecurityEvent('ip_change', "IP changed from {$_SESSION['last_ip']} to {$current_ip}");
    }
    
    $_SESSION['last_ip'] = $current_ip;
    
    // Cek multiple login attempts
    if (!checkRateLimit('login_attempt', 5, 900)) {
        logSecurityEvent('rate_limit_exceeded', 'Too many login attempts');
    }
}

// Jalankan detection hanya jika user login
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    detectSuspiciousActivity();
}

// Set timezone jika belum di-set
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Jakarta');
}

// Function untuk cek AJAX request
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Untuk AJAX requests, return JSON response instead of redirect
if (isAjaxRequest() && !isAdmin() && isDebugOrUtilityFile()) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Admin access required',
        'redirect' => '../project/login.php?error=admin_required'
    ]);
    exit();
}
?>