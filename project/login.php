<?php
session_start();

// Include database configuration
include 'config/config.php';

// Variables for error messages
$error_message = '';
$success_message = '';
$ban_message = '';
$warning_message = '';

// Check for success message from registration
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Check for ban/logout reasons
if (isset($_GET['reason'])) {
    switch ($_GET['reason']) {
        case 'banned':
            $ban_message = "Akun Anda telah di-ban. Silakan hubungi administrator untuk informasi lebih lanjut.";
            break;
        case 'session_timeout':
            $error_message = "Sesi Anda telah berakhir. Silakan login kembali.";
            break;
    }
}

// Check for logout success
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = "Anda telah berhasil logout.";
}

if (isset($_GET['message']) && $_GET['message'] === 'logout_success') {
    $success_message = "Anda telah berhasil logout.";
}

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error_message = "Username dan password harus diisi!";
    } else {
        // Query to check user credentials AND ban status - FIXED QUERY
        $query = "SELECT id, username, password, email, full_name, role, is_banned, ban_until, ban_reason, warning_count FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($user = mysqli_fetch_assoc($result)) {
                error_log("Login attempt for user: " . print_r($user, true));
                
                // PERBAIKAN UTAMA: Check if user is banned first
                if ($user['is_banned'] == 1 && !empty($user['ban_until'])) {
                    $ban_until = strtotime($user['ban_until']);
                    $now = time();
                    
                    error_log("Ban check - Ban until: " . $user['ban_until'] . " (" . $ban_until . "), Now: " . date('Y-m-d H:i:s', $now) . " (" . $now . ")");
                    
                    if ($ban_until > $now) {
                        // User is still banned
                        $remaining_time = $ban_until - $now;
                        $days = floor($remaining_time / 86400);
                        $hours = floor(($remaining_time % 86400) / 3600);
                        $minutes = floor(($remaining_time % 3600) / 60);
                        
                        $time_left = '';
                        if ($days > 0) {
                            $time_left = "$days hari, $hours jam";
                        } elseif ($hours > 0) {
                            $time_left = "$hours jam, $minutes menit";
                        } else {
                            $time_left = "$minutes menit";
                        }
                        
                        $ban_message = "Akun Anda di-ban sampai " . date('d F Y H:i', $ban_until) . 
                                      " (sisa: $time_left). Alasan: " . htmlspecialchars($user['ban_reason']);
                        
                        error_log("User banned: " . $ban_message);
                    } else {
                        // Ban has expired, remove ban status
                        $update_query = "UPDATE users SET is_banned = 0, ban_until = NULL, ban_reason = NULL, banned_by = NULL, banned_at = NULL WHERE id = ?";
                        $update_stmt = mysqli_prepare($koneksi, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
                        mysqli_stmt_execute($update_stmt);
                        
                        error_log("Ban expired for user ID: " . $user['id']);
                        
                        // Continue with normal login process
                        if (verify_password($password, $user['password'])) {
                            // Login successful
                            $_SESSION['logged_in'] = true;
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['id'] = $user['id']; // Add both for compatibility
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['user_role'] = $user['role'];
                            
                            error_log("Login successful for user: " . $user['username']);
                            
                            // Check for warnings
                            if ($user['warning_count'] > 0) {
                                $success_message = "Login berhasil! Anda memiliki " . $user['warning_count'] . " warning. Harap patuhi aturan website.";
                            }
                            
                            header("Location: index.php");
                            exit();
                        } else {
                            $error_message = "Username atau password salah!";
                        }
                    }
                } else {
                    // User is not banned, verify password
                    if (verify_password($password, $user['password'])) {
                        // Login successful
                        $_SESSION['logged_in'] = true;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['id'] = $user['id']; // Add both for compatibility  
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        
                        error_log("Login successful for user: " . $user['username'] . " (ID: " . $user['id'] . ")");
                        
                        // Check for warnings and show message
                        if ($user['warning_count'] > 0) {
                            $_SESSION['login_warning'] = "Anda memiliki " . $user['warning_count'] . " warning. Harap patuhi aturan website.";
                        }
                        
                        header("Location: index.php");
                        exit();
                    } else {
                        $error_message = "Username atau password salah!";
                    }
                }
            } else {
                $error_message = "Username atau password salah!";
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sorot Dunia</title>
    <link rel="icon" href="img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
    .d-flex a {
        text-decoration: none;
        color: #333;
    }

    .d-flex a:hover {
        text-decoration: none;
    }

    .background-image {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: -1;
    }

    .btn-outline-primary {
        color: #007bff;
        border-color: #007bff;
    }

    .btn-outline-primary:hover {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }

    /* Main Container */
    .login-container {
        height: 100vh;
        display: flex;
        align-items: center;
        padding: 20px;
    }

    /* Login Card */
    .login-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 40px 35px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 400px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        /*load animasi card*/
        animation: slideIn 0.6s ease-out;
    }

    /*load animasi card*/
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .login-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    }

    /* Title */
    .login-title {
        font-size: 2rem;
        font-weight: 600;
        text-align: center;
        margin-bottom: 30px;
        color: #333;
        letter-spacing: -0.5px;
    }

    /* Form Elements */
    .form-label {
        font-weight: 500;
        color: #555;
        margin-bottom: 8px;
    }

    .form-control {
        border: 2px solid #e1e5e9;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.9);
    }

    .form-control:focus {
        border-color: #4CAF50;
        box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        background: white;
    }

    .btn-signin {
        background: linear-gradient(135deg, #4CAF50, #45a049);
        border: none;
        border-radius: 12px;
        padding: 14px 20px;
        font-size: 16px;
        font-weight: 600;
        color: white;
        width: 100%;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-signin:hover {
        background: linear-gradient(135deg, #45a049, #3d8b40);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
    }

    .btn-signin:active {
        transform: translateY(0);
    }

    .btn-signin:disabled {
        background: linear-gradient(135deg, #dc3545, #c82333);
        transform: none;
        box-shadow: none;
        cursor: not-allowed;
    }

    .footer-links {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-top: 25px;
        flex-wrap: wrap;
    }

    .footer-links a {
        color: #666;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease;
        padding: 5px 10px;
        border-radius: 6px;
    }

    .footer-links a:hover {
        color: #4CAF50;
        background: rgba(76, 175, 80, 0.1);
    }

    .divider {
        color: #999;
        font-weight: 300;
    }

    /* Alert Messages */
    .alert {
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 20px;
        border: none;
        font-size: 14px;
    }

    .alert-danger {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border-left: 4px solid #dc3545;
    }

    .alert-success {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border-left: 4px solid #28a745;
    }

    .alert-warning {
        background-color: rgba(255, 193, 7, 0.1);
        color: #856404;
        border-left: 4px solid #ffc107;
    }

    /* Ban Alert - Special styling */
    .alert-ban {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.15), rgba(108, 117, 125, 0.1));
        color: #721c24;
        border: 2px solid #dc3545;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        animation: banAlert 0.8s ease-out;
    }

    @keyframes banAlert {
        0% {
            opacity: 0;
            transform: scale(0.8) rotate(-2deg);
        }

        50% {
            transform: scale(1.05) rotate(1deg);
        }

        100% {
            opacity: 1;
            transform: scale(1) rotate(0deg);
        }
    }

    .alert-ban .ban-icon {
        font-size: 28px;
        margin-right: 12px;
        color: #dc3545;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    .alert-ban .ban-title {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
    }

    .alert-ban .ban-details {
        font-size: 14px;
        line-height: 1.6;
        margin-left: 40px;
        background: rgba(255, 255, 255, 0.8);
        padding: 10px;
        border-radius: 8px;
        right: 1.5rem;
        position: relative;
    }

    /* Responsive Design */
    @media (max-width: 991px) {
        .login-card {
            margin: 20px;
            padding: 30px 25px;
            max-width: 400px;
        }

        .login-title {
            font-size: 1.75rem;
        }

        .footer-links {
            flex-direction: row;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .divider {
            display: block;
            text-align: center;
            margin: 5px 0;
        }

        .login-container {
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .login-container {
            padding: 15px;
        }

        .login-card {
            padding: 25px 20px;
            margin: 15px;
        }

        .login-title {
            font-size: 1.5rem;
            margin-bottom: 25px;
        }

        .alert-ban {
            padding: 15px;
        }

        .alert-ban .ban-title {
            font-size: 18px;
        }

        .alert-ban .ban-details {
            font-size: 13px;
            margin-left: 35px;
        }

        .alert-ban .ban-icon {
            font-size: 24px;
        }
    }

    @media (min-width: 992px) {
        .login-card {
            max-width: 380px;
            width: 380px;
        }

        /*card agar ketengah*/
        .login-container {
            justify-content: flex-start;
            padding-left: 8%;
        }
    }
    </style>

</head>

<body>
    <img src="img/bg_login.webp" alt="Background Image" class="background-image">

    <div class="login-container">
        <div class="login-card">
            <h2 class="login-title">Sign-in</h2>

            <!-- Display ban message with special styling -->
            <?php if (!empty($ban_message)): ?>
            <div class="alert alert-ban" role="alert">
                <div class="ban-title">
                    <i class="fas fa-ban ban-icon"></i>
                    Akun Dibanned
                </div>
                <div class="ban-details">
                    <?php echo $ban_message; ?>
                    <br><br>
                    <small><i class="fas fa-info-circle"></i> Hubungi administrator jika Anda merasa ini adalah
                        kesalahan.</small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Display error message -->
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <!-- Display success message -->
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>

            <!-- Display warning message -->
            <?php if (!empty($warning_message)): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($warning_message); ?>
            </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username atau Email</label>
                    <input type="text" class="form-control" id="username" name="username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        <?php echo !empty($ban_message) ? 'disabled' : 'required'; ?>>
                </div>
                <!-- Password-->
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password"
                        <?php echo !empty($ban_message) ? 'disabled' : 'required'; ?>>
                </div>
                <!-- Sign In Button -->
                <button type="submit" class="btn btn-signin" <?php echo !empty($ban_message) ? 'disabled' : ''; ?>>
                    <?php if (!empty($ban_message)): ?>
                    <i class="fas fa-ban me-2"></i>Akun Dibanned
                    <?php else: ?>
                    Sign In
                    <?php endif; ?>
                </button>
                <!-- Footer Links -->
                <div class="footer-links">
                    <a href="forgetpw.php">Forget Password</a>
                    <span class="divider">|</span>
                    <a href="register.php">Register</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Input focus effects
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', function() {
            if (!this.disabled) {
                this.parentElement.style.transform = 'translateY(-2px)';
            }
        });

        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });

    // Form submission with loading state
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = document.querySelector('.btn-signin');
        const originalText = btn.innerHTML;

        // Don't process if user is banned
        if (btn.disabled) {
            e.preventDefault();
            return false;
        }

        // Show loading state
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
        btn.disabled = true;

        // Re-enable button after 3 seconds if form doesn't submit
        setTimeout(() => {
            if (btn.disabled) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }, 3000);
    });

    // Auto-hide alerts after 10 seconds (except ban alerts)
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-ban)');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }, 10000);
        });
    });

    // Ban alert special effects
    const banAlert = document.querySelector('.alert-ban');
    if (banAlert) {
        // Disable all form elements if banned
        const form = document.getElementById('loginForm');
        const inputs = form.querySelectorAll('input');
        inputs.forEach(input => {
            input.style.opacity = '0.5';
            input.style.pointerEvents = 'none';
        });

        // Add warning sound effect (if you want)
        console.log('🚫 User account is banned');
    }
    </script>
</body>

</html>