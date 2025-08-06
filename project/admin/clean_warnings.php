<?php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || $input['action'] !== 'clean_expired') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    // Start transaction
    mysqli_begin_transaction($koneksi);
    
    // Count expired warnings
    $count_query = "
        SELECT COUNT(*) as count 
        FROM user_warnings 
        WHERE expires_at IS NOT NULL 
        AND expires_at <= NOW()
    ";
    $count_result = mysqli_query($koneksi, $count_query);
    
    if (!$count_result) {
        throw new Exception("Error counting expired warnings: " . mysqli_error($koneksi));
    }
    
    $expired_count = mysqli_fetch_assoc($count_result)['count'];
    
    // Log what we found
    error_log("Found {$expired_count} expired warnings to clean");
    
    // Get details of warnings to be deleted for logging
    $detail_query = "
        SELECT w.id, w.user_id, w.warning_level, w.reason, w.expires_at, u.username
        FROM user_warnings w
        JOIN users u ON w.user_id = u.id
        WHERE w.expires_at IS NOT NULL 
        AND w.expires_at <= NOW()
    ";
    $detail_result = mysqli_query($koneksi, $detail_query);
    $warnings_to_delete = [];
    
    if ($detail_result) {
        while ($row = mysqli_fetch_assoc($detail_result)) {
            $warnings_to_delete[] = $row;
        }
    }
    
    // Log each warning being deleted
    foreach ($warnings_to_delete as $warning) {
        error_log("Deleting expired warning ID {$warning['id']} for user {$warning['username']} (level: {$warning['warning_level']}, expired: {$warning['expires_at']})");
    }
    
    // Delete expired warnings
    $delete_warnings = "
        DELETE FROM user_warnings 
        WHERE expires_at IS NOT NULL 
        AND expires_at <= NOW()
    ";
    $delete_result = mysqli_query($koneksi, $delete_warnings);
    
    if (!$delete_result) {
        throw new Exception("Error deleting expired warnings: " . mysqli_error($koneksi));
    }
    
    $actually_deleted = mysqli_affected_rows($koneksi);
    error_log("Actually deleted {$actually_deleted} warnings from database");
    
    // Clean expired warning sessions 
    $delete_sessions = "DELETE FROM warning_sessions WHERE expires_at <= NOW()";
    $sessions_result = mysqli_query($koneksi, $delete_sessions);
    $deleted_sessions = mysqli_affected_rows($koneksi);
    
    if (!$sessions_result) {
        error_log("Warning: Failed to delete expired sessions: " . mysqli_error($koneksi));
    }
    
    // Update user warning counts - Fixed query
    $update_counts = "
        UPDATE users u SET 
        warning_count = COALESCE((
            SELECT COUNT(*) 
            FROM user_warnings w 
            WHERE w.user_id = u.id 
            AND (w.expires_at IS NULL OR w.expires_at > NOW())
        ), 0),
        last_warning_at = (
            SELECT MAX(w.given_at) 
            FROM user_warnings w 
            WHERE w.user_id = u.id 
            AND (w.expires_at IS NULL OR w.expires_at > NOW())
        )
        WHERE u.id IN (
            SELECT DISTINCT user_id FROM (
                SELECT user_id FROM user_warnings 
                UNION 
                SELECT id as user_id FROM users WHERE warning_count > 0
            ) AS affected_users
        )
    ";
    
    $update_result = mysqli_query($koneksi, $update_counts);
    $updated_users = mysqli_affected_rows($koneksi);
    
    if (!$update_result) {
        throw new Exception("Error updating user warning counts: " . mysqli_error($koneksi));
    }
    
    // Commit transaction
    mysqli_commit($koneksi);
    
    // Log admin action
    error_log("Admin {$_SESSION['user_id']} cleaned {$actually_deleted} expired warnings, {$deleted_sessions} sessions, updated {$updated_users} users");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Expired warnings cleaned successfully',
        'cleaned_count' => $actually_deleted,
        'deleted_sessions' => $deleted_sessions,
        'updated_users' => $updated_users,
        'details' => $warnings_to_delete
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($koneksi);
    
    error_log("Error cleaning warnings: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error cleaning warnings: ' . $e->getMessage()
    ]);
}
?>