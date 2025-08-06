<?php
session_start();

// Include database configuration
include 'config/config.php';

// Include email configuration
require_once 'config/email.php';

// Variables for messages
$error_message = '';
$success_message = '';
$info_message = '';

// Rate limiting variables
$max_attempts = 3; // Maximum attempts per hour
$lockout_time = 3600; // 1 hour in seconds

// PERBAIKAN: Set timezone ke UTC untuk konsistensi
date_default_timezone_set('UTC');

// Function untuk generate secure token
function generateSecureToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Function untuk check rate limiting dengan UTC
function checkRateLimit($email, $koneksi, $max_attempts, $lockout_time) {
    // Gunakan UTC timestamp
    $one_hour_ago_utc = gmdate('Y-m-d H:i:s', time() - $lockout_time);
    
    $query = "SELECT COUNT(*) as attempt_count FROM password_resets 
              WHERE email = ? AND created_at > ? AND used = 0";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "ss", $email, $one_hour_ago_utc);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['attempt_count'] < $max_attempts;
}

// Function untuk send email dengan timezone Jakarta untuk display
function sendPasswordResetEmail($email, $token, $full_name, $username = '') {
    try {
        // URL reset password
        $reset_url = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=" . $token;
        
        // Hitung waktu expire dalam Jakarta timezone untuk user
        $expire_time_utc = gmdate('Y-m-d H:i:s', time() + 3600); // UTC
        $expire_time_jakarta = date('d F Y H:i', strtotime($expire_time_utc . ' UTC') + (7 * 3600)); // Convert to Jakarta
        
        $subject = "🔐 Reset Password - Sorot Dunia";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Reset Password</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f5f5f5;
                }
                .email-container { 
                    max-width: 600px; 
                    margin: 20px auto; 
                    background: white;
                    border-radius: 15px;
                    overflow: hidden;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #4CAF50, #45a049); 
                    color: white; 
                    padding: 30px 20px; 
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 600;
                }
                .content { 
                    padding: 40px 30px; 
                }
                .greeting {
                    font-size: 18px;
                    margin-bottom: 20px;
                    color: #333;
                }
                .reset-button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #4CAF50, #45a049); 
                    color: white !important; 
                    padding: 15px 35px; 
                    text-decoration: none; 
                    border-radius: 50px; 
                    margin: 25px 0;
                    font-weight: 600;
                    font-size: 16px;
                    text-align: center;
                    transition: all 0.3s ease;
                }
                .url-box {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 10px;
                    padding: 15px;
                    margin: 20px 0;
                    word-break: break-all;
                    font-family: monospace;
                    font-size: 14px;
                    color: #495057;
                }
                .warning-box { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    border-radius: 10px; 
                    padding: 20px; 
                    margin: 25px 0;
                    border-left: 5px solid #ffc107;
                }
                .warning-title {
                    font-weight: 600;
                    color: #856404;
                    margin-bottom: 10px;
                }
                .footer { 
                    background: #f8f9fa; 
                    color: #6c757d; 
                    padding: 25px 30px; 
                    font-size: 13px; 
                    line-height: 1.5;
                    border-top: 1px solid #e9ecef;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin: 20px 0;
                }
                .info-item {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    border-left: 3px solid #4CAF50;
                }
                .info-label {
                    font-weight: 600;
                    color: #495057;
                    font-size: 12px;
                    text-transform: uppercase;
                    margin-bottom: 5px;
                }
                .info-value {
                    color: #333;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🔐 Reset Password</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Sorot Dunia - Portal Berita Terpercaya</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>
                        Halo <strong>" . htmlspecialchars($full_name ?: $username ?: 'User') . "</strong>,
                    </div>
                    
                    <p>Kami menerima permintaan untuk mereset password akun Anda di <strong>Sorot Dunia</strong>.</p>
                    
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Email</div>
                            <div class='info-value'>" . htmlspecialchars($email) . "</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Berlaku Sampai</div>
                            <div class='info-value'>" . $expire_time_jakarta . " WIB</div>
                        </div>
                    </div>
                    
                    <p>Klik tombol di bawah ini untuk mereset password Anda:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $reset_url . "' class='reset-button'>
                            🔑 Reset Password Sekarang
                        </a>
                    </div>
                    
                    <p>Jika tombol tidak berfungsi, salin dan tempel link berikut di browser Anda:</p>
                    <div class='url-box'>" . $reset_url . "</div>
                    
                    <div class='warning-box'>
                        <div class='warning-title'>⚠️ Penting untuk Diketahui</div>
                        <ul style='margin: 0; padding-left: 20px;'>
                            <li>Link ini akan <strong>kadaluarsa dalam 1 jam</strong></li>
                            <li>Link hanya dapat digunakan <strong>satu kali</strong></li>
                            <li>Jika Anda tidak meminta reset password, <strong>abaikan email ini</strong></li>
                            <li>Akun Anda tetap aman sampai password direset</li>
                        </ul>
                    </div>
                </div>
                
                <div class='footer'>
                    <p style='margin: 0; text-align: center; color: #868e96;'>
                        © " . date('Y') . " Sorot Dunia. Semua hak dilindungi.<br>
                        <small>Email ini dikirim pada " . date('d F Y H:i:s', time() + (7 * 3600)) . " WIB</small>
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        // Panggil fungsi send email
        return sendEmail($email, $subject, $message, $full_name ?: $username);
        
    } catch (Exception $e) {
        error_log("Error sending password reset email: " . $e->getMessage());
        return false;
    }
}

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    
    // Validate input
    if (empty($email)) {
        $error_message = "Email harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid!";
    } else {
        // Check rate limiting
        if (!checkRateLimit($email, $koneksi, $max_attempts, $lockout_time)) {
            $error_message = "Terlalu banyak percobaan reset password. Silakan coba lagi dalam 1 jam.";
        } else {
            // Check if email exists
            $query = "SELECT id, username, email, full_name, is_banned FROM users WHERE email = ?";
            $stmt = mysqli_prepare($koneksi, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($user = mysqli_fetch_assoc($result)) {
                    // Check if user is banned
                    if ($user['is_banned'] == 1) {
                        $error_message = "Akun Anda sedang di-banned. Tidak dapat mereset password.";
                    } else {
                        // Generate token
                        $token = generateSecureToken();
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                        
                        // PERBAIKAN: Gunakan stored procedure untuk insert token
                        $sp_query = "CALL InsertPasswordResetTokenSafe(?, ?, ?, ?, ?)";
                        $sp_stmt = mysqli_prepare($koneksi, $sp_query);
                        
                        if ($sp_stmt) {
                            mysqli_stmt_bind_param($sp_stmt, "issss", 
                                $user['id'], $user['email'], $token, $ip_address, $user_agent);
                            
                            if (mysqli_stmt_execute($sp_stmt)) {
                                $sp_result = mysqli_stmt_get_result($sp_stmt);
                                $sp_data = mysqli_fetch_assoc($sp_result);
                                
                                if ($sp_data && $sp_data['status'] === 'SUCCESS') {
                                    // Send email
                                    if (sendPasswordResetEmail($user['email'], $token, $user['full_name'], $user['username'])) {
                                        $success_message = "Link reset password telah dikirim ke email Anda. Silakan periksa inbox dan folder spam.";
                                        
                                        // Log security event dengan UTC
                                        $current_utc = gmdate('Y-m-d H:i:s');
                                        error_log("Password reset requested for user: " . $user['username'] . " (ID: " . $user['id'] . ") from IP: " . $ip_address . " at " . $current_utc . " UTC");
                                    } else {
                                        $error_message = "Gagal mengirim email. Silakan coba lagi atau hubungi administrator.";
                                        
                                        // Delete the token since email failed
                                        $delete_query = "DELETE FROM password_resets WHERE token = ?";
                                        $delete_stmt = mysqli_prepare($koneksi, $delete_query);
                                        mysqli_stmt_bind_param($delete_stmt, "s", $token);
                                        mysqli_stmt_execute($delete_stmt);
                                    }
                                } else {
                                    $error_message = "Terjadi kesalahan sistem saat membuat token. Silakan coba lagi.";
                                }
                            } else {
                                $error_message = "Terjadi kesalahan sistem. Silakan coba lagi.";
                                error_log("Failed to execute InsertPasswordResetTokenSafe: " . mysqli_error($koneksi));
                            }
                            
                            mysqli_stmt_close($sp_stmt);
                        } else {
                            $error_message = "Terjadi kesalahan sistem. Silakan coba lagi.";
                            error_log("Failed to prepare InsertPasswordResetTokenSafe: " . mysqli_error($koneksi));
                        }
                    }
                } else {
                    // Email not found - don't reveal this for security
                    $info_message = "Jika email tersebut terdaftar, link reset password akan dikirim dalam beberapa menit.";
                }
                
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Terjadi kesalahan sistem. Silakan coba lagi.";
            }
        }
    }
}

