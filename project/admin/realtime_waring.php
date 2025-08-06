<?php
// realtime_warning.php - API untuk mendapatkan statistik warning real-time (FIXED VERSION)
session_start();

// Include config
require_once '../config/config.php';
require_once '../config/auth_check.php';

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate, max-age=0');

try {
    // Get updated warning stats dengan query yang lebih akurat
    $stats_query = "
        SELECT 
            u.id,
            u.username,
            u.warning_count,
            u.last_warning_at,
            COALESCE((
                SELECT COUNT(*) 
                FROM user_warnings w 
                WHERE w.user_id = u.id 
                AND (w.expires_at IS NULL OR w.expires_at > NOW())
            ), 0) as active_warnings,
            (
                SELECT MAX(w.given_at) 
                FROM user_warnings w 
                WHERE w.user_id = u.id 
                AND (w.expires_at IS NULL OR w.expires_at > NOW())
            ) as latest_warning,
            (
                SELECT COUNT(*) 
                FROM user_warnings w 
                WHERE w.user_id = u.id 
                AND w.expires_at IS NOT NULL 
                AND w.expires_at <= NOW()
            ) as expired_warnings
        FROM users u
        ORDER BY u.id
    ";
    
    $result = mysqli_query($koneksi, $stats_query);
    $users = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
    }
    
    // Get system-wide warning statistics dengan query yang lebih detail
    $system_stats_query = "
        SELECT 
            COUNT(*) as total_warnings,
            COUNT(CASE WHEN expires_at IS NULL OR expires_at > NOW() THEN 1 END) as active_warnings,
            COUNT(CASE WHEN expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_warnings,
            COUNT(CASE WHEN warning_level = 'low' AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 END) as active_low_warnings,
            COUNT(CASE WHEN warning_level = 'medium' AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 END) as active_medium_warnings,
            COUNT(CASE WHEN warning_level = 'high' AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 END) as active_high_warnings,
            COUNT(CASE WHEN warning_level = 'low' AND expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_low_warnings,
            COUNT(CASE WHEN warning_level = 'medium' AND expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_medium_warnings,
            COUNT(CASE WHEN warning_level = 'high' AND expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_high_warnings,
            COUNT(CASE WHEN notification_shown = 0 THEN 1 END) as unshown_warnings
        FROM user_warnings
    ";
    
    $system_result = mysqli_query($koneksi, $system_stats_query);
    $system_stats = mysqli_fetch_assoc($system_result);
    
    // Get recent warning activity (last 48 hours) dengan detail lebih lengkap
    $recent_activity_query = "
        SELECT 
            w.id,
            w.warning_level,
            w.reason,
            w.given_at,
            w.expires_at,
            w.notification_shown,
            w.shown_at,
            CASE 
                WHEN w.expires_at IS NULL THEN 'permanent'
                WHEN w.expires_at > NOW() THEN 'active' 
                ELSE 'expired'
            END as status,
            u.username as user,
            admin.username as given_by
        FROM user_warnings w
        JOIN users u ON w.user_id = u.id
        LEFT JOIN users admin ON w.given_by = admin.id
        WHERE w.given_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY w.given_at DESC
        LIMIT 15
    ";
    
    $activity_result = mysqli_query($koneksi, $recent_activity_query);
    $recent_activity = [];
    
    if ($activity_result) {
        while ($row = mysqli_fetch_assoc($activity_result)) {
            $recent_activity[] = $row;
        }
    }
    
    // Get warning sessions statistics
    $sessions_query = "
        SELECT 
            COUNT(*) as total_sessions,
            COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_sessions,
            COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_sessions,
            COUNT(DISTINCT user_id) as users_with_sessions
        FROM warning_sessions
        WHERE 1=1
    ";
    
    $sessions_result = mysqli_query($koneksi, $sessions_query);
    $session_stats = [];
    
    if ($sessions_result) {
        $session_stats = mysqli_fetch_assoc($sessions_result);
    } else {
        // Default values if table doesn't exist or query fails
        $session_stats = [
            'total_sessions' => 0,
            'active_sessions' => 0,
            'expired_sessions' => 0,
            'users_with_sessions' => 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'system_stats' => $system_stats,
        'session_stats' => $session_stats,
        'recent_activity' => $recent_activity,
        'timestamp' => time(),
        'summary' => [
            'total_users' => count($users),
            'users_with_warnings' => count(array_filter($users, function($u) { return $u['active_warnings'] > 0; })),
            'users_with_expired' => count(array_filter($users, function($u) { return $u['expired_warnings'] > 0; })),
            'ready_to_clean' => $system_stats['expired_warnings']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error getting warning stats: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error getting warning statistics: ' . $e->getMessage()
    ]);
}
?>