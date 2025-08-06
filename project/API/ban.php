<?php
// Start output buffering to prevent headers already sent error
ob_start();

// Start session
session_start();

// Include config dengan path yang disesuaikan
$config_paths = [
    __DIR__ . '/../config/config.php',
    dirname(__DIR__) . '/config/config.php',
    '../config/config.php',
    'config/config.php'
];

$koneksi = null;
foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        require_once $config_path;
        break;
    }
}

// Fallback database connection
if (!isset($koneksi) || !$koneksi) {
    $host = "64.235.41.175";
    $user = "arinnapr_uinievan";
    $pass = ")^YZ!dZxr{l2";
    $db   = "arinnapr_dbinievan";
    
    $koneksi = mysqli_connect($host, $user, $pass, $db);
    if (!$koneksi) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database connection failed: ' . mysqli_connect_error(),
            'timestamp' => time()
        ]);
        exit();
    }
    mysqli_set_charset($koneksi, "utf8");
}

// Clean buffer and set headers
ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Allow both POST and GET requests for ban checking
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST and GET requests allowed',
        'timestamp' => time()
    ]);
    exit();
}

// Debug log
error_log("=== BAN CHECK API CALLED ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Session ID: " . session_id());

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode([
        'status' => 'not_logged_in',
        'timestamp' => time(),
        'debug' => 'User session not found or invalid'
    ]);
    exit();
}

// Get user ID with multiple fallbacks
$user_id = 0;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $user_id = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['id']) && $_SESSION['id'] > 0) {
    $user_id = (int)$_SESSION['id'];
}

if ($user_id <= 0) {
    error_log("Invalid user ID");
    echo json_encode([
        'status' => 'invalid_user_id', 
        'timestamp' => time(),
        'debug' => [
            'session_keys' => array_keys($_SESSION),
            'user_id' => $_SESSION['user_id'] ?? 'not_set',
            'id' => $_SESSION['id'] ?? 'not_set'
        ]
    ]);
    exit();
}

error_log("Checking ban status for user ID: " . $user_id);