// PERBAIKAN: Manual cleanup expired tokens (probabilistic - 10% chance)
if (rand(1, 10) === 1) {
    try {
        $cleanup_query = "CALL CleanupExpiredTokensUTC()";
        $cleanup_stmt = mysqli_prepare($koneksi, $cleanup_query);
        if ($cleanup_stmt) {
            mysqli_stmt_execute($cleanup_stmt);
            mysqli_stmt_close($cleanup_stmt);
        }
    } catch (Exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Sorot Dunia</title>
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
        max-width: 450px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        animation: slideIn 0.6s ease-out;
    }

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
        margin-bottom: 10px;
        color: #333;
        letter-spacing: -0.5px;
    }

    .login-subtitle {
        text-align: center;
        color: #666;
        margin-bottom: 30px;
        font-size: 14px;
        line-height: 1.5;
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

    .btn-reset {
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

    .btn-reset:hover {
        background: linear-gradient(135deg, #45a049, #3d8b40);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
    }

    .btn-reset:disabled {
        background: linear-gradient(135deg, #6c757d, #5a6268);
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

    .alert-info {
        background-color: rgba(23, 162, 184, 0.1);
        color: #17a2b8;
        border-left: 4px solid #17a2b8;
    }

    /* Info Box */
    .info-box {
        background: rgba(23, 162, 184, 0.05);
        border: 1px solid rgba(23, 162, 184, 0.2);
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        font-size: 13px;
        color: #0c5460;
    }

    .info-box .info-title {
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
    }

    .info-box .info-title i {
        margin-right: 8px;
        color: #17a2b8;
    }

    /* Security indicators */
    .security-note {
        background: #f8f9fa;
        border-left: 4px solid #6c757d;
        padding: 10px 15px;
        margin-top: 20px;
        border-radius: 0 8px 8px 0;
        font-size: 12px;
        color: #6c757d;
    }

    /* Responsive Design */
    @media (max-width: 991px) {
        .login-card {
            margin: 20px;
            padding: 30px 25px;
            max-width: 450px;
        }

        .login-title {
            font-size: 1.75rem;
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
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 13px;
            margin-bottom: 25px;
        }
    }

    @media (min-width: 992px) {
        .login-card {
            max-width: 430px;
            width: 430px;
        }

        .login-container {
            justify-content: flex-start;
            padding-left: 8%;
        }
    }

    /* Loading animation */
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Success animation */
    .success-icon {
        animation: successPulse 0.6s ease-out;
    }

    @keyframes successPulse {
        0% {
            transform: scale(0.8);
            opacity: 0;
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
    </style>
</head>

<body>
    <img src="img/bg_login.webp" alt="Background Image" class="background-image">

    <div class="login-container">
        <div class="login-card">
            <h2 class="login-title">
                <i class="fas fa-key me-2"></i>Lupa Password
            </h2>
            <p class="login-subtitle">
                Masukkan email Anda dan kami akan mengirimkan link untuk mereset password
            </p>

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
                <i class="fas fa-check-circle me-2 success-icon"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>

            <!-- Display info message -->
            <?php if (!empty($info_message)): ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?php echo htmlspecialchars($info_message); ?>
            </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-title">
                    <i class="fas fa-shield-alt"></i>
                    Informasi Keamanan
                </div>
                <ul class="mb-0" style="padding-left: 20px;">
                    <li>Link reset password berlaku selama <strong>1 jam</strong></li>
                    <li>Maksimal <strong>3 percobaan</strong> per jam</li>
                    <li>Periksa folder <strong>spam/junk</strong> jika email tidak masuk</li>
                    <li>Link hanya dapat digunakan <strong>sekali</strong></li>
                    <li>Sistem menggunakan <strong>UTC timezone</strong> untuk keamanan</li>
                </ul>
            </div>

            <form id="forgotPasswordForm" method="POST" action="">
                <div class="mb-4">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-1"></i>Email Address
                    </label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Masukkan email Anda"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    <div class="form-text mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Pastikan email yang Anda masukkan benar dan masih aktif
                        </small>
                    </div>
                </div>

                <!-- Reset Button -->
                <button type="submit" class="btn btn-reset" id="resetBtn">
                    <i class="fas fa-paper-plane me-2"></i>
                    Kirim Link Reset
                </button>

                <!-- Security Note -->
                <div class="security-note">
                    <i class="fas fa-lock me-1"></i>
                    <strong>Keamanan:</strong> Kami tidak akan pernah meminta password melalui email.
                    Jika Anda menerima email mencurigakan, jangan klik link apapun dan laporkan kepada kami.
                </div>

                <!-- Footer Links -->
                <div class="footer-links">
                    <a href="login.php">
                        <i class="fas fa-arrow-left me-1"></i>
                        Kembali ke Login
                    </a>
                    <span class="divider">|</span>
                    <a href="register.php">
                        <i class="fas fa-user-plus me-1"></i>
                        Daftar Akun Baru
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Input focus effects
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });

        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });

    // Form submission with loading state
    document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('resetBtn');
        const originalHTML = btn.innerHTML;

        // Show loading state
        btn.innerHTML = '<span class="loading-spinner me-2"></span>Mengirim...';
        btn.disabled = true;

        // Re-enable button after 10 seconds if form doesn't submit
        setTimeout(() => {
            if (btn.disabled) {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        }, 10000);
    });

    // Email validation
    document.getElementById('email').addEventListener('input', function() {
        const email = this.value;
        const btn = document.getElementById('resetBtn');

        if (email && !this.checkValidity()) {
            this.style.borderColor = '#dc3545';
            btn.disabled = true;
        } else {
            this.style.borderColor = '#e1e5e9';
            btn.disabled = false;
        }
    });

    // Auto-hide alerts after 15 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            // Don't auto-hide success messages
            if (!alert.classList.contains('alert-success')) {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }, 15000);
            }
        });
    });

    // Add typing indicator for email field
    let typingTimer;
    const typingDelay = 1000;
    const emailInput = document.getElementById('email');

    emailInput.addEventListener('keyup', function() {
        clearTimeout(typingTimer);

        typingTimer = setTimeout(function() {
            if (emailInput.value && emailInput.checkValidity()) {
                emailInput.style.backgroundImage =
                    'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%2328a745\'%3e%3cpath d=\'M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z\'/%3e%3c/svg%3e")';
                emailInput.style.backgroundRepeat = 'no-repeat';
                emailInput.style.backgroundPosition = 'right 12px center';
                emailInput.style.backgroundSize = '16px 16px';
            } else {
                emailInput.style.backgroundImage = 'none';
            }
        }, typingDelay);
    });

    // Clear background on focus
    emailInput.addEventListener('focus', function() {
        this.style.backgroundImage = 'none';
    });
    </script>
</body>

</html>