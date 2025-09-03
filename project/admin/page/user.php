<?php
// Include required files
require_once '../config/config.php';
require_once '../config/auth_check.php';

// Check if user is admin
checkAdminRole();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $password = sanitize_input($_POST['password']);
                $role = sanitize_input($_POST['role']);
                
                // Validasi input
                if (empty($username) || empty($email) || empty($password) || empty($role)) {
                    $error = "Semua field harus diisi!";
                } else {
                    // Cek apakah username atau email sudah ada
                    $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
                    $stmt = mysqli_prepare($koneksi, $check_query);
                    mysqli_stmt_bind_param($stmt, "ss", $username, $email);
                    mysqli_stmt_execute($stmt);
                    $check_result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $error = "Username atau email sudah digunakan!";
                    } else {
                        // Hash password
                        $hashed_password = hash_password($password);
                        
                        // Insert user baru
                        $insert_query = "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())";
                        $stmt = mysqli_prepare($koneksi, $insert_query);
                        mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $role);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "User berhasil ditambahkan!";
                        } else {
                            $error = "Gagal menambahkan user: " . mysqli_error($koneksi);
                        }
                    }
                }
                break;
                
            case 'edit_user':
                $user_id = sanitize_input($_POST['user_id']);
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $role = sanitize_input($_POST['role']);
                $password = sanitize_input($_POST['password']);
                
                if (empty($username) || empty($email) || empty($role)) {
                    $error = "Username, email, dan role harus diisi!";
                } else {
                    // Update query
                    if (!empty($password)) {
                        $hashed_password = hash_password($password);
                        $update_query = "UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?";
                        $stmt = mysqli_prepare($koneksi, $update_query);
                        mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $role, $hashed_password, $user_id);
                    } else {
                        $update_query = "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?";
                        $stmt = mysqli_prepare($koneksi, $update_query);
                        mysqli_stmt_bind_param($stmt, "sssi", $username, $email, $role, $user_id);
                    }
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "User berhasil diupdate!";
                    } else {
                        $error = "Gagal mengupdate user: " . mysqli_error($koneksi);
                    }
                }
                break;
                
            case 'delete_user':
                $user_id = sanitize_input($_POST['user_id']);
                
                // Jangan hapus user yang sedang login
                if ($user_id == $_SESSION['user_id']) {
                    $error = "Tidak dapat menghapus user yang sedang login!";
                } else {
                    $delete_query = "DELETE FROM users WHERE id = ?";
                    $stmt = mysqli_prepare($koneksi, $delete_query);
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "User berhasil dihapus!";
                    } else {
                        $error = "Gagal menghapus user: " . mysqli_error($koneksi);
                    }
                }
                break;

            case 'give_warning':
                $user_id = (int)$_POST['user_id'];
                $warning_level = sanitize_input($_POST['warning_level']);
                $warning_reason = trim(sanitize_input($_POST['warning_reason']));
                $duration_value = (int)$_POST['warning_duration_value'];
                $duration_unit = sanitize_input($_POST['warning_duration_unit']);
                $admin_id = $_SESSION['user_id'];
                
                // Debug info
                error_log("WARNING DEBUG: user_id=$user_id, level=$warning_level, reason='$warning_reason', duration_value=$duration_value, duration_unit=$duration_unit");
                
                if (empty($warning_reason) || $duration_value <= 0 || empty($duration_unit)) {
                    $error = "Alasan warning, durasi dan satuan waktu harus diisi dengan lengkap!";
                    error_log("WARNING ERROR: Missing required fields");
                } else {
                    // Hitung tanggal kedaluwarsa berdasarkan durasi
                    $expires_at = null;
                    $duration_text = "";
                    
                    switch ($duration_unit) {
                        case 'minutes':
                            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_value} minutes"));
                            $duration_text = "{$duration_value} menit";
                            break;
                        case 'hours':
                            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_value} hours"));
                            $duration_text = "{$duration_value} jam";
                            break;
                        case 'days':
                            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_value} days"));
                            $duration_text = "{$duration_value} hari";
                            break;
                        case 'weeks':
                            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_value} weeks"));
                            $duration_text = "{$duration_value} minggu";
                            break;
                        case 'months':
                            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_value} months"));
                            $duration_text = "{$duration_value} bulan";
                            break;
                        case 'years':
                            $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_value} years"));
                            $duration_text = "{$duration_value} tahun";
                            break;
                        default:
                            $error = "Satuan waktu tidak valid!";
                            error_log("WARNING ERROR: Invalid duration unit: $duration_unit");
                            break;
                    }
                    
                    if (!isset($error) && $expires_at) {
                        // Insert warning ke database
                        $warning_query = "INSERT INTO user_warnings (user_id, warning_level, reason, given_by, given_at, expires_at, notification_shown) VALUES (?, ?, ?, ?, NOW(), ?, 0)";
                        $stmt = mysqli_prepare($koneksi, $warning_query);
                        
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "issis", $user_id, $warning_level, $warning_reason, $admin_id, $expires_at);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                // Update user warning count
                                $update_query = "UPDATE users SET warning_count = warning_count + 1, last_warning_at = NOW() WHERE id = ?";
                                $stmt2 = mysqli_prepare($koneksi, $update_query);
                                if ($stmt2) {
                                    mysqli_stmt_bind_param($stmt2, "i", $user_id);
                                    mysqli_stmt_execute($stmt2);
                                    mysqli_stmt_close($stmt2);
                                }
                                
                                $success = "Warning {$warning_level} berhasil diberikan untuk durasi {$duration_text}!";
                                
                                // Log admin action
                                error_log("ADMIN ACTION: Admin {$admin_id} gave {$warning_level} warning to user {$user_id} for {$duration_text}: {$warning_reason}");
                            } else {
                                $error = "Gagal memberikan warning: " . mysqli_error($koneksi);
                                error_log("WARNING SQL ERROR: " . mysqli_error($koneksi));
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            $error = "Gagal mempersiapkan query warning: " . mysqli_error($koneksi);
                            error_log("WARNING PREPARE ERROR: " . mysqli_error($koneksi));
                        }
                    }
                }
                break;

            case 'ban_user':
                $user_id = (int)$_POST['user_id'];
                $ban_duration_value = (int)$_POST['ban_duration_value'];
                $ban_duration_unit = sanitize_input($_POST['ban_duration_unit']);
                $ban_reason = trim(sanitize_input($_POST['ban_reason']));
                $admin_id = $_SESSION['user_id'];
                
                if (empty($ban_reason) || $ban_duration_value <= 0 || empty($ban_duration_unit)) {
                    $error = "Alasan ban, durasi dan satuan waktu harus diisi dengan benar!";
                } else {
                    // Hitung tanggal berakhir ban
                    $ban_until = date('Y-m-d H:i:s', strtotime("+$ban_duration_value $ban_duration_unit"));
                    
                    // Update user dengan status ban
                    $ban_query = "UPDATE users SET is_banned = 1, ban_until = ?, ban_reason = ?, banned_by = ?, banned_at = NOW() WHERE id = ?";
                    $stmt = mysqli_prepare($koneksi, $ban_query);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "ssii", $ban_until, $ban_reason, $admin_id, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            // Clear all existing warning sessions untuk user yang di-ban
                            $clear_sessions = "DELETE FROM warning_sessions WHERE user_id = ?";
                            $stmt_clear = mysqli_prepare($koneksi, $clear_sessions);
                            if ($stmt_clear) {
                                mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
                                mysqli_stmt_execute($stmt_clear);
                                mysqli_stmt_close($stmt_clear);
                            }
                            
                            $success = "User berhasil di-ban sampai " . date('d F Y H:i', strtotime($ban_until)) . "!";
                            
                            // Log admin action
                            error_log("ADMIN ACTION: Admin {$admin_id} banned user {$user_id} until {$ban_until}: {$ban_reason}");
                        } else {
                            $error = "Gagal ban user: " . mysqli_error($koneksi);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Gagal mempersiapkan query ban: " . mysqli_error($koneksi);
                    }
                }
                break;

            case 'unban_user':
                $user_id = (int)$_POST['user_id'];
                
                // Unban user
                $unban_query = "UPDATE users SET is_banned = 0, ban_until = NULL, ban_reason = NULL, banned_by = NULL, banned_at = NULL WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $unban_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Clear warning sessions when unbanning
                        $clear_sessions = "DELETE FROM warning_sessions WHERE user_id = ?";
                        $stmt_clear = mysqli_prepare($koneksi, $clear_sessions);
                        if ($stmt_clear) {
                            mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
                            mysqli_stmt_execute($stmt_clear);
                            mysqli_stmt_close($stmt_clear);
                        }
                        
                        $success = "User berhasil di-unban!";
                        
                        // Log admin action
                        error_log("ADMIN ACTION: Admin {$_SESSION['user_id']} unbanned user {$user_id}");
                    } else {
                        $error = "Gagal unban user: " . mysqli_error($koneksi);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Gagal mempersiapkan query unban: " . mysqli_error($koneksi);
                }
                break;

            case 'clear_warnings':
                $user_id = (int)$_POST['user_id'];
                
                // Set all warnings as expired
                $clear_query = "UPDATE user_warnings SET expires_at = NOW() WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())";
                $stmt = mysqli_prepare($koneksi, $clear_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Clear warning sessions
                        $clear_sessions = "DELETE FROM warning_sessions WHERE user_id = ?";
                        $stmt_clear = mysqli_prepare($koneksi, $clear_sessions);
                        if ($stmt_clear) {
                            mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
                            mysqli_stmt_execute($stmt_clear);
                            mysqli_stmt_close($stmt_clear);
                        }
                        
                        // Reset warning count
                        $reset_count = "UPDATE users SET warning_count = 0, last_warning_at = NULL WHERE id = ?";
                        $stmt_reset = mysqli_prepare($koneksi, $reset_count);
                        if ($stmt_reset) {
                            mysqli_stmt_bind_param($stmt_reset, "i", $user_id);
                            mysqli_stmt_execute($stmt_reset);
                            mysqli_stmt_close($stmt_reset);
                        }
                        
                        $success = "Semua warning user berhasil dihapus!";
                        
                        // Log admin action
                        error_log("ADMIN ACTION: Admin {$_SESSION['user_id']} cleared all warnings for user {$user_id}");
                    } else {
                        $error = "Gagal menghapus warning: " . mysqli_error($koneksi);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Gagal mempersiapkan query clear warning: " . mysqli_error($koneksi);
                }
                break;
        }
    }
}

