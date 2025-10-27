<?php
// email.php - SMTP Configuration - COMPLETE FIX
// ⚠️ NO WHITESPACE before <?php!

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Auto-detect project root
$project_root = dirname(__DIR__);

// Load PHPMailer - Try multiple paths
$phpmailer_paths = [
    $project_root . '/vendor/phpmailer/phpmailer/src/',
    $project_root . '/config/vendor/phpmailer/phpmailer/src/',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
    __DIR__ . '/vendor/phpmailer/src/',
];

$phpmailer_loaded = false;
$loaded_from = '';

foreach ($phpmailer_paths as $path) {
    if (file_exists($path . 'Exception.php') && 
        file_exists($path . 'PHPMailer.php') && 
        file_exists($path . 'SMTP.php')) {
        
        require_once $path . 'Exception.php';
        require_once $path . 'PHPMailer.php';
        require_once $path . 'SMTP.php';
        
        $phpmailer_loaded = true;
        $loaded_from = $path;
        error_log("✅ PHPMailer loaded successfully from: " . $path);
        break;
    }
}

if (!$phpmailer_loaded) {
    error_log("❌ PHPMailer NOT FOUND in any of these paths:");
    foreach ($phpmailer_paths as $path) {
        error_log("   - " . $path . " (exists: " . (file_exists($path) ? 'YES' : 'NO') . ")");
    }
    
    // Define dummy function to prevent fatal errors
    if (!function_exists('sendResetEmailSMTP')) {
        function sendResetEmailSMTP($to_email, $token, $username, $full_name = '') {
            error_log("❌ PHPMailer not available - email cannot be sent");
            error_log("❌ Please install PHPMailer: composer require phpmailer/phpmailer");
            return false;
        }
    }
    return;
}

// ============================================
// SMTP CONFIGURATION - SOROT DUNIA
// ============================================
class EmailConfig {
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'sorotdunia10@gmail.com';
    const SMTP_PASSWORD = 'zxmq rhax bolq nkfp'; // App Password dari screenshot
    const SMTP_ENCRYPTION = PHPMailer::ENCRYPTION_STARTTLS;
    
    const FROM_EMAIL = 'sorotdunia10@gmail.com';
    const FROM_NAME = 'Sorot Dunia';
    
    const DEBUG_MODE = true; // Set false untuk production
}

/**
 * Send password reset email via SMTP Gmail
 * 
 * @param string $to_email Email tujuan
 * @param string $token Reset token (plain text)
 * @param string $username Username
 * @param string $full_name Full name (optional)
 * @return bool Success status
 */
