<?php
// Include required files
require_once '../config/config.php';
require_once '../config/auth_check.php';

// Check if user is admin
checkAdminRole();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $password = sanitize_input($_POST['password']);
                $full_name = sanitize_input($_POST['full_name']);
                $role = sanitize_input($_POST['role']);
                
                // Validasi input
                if (empty($username) || empty($email) || empty($password) || empty($role)) {
                    $error = "Semua field wajib harus diisi!";
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
                        $insert_query = "INSERT INTO users (username, email, full_name, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                        $stmt = mysqli_prepare($koneksi, $insert_query);
                        mysqli_stmt_bind_param($stmt, "sssss", $username, $email, $full_name, $hashed_password, $role);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "User berhasil ditambahkan!";
                        } else {
                            $error = "Gagal menambahkan user: " . mysqli_error($koneksi);
                        }
                    }
                }
                break;
                
            case 'edit_user':
                $user_id = intval($_POST['user_id']);
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $full_name = sanitize_input($_POST['full_name']);
                $role = sanitize_input($_POST['role']);
                $password = sanitize_input($_POST['password']);
                
                if (empty($username) || empty($email) || empty($role)) {
                    $error = "Username, email, dan role harus diisi!";
                } else {
                    // Update query
                    if (!empty($password)) {
                        $hashed_password = hash_password($password);
                        $update_query = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, password = ? WHERE id = ?";
                        $stmt = mysqli_prepare($koneksi, $update_query);
                        mysqli_stmt_bind_param($stmt, "sssssi", $username, $email, $full_name, $role, $hashed_password, $user_id);
                    } else {
                        $update_query = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?";
                        $stmt = mysqli_prepare($koneksi, $update_query);
                        mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $full_name, $role, $user_id);
                    }
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "User berhasil diupdate!";
                    } else {
                        $error = "Gagal mengupdate user: " . mysqli_error($koneksi);
                    }
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                
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
                $user_id = intval($_POST['user_id']);
                $warning_level = sanitize_input($_POST['warning_level']);
                $warning_reason = trim(sanitize_input($_POST['warning_reason']));
                $duration_value = intval($_POST['warning_duration_value']);
                $duration_unit = sanitize_input($_POST['warning_duration_unit']);
                $admin_id = $_SESSION['user_id'];
                
                if (empty($warning_reason) || $duration_value <= 0 || empty($duration_unit)) {
                    $error = "Alasan warning, durasi dan satuan waktu harus diisi dengan lengkap!";
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
                            } else {
                                $error = "Gagal memberikan warning: " . mysqli_error($koneksi);
                            }
                            mysqli_stmt_close($stmt);
                        } else {
                            $error = "Gagal mempersiapkan query warning: " . mysqli_error($koneksi);
                        }
                    }
                }
                break;

            case 'ban_user':
                $user_id = intval($_POST['user_id']);
                $ban_duration_value = intval($_POST['ban_duration_value']);
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
                            $success = "User berhasil di-ban sampai " . date('d F Y H:i', strtotime($ban_until)) . "!";
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
                $user_id = intval($_POST['user_id']);
                
                // Unban user
                $unban_query = "UPDATE users SET is_banned = 0, ban_until = NULL, ban_reason = NULL, banned_by = NULL, banned_at = NULL WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $unban_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "User berhasil di-unban!";
                    } else {
                        $error = "Gagal unban user: " . mysqli_error($koneksi);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Gagal mempersiapkan query unban: " . mysqli_error($koneksi);
                }
                break;

            case 'clear_warnings':
                $user_id = intval($_POST['user_id']);
                
                // Set all warnings as expired
                $clear_query = "UPDATE user_warnings SET expires_at = NOW() WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())";
                $stmt = mysqli_prepare($koneksi, $clear_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Reset warning count
                        $reset_count = "UPDATE users SET warning_count = 0, last_warning_at = NULL WHERE id = ?";
                        $stmt_reset = mysqli_prepare($koneksi, $reset_count);
                        if ($stmt_reset) {
                            mysqli_stmt_bind_param($stmt_reset, "i", $user_id);
                            mysqli_stmt_execute($stmt_reset);
                            mysqli_stmt_close($stmt_reset);
                        }
                        
                        $success = "Semua warning user berhasil dihapus!";
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

// Get all users with enhanced warning and ban info
$users_query = "SELECT u.id, u.username, u.email, u.full_name, u.role, u.created_at, u.is_banned, u.ban_until, u.ban_reason, u.warning_count, u.last_warning_at,
                    admin.username as banned_by_username,
                    (SELECT COUNT(*) FROM user_warnings w WHERE w.user_id = u.id AND (w.expires_at IS NULL OR w.expires_at > NOW())) as active_warnings,
                    (SELECT MAX(w.given_at) FROM user_warnings w WHERE w.user_id = u.id AND (w.expires_at IS NULL OR w.expires_at > NOW())) as latest_warning,
                    (SELECT COUNT(*) FROM articles WHERE author_id = u.id AND article_status = 'published') as published_articles,
                    (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as total_comments
                FROM users u
                LEFT JOIN users admin ON u.banned_by = admin.id
                ORDER BY u.created_at DESC";
$users_result = mysqli_query($koneksi, $users_query);

// Get user count by role
$role_counts_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$role_counts_result = mysqli_query($koneksi, $role_counts_query);
$role_counts = [];
while ($row = mysqli_fetch_assoc($role_counts_result)) {
    $role_counts[$row['role']] = $row['count'];
}

// Get total user count
$user_count_query = "SELECT COUNT(*) as total FROM users";
$user_count_result = mysqli_query($koneksi, $user_count_query);
$user_count = mysqli_fetch_assoc($user_count_result)['total'];

// Get banned users count
$banned_count_query = "SELECT COUNT(*) as count FROM users WHERE is_banned = 1 AND ban_until > NOW()";
$banned_count_result = mysqli_query($koneksi, $banned_count_query);
$banned_count = mysqli_fetch_assoc($banned_count_result)['count'];

// Get warned users count
$warned_count_query = "SELECT COUNT(DISTINCT user_id) as count FROM user_warnings WHERE expires_at > NOW()";
$warned_count_result = mysqli_query($koneksi, $warned_count_query);
$warned_count = mysqli_fetch_assoc($warned_count_result)['count'];

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
            return '<span class="badge bg-danger"><i class="bi bi-ban me-1"></i>Banned</span>';
        case 'warned':
            $count = $user ? $user['active_warnings'] : 0;
            return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Warning (' . $count . ')</span>';
        default:
            return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>';
    }
}

