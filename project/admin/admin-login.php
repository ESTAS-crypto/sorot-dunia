<?php
// admin/admin-login.php - FIXED VERSION
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Include config
require_once '../config/config.php';

// Connection check
if (!$koneksi) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Variables
$login_error = '';
$logout_message = '';
$session_expired = false;

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    session_start();
    header("Location: admin-login.php?logout=success");
    exit();
}

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $logout_message = 'Anda telah berhasil logout.';
}

// Check for session timeout
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'session_timeout') {
        $login_error = 'Sesi Anda telah berakhir. Silakan login kembali.';
        $session_expired = true;
    } elseif ($_GET['error'] === 'not_admin') {
        $login_error = 'Akses ditolak. Hanya admin yang dapat login.';
    } elseif ($_GET['error'] === 'security_error') {
        $login_error = 'Terjadi kesalahan keamanan. Silakan login kembali.';
    }
}

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_role'])) {
    $role = trim(strtolower($_SESSION['user_role']));
    if ($role === 'admin') {
        header("Location: index.php?page=dashboard");
        exit();
    } else {
        // Clear session if not admin
        session_unset();
        session_destroy();
        session_start();
    }
}

// Process Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username_input = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password_input = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (empty($username_input)) {
        $login_error = 'Username atau email tidak boleh kosong!';
    } elseif (empty($password_input)) {
        $login_error = 'Password tidak boleh kosong!';
    } else {
        // Sanitize input
        $username_clean = mysqli_real_escape_string($koneksi, $username_input);
        
        // Build query
        $query = "SELECT id, username, email, password, role, full_name, is_banned 
                  FROM users 
                  WHERE (username = '$username_clean' OR email = '$username_clean') 
                  LIMIT 1";
        
        // Execute query
        $result = mysqli_query($koneksi, $query);
        
        if (!$result) {
            $login_error = 'Database error: ' . mysqli_error($koneksi);
            error_log("Login query error: " . mysqli_error($koneksi));
        } else {
            $num_rows = mysqli_num_rows($result);
            
            if ($num_rows === 0) {
                $login_error = 'Username atau email tidak ditemukan!';
            } else {
                $user = mysqli_fetch_assoc($result);
                
                // Check if banned
                if ($user['is_banned'] == 1) {
                    $login_error = 'Akun Anda telah diblokir!';
                } else {
                    // Verify password
                    if (password_verify($password_input, $user['password'])) {
                        // Check role
                        $user_role = trim(strtolower($user['role']));
                        
                        if ($user_role === 'admin') {
                            // Clear any old session data
                            session_unset();
                            
                            // Regenerate session ID for security
                            session_regenerate_id(true);
                            
                            // Set new session with admin data
                            $_SESSION['logged_in'] = true;
                            $_SESSION['user_id'] = (int)$user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['user_role'] = 'admin';
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['last_activity'] = time();
                            
                            // Log successful login
                            error_log("Admin login successful: User ID " . $user['id'] . " - " . $user['username']);
                            
                            // Redirect to dashboard
                            header("Location: index.php?page=dashboard");
                            exit();
                            
                        } elseif ($user_role === 'penulis') {
                            $login_error = 'Akses Ditolak! Anda adalah PENULIS. Hanya ADMIN yang dapat login ke panel admin.';
                        } elseif ($user_role === 'pembaca') {
                            $login_error = 'Akses Ditolak! Anda adalah PEMBACA. Hanya ADMIN yang dapat login ke panel admin.';
                        } else {
                            $login_error = "Akses Ditolak! Role '$user_role' tidak dikenal. Hanya ADMIN yang dapat login.";
                        }
                    } else {
                        $login_error = 'Password salah!';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sorot Dunia</title>
    <link rel="icon" href="/project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-primary: #0d0d0d;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #262626;
            --bg-hover: #2d2d2d;
            --border-color: #404040;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-muted: #808080;
            --accent: #ffffff;
            --accent-hover: #f0f0f0;
            --error: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --input-text: #ffffff;
            --input-bg: #1a1a1a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.03) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .login-container {
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-hover) 100%);
            border: 2px solid var(--border-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: var(--accent);
            transition: all 0.3s ease;
        }

        .logo-circle:hover {
            transform: scale(1.05);
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
        }

        .login-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
            pointer-events: none;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: var(--input-bg) !important;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--input-text) !important;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-muted) !important;
            opacity: 1;
        }

        .form-control:focus {
            background: var(--bg-hover) !important;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
            color: var(--input-text) !important;
        }

        .form-control:focus + .input-icon {
            color: var(--accent);
        }

        .form-control:-webkit-autofill,
        .form-control:-webkit-autofill:hover,
        .form-control:-webkit-autofill:focus,
        .form-control:-webkit-autofill:active {
            -webkit-text-fill-color: var(--input-text) !important;
            -webkit-box-shadow: 0 0 0 30px var(--input-bg) inset !important;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 18px;
            padding: 4px;
            transition: color 0.3s ease;
            z-index: 3;
        }

        .password-toggle:hover {
            color: var(--text-secondary);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: var(--bg-primary);
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.2);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            border: 1px solid;
            animation: slideDown 0.4s ease-out;
            display: flex;
            align-items: center;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert i {
            font-size: 18px;
            margin-right: 10px;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fcd34d;
            border-color: rgba(245, 158, 11, 0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 32px 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            padding: 0 16px;
        }

        .back-home {
            text-align: center;
        }

        .back-home a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .back-home a:hover {
            color: var(--accent);
        }

        @media (max-width: 480px) {
            .login-card { padding: 36px 28px; }
            .login-title { font-size: 24px; }
            .logo-circle { width: 70px; height: 70px; font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-circle">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1 class="login-title">Admin Login</h1>
                <h2 class="login-subtitle" style="font-size: 18px; margin-bottom: 4px;">Sorot Dunia</h2>
                <p class="login-subtitle">Masuk ke Panel Administrator</p>
            </div>

            <?php if (!empty($login_error)): ?>
            <div class="alert alert-<?php echo $session_expired ? 'warning' : 'danger'; ?>">
                <i class="bi bi-<?php echo $session_expired ? 'clock-history' : 'exclamation-triangle'; ?>"></i>
                <span><?php echo htmlspecialchars($login_error); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($logout_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <span><?php echo htmlspecialchars($logout_message); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="username">Username atau Email</label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            placeholder="Masukkan username atau email"
                            required
                            autofocus
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        >
                        <i class="input-icon bi bi-person"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Masukkan password"
                            required
                        >
                        <i class="input-icon bi bi-lock"></i>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                </button>

                <div class="divider">
                    <span>atau</span>
                </div>

                <div class="back-home">
                    <a href="../index.php">
                        <i class="bi bi-arrow-left"></i>
                        Kembali ke Beranda
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            });
        }

        // Form validation
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Username dan password harus diisi!');
                    return false;
                }
            });
        }

        // Auto-dismiss success message
        <?php if (!empty($logout_message)): ?>
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>