function sendResetEmailSMTP($to_email, $token, $username, $full_name = '') {
    $mail = new PHPMailer(true);
    
    try {
        // ============================================
        // SERVER SETTINGS
        // ============================================
        $mail->isSMTP();
        $mail->Host       = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EmailConfig::SMTP_USERNAME;
        $mail->Password   = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = EmailConfig::SMTP_ENCRYPTION;
        $mail->Port       = EmailConfig::SMTP_PORT;
        
        // Debug mode (disable in production)
        if (EmailConfig::DEBUG_MODE) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP Debug level $level: $str");
            };
        } else {
            $mail->SMTPDebug = 0;
        }
        
        // Set charset
        $mail->CharSet = 'UTF-8';
        
        // Timeout settings
        $mail->Timeout = 30;
        
        // SSL/TLS options
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // ============================================
        // RECIPIENTS
        // ============================================
        $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        $mail->addAddress($to_email, $full_name ?: $username);
        $mail->addReplyTo(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        
        // ============================================
        // GENERATE RESET LINK
        // ============================================
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Calculate base path
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Remove /ajax/ from path if exists
        $base_path = dirname(dirname($script_name));
        $base_path = str_replace('\\', '/', $base_path);
        $base_path = str_replace('/ajax', '', $base_path);
        $base_path = rtrim($base_path, '/');
        
        $reset_link = "{$protocol}://{$host}{$base_path}/reset_password.php?token={$token}";
        
        error_log("📧 Reset link generated: $reset_link");
        error_log("📧 Sending to: $to_email");
        
        // ============================================
        // EMAIL CONTENT
        // ============================================
        $mail->isHTML(true);
        $mail->Subject = '🔐 Reset Password - Sorot Dunia';
        
        // HTML email template
        $mail->Body = getResetEmailTemplate($username, $full_name, $reset_link, $token);
        
        // Plain text version
        $mail->AltBody = getResetEmailTextVersion($username, $reset_link);
        
        // ============================================
        // SEND EMAIL
        // ============================================
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ Password reset email sent successfully to: $to_email");
            return true;
        } else {
            error_log("❌ Failed to send password reset email to: $to_email");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ Email sending failed: {$mail->ErrorInfo}");
        error_log("❌ PHPMailer Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * HTML Email Template - Modern & Professional
 */
function getResetEmailTemplate($username, $full_name, $reset_link, $token) {
    $display_name = $full_name ?: $username;
    $current_year = date('Y');
    
    return "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Reset Password - Sorot Dunia</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 0;
                background-color: #f4f4f4;
            }
            .container {
                background-color: #ffffff;
                margin: 20px;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #ff6b35, #f7931e);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            .content {
                padding: 30px 20px;
            }
            .greeting {
                font-size: 18px;
                margin-bottom: 20px;
                color: #2c3e50;
            }
            .message {
                margin-bottom: 25px;
                line-height: 1.8;
            }
            .reset-button {
                text-align: center;
                margin: 30px 0;
            }
            .reset-button a {
                display: inline-block;
                background: linear-gradient(135deg, #ff6b35, #f7931e);
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 25px;
                font-weight: 600;
                font-size: 16px;
                box-shadow: 0 4px 6px rgba(255, 107, 53, 0.3);
            }
            .reset-button a:hover {
                box-shadow: 0 6px 8px rgba(255, 107, 53, 0.4);
            }
            .info-box {
                background-color: #e3f2fd;
                border-left: 4px solid #2196f3;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .warning-box {
                background-color: #fff3e0;
                border-left: 4px solid #ff9800;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .footer {
                background-color: #f8f9fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #6c757d;
                border-top: 1px solid #dee2e6;
            }
            .token-info {
                background-color: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                word-break: break-all;
                margin: 10px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Reset Password</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Sorot Dunia</p>
            </div>
            
            <div class='content'>
                <div class='greeting'>
                    Halo, <strong>$display_name</strong>!
                </div>
                
                <div class='message'>
                    Kami menerima permintaan untuk mereset password akun Anda di <strong>Sorot Dunia</strong>. 
                    Jika Anda yang melakukan permintaan ini, silakan klik tombol di bawah untuk melanjutkan proses reset password.
                </div>
                
                <div class='reset-button'>
                    <a href='$reset_link' target='_blank'>
                        🔓 Reset Password Sekarang
                    </a>
                </div>
                
                <div class='info-box'>
                    <strong>📋 Informasi Penting:</strong><br>
                    • Link reset ini hanya berlaku selama <strong>1 jam</strong><br>
                    • Link hanya dapat digunakan <strong>satu kali</strong><br>
                    • Setelah reset, silakan login dengan password baru Anda
                </div>
                
                <div class='warning-box'>
                    <strong>⚠️ Keamanan:</strong><br>
                    Jika Anda <strong>tidak</strong> meminta reset password, abaikan email ini. 
                    Password Anda akan tetap aman dan tidak berubah.
                </div>
                
                <p style='margin-top: 25px; font-size: 14px; color: #6c757d;'>
                    Jika tombol di atas tidak berfungsi, copy dan paste link berikut ke browser Anda:
                </p>
                <div class='token-info'>
                    $reset_link
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 14px; color: #6c757d;'>
                    <strong>Butuh bantuan?</strong><br>
                    Hubungi kami di <a href='mailto:" . EmailConfig::FROM_EMAIL . "' style='color: #ff6b35;'>" . EmailConfig::FROM_EMAIL . "</a>
                </div>
            </div>
            
            <div class='footer'>
                <p>© $current_year Sorot Dunia. All rights reserved.</p>
                <p>Email ini dikirim secara otomatis, mohon jangan membalas email ini.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Plain text version for email clients that don't support HTML
 */
function getResetEmailTextVersion($username, $reset_link) {
    return "
Reset Password - Sorot Dunia

Halo $username,

Kami menerima permintaan untuk mereset password akun Anda di Sorot Dunia.

Untuk mereset password Anda, silakan kunjungi link berikut:
$reset_link

PENTING:
- Link ini hanya berlaku selama 1 jam
- Link hanya dapat digunakan satu kali
- Jika Anda tidak meminta reset password, abaikan email ini

Jika Anda mengalami masalah, hubungi kami di " . EmailConfig::FROM_EMAIL . "

Terima kasih,
Tim Sorot Dunia

---
© " . date('Y') . " Sorot Dunia. All rights reserved.
Email ini dikirim secara otomatis, mohon jangan membalas.
    ";
}

/**
 * Test SMTP connection - for debugging
 */
function testSMTPConnection() {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EmailConfig::SMTP_USERNAME;
        $mail->Password   = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = EmailConfig::SMTP_ENCRYPTION;
        $mail->Port       = EmailConfig::SMTP_PORT;
        $mail->SMTPDebug  = 2;
        $mail->Timeout    = 30;
        
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return ['success' => true, 'message' => 'SMTP connection successful'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send test email - for debugging
 */
function sendTestEmail($to_email = 'sorotdunia10@gmail.com') {
    return sendResetEmailSMTP(
        $to_email,
        'TEST_TOKEN_12345',
        'TestUser',
        'Test User Name'
    );
}

// Log successful load
error_log("✅ Email configuration loaded successfully");
error_log("📧 SMTP Host: " . EmailConfig::SMTP_HOST);
error_log("📧 From: " . EmailConfig::FROM_EMAIL);
?>