<?php
// reset_password.php - COMPLETE FIX VERSION
ob_start();
session_start();
require_once 'config/config.php';

// Redirect jika sudah login
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$error_message = '';
$success_message = '';
$token_valid = false;
$user_email = '';
$user_data = null;

// PERBAIKAN UTAMA: Validasi token dari URL
if (!empty($token)) {
    // Log untuk debugging
    error_log("=== RESET PASSWORD TOKEN VALIDATION ===");
    error_log("Raw token from URL: " . $token);
    error_log("Token length: " . strlen($token));
    
    // Hash token menggunakan SHA-256 (sama dengan saat create)
    $token_hash = hash('sha256', $token);
    error_log("Token hash for DB: " . $token_hash);
    
    // Cek token di database
    $check_query = "SELECT 
                        prt.id,
                        prt.user_id,
                        prt.token_hash,
                        prt.expires_at,
                        prt.used_at,
                        prt.created_at,
                        u.id as uid,
                        u.email, 
                        u.username,
                        u.full_name
                    FROM password_reset_tokens prt
                    JOIN users u ON prt.user_id = u.id
                    WHERE prt.token_hash = ? 
                    LIMIT 1";
    
    $stmt = mysqli_prepare($koneksi, $check_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $token_hash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $token_data = mysqli_fetch_assoc($result);
            
            error_log("Token found in database!");
            error_log("Token ID: " . $token_data['id']);
            error_log("User ID: " . $token_data['user_id']);
            error_log("Expires at: " . $token_data['expires_at']);
            error_log("Used at: " . ($token_data['used_at'] ?? 'NULL'));
            
            // Cek apakah token sudah digunakan
            if (!empty($token_data['used_at'])) {
                error_log("Token already used at: " . $token_data['used_at']);
                $error_message = 'Token ini sudah pernah digunakan. Silakan minta link reset password baru.';
            }
            // Cek apakah token sudah kadaluarsa
            else if (strtotime($token_data['expires_at']) < time()) {
                error_log("Token expired. Expires at: " . $token_data['expires_at'] . ", Current time: " . date('Y-m-d H:i:s'));
                $error_message = 'Token sudah kadaluarsa. Silakan minta link reset password baru.';
            }
            // Token valid!
            else {
                $token_valid = true;
                $user_email = $token_data['email'];
                $user_data = $token_data;
                error_log("✅ Token is VALID!");
            }
        } else {
            error_log("❌ Token NOT FOUND in database");
            error_log("SQL Query: " . $check_query);
            error_log("Token hash used: " . $token_hash);
            
            // Debug: Cek token yang ada di database
            $debug_query = "SELECT id, LEFT(token_hash, 20) as hash_preview, expires_at, used_at 
                           FROM password_reset_tokens 
                           ORDER BY created_at DESC LIMIT 5";
            $debug_result = mysqli_query($koneksi, $debug_query);
            
            if ($debug_result) {
                error_log("Recent tokens in database:");
                while ($row = mysqli_fetch_assoc($debug_result)) {
                    error_log("  ID: {$row['id']}, Hash (first 20): {$row['hash_preview']}..., Expires: {$row['expires_at']}, Used: " . ($row['used_at'] ?? 'NULL'));
                }
            }
            
            $error_message = 'Token tidak valid atau tidak ditemukan. Silakan minta link reset password baru.';
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("❌ Failed to prepare statement: " . mysqli_error($koneksi));
        $error_message = 'Kesalahan sistem. Silakan hubungi admin.';
    }
} else {
    $error_message = 'Token tidak ditemukan di URL.';
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Sorot Dunia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .reset-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .reset-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .reset-header p {
            color: #666;
            font-size: 14px;
        }

        .password-container {
            position: relative;
            margin-bottom: 20px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            cursor: pointer;
            color: #667eea;
            z-index: 10;
        }

        .form-control {
            padding-right: 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .password-strength-meter {
            height: 4px;
            background-color: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-weak .password-strength-bar {
            width: 33.33%;
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        .strength-medium .password-strength-bar {
            width: 66.66%;
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .strength-strong .password-strength-bar {
            width: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .password-strength-text {
            font-size: 12px;
            font-weight: 500;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-requirements {
            background-color: #f8f9fa;
            border-left: 3px solid #667eea;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .password-requirements h6 {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            font-size: 12px;
            padding: 4px 0;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-requirements li i {
            width: 14px;
            text-align: center;
        }

        .password-requirements li.valid {
            color: #10b981;
        }

        .password-requirements li.valid i {
            color: #10b981;
        }

        .password-requirements li.invalid i {
            color: #dc3545;
        }

        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-reset:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: #fee;
            color: #c33;
        }

        .alert-success {
            background-color: #efe;
            color: #3c3;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .error-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .error-icon i {
            font-size: 48px;
            color: #dc3545;
        }

        .user-info-box {
            background: #f8f9fa;
            border-left: 3px solid #667eea;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .user-info-box strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if (!$token_valid): ?>
            <!-- TOKEN INVALID -->
            <div class="error-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            
            <div class="reset-header">
                <h2>Token Tidak Valid</h2>
                <p>Maaf, link reset password Anda tidak valid atau sudah kadaluarsa.</p>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>Kembali ke Beranda
                </a>
            </div>

            <div class="back-link">
                <p>Butuh reset password lagi?</p>
                <a href="index.php" onclick="event.preventDefault(); openForgotPasswordModal();">
                    <i class="fas fa-key me-1"></i>Klik di sini untuk request baru
                </a>
            </div>

        <?php else: ?>
            <!-- TOKEN VALID - SHOW FORM -->
            <div class="reset-header">
                <h2><i class="fas fa-lock-open me-2"></i>Reset Password</h2>
                <p>Masukkan password baru Anda</p>
            </div>

            <!-- User Info -->
            <div class="user-info-box">
                <div><i class="fas fa-user me-2"></i><strong>Username:</strong> <?php echo htmlspecialchars($user_data['username']); ?></div>
                <div><i class="fas fa-envelope me-2"></i><strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?></div>
            </div>

            <form id="resetPasswordForm">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="mb-3">
                    <label for="newPassword" class="form-label">Password Baru</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="newPassword" 
                            name="new_password" 
                            required
                            minlength="8"
                            maxlength="100"
                            placeholder="Minimal 8 karakter">
                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Strength Indicator -->
                <div class="password-strength-meter" id="strengthMeter">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
                <div class="password-strength-text" id="strengthText"></div>

                <!-- Password Requirements -->
                <div class="password-requirements">
                    <h6><i class="fas fa-shield-alt me-1"></i>Persyaratan Password:</h6>
                    <ul id="passwordRequirements">
                        <li id="req-length" class="invalid">
                            <i class="fas fa-times-circle"></i>
                            <span>Minimal 8 karakter</span>
                        </li>
                        <li id="req-uppercase" class="invalid">
                            <i class="fas fa-times-circle"></i>
                            <span>Minimal 1 huruf besar (A-Z)</span>
                        </li>
                        <li id="req-lowercase" class="invalid">
                            <i class="fas fa-times-circle"></i>
                            <span>Minimal 1 huruf kecil (a-z)</span>
                        </li>
                        <li id="req-number" class="invalid">
                            <i class="fas fa-times-circle"></i>
                            <span>Minimal 1 angka (0-9)</span>
                        </li>
                        <li id="req-special" class="invalid">
                            <i class="fas fa-times-circle"></i>
                            <span>Minimal 1 karakter spesial (!@#$%^&*)</span>
                        </li>
                    </ul>
                </div>

                <div class="mb-3 mt-4">
                    <label for="confirmPassword" class="form-label">Konfirmasi Password</label>
                    <div class="password-container">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="confirmPassword" 
                            name="confirm_password" 
                            required
                            minlength="8"
                            maxlength="100"
                            placeholder="Ulangi password">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchMessage"></div>
                </div>

                <div id="resetAlert"></div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-reset" id="resetSubmitBtn">
                        <i class="fas fa-check-circle me-2"></i>Reset Password
                    </button>
                </div>
            </form>

            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Kembali ke Beranda
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Password strength checker dan form handler
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const strengthMeter = document.getElementById('strengthMeter');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordMatchMessage = document.getElementById('passwordMatchMessage');
    const resetForm = document.getElementById('resetPasswordForm');
    const resetSubmitBtn = document.getElementById('resetSubmitBtn');
    const resetAlert = document.getElementById('resetAlert');

    // Requirements elements
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqLowercase = document.getElementById('req-lowercase');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');

    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = event.currentTarget.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function checkPasswordStrength(password) {
        let strength = 0;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };

        // Update requirements UI
        updateRequirement(reqLength, requirements.length);
        updateRequirement(reqUppercase, requirements.uppercase);
        updateRequirement(reqLowercase, requirements.lowercase);
        updateRequirement(reqNumber, requirements.number);
        updateRequirement(reqSpecial, requirements.special);

        // Calculate strength
        if (requirements.length) strength++;
        if (requirements.uppercase) strength++;
        if (requirements.lowercase) strength++;
        if (requirements.number) strength++;
        if (requirements.special) strength++;

        return { score: strength, requirements: requirements };
    }

    function updateRequirement(element, isValid) {
        if (!element) return;
        const icon = element.querySelector('i');
        if (isValid) {
            element.classList.remove('invalid');
            element.classList.add('valid');
            icon.className = 'fas fa-check-circle';
        } else {
            element.classList.remove('valid');
            element.classList.add('invalid');
            icon.className = 'fas fa-times-circle';
        }
    }

    function updateStrengthUI(score) {
        if (!strengthMeter) return;
        
        strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');

        if (score === 0) {
            strengthText.innerHTML = '';
            return;
        }

        if (score <= 2) {
            strengthMeter.classList.add('strength-weak');
            strengthText.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Password Lemah</span>';
        } else if (score <= 4) {
            strengthMeter.classList.add('strength-medium');
            strengthText.innerHTML = '<i class="fas fa-shield-alt"></i><span>Password Sedang</span>';
        } else {
            strengthMeter.classList.add('strength-strong');
            strengthText.innerHTML = '<i class="fas fa-shield-alt"></i><span>Password Kuat</span>';
        }
    }

    function checkPasswordMatch() {
        if (!newPassword || !confirmPassword || !passwordMatchMessage) return false;
        
        const password = newPassword.value;
        const confirm = confirmPassword.value;

        if (confirm === '') {
            passwordMatchMessage.innerHTML = '';
            return false;
        }

        if (password === confirm) {
            passwordMatchMessage.innerHTML = '<div style="color: #10b981; font-size: 12px; margin-top: 6px;"><i class="fas fa-check-circle me-1"></i>Password cocok</div>';
            return true;
        } else {
            passwordMatchMessage.innerHTML = '<div style="color: #dc3545; font-size: 12px; margin-top: 6px;"><i class="fas fa-times-circle me-1"></i>Password tidak cocok</div>';
            return false;
        }
    }

    function updateSubmitButton() {
        if (!newPassword || !resetSubmitBtn) return;
        
        const result = checkPasswordStrength(newPassword.value);
        const allRequirementsMet = Object.values(result.requirements).every(req => req === true);
        const passwordsMatch = checkPasswordMatch();

        if (allRequirementsMet && passwordsMatch) {
            resetSubmitBtn.disabled = false;
            resetSubmitBtn.style.opacity = '1';
        } else {
            resetSubmitBtn.disabled = true;
            resetSubmitBtn.style.opacity = '0.6';
        }
    }

    if (newPassword) {
        newPassword.addEventListener('input', function() {
            const result = checkPasswordStrength(this.value);
            updateStrengthUI(result.score);
            updateSubmitButton();
        });
    }

    if (confirmPassword) {
        confirmPassword.addEventListener('input', updateSubmitButton);
    }

    // Form submit handler
    if (resetForm) {
        resetForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const token = this.querySelector('input[name="token"]').value;
            const newPass = newPassword.value;
            const confirmPass = confirmPassword.value;

            console.log('🚀 Submitting reset password form...');
            console.log('Token:', token);

            if (newPass !== confirmPass) {
                showAlert('danger', 'Password tidak cocok!');
                return;
            }

            const result = checkPasswordStrength(newPass);
            if (!Object.values(result.requirements).every(req => req === true)) {
                showAlert('danger', 'Password tidak memenuhi semua persyaratan!');
                return;
            }

            resetSubmitBtn.disabled = true;
            resetSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';

            try {
                const formData = new FormData();
                formData.append('token', token);
                formData.append('new_password', newPass);
                formData.append('confirm_password', confirmPass);

                console.log('📤 Sending request to ajax/reset_password_handler.php');

                const response = await fetch('ajax/reset_password_handler.php', {
                    method: 'POST',
                    body: formData
                });

                console.log('📥 Response status:', response.status);

                const resultData = await response.json();
                console.log('📄 Response data:', resultData);

                if (resultData.success) {
                    showAlert('success', resultData.message);
                    setTimeout(() => {
                        window.location.href = 'index.php?reset=success';
                    }, 2000);
                } else {
                    showAlert('danger', resultData.message);
                    resetSubmitBtn.disabled = false;
                    resetSubmitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Reset Password';
                }
            } catch (error) {
                console.error('❌ Error:', error);
                showAlert('danger', 'Terjadi kesalahan: ' + error.message);
                resetSubmitBtn.disabled = false;
                resetSubmitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Reset Password';
            }
        });
    }

    function showAlert(type, message) {
        resetAlert.className = `alert alert-${type} alert-dismissible fade show`;
        const iconClass = type === 'success' ? 'check-circle' : 'exclamation-circle';
        resetAlert.innerHTML = `
            <i class="fas fa-${iconClass} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        resetAlert.style.display = 'block';
    }

    // Function untuk open forgot password modal di index.php
    function openForgotPasswordModal() {
        // Redirect ke index dengan parameter untuk auto-open modal
        window.location.href = 'index.php?action=forgot_password';
    }

    // Initialize button state
    if (resetSubmitBtn) {
        updateSubmitButton();
    }
    </script>
</body>
</html>