// Function to get role badge
function getRoleBadge($role) {
    $badges = [
        'admin' => '<span class="badge bg-danger"><i class="bi bi-shield-fill-check me-1"></i>Admin</span>',
        'penulis' => '<span class="badge bg-primary"><i class="bi bi-pen-fill me-1"></i>Penulis</span>',
        'pembaca' => '<span class="badge bg-info"><i class="bi bi-book-fill me-1"></i>Pembaca</span>',
        'premium' => '<span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Premium</span>'
    ];
    return $badges[$role] ?? '<span class="badge bg-secondary">' . ucfirst($role) . '</span>';
}
?>

<style>
.user-stats-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.user-stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.user-stats-card.primary::before {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.user-stats-card.success::before {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.user-stats-card.warning::before {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.user-stats-card.danger::before {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.user-stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.user-stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.user-stat-icon.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.user-stat-icon.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.user-stat-icon.warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.user-stat-icon.danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.user-info-card {
    background: var(--bg-tertiary);
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
}

.filter-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.fw-semibold{
    color:  #ffffffff;
}
.filter-tab {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.875rem;
    font-weight: 500;
}

.filter-tab:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.filter-tab.active {
    background: var(--accent);
    color: var(--bg-primary);
    border-color: var(--accent);
}

.action-btn-group {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.action-btn-group .btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 6px;
}

@media (max-width: 768px) {
    .user-stats-card {
        padding: 1rem;
    }
    
    .user-stat-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }
    
    .action-btn-group {
        width: 100%;
    }
    
    .action-btn-group .btn {
        flex: 1;
        font-size: 0.75rem;
    }
}
</style>

<!-- Page Header -->
<div class="mb-4">
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <h2 class="mb-1 h4">
                        <i class="bi bi-people-fill me-2"></i>
                        User Management
                    </h2>
                    <small class="text-muted">Kelola pengguna dan hak akses sistem</small>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-circle me-2"></i>Tambah User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="user-stats-card primary">
            <div class="user-stat-icon primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo number_format($user_count); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Users</div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="user-stats-card success">
            <div class="user-stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo number_format($user_count - $banned_count); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Active Users</div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="user-stats-card warning">
            <div class="user-stat-icon warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo number_format($warned_count); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Warned Users</div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="user-stats-card danger">
            <div class="user-stat-icon danger">
               <i class="fa-solid fa-ban"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo number_format($banned_count); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Banned Users</div>
        </div>
    </div>
</div>

<!-- Role Filter Pills -->
<div class="mb-4">
    <div class="filter-tabs" id="roleFilter">
        <button class="filter-tab active" data-role="all">
            <i class="bi bi-grid me-1"></i>Semua (<?php echo $user_count; ?>)
        </button>
        <button class="filter-tab" data-role="admin">
            <i class="bi bi-shield-check me-1"></i>Admin (<?php echo $role_counts['admin'] ?? 0; ?>)
        </button>
        <button class="filter-tab" data-role="penulis">
            <i class="bi bi-pen me-1"></i>Penulis (<?php echo $role_counts['penulis'] ?? 0; ?>)
        </button>
        <button class="filter-tab" data-role="pembaca">
            <i class="bi bi-book me-1"></i>Pembaca (<?php echo $role_counts['pembaca'] ?? 0; ?>)
        </button>
        <button class="filter-tab" data-role="premium">
            <i class="bi bi-star me-1"></i>Premium (<?php echo $role_counts['premium'] ?? 0; ?>)
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-ul me-2"></i>Daftar Pengguna
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-striped mb-0">
                <thead>
                    <tr>
                        <th width="80">Avatar</th>
                        <th>User Info</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Activity</th>
                        <th>Joined</th>
                        <th class="text-center" width="200">Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                    <?php $status = getUserStatus($user); ?>
                    <tr data-role="<?php echo $user['role']; ?>" data-status="<?php echo $status; ?>">
                        <td>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
                            <small class="text-muted">
                                <i class="bi bi-at"></i><?php echo htmlspecialchars($user['username']); ?>
                            </small>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i><?php echo htmlspecialchars($user['email']); ?>
                            </small>
                        </td>
                        <td><?php echo getRoleBadge($user['role']); ?></td>
                        <td>
                            <?php echo getStatusBadge($status, $user); ?>
                            <?php if ($status === 'banned' && $user['ban_until']): ?>
                            <br><small class="text-danger">
                                <i class="bi bi-clock"></i> Until: <?php echo date('d/m/Y H:i', strtotime($user['ban_until'])); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="d-block text-muted">
                                <i class="bi bi-file-text me-1"></i><?php echo $user['published_articles']; ?> artikel
                            </small>
                            <small class="d-block text-muted">
                                <i class="bi bi-chat me-1"></i><?php echo $user['total_comments']; ?> komentar
                            </small>
                            <?php if ($user['warning_count'] > 0): ?>
                            <small class="d-block text-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i><?php echo $user['warning_count']; ?> warnings
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <div class="action-btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                        title="Edit User">
                                    <i class="bi bi-pencil" style="color: black;"></i>
                                </button>

                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <?php if ($status === 'banned'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                            onclick="unbanUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            title="Unban User">
                                        <i class="bi bi-unlock"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                            onclick="giveWarning(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            title="Give Warning">
                                        <i class="bi bi-exclamation-triangle"style="color: #f59e0b;"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="banUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            title="Ban User">
                                        <i class="fa-solid fa-ban" style="color: red;"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($user['warning_count'] > 0 || $user['active_warnings'] > 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info"
                                            onclick="clearWarnings(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            title="Clear Warnings">
                                        <i class="bi bi-eraser"></i>
                                    </button>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            title="Delete User">
                                        <i class="bi bi-trash" style="color: #10b981;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-4 d-block mb-2 opacity-50"></i>
                            Tidak ada user ditemukan
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Tambah User Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person me-1"></i>Username <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">
                            <i class="bi bi-card-text me-1"></i>Nama Lengkap
                        </label>
                        <input type="text" class="form-control" id="full_name" name="full_name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope me-1"></i>Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock me-1"></i>Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">
                            <i class="bi bi-shield me-1"></i>Role <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="penulis">Penulis</option>
                            <option value="pembaca">Pembaca</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Tambah User
                    </button>
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
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">
                            <i class="bi bi-person me-1"></i>Username <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">
                            <i class="bi bi-card-text me-1"></i>Nama Lengkap
                        </label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">
                            <i class="bi bi-envelope me-1"></i>Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">
                            <i class="bi bi-lock me-1"></i>Password
                            <small class="text-muted">(kosongkan jika tidak ingin diubah)</small>
                        </label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">
                            <i class="bi bi-shield me-1"></i>Role <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="penulis">Penulis</option>
                            <option value="pembaca">Pembaca</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Give Warning -->
<div class="modal fade" id="warningModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>Berikan Warning
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="give_warning">
                    <input type="hidden" name="user_id" id="warning_user_id">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Smart Warning System:</strong> Warning akan otomatis expire sesuai durasi yang dipilih.
                    </div>

                    <div class="mb-3">
                        <p>Memberikan warning kepada: <strong id="warning_username"></strong></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Level Warning</label>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="warning_level" value="low" id="warning_low" checked>
                                <label class="form-check-label" for="warning_low">
                                    <span class="badge bg-info">Ringan</span>
                                    <small class="text-muted d-block">Peringatan ringan</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="warning_level" value="medium" id="warning_medium">
                                <label class="form-check-label" for="warning_medium">
                                    <span class="badge bg-warning">Sedang</span>
                                    <small class="text-muted d-block">Peringatan sedang</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="warning_level" value="high" id="warning_high">
                                <label class="form-check-label" for="warning_high">
                                    <span class="badge bg-danger">Berat</span>
                                    <small class="text-muted d-block">Peringatan berat</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Durasi Warning</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="number" class="form-control" name="warning_duration_value" 
                                       placeholder="Jumlah" min="1" max="999" value="24" required>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" name="warning_duration_unit" required>
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
                    </div>

                    <div class="mb-3">
                        <label for="warning_reason" class="form-label">Alasan Warning <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="warning_reason" name="warning_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>Berikan Warning
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
                        <i class="bi bi-ban me-2"></i>Ban User
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
                                <input type="number" class="form-control" name="ban_duration_value" placeholder="Jumlah" min="1" max="999" required>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" name="ban_duration_unit" required>
                                    <option value="">Pilih Satuan</option>
                                    <option value="minutes">Menit</option>
                                    <option value="hours">Jam</option>
                                    <option value="days" selected>Hari</option>
                                    <option value="weeks">Minggu</option>
                                    <option value="months">Bulan</option>
                                    <option value="years">Tahun</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="ban_reason" class="form-label">Alasan Ban <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="ban_reason" name="ban_reason" rows="3" required></textarea>
                    </div>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Peringatan:</strong> User yang di-ban tidak akan bisa mengakses website sampai masa ban berakhir.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-ban me-1"></i>Ban User
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
                        <i class="bi bi-unlock me-2"></i>Unban User
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-unlock me-1"></i>Unban User
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
                        <i class="bi bi-eraser me-2"></i>Hapus Semua Warning
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="clear_warnings">
                    <input type="hidden" name="user_id" id="clear_warning_user_id">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Aksi ini akan:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Menghapus semua warning aktif untuk user</li>
                            <li>Mereset counter warning ke 0</li>
                        </ul>
                    </div>

                    <p>Apakah Anda yakin ingin menghapus semua warning untuk user <strong id="clear_warning_username"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-eraser me-1"></i>Hapus Semua Warning
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete User -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-trash me-2"></i>Konfirmasi Hapus
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Hapus User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Role Filter
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const role = this.dataset.role;
        const rows = document.querySelectorAll('#usersTableBody tr[data-role]');
        
        rows.forEach(row => {
            if (role === 'all' || row.dataset.role === role) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});

// Edit User Function
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_full_name').value = user.full_name || '';
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_password').value = '';

    var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editModal.show();
}

// Give Warning Function
function giveWarning(userId, username) {
    document.getElementById('warning_user_id').value = userId;
    document.getElementById('warning_username').textContent = username;
    document.getElementById('warning_reason').value = '';
    document.getElementById('warning_low').checked = true;

    var warningModal = new bootstrap.Modal(document.getElementById('warningModal'));
    warningModal.show();
}

// Ban User Function
function banUser(userId, username) {
    document.getElementById('ban_user_id').value = userId;
    document.getElementById('ban_username').textContent = username;
    document.getElementById('ban_reason').value = '';

    var banModal = new bootstrap.Modal(document.getElementById('banModal'));
    banModal.show();
}

// Unban User Function
function unbanUser(userId, username) {
    document.getElementById('unban_user_id').value = userId;
    document.getElementById('unban_username').textContent = username;

    var unbanModal = new bootstrap.Modal(document.getElementById('unbanModal'));
    unbanModal.show();
}

// Clear Warnings Function
function clearWarnings(userId, username) {
    document.getElementById('clear_warning_user_id').value = userId;
    document.getElementById('clear_warning_username').textContent = username;

    var clearModal = new bootstrap.Modal(document.getElementById('clearWarningModal'));
    clearModal.show();
}

// Delete User Function
function deleteUser(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username').textContent = username;

    var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    deleteModal.show();
}

// Auto dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert && document.contains(alert)) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, 5000);
    });
    
    // Animate stat cards on load
    const statCards = document.querySelectorAll('.user-stats-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });
});

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Mohon lengkapi semua field yang wajib diisi!');
        }
    });
});

// Real-time search functionality
const searchInput = document.createElement('input');
searchInput.type = 'text';
searchInput.className = 'form-control mb-3';
searchInput.placeholder = 'Cari user berdasarkan nama, username, atau email...';
searchInput.style.maxWidth = '400px';

document.querySelector('.card-header').appendChild(searchInput);

searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTableBody tr[data-role]');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>