try {
    // Check user status from database
    $query = "SELECT is_banned, ban_until, ban_reason, warning_count, last_warning_at, 
                     banned_by, banned_at, username, email
              FROM users 
              WHERE id = ?";
    
    $stmt = mysqli_prepare($koneksi, $query);
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . mysqli_error($koneksi));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        error_log("User data retrieved: " . print_r($user, true));
        
        // PRIORITY 1: Check ban status
        if ($user['is_banned'] == 1 && !empty($user['ban_until'])) {
            $ban_until = strtotime($user['ban_until']);
            $now = time();
            
            error_log("Ban check - Until: " . $user['ban_until'] . " (" . $ban_until . "), Now: " . date('Y-m-d H:i:s', $now) . " (" . $now . ")");
            
            if ($ban_until > $now) {
                // User is still banned
                $remaining_time = $ban_until - $now;
                $days = floor($remaining_time / 86400);
                $hours = floor(($remaining_time % 86400) / 3600);
                $minutes = floor(($remaining_time % 3600) / 60);
                
                $time_left = '';
                if ($days > 0) {
                    $time_left = "$days hari, $hours jam, $minutes menit";
                } elseif ($hours > 0) {
                    $time_left = "$hours jam, $minutes menit";
                } else {
                    $time_left = "$minutes menit";
                }
                
                error_log("USER IS BANNED! Returning ban status");
                
                echo json_encode([
                    'status' => 'banned',
                    'ban_reason' => $user['ban_reason'] ?: 'Tidak ada alasan yang diberikan',
                    'ban_until' => date('d F Y H:i', $ban_until),
                    'time_remaining' => $time_left,
                    'banned_at' => $user['banned_at'] ? date('d F Y H:i', strtotime($user['banned_at'])) : null,
                    'banned_by' => $user['banned_by'] ?: null,
                    'username' => $user['username'],
                    'timestamp' => time()
                ]);
                exit();
            } else {
                // Ban expired, remove ban status
                error_log("Ban expired, removing ban status");
                $update_query = "UPDATE users SET is_banned = 0, ban_until = NULL, ban_reason = NULL, banned_by = NULL, banned_at = NULL WHERE id = ?";
                $update_stmt = mysqli_prepare($koneksi, $update_query);
                mysqli_stmt_bind_param($update_stmt, "i", $user_id);
                mysqli_stmt_execute($update_stmt);
            }
        }
        
        // PRIORITY 2: Check for unshown warnings
        // IMPROVED: Smart warning detection with tracking
        $warning_to_show = checkUnshownWarning($koneksi, $user_id, $user);
        
        if ($warning_to_show) {
            error_log("User has unshown warning, returning warning status");
            
            echo json_encode([
                'status' => 'warning',
                'warning_level' => $warning_to_show['warning_level'],
                'warning_reason' => $warning_to_show['reason'],
                'warning_count' => $user['warning_count'],
                'given_at' => date('d F Y H:i', strtotime($warning_to_show['given_at'])),
                'given_by' => $warning_to_show['given_by'] ?: null,
                'warning_id' => $warning_to_show['id'],
                'timestamp' => time()
            ]);
            exit();
        }
        
        // User is active (no ban, no unshown warning)
        error_log("User is active");
        echo json_encode([
            'status' => 'active',
            'username' => $user['username'],
            'timestamp' => time()
        ]);
        
    } else {
        error_log("User not found in database");
        echo json_encode([
            'status' => 'user_not_found', 
            'user_id' => $user_id,
            'timestamp' => time()
        ]);
    }
    
} catch (Exception $e) {
    error_log("Ban check error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
}

/**
 * Enhanced function to check for unshown warnings with smart tracking
 */
function checkUnshownWarning($koneksi, $user_id, $user) {
    $current_session = session_id();
    $now = date('Y-m-d H:i:s');
    
    // METODE 1: Check by expiration time (24 jam setelah warning diberikan)
    $warning_query = "SELECT w.id, w.warning_level, w.reason, w.given_at, w.given_by, 
                             w.notification_shown, w.shown_at, w.expires_at
                      FROM user_warnings w
                      WHERE w.user_id = ? 
                      AND (
                          -- Warning yang belum pernah ditampilkan
                          w.notification_shown = 0
                          OR 
                          -- Warning yang expired tapi masih dalam 24 jam
                          (w.expires_at IS NULL OR w.expires_at > NOW())
                      )
                      ORDER BY w.given_at DESC 
                      LIMIT 1";
    
    $stmt = mysqli_prepare($koneksi, $warning_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $warning = mysqli_fetch_assoc($result);
        
        // Check if this warning should be shown
        if (shouldShowWarning($koneksi, $warning, $user_id, $current_session)) {
            
            // METODE 2: Tambahan check berdasarkan session tracking
            if (!hasWarningBeenShownInSession($koneksi, $warning['id'], $user_id, $current_session)) {
                
                // Mark warning as shown untuk session ini
                markWarningAsShown($koneksi, $warning['id'], $user_id, $current_session);
                
                return $warning;
            }
        }
    }
    
    return null;
}

/**
 * Check if warning should be shown based on various conditions
 */
function shouldShowWarning($koneksi, $warning, $user_id, $session_id) {
    $now = time();
    $given_time = strtotime($warning['given_at']);
    $time_diff = $now - $given_time;
    
    // Rule 1: Jangan tampilkan warning yang lebih dari 24 jam
    if ($time_diff > 86400) { // 24 hours
        error_log("Warning too old (>24h), skipping");
        return false;
    }
    
    // Rule 2: Check jika sudah expired secara manual
    if (!empty($warning['expires_at'])) {
        if (strtotime($warning['expires_at']) < $now) {
            error_log("Warning manually expired, skipping");
            return false;
        }
    }
    
    // Rule 3: Check frequency - jangan spam warning level rendah
    if ($warning['warning_level'] === 'low') {
        // Low warning: maksimal sekali per 6 jam
        if ($warning['notification_shown'] && !empty($warning['shown_at'])) {
            $last_shown = strtotime($warning['shown_at']);
            if (($now - $last_shown) < 21600) { // 6 hours
                error_log("Low warning shown too recently, skipping");
                return false;
            }
        }
    } elseif ($warning['warning_level'] === 'medium') {
        // Medium warning: maksimal sekali per 3 jam
        if ($warning['notification_shown'] && !empty($warning['shown_at'])) {
            $last_shown = strtotime($warning['shown_at']);
            if (($now - $last_shown) < 10800) { // 3 hours
                error_log("Medium warning shown too recently, skipping");
                return false;
            }
        }
    }
    // High warning: selalu tampilkan jika masih dalam 24 jam
    
    return true;
}

/**
 * Check if warning has been shown in current session
 */
function hasWarningBeenShownInSession($koneksi, $warning_id, $user_id, $session_id) {
    $query = "SELECT id FROM warning_sessions 
              WHERE user_id = ? AND warning_id = ? AND session_id = ? 
              AND expires_at > NOW()";
    
    $stmt = mysqli_prepare($koneksi, $query);
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "iis", $user_id, $warning_id, $session_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return $result && mysqli_num_rows($result) > 0;
}

/**
 * Mark warning as shown in database and session
 */
function markWarningAsShown($koneksi, $warning_id, $user_id, $session_id) {
    $now = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', strtotime('+6 hours')); // Session tracking expires in 6 hours
    
    // Update warning table
    $update_query = "UPDATE user_warnings 
                     SET notification_shown = 1, shown_at = ? 
                     WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $update_query);
    mysqli_stmt_bind_param($stmt, "si", $now, $warning_id);
    mysqli_stmt_execute($stmt);
    
    // Insert session tracking
    $session_query = "INSERT INTO warning_sessions (user_id, warning_id, session_id, shown_at, expires_at) 
                      VALUES (?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE shown_at = VALUES(shown_at), expires_at = VALUES(expires_at)";
    $stmt2 = mysqli_prepare($koneksi, $session_query);
    mysqli_stmt_bind_param($stmt2, "iisss", $user_id, $warning_id, $session_id, $now, $expires);
    mysqli_stmt_execute($stmt2);
    
    error_log("Warning $warning_id marked as shown for user $user_id in session $session_id");
}

// Clean expired session tracking (jalankan kadang-kadang)
function cleanExpiredSessions($koneksi) {
    $clean_query = "DELETE FROM warning_sessions WHERE expires_at < NOW()";
    mysqli_query($koneksi, $clean_query);
}

// Clean expired sessions occasionally (1% chance)
if (rand(1, 100) === 1) {
    cleanExpiredSessions($koneksi);
}

// End output buffering
ob_end_flush();
?>