// Create enhanced tables if not exists
$create_warnings_table = "CREATE TABLE IF NOT EXISTS user_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    warning_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    reason TEXT NOT NULL,
    given_by INT NOT NULL,
    given_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notification_shown TINYINT(1) DEFAULT 0,
    shown_at DATETIME NULL,
    expires_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (given_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_warnings_tracking (user_id, notification_shown, expires_at)
)";
mysqli_query($koneksi, $create_warnings_table);

$create_sessions_table = "CREATE TABLE IF NOT EXISTS warning_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    warning_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    shown_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (warning_id) REFERENCES user_warnings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session (user_id, warning_id, session_id),
    INDEX idx_session_tracking (user_id, session_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
mysqli_query($koneksi, $create_sessions_table);

// Add warning columns to users table if not exists
$add_warning_columns = "ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS warning_count INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_warning_at DATETIME NULL";
mysqli_query($koneksi, $add_warning_columns);

// Get all users with enhanced warning and ban info
$users_query = "SELECT u.id, u.username, u.email, u.role, u.created_at, u.is_banned, u.ban_until, u.ban_reason, u.warning_count, u.last_warning_at,
                    admin.username as banned_by_username,
                    (SELECT COUNT(*) FROM user_warnings w WHERE w.user_id = u.id AND (w.expires_at IS NULL OR w.expires_at > NOW())) as active_warnings,
                    (SELECT MAX(w.given_at) FROM user_warnings w WHERE w.user_id = u.id AND (w.expires_at IS NULL OR w.expires_at > NOW())) as latest_warning
                FROM users u
                LEFT JOIN users admin ON u.banned_by = admin.id
                ORDER BY u.created_at DESC";
$users_result = mysqli_query($koneksi, $users_query);

// Get user count
$user_count_query = "SELECT COUNT(*) as total FROM users";
$user_count_result = mysqli_query($koneksi, $user_count_query);
$user_count = mysqli_fetch_assoc($user_count_result)['total'];

// Function to get user status
function getUserStatus($user) {
    if ($user['is_banned'] && $user['ban_until'] && strtotime($user['ban_until']) > time()) {
        return 'banned';
    } elseif ($user['active_warnings'] > 0) {
        return 'warned';
    } else {
        return 'active';
    }
}

// Function to get status badge
function getStatusBadge($status, $user = null) {
    switch ($status) {
        case 'banned':
            return '<span class="status-badge status-banned">Banned</span>';
        case 'warned':
            $count = $user ? $user['active_warnings'] : 0;
            return '<span class="status-badge status-warned">Warning (' . $count . ')</span>';
        default:
            return '<span class="status-badge status-active">Active</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User</title>
    <link rel="icon" href="/project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Dark theme colors - Enhanced version */
    :root {
        --bg-primary: #1a1a1a;
        --bg-secondary: #2d2d2d;
        --bg-tertiary: #404040;
        --border-color: #555555;
        --text-primary: #ffffff;
        --text-secondary: #adb5bd;
        --accent-primary: #6c757d;
        --accent-hover: #5a6268;
        --success-color: #28a745;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
    }

    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Enhanced Card Styling */
    .card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }

    .card-header {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 12px 12px 0 0 !important;
        padding: 1.5rem;
    }

    .card-body {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        padding: 1.5rem;
    }

    /* Stats Cards */
    .stats-card {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 1.5rem;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }

    .stats-card .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: var(--accent-primary);
        margin-bottom: 0.5rem;
    }

    .stats-card .stats-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Enhanced Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 8px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-action::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .btn-action:hover::before {
        left: 100%;
    }

    .btn-edit {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .btn-edit:hover {
        background: linear-gradient(135deg, #5a6268, #495057);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-delete {
        background: linear-gradient(135deg, #495057, #343a40);
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .btn-delete:hover {
        background: linear-gradient(135deg, #343a40, #23272b);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-view {
        background: linear-gradient(135deg, var(--info-color), #138496);
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .btn-view:hover {
        background: linear-gradient(135deg, #138496, #0f6674);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    /* Enhanced Table */
    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
        background-color: var(--bg-secondary);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .table {
        margin-bottom: 0;
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        min-width: 800px;
    }

    .table th {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        border-bottom: 2px solid var(--border-color);
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
    }

    .table td {
        vertical-align: middle;
        padding: 15px 12px;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        transition: background-color 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: var(--bg-tertiary);
        transform: scale(1.01);
        transition: all 0.3s ease;
    }

    .table tbody tr:hover td {
        background-color: var(--bg-tertiary);
    }

    /* Enhanced Badges */
    .badge {
        font-size: 11px;
        font-weight: 600;
        padding: 6px 10px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover));
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .badge-success {
        background: linear-gradient(135deg, var(--success-color), #1e7e34);
    }

    .badge-warning {
        background: linear-gradient(135deg, var(--warning-color), #e0a800);
        color: #000;
    }

    .badge-info {
        background: linear-gradient(135deg, var(--info-color), #117a8b);
    }

    /* Enhanced Modal */
    .modal-content {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        border-bottom: 1px solid var(--border-color);
        border-radius: 12px 12px 0 0;
    }

    .modal-title {
        color: var(--text-primary);
        font-weight: 600;
    }

    .modal-footer {
        background-color: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
        border-radius: 0 0 12px 12px;
    }

    /* Enhanced Form Controls */
    .form-control,
    .form-select {
        background-color: var(--bg-tertiary);
        border: 2px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 8px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
        background-color: var(--bg-tertiary);
        border-color: var(--accent-primary);
        color: var(--text-primary);
        box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        transform: translateY(-2px);
    }

    .form-label {
        color: var(--text-primary);
        font-weight: 500;
        margin-bottom: 8px;
    }

    /* Enhanced Alerts */
    .alert {
        border-radius: 8px;
        border: none;
        font-weight: 500;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .alert-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }

    .alert-success {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
    }

    /* Enhanced Buttons */
    .btn {
        border-radius: 8px;
        font-weight: 500;
        padding: 10px 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover));
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--accent-hover), #495057);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #495057, #343a40);
        border: none;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        border: none;
    }

    /* Date formatting */
    .date-text {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-style: italic;
    }

    /* Scroll hint enhancement */
    .scroll-hint {
        background: linear-gradient(135deg, var(--bg-tertiary), var(--accent-primary));
        color: var(--text-primary);
        padding: 10px 15px;
        border-radius: 8px;
        font-size: 12px;
        text-align: center;
        margin-bottom: 15px;
        border: 1px solid var(--border-color);
    }

    /* Mobile responsive enhancements */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 10px;
        }

        .card-header {
            padding: 1rem;
            flex-direction: column;
            gap: 1rem;
        }

        .stats-card {
            margin-bottom: 1rem;
        }

        .stats-card .stats-number {
            font-size: 2rem;
        }
    }

    /* Loading animation */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, .3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        height: 10px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background-color: var(--bg-tertiary);
        border-radius: 5px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--accent-primary), var(--accent-hover));
        border-radius: 5px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--accent-hover), #495057);
    }
    </style>
</head>

<body>
    <div class="container-fluid p-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-users"></i> Kelola User
                            <small class="text-muted">(Smart Warning System)</small>
                        </h4>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i> Tambah User
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Total User: <?php echo $user_count; ?></small>
                            </div>
                            <div>
                                <button class="btn btn-info btn-sm" onclick="cleanExpiredWarnings()">
                                    <i class="fas fa-broom"></i> Bersihkan Warning Expired
                                </button>
                            </div>
                        </div>

                        <!-- Scroll hint for mobile -->
                        <div class="scroll-hint">
                            <i class="fas fa-arrows-alt-h"></i> Geser ke kiri/kanan untuk melihat seluruh tabel
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Warning Info</th>
                                        <th>Tanggal Daftar</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
                                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                    <?php $status = getUserStatus($user); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php
                                            $role_class = '';
                                            switch ($user['role']) {
                                                case 'admin':
                                                    $role_class = 'bg-danger';
                                                    break;
                                                case 'penulis':
                                                    $role_class = 'bg-warning';
                                                    break;
                                                case 'pembaca':
                                                    $role_class = 'bg-primary';
                                                    break;
                                                default:
                                                    $role_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?php echo $role_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($status, $user); ?>
                                            <?php if ($status === 'banned' && $user['ban_until']): ?>
                                            <small class="text-muted d-block">
                                                Until: <?php echo date('d/m/Y H:i', strtotime($user['ban_until'])); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['warning_count'] > 0 || $user['active_warnings'] > 0): ?>
                                            <div class="warning-info">
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    Total: <?php echo $user['warning_count']; ?> |
                                                    Aktif: <?php echo $user['active_warnings']; ?>
                                                </small>
                                                <?php if ($user['latest_warning']): ?>
                                                <small class="text-muted d-block">
                                                    Terakhir:
                                                    <?php echo date('d/m H:i', strtotime($user['latest_warning'])); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            <?php else: ?>
                                            <small class="text-muted">Tidak ada warning</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action btn-edit" title="Edit User"
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <?php if ($status === 'banned'): ?>
                                                <button type="button" class="btn-action btn-unban" title="Unban User"
                                                    onclick="unbanUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-unlock"></i>
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="btn-action btn-warning"
                                                    title="Give Warning"
                                                    onclick="giveWarning(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-ban" title="Ban User"
                                                    onclick="banUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                <?php endif; ?>

                                                <?php if ($user['warning_count'] > 0 || $user['active_warnings'] > 0): ?>
                                                <button type="button" class="btn-action btn-clear"
                                                    title="Clear Warnings"
                                                    onclick="clearWarnings(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-eraser"></i>
                                                </button>
                                                <?php endif; ?>

                                                <button type="button" class="btn-action btn-delete" title="Hapus User"
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Tidak ada user ditemukan</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah User -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="addUserForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah User Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="penulis">Penulis</option>
                                <option value="pembaca">Pembaca</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editUserForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password
                                <small class="text-muted">(kosongkan jika tidak ingin diubah)</small>
                            </label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="penulis">Penulis</option>
                                <option value="pembaca">Pembaca</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Enhanced Modal Give Warning with Flexible Duration -->
    <div class="modal fade" id="warningModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="warningForm">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> Berikan Warning
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="give_warning">
                        <input type="hidden" name="user_id" id="warning_user_id">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Smart Warning System:</strong> Warning akan otomatis expire sesuai durasi yang
                            dipilih dan tidak akan spam notifikasi ke user.
                        </div>

                        <div class="mb-3">
                            <p>Memberikan warning kepada: <strong id="warning_username"></strong></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Level Warning</label>
                            <div class="warning-level">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="warning_level" value="low"
                                        id="warning_low" checked>
                                    <label class="form-check-label" for="warning_low">
                                        <span class="badge bg-info">Ringan</span>
                                        <small class="text-muted d-block">Peringatan ringan - cooldown 30 menit</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="warning_level" value="medium"
                                        id="warning_medium">
                                    <label class="form-check-label" for="warning_medium">
                                        <span class="badge bg-warning">Sedang</span>
                                        <small class="text-muted d-block">Peringatan sedang - cooldown 15 menit</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="warning_level" value="high"
                                        id="warning_high">
                                    <label class="form-check-label" for="warning_high">
                                        <span class="badge bg-danger">Berat</span>
                                        <small class="text-muted d-block">Peringatan berat - selalu ditampilkan</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Durasi Warning</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="warning_duration_value"
                                        id="warning_duration_value" placeholder="Jumlah" min="1" max="999" value="24"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" name="warning_duration_unit" id="warning_duration_unit"
                                        required>
                                        <option value="">Pilih Satuan</option>
                                        <option value="minutes">Menit</option>
                                        <option value="hours" selected>Jam</option>
                                        <option value="days">Hari</option>
                                        <option value="weeks">Minggu</option>
                                        <option value="months">Bulan</option>
                                        <option value="years">Tahun</option>
                                    </select>
                                </div>
                            </div>
                            <small class="text-muted">Warning akan otomatis expire setelah durasi ini</small>
                        </div>

                        <div class="mb-3">
                            <label for="warning_reason" class="form-label">Alasan Warning</label>
                            <textarea class="form-control" id="warning_reason" name="warning_reason" rows="3"
                                placeholder="Jelaskan alasan warning..." required></textarea>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-lightbulb"></i>
                            <strong>Tips:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Warning akan otomatis menghilang setelah durasi habis</li>
                                <li>User tidak akan spam dengan notifikasi warning yang sama</li>
                                <li>Durasi bisa disesuaikan dari menit hingga tahun</li>
                                <li>Sistem akan mencegah warning berlebihan</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning" id="submitWarningBtn">
                            <i class="fas fa-exclamation-triangle"></i> Berikan Warning
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ban User -->
    <div class="modal fade" id="banModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="banForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-ban"></i> Ban User
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ban_user">
                        <input type="hidden" name="user_id" id="ban_user_id">

                        <div class="mb-3">
                            <p>Akan ban user: <strong id="ban_username"></strong></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Durasi Ban</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="ban_duration_value"
                                        id="ban_duration_value" placeholder="Jumlah" min="1" max="999" required>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" name="ban_duration_unit" id="ban_duration_unit"
                                        required>
                                        <option value="">Pilih Satuan</option>
                                        <option value="minutes">Menit</option>
                                        <option value="hours">Jam</option>
                                        <option value="days">Hari</option>
                                        <option value="weeks">Minggu</option>
                                        <option value="months">Bulan</option>
                                        <option value="years">Tahun</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="ban_reason" class="form-label">Alasan Ban</label>
                            <textarea class="form-control" id="ban_reason" name="ban_reason" rows="3"
                                placeholder="Jelaskan alasan ban..." required></textarea>
                        </div>

                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Peringatan:</strong> User yang di-ban tidak akan bisa mengakses website sampai masa
                            ban berakhir. Semua warning session akan dihapus.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban"></i> Ban User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Unban User -->
    <div class="modal fade" id="unbanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="unbanForm">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-unlock"></i> Unban User
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="unban_user">
                        <input type="hidden" name="user_id" id="unban_user_id">
                        <p>Apakah Anda yakin ingin unban user <strong id="unban_username"></strong>?</p>
                        <p class="text-success">User akan dapat mengakses website kembali setelah di-unban.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-unlock"></i> Unban User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Clear Warnings -->
    <div class="modal fade" id="clearWarningModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="clearWarningForm">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-eraser"></i> Hapus Semua Warning
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="clear_warnings">
                        <input type="hidden" name="user_id" id="clear_warning_user_id">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Aksi ini akan:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Menghapus semua warning aktif untuk user</li>
                                <li>Mereset counter warning ke 0</li>
                                <li>Menghapus semua session warning</li>
                                <li>User tidak akan melihat notifikasi warning lagi</li>
                            </ul>
                        </div>

                        <p>Apakah Anda yakin ingin menghapus semua warning untuk user <strong
                                id="clear_warning_username"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-eraser"></i> Hapus Semua Warning
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Confirm Delete -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteUserForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-trash"></i> Konfirmasi Hapus
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Apakah Anda yakin ingin menghapus user <strong id="delete_username"></strong>?</p>
                        <p class="text-danger">Tindakan ini tidak dapat dibatalkan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Hapus User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Global variables for debugging
    window.userSystemDebug = true;

    function debugLog(message, data = null) {
        if (window.userSystemDebug) {
            console.log('[USER SYSTEM] ' + message, data || '');
        }
    }

    function editUser(user) {
        debugLog('Opening edit modal for user:', user);
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_password').value = '';

        var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editModal.show();
    }

    function giveWarning(userId, username) {
        debugLog('Opening warning modal for user:', {
            userId,
            username
        });

        // Clear previous values first
        document.getElementById('warning_user_id').value = '';
        document.getElementById('warning_username').textContent = '';
        document.getElementById('warning_reason').value = '';

        // Set new values
        document.getElementById('warning_user_id').value = userId;
        document.getElementById('warning_username').textContent = username;

        // Reset form to default values
        document.getElementById('warning_low').checked = true;
        document.getElementById('warning_duration_value').value = '24';
        document.getElementById('warning_duration_unit').value = 'hours';

        // Enable submit button
        const submitBtn = document.getElementById('submitWarningBtn');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Berikan Warning';

        var warningModal = new bootstrap.Modal(document.getElementById('warningModal'));
        warningModal.show();

        debugLog('Warning modal opened with values:', {
            userId: document.getElementById('warning_user_id').value,
            username: document.getElementById('warning_username').textContent
        });
    }

    function banUser(userId, username) {
        debugLog('Opening ban modal for user:', {
            userId,
            username
        });
        document.getElementById('ban_user_id').value = userId;
        document.getElementById('ban_username').textContent = username;

        // Reset ban form
        document.getElementById('ban_duration_value').value = '';
        document.getElementById('ban_duration_unit').value = '';
        document.getElementById('ban_reason').value = '';

        var banModal = new bootstrap.Modal(document.getElementById('banModal'));
        banModal.show();
    }

    function unbanUser(userId, username) {
        debugLog('Opening unban modal for user:', {
            userId,
            username
        });
        document.getElementById('unban_user_id').value = userId;
        document.getElementById('unban_username').textContent = username;

        var unbanModal = new bootstrap.Modal(document.getElementById('unbanModal'));
        unbanModal.show();
    }

    function clearWarnings(userId, username) {
        debugLog('Opening clear warning modal for user:', {
            userId,
            username
        });
        document.getElementById('clear_warning_user_id').value = userId;
        document.getElementById('clear_warning_username').textContent = username;

        var clearModal = new bootstrap.Modal(document.getElementById('clearWarningModal'));
        clearModal.show();
    }

    function deleteUser(userId, username) {
        debugLog('Opening delete modal for user:', {
            userId,
            username
        });
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('delete_username').textContent = username;

        var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        deleteModal.show();
    }

    // Enhanced form validation for warning
    document.getElementById('warningForm').addEventListener('submit', function(e) {
        debugLog('Warning form submitted');

        const userId = document.getElementById('warning_user_id').value;
        const durationValue = document.getElementById('warning_duration_value').value;
        const durationUnit = document.getElementById('warning_duration_unit').value;
        const reason = document.getElementById('warning_reason').value.trim();
        const warningLevel = document.querySelector('input[name="warning_level"]:checked');

        debugLog('Form values:', {
            userId: userId,
            durationValue: durationValue,
            durationUnit: durationUnit,
            reason: reason,
            warningLevel: warningLevel ? warningLevel.value : 'none'
        });

        // Validation
        if (!userId || userId === '') {
            e.preventDefault();
            alert('Error: User ID tidak valid!');
            debugLog('ERROR: Missing user ID');
            return false;
        }

        if (!durationValue || !durationUnit || !reason || !warningLevel) {
            e.preventDefault();
            alert('Mohon lengkapi semua field: level warning, durasi, satuan waktu, dan alasan warning!');
            debugLog('ERROR: Missing required fields');
            return false;
        }

        if (parseInt(durationValue) <= 0) {
            e.preventDefault();
            alert('Durasi harus lebih dari 0!');
            debugLog('ERROR: Invalid duration value');
            return false;
        }

        if (reason.length < 5) {
            e.preventDefault();
            alert('Alasan warning harus minimal 5 karakter!');
            debugLog('ERROR: Reason too short');
            return false;
        }

        // Confirm before submitting
        const username = document.getElementById('warning_username').textContent;
        const level = warningLevel.value;
        const confirmation = confirm(
            `Apakah Anda yakin ingin memberikan warning ${level} kepada ${username} selama ${durationValue} ${durationUnit}?\n\nAlasan: ${reason}`
        );

        if (!confirmation) {
            e.preventDefault();
            debugLog('User cancelled warning confirmation');
            return false;
        }

        // Show loading state
        const submitBtn = document.getElementById('submitWarningBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        submitBtn.disabled = true;

        debugLog('Warning form validation passed, submitting...');
        return true;
    });

    // Form validation for ban
    document.getElementById('banForm').addEventListener('submit', function(e) {
        const userId = document.getElementById('ban_user_id').value;
        const durationValue = document.getElementById('ban_duration_value').value;
        const durationUnit = document.getElementById('ban_duration_unit').value;
        const reason = document.getElementById('ban_reason').value.trim();

        if (!userId || !durationValue || !durationUnit || !reason) {
            e.preventDefault();
            alert('Mohon lengkapi semua field ban!');
            return false;
        }

        if (parseInt(durationValue) <= 0) {
            e.preventDefault();
            alert('Durasi ban harus lebih dari 0!');
            return false;
        }

        const username = document.getElementById('ban_username').textContent;
        const confirmation = confirm(
            `Apakah Anda yakin ingin ban user ${username} selama ${durationValue} ${durationUnit}?`);

        if (!confirmation) {
            e.preventDefault();
            return false;
        }
    });

    // Function to clean expired warnings
    function cleanExpiredWarnings() {
        if (confirm('Apakah Anda yakin ingin membersihkan semua warning yang sudah expired?')) {
            const button = event.target;
            const originalText = button.innerHTML;

            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membersihkan...';
            button.disabled = true;

            fetch('clean_warnings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'clean_expired'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    button.innerHTML = originalText;
                    button.disabled = false;

                    if (data.success) {
                        alert(`Berhasil membersihkan ${data.cleaned_count} warning expired`);
                        location.reload();
                    } else {
                        alert('Gagal membersihkan warning: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                    alert('Terjadi kesalahan saat membersihkan warning');
                });
        }
    }

    // Auto-refresh warning counts every 30 seconds
    setInterval(() => {
        debugLog('Auto-refreshing warning data...');

        fetch('realtime_warning.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    debugLog('Warning data updated:', data);
                    // Update warning counts in table
                    data.users.forEach(user => {
                        const row = document.querySelector(`tr[data-user-id="${user.id}"]`);
                        if (row) {
                            const warningCell = row.querySelector('.warning-info');
                            if (warningCell && user.active_warnings !== undefined) {
                                // Update warning display
                                if (user.active_warnings > 0) {
                                    warningCell.innerHTML = `
                                        <small class="text-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Total: ${user.warning_count} | Aktif: ${user.active_warnings}
                                        </small>
                                        ${user.latest_warning ? `
                                        <small class="text-muted d-block">
                                            Terakhir: ${new Date(user.latest_warning).toLocaleDateString('id-ID')}
                                        </small>
                                        ` : ''}
                                    `;
                                }
                            }
                        }
                    });
                }
            })
            .catch(error => {
                debugLog('Warning stats update failed:', error);
            });
    }, 30000);

    // Add data attributes for easier targeting
    document.addEventListener('DOMContentLoaded', function() {
        debugLog('DOM loaded, initializing user system...');

        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const userIdCell = row.querySelector('td:first-child');
            if (userIdCell) {
                row.setAttribute('data-user-id', userIdCell.textContent);
            }
        });

        // Ensure warning duration unit has default value
        const durationUnit = document.getElementById('warning_duration_unit');
        if (durationUnit && !durationUnit.value) {
            durationUnit.value = 'hours';
        }

        debugLog('User system initialized successfully');
    });

    // Dynamic duration preview
    function updateDurationPreview() {
        const value = document.getElementById('warning_duration_value').value;
        const unit = document.getElementById('warning_duration_unit').value;

        if (value && unit) {
            let unitText = '';
            switch (unit) {
                case 'minutes':
                    unitText = 'menit';
                    break;
                case 'hours':
                    unitText = 'jam';
                    break;
                case 'days':
                    unitText = 'hari';
                    break;
                case 'weeks':
                    unitText = 'minggu';
                    break;
                case 'months':
                    unitText = 'bulan';
                    break;
                case 'years':
                    unitText = 'tahun';
                    break;
            }

            debugLog(`Duration preview: ${value} ${unitText}`);

            // Update the help text
            const helpText = document.querySelector('.warning-duration-grid + small');
            if (helpText) {
                helpText.textContent = `Warning akan otomatis expire setelah ${value} ${unitText}`;
            }
        }
    }

    // Add event listeners for duration preview
    document.addEventListener('DOMContentLoaded', function() {
        const durationValue = document.getElementById('warning_duration_value');
        const durationUnit = document.getElementById('warning_duration_unit');

        if (durationValue && durationUnit) {
            durationValue.addEventListener('input', updateDurationPreview);
            durationUnit.addEventListener('change', updateDurationPreview);
        }
    });

    // Reset forms when modals are hidden
    document.getElementById('warningModal').addEventListener('hidden.bs.modal', function() {
        debugLog('Warning modal closed, resetting form');
        document.getElementById('warningForm').reset();
        document.getElementById('warning_low').checked = true;
        document.getElementById('warning_duration_value').value = '24';
        document.getElementById('warning_duration_unit').value = 'hours';

        // Reset submit button
        const submitBtn = document.getElementById('submitWarningBtn');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Berikan Warning';
    });

    // Add form debugging
    ['warningForm', 'banForm', 'unbanForm', 'clearWarningForm', 'deleteUserForm'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                debugLog(`Form ${formId} submitted with data:`, new FormData(form));
            });
        }
    });
    </script>
</body>

</html>