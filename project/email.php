<?php
// email.php - SMTP Configuration for Password Reset

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader (jika menggunakan Composer)
// require 'vendor/autoload.php';

// Atau load PHPMailer secara manual (download PHPMailer dan extract ke folder phpmailer)
require_once 'vendor/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/src/SMTP.php';

// SMTP Configuration - Sesuaikan dengan kredensial SMTP Anda
class EmailConfig {
    // Gmail SMTP Settings (contoh)
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'sorotdunia10@gmail.com'; // Ganti dengan email Anda
    const SMTP_PASSWORD = 'fqtx qtyv sgva tudq'; // Ganti dengan App Password Gmail
    const SMTP_ENCRYPTION = PHPMailer::ENCRYPTION_STARTTLS;
    
    // Sender Info
    const FROM_EMAIL = 'sorotdunia10.com'; // Ganti dengan email Anda
    const FROM_NAME = 'Sorot Dunia';
    
    // Alternative SMTP (jika menggunakan hosting/domain email)
    /*
    const SMTP_HOST = 'mail.yourdomain.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'noreply@yourdomain.com';
    const SMTP_PASSWORD = 'your_email_password';
    const SMTP_ENCRYPTION = PHPMailer::ENCRYPTION_STARTTLS;
    const FROM_EMAIL = 'noreply@yourdomain.com';
    const FROM_NAME = 'Sorot Dunia';
    */
}

/**
 * Send password reset email
 */
function sendResetEmailSMTP($to_email, $token, $username, $full_name = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EmailConfig::SMTP_USERNAME;
        $mail->Password   = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = EmailConfig::SMTP_ENCRYPTION;
        $mail->Port       = EmailConfig::SMTP_PORT;
        
        // Set charset
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        $mail->addAddress($to_email, $full_name ?: $username);
        $mail->addReplyTo(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        
        // Generate reset link
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=" . $token;
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset Password - Sorot Dunia';
        
        // HTML email template
        $mail->Body = getResetEmailTemplate($username, $full_name, $reset_link, $token);
        
        // Plain text version
        $mail->AltBody = getResetEmailTextVersion($username, $reset_link);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("Password reset email sent successfully to: $to_email");
            return true;
        } else {
            error_log("Failed to send password reset email to: $to_email");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

/**
 * HTML Email Template for Password Reset
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
                transition: transform 0.2s ease;
            }
            .reset-button a:hover {
                transform: translateY(-2px);
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
            @media (max-width: 600px) {
                .container {
                    margin: 10px;
                }
                .content {
                    padding: 20px 15px;
                }
                .header {
                    padding: 20px 15px;
                }
                .header h1 {
                    font-size: 24px;
                }
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
                        🔑 Reset Password Sekarang
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
                    Password Anda akan tetap aman dan tidak berubah. Kami juga menyarankan untuk:
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>Periksa aktivitas akun Anda</li>
                        <li>Ganti password secara berkala</li>
                        <li>Jangan bagikan informasi login kepada orang lain</li>
                    </ul>
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
                <p style='margin-top: 10px;'>
                    <small>Token: " . substr($token, 0, 8) . "...****</small>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Plain text version of reset email
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
 * Test SMTP connection
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
        
        // Test connection
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return ['success' => true, 'message' => 'SMTP connection successful'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send test email
 */
function sendTestEmail($to_email) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EmailConfig::SMTP_USERNAME;
        $mail->Password   = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = EmailConfig::SMTP_ENCRYPTION;
        $mail->Port       = EmailConfig::SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        $mail->addAddress($to_email);
        
        $mail->isHTML(true);
        $mail->Subject = 'Test Email - Sorot Dunia SMTP';
        $mail->Body    = '<h2>SMTP Test Berhasil!</h2><p>Konfigurasi email Anda sudah benar.</p><p>Tanggal: ' . date('Y-m-d H:i:s') . '</p>';
        $mail->AltBody = 'SMTP Test Berhasil! Konfigurasi email Anda sudah benar. Tanggal: ' . date('Y-m-d H:i:s');
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Test email failed: {$mail->ErrorInfo}");
        return false;
    }
}

?>