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
                $user_id = sanitize_input($_POST['user_id']);
                $warning_level = sanitize_input($_POST['warning_level']);
                $warning_reason = sanitize_input($_POST['warning_reason']);
                $duration_value = (int)$_POST['warning_duration_value'];
                $duration_unit = sanitize_input($_POST['warning_duration_unit']);
                $admin_id = $_SESSION['user_id'];
                
                if (empty($warning_reason) || $duration_value <= 0 || empty($duration_unit)) {
                    $error = "Alasan warning, durasi dan satuan waktu harus diisi!";
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
                            break;
                    }
                    
                    if (!isset($error)) {
                        // Insert warning ke database
                        $warning_query = "INSERT INTO user_warnings (user_id, warning_level, reason, given_by, given_at, expires_at, notification_shown) VALUES (?, ?, ?, ?, NOW(), ?, 0)";
                        $stmt = mysqli_prepare($koneksi, $warning_query);
                        mysqli_stmt_bind_param($stmt, "ississ", $user_id, $warning_level, $warning_reason, $admin_id, $expires_at);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            // Update user warning count
                            $update_query = "UPDATE users SET warning_count = warning_count + 1, last_warning_at = NOW() WHERE id = ?";
                            $stmt2 = mysqli_prepare($koneksi, $update_query);
                            mysqli_stmt_bind_param($stmt2, "i", $user_id);
                            mysqli_stmt_execute($stmt2);
                            
                            $success = "Warning {$warning_level} berhasil diberikan untuk durasi {$duration_text}!";
                            
                            // Log admin action
                            error_log("Admin {$admin_id} gave {$warning_level} warning to user {$user_id} for {$duration_text}: {$warning_reason}");
                        } else {
                            $error = "Gagal memberikan warning: " . mysqli_error($koneksi);
                        }
                    }
                }
                break;

            case 'ban_user':
                $user_id = sanitize_input($_POST['user_id']);
                $ban_duration_value = (int)$_POST['ban_duration_value'];
                $ban_duration_unit = sanitize_input($_POST['ban_duration_unit']);
                $ban_reason = sanitize_input($_POST['ban_reason']);
                $admin_id = $_SESSION['user_id'];
                
                if (empty($ban_reason) || $ban_duration_value <= 0) {
                    $error = "Alasan ban dan durasi harus diisi dengan benar!";
                } else {
                    // Hitung tanggal berakhir ban
                    $ban_until = date('Y-m-d H:i:s', strtotime("+$ban_duration_value $ban_duration_unit"));
                    
                    // Update user dengan status ban
                    $ban_query = "UPDATE users SET is_banned = 1, ban_until = ?, ban_reason = ?, banned_by = ?, banned_at = NOW() WHERE id = ?";
                    $stmt = mysqli_prepare($koneksi, $ban_query);
                    mysqli_stmt_bind_param($stmt, "ssii", $ban_until, $ban_reason, $admin_id, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Clear all existing warning sessions untuk user yang di-ban
                        $clear_sessions = "DELETE FROM warning_sessions WHERE user_id = ?";
                        $stmt_clear = mysqli_prepare($koneksi, $clear_sessions);
                        mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
                        mysqli_stmt_execute($stmt_clear);
                        
                        $success = "User berhasil di-ban sampai " . date('d F Y H:i', strtotime($ban_until)) . "!";
                        
                        // Log admin action
                        error_log("Admin {$admin_id} banned user {$user_id} until {$ban_until}: {$ban_reason}");
                    } else {
                        $error = "Gagal ban user: " . mysqli_error($koneksi);
                    }
                }
                break;

            case 'unban_user':
                $user_id = sanitize_input($_POST['user_id']);
                
                // Unban user
                $unban_query = "UPDATE users SET is_banned = 0, ban_until = NULL, ban_reason = NULL, banned_by = NULL, banned_at = NULL WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $unban_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Clear warning sessions when unbanning
                    $clear_sessions = "DELETE FROM warning_sessions WHERE user_id = ?";
                    $stmt_clear = mysqli_prepare($koneksi, $clear_sessions);
                    mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
                    mysqli_stmt_execute($stmt_clear);
                    
                    $success = "User berhasil di-unban!";
                    
                    // Log admin action
                    error_log("Admin {$_SESSION['user_id']} unbanned user {$user_id}");
                } else {
                    $error = "Gagal unban user: " . mysqli_error($koneksi);
                }
                break;

            case 'clear_warnings':
                $user_id = sanitize_input($_POST['user_id']);
                
                // Set all warnings as expired
                $clear_query = "UPDATE user_warnings SET expires_at = NOW() WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())";
                $stmt = mysqli_prepare($koneksi, $clear_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Clear warning sessions
                    $clear_sessions = "DELETE FROM warning_sessions WHERE user_id = ?";
                    $stmt_clear = mysqli_prepare($koneksi, $clear_sessions);
                    mysqli_stmt_bind_param($stmt_clear, "i", $user_id);
                    mysqli_stmt_execute($stmt_clear);
                    
                    // Reset warning count
                    $reset_count = "UPDATE users SET warning_count = 0, last_warning_at = NULL WHERE id = ?";
                    $stmt_reset = mysqli_prepare($koneksi, $reset_count);
                    mysqli_stmt_bind_param($stmt_reset, "i", $user_id);
                    mysqli_stmt_execute($stmt_reset);
                    
                    $success = "Semua warning user berhasil dihapus!";
                    
                    // Log admin action
                    error_log("Admin {$_SESSION['user_id']} cleared all warnings for user {$user_id}");
                } else {
                    $error = "Gagal menghapus warning: " . mysqli_error($koneksi);
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
    <link rel="stylesheet" href="/project/style/user.css">
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
                <form method="POST">
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
                <form method="POST">
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
                            <div class="warning-duration-grid">
                                <input type="number" class="form-control" name="warning_duration_value"
                                    id="warning_duration_value" placeholder="Jumlah" min="1" max="999" value="24"
                                    required>
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
                        <button type="submit" class="btn btn-warning">
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
                <form method="POST">
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
                            <div class="ban-duration-grid">
                                <input type="number" class="ban-value-input" name="ban_duration_value"
                                    placeholder="Jumlah" min="1" max="999" required>
                                <select class="ban-unit-select" name="ban_duration_unit" required>
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
                <form method="POST">
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
                <form method="POST">
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
                <form method="POST">
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
    function editUser(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_password').value = '';

        var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editModal.show();
    }

    function giveWarning(userId, username) {
        document.getElementById('warning_user_id').value = userId;
        document.getElementById('warning_username').textContent = username;
        document.getElementById('warning_reason').value = '';

        // Reset form to default values
        document.getElementById('warning_low').checked = true;
        document.getElementById('warning_duration_value').value = '24';
        document.getElementById('warning_duration_unit').value = 'hours';

        var warningModal = new bootstrap.Modal(document.getElementById('warningModal'));
        warningModal.show();
    }

    function banUser(userId, username) {
        document.getElementById('ban_user_id').value = userId;
        document.getElementById('ban_username').textContent = username;
        document.getElementById('ban_reason').value = '';

        // Reset ban form
        document.querySelector('input[name="ban_duration_value"]').value = '';
        document.querySelector('select[name="ban_duration_unit"]').value = '';

        var banModal = new bootstrap.Modal(document.getElementById('banModal'));
        banModal.show();
    }

    function unbanUser(userId, username) {
        document.getElementById('unban_user_id').value = userId;
        document.getElementById('unban_username').textContent = username;

        var unbanModal = new bootstrap.Modal(document.getElementById('unbanModal'));
        unbanModal.show();
    }

    function clearWarnings(userId, username) {
        document.getElementById('clear_warning_user_id').value = userId;
        document.getElementById('clear_warning_username').textContent = username;

        var clearModal = new bootstrap.Modal(document.getElementById('clearWarningModal'));
        clearModal.show();
    }

    function deleteUser(userId, username) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('delete_username').textContent = username;

        var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        deleteModal.show();
    }

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

    // Enhanced form validation for warning duration
    document.getElementById('warningForm').addEventListener('submit', function(e) {
        const durationValue = document.getElementById('warning_duration_value').value;
        const durationUnit = document.getElementById('warning_duration_unit').value;
        const reason = document.getElementById('warning_reason').value.trim();

        if (!durationValue || !durationUnit || !reason) {
            e.preventDefault();
            alert('Mohon lengkapi semua field: durasi, satuan waktu, dan alasan warning!');
            return false;
        }

        if (parseInt(durationValue) <= 0) {
            e.preventDefault();
            alert('Durasi harus lebih dari 0!');
            return false;
        }

        // Confirm before submitting
        const username = document.getElementById('warning_username').textContent;
        const level = document.querySelector('input[name="warning_level"]:checked').value;
        const confirmation = confirm(
            `Apakah Anda yakin ingin memberikan warning ${level} kepada ${username} selama ${durationValue} ${durationUnit}?`
        );

        if (!confirmation) {
            e.preventDefault();
            return false;
        }

        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        submitBtn.disabled = true;
    });

    // Auto-refresh warning counts every 30 seconds
    setInterval(() => {
        console.log('Auto-refreshing warning data...');

        fetch('realtime_warning.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
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
                console.log('Warning stats update failed:', error);
            });
    }, 30000);

    // Add data attributes for easier targeting
    document.addEventListener('DOMContentLoaded', function() {
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

            console.log(`Warning akan aktif selama ${value} ${unitText}`);

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
    </script>
</body>

</html>