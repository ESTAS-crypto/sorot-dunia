<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session
session_start();

// Include database configuration
include 'config/config.php';

// Variables for error and success messages
$error_message = '';
$success_message = '';

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

// Check database connection
if (!$koneksi) {
    $error_message = "Koneksi database gagal: " . mysqli_connect_error();
} else {
    // Process registration form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize inputs with fallback to empty string if not set
        $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $full_name = isset($_POST['full_name']) ? sanitize_input($_POST['full_name']) : '';

        // Enhanced validation
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
            $error_message = "Semua kolom harus diisi!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid!";
        } elseif (strlen($email) > 100) {
            $error_message = "Email terlalu panjang (maksimal 100 karakter)!";
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $error_message = "Username harus 3-20 karakter!";
        } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $username)) {
            $error_message = "Username harus dimulai dengan huruf dan hanya boleh mengandung huruf, angka, dan underscore!";
        } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
            $error_message = "Nama lengkap harus 2-100 karakter!";
        } elseif (!preg_match('/^[a-zA-Z\s\.\-\']+$/', $full_name)) {
            $error_message = "Nama lengkap hanya boleh mengandung huruf, spasi, titik, tanda hubung, dan apostrof!";
        } elseif ($password !== $confirm_password) {
            $error_message = "Password dan konfirmasi password tidak sama!";
        } elseif (strlen($password) < 8) {
            $error_message = "Password minimal 8 karakter!";
        } elseif (strlen($password) > 128) {
            $error_message = "Password maksimal 128 karakter!";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]).{8,}$/', $password)) {
            $error_message = "Password harus mengandung minimal 1 huruf kecil, 1 huruf besar, 1 angka, dan 1 simbol!";
        } elseif (preg_match('/(?:abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz|123|234|345|456|567|678|789)/i', $password)) {
            $error_message = "Password tidak boleh mengandung urutan karakter beruntun!";
        } else {
            // Check for common weak passwords
            $common_passwords = [
                'password', 'Password1!', '12345678', 'qwerty123', 'admin123',
                'Password123!', '123456789', 'password123', 'admin1234'
            ];
            
            if (in_array($password, $common_passwords)) {
                $error_message = "Password terlalu umum, gunakan password yang lebih unik!";
            } else {
                // Check if username or email already exists
                $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
                $check_stmt = mysqli_prepare($koneksi, $check_query);

                if ($check_stmt === false) {
                    $error_message = "Gagal menyiapkan pernyataan cek: " . mysqli_error($koneksi);
                } else {
                    mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                    if (!mysqli_stmt_execute($check_stmt)) {
                        $error_message = "Gagal mengeksekusi pernyataan cek: " . mysqli_stmt_error($check_stmt);
                    } else {
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        if ($check_result === false) {
                            $error_message = "Gagal mendapatkan hasil: " . mysqli_error($koneksi);
                        } else {
                            $row_count = mysqli_num_rows($check_result);
                            if ($row_count > 0) {
                                $error_message = "Username atau email sudah digunakan!";
                            } else {
                                // Hash password and insert new user
                                $hashed_password = hash_password($password);

                                // Insert new user
                                $insert_query = "INSERT INTO users (username, email, password, full_name, role, created_at) VALUES (?, ?, ?, ?, 'pembaca', NOW())";
                                $insert_stmt = mysqli_prepare($koneksi, $insert_query);

                                if ($insert_stmt === false) {
                                    $error_message = "Gagal menyiapkan pernyataan insert: " . mysqli_error($koneksi);
                                } else {
                                    mysqli_stmt_bind_param($insert_stmt, "ssss", $username, $email, $hashed_password, $full_name);
                                    if (!mysqli_stmt_execute($insert_stmt)) {
                                        $error_message = "Gagal mengeksekusi pernyataan insert: " . mysqli_stmt_error($insert_stmt);
                                    } else {
                                        // Set success message in session and redirect immediately
                                        $_SESSION['success_message'] = "Registrasi berhasil! Silakan login dengan akun baru Anda.";
                                        header("Location: login.php");
                                        exit();
                                    }
                                    mysqli_stmt_close($insert_stmt);
                                }
                            }
                        }
                        mysqli_stmt_close($check_stmt);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sorot Dunia</title>
    <link rel="icon" href="img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Base Reset & Background */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html,
    body {
        height: 100%;
        overflow-x: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

    /* Main Container */
    .register-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    /* Register Card */
    .register-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 2.5rem 2rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 440px;
        border: 1px solid rgba(255, 255, 255, 0.2);
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

    .register-title {
        font-size: 2.2rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 2rem;
        color: #2c3e50;
        letter-spacing: -0.5px;
    }

    /* Form Group with proper spacing */
    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 14px;
        display: block;
    }

    /* Input Container for proper icon positioning */
    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .form-control {
        width: 100%;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 14px 50px 14px 16px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.9);
        line-height: 1.2;
    }

    .form-control:focus {
        border-color: #4CAF50;
        box-shadow: 0 0 0 0.25rem rgba(76, 175, 80, 0.15);
        background: white;
        outline: none;
    }

    .form-control.is-valid {
        border-color: #28a745;
        background-image: none;
    }

    .form-control.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }

    /* Icon positioning - Fixed */
    .field-icon {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
        font-size: 16px;
        pointer-events: none;
        z-index: 2;
        transition: all 0.3s ease;
    }

    .field-icon.valid {
        color: #28a745;
    }

    .field-icon.invalid {
        color: #dc3545;
    }

    /* Password toggle - separate from validation icon */
    .password-toggle-btn {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
        font-size: 16px;
        cursor: pointer;
        z-index: 3;
        padding: 4px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .password-toggle-btn:hover {
        color: #495057;
        background-color: rgba(0, 0, 0, 0.05);
    }

    .password-toggle-btn:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.25);
    }

    /* Password requirements styling */
    .password-requirements {
        margin-top: 0.75rem;
        padding: 0.75rem;
        background: rgba(248, 249, 250, 0.8);
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .requirement {
        display: flex;
        align-items: center;
        font-size: 12px;
        margin-bottom: 0.25rem;
        transition: all 0.3s ease;
    }

    .requirement:last-child {
        margin-bottom: 0;
    }

    .requirement-icon {
        width: 14px;
        height: 14px;
        margin-right: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 8px;
        flex-shrink: 0;
    }

    .requirement.met {
        color: #28a745;
    }

    .requirement.met .requirement-icon {
        background-color: #28a745;
        color: white;
    }

    .requirement.unmet {
        color: #6c757d;
    }

    .requirement.unmet .requirement-icon {
        background-color: #dc3545;
        color: white;
    }

    /* Helper text */
    .form-text {
        font-size: 12px;
        color: #6c757d;
        margin-top: 0.5rem;
        line-height: 1.4;
    }

    /* Submit button */
    .btn-register {
        width: 100%;
        background: linear-gradient(135deg, #4CAF50, #45a049);
        border: none;
        border-radius: 12px;
        padding: 16px 24px;
        font-size: 16px;
        font-weight: 600;
        color: white;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        margin-top: 1rem;
    }

    .btn-register:hover:not(:disabled) {
        background: linear-gradient(135deg, #45a049, #3d8b40);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
    }

    .btn-register:disabled {
        background: #adb5bd;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-register:focus {
        outline: none;
        box-shadow: 0 0 0 0.25rem rgba(76, 175, 80, 0.25);
    }

    /* Footer links */
    .footer-links {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e9ecef;
    }

    .footer-links a {
        color: #4CAF50;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .footer-links a:hover {
        color: #45a049;
        text-decoration: underline;
    }

    /* Alert messages */
    .alert {
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        border: none;
        font-size: 14px;
        display: flex;
        align-items: center;
    }

    .alert i {
        margin-right: 0.5rem;
        font-size: 16px;
    }

    .alert-danger {
        background-color: rgba(220, 53, 69, 0.1);
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    .alert-success {
        background-color: rgba(40, 167, 69, 0.1);
        color: #155724;
        border-left: 4px solid #28a745;
    }

    /* Caps lock warning */
    .caps-warning {
        font-size: 12px;
        color: #ffc107;
        margin-top: 0.25rem;
        display: flex;
        align-items: center;
    }

    .caps-warning i {
        margin-right: 0.5rem;
    }

    /* Loading state */
    .loading-spinner {
        width: 16px;
        height: 16px;
        margin-right: 0.5rem;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .register-container {
            padding: 15px;
        }

        .register-card {
            padding: 2rem 1.5rem;
            max-width: 100%;
        }

        .register-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }

        .form-control {
            padding: 12px 45px 12px 14px;
            font-size: 15px;
        }

        .field-icon,
        .password-toggle-btn {
            right: 14px;
        }
    }

    @media (max-width: 480px) {
        .register-container {
            padding: 10px;
        }

        .register-card {
            padding: 1.5rem 1rem;
            border-radius: 16px;
        }

        .register-title {
            font-size: 1.6rem;
            margin-bottom: 1.25rem;
        }

        .form-control {
            padding: 12px 40px 12px 12px;
            font-size: 14px;
        }

        .field-icon,
        .password-toggle-btn {
            right: 12px;
            font-size: 14px;
        }

        .btn-register {
            padding: 14px 20px;
            font-size: 15px;
        }
    }

    @media (max-width: 375px) {
        .register-card {
            padding: 1.25rem 0.75rem;
        }

        .form-control {
            padding: 10px 35px 10px 10px;
        }

        .field-icon,
        .password-toggle-btn {
            right: 10px;
        }
    }

    /* Desktop specific */
    @media (min-width: 992px) {
        .register-container {
            justify-content: flex-start;
            padding-left: 8%;
        }

        .register-card {
            max-width: 420px;
        }
    }

    /* Landscape mobile */
    @media (max-height: 500px) and (orientation: landscape) {
        .register-container {
            min-height: auto;
            padding: 15px;
        }

        .register-card {
            padding: 1.5rem;
        }

        .register-title {
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }
    }
    </style>
</head>

<body>
    <img src="img/bg_login.webp" alt="Background Image" class="background-image">

    <div class="register-container">
        <div class="register-card">
            <h2 class="register-title">Sign Up</h2>

            <!-- PHP Error/Success Messages -->
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
            <?php endif; ?>

            <!-- JavaScript Error/Success Messages -->
            <div id="errorAlert" class="alert alert-danger d-none" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <span id="errorText"></span>
            </div>

            <div id="successAlert" class="alert alert-success d-none" role="alert">
                <i class="fas fa-check-circle"></i>
                <span id="successText"></span>
            </div>

            <form id="registerForm" method="POST" action="" autocomplete="off">
                <!-- Full Name -->
                <div class="form-group">
                    <label for="full_name" class="form-label">Nama Lengkap</label>
                    <div class="input-wrapper">
                        <input type="text" class="form-control" id="full_name" name="full_name"
                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                            required autocomplete="name">
                        <i class="field-icon fas fa-user"></i>
                    </div>
                    <div class="form-text">2-100 karakter, hanya huruf, spasi, titik, tanda hubung, dan apostrof.</div>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-wrapper">
                        <input type="text" class="form-control" id="username" name="username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required autocomplete="username">
                        <i class="field-icon fas fa-at"></i>
                    </div>
                    <div class="form-text">3-20 karakter, huruf, angka, dan underscore. Dimulai dengan huruf.</div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-wrapper">
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            required autocomplete="email">
                        <i class="field-icon fas fa-envelope"></i>
                    </div>
                    <div class="form-text">Maksimal 100 karakter, format email yang valid.</div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <input type="password" class="form-control" id="password" name="password" required
                            autocomplete="new-password">
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('password')"
                            aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <div class="requirement unmet" id="req-length">
                            <div class="requirement-icon">
                                <i class="fas fa-times"></i>
                            </div>
                            Minimal 8 karakter
                        </div>
                        <div class="requirement unmet" id="req-upper">
                            <div class="requirement-icon">
                                <i class="fas fa-times"></i>
                            </div>
                            Minimal 1 huruf besar
                        </div>
                        <div class="requirement unmet" id="req-lower">
                            <div class="requirement-icon">
                                <i class="fas fa-times"></i>
                            </div>
                            Minimal 1 huruf kecil
                        </div>
                        <div class="requirement unmet" id="req-number">
                            <div class="requirement-icon">
                                <i class="fas fa-times"></i>
                            </div>
                            Minimal 1 angka
                        </div>
                        <div class="requirement unmet" id="req-special">
                            <div class="requirement-icon">
                                <i class="fas fa-times"></i>
                            </div>
                            Minimal 1 simbol (!@#$%^&*()_+-=[]{}|;':",./<>?)
                        </div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            required autocomplete="new-password">
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm_password')"
                            aria-label="Toggle confirm password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-register" id="submitBtn" disabled>
                    <span id="submitText">Sign Up</span>
                </button>

                <div class="footer-links">
                    <span class="text-muted">Sudah punya akun? </span>
                    <a href="login.php">Login di sini</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Password visibility toggle
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const toggleBtn = field.nextElementSibling;
        const icon = toggleBtn.querySelector('i');

        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            toggleBtn.setAttribute('aria-label', 'Hide password');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            toggleBtn.setAttribute('aria-label', 'Show password');
        }
    }

    // Real-time password validation
    function validatePasswordRealTime(password) {
        const requirements = {
            'req-length': password.length >= 8,
            'req-upper': /[A-Z]/.test(password),
            'req-lower': /[a-z]/.test(password),
            'req-number': /[0-9]/.test(password),
            'req-special': /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };

        let allMet = true;
        for (const [reqId, isMet] of Object.entries(requirements)) {
            const element = document.getElementById(reqId);
            const icon = element.querySelector('.requirement-icon i');

            if (isMet) {
                element.classList.remove('unmet');
                element.classList.add('met');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-check');
            } else {
                element.classList.remove('met');
                element.classList.add('unmet');
                icon.classList.remove('fa-check');
                icon.classList.add('fa-times');
                allMet = false;
            }
        }

        return allMet;
    }

    // Input validation functions
    function validateInput(input, type) {
        let isValid = false;
        const value = input.value.trim();
        const wrapper = input.parentElement;
        const icon = wrapper.querySelector('.field-icon');

        switch (type) {
            case 'username':
                isValid = value.length >= 3 && value.length <= 20 &&
                    /^[a-zA-Z][a-zA-Z0-9_]*$/.test(value);
                break;
            case 'email':
                isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) &&
                    value.length <= 100;
                break;
            case 'fullname':
                isValid = value.length >= 2 && value.length <= 100 &&
                    /^[a-zA-Z\s\.\-\']+$/.test(value);
                break;
            case 'password':
                isValid = validatePasswordRealTime(value);
                break;
        }

        // Update input styling and icon
        if (value === '') {
            input.classList.remove('is-valid', 'is-invalid');
            if (icon) {
                icon.classList.remove('valid', 'invalid');
            }
        } else if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            if (icon) {
                icon.classList.remove('invalid');
                icon.classList.add('valid');
            }
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            if (icon) {
                icon.classList.remove('valid');
                icon.classList.add('invalid');
            }
        }

        return isValid;
    }

    // Password match validation
    function validatePasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const confirmInput = document.getElementById('confirm_password');

        if (confirmPassword === '') {
            confirmInput.classList.remove('is-valid', 'is-invalid');
            return false;
        }

        if (password === confirmPassword && password !== '') {
            confirmInput.classList.remove('is-invalid');
            confirmInput.classList.add('is-valid');
            return true;
        } else {
            confirmInput.classList.remove('is-valid');
            confirmInput.classList.add('is-invalid');
            return false;
        }
    }

    // Update submit button state
    function updateSubmitButton() {
        const inputs = {
            username: document.getElementById('username'),
            email: document.getElementById('email'),
            fullName: document.getElementById('full_name'),
            password: document.getElementById('password'),
            confirmPassword: document.getElementById('confirm_password')
        };

        const validations = {
            username: validateInput(inputs.username, 'username'),
            email: validateInput(inputs.email, 'email'),
            fullName: validateInput(inputs.fullName, 'fullname'),
            password: validateInput(inputs.password, 'password'),
            confirmPassword: validatePasswordMatch()
        };

        const allValid = Object.values(validations).every(valid => valid);
        document.getElementById('submitBtn').disabled = !allValid;

        return allValid;
    }

    // Caps Lock detection
    function checkCapsLock(e, input) {
        const char = String.fromCharCode(e.which || e.keyCode);
        if (char && char === char.toUpperCase() && char !== char.toLowerCase() && !e.shiftKey) {
            showCapsLockWarning(input);
        } else {
            hideCapsLockWarning(input);
        }
    }

    function showCapsLockWarning(input) {
        const wrapper = input.parentElement.parentElement;
        let warning = wrapper.querySelector('.caps-warning');
        if (!warning) {
            warning = document.createElement('div');
            warning.className = 'caps-warning';
            warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i>Caps Lock aktif';
            wrapper.appendChild(warning);
        }
    }

    function hideCapsLockWarning(input) {
        const wrapper = input.parentElement.parentElement;
        const warning = wrapper.querySelector('.caps-warning');
        if (warning) {
            warning.remove();
        }
    }

    // Message display functions
    function showError(message) {
        const errorAlert = document.getElementById('errorAlert');
        const errorText = document.getElementById('errorText');
        errorText.textContent = message;
        errorAlert.classList.remove('d-none');
        document.getElementById('successAlert').classList.add('d-none');

        // Scroll to top of form
        document.querySelector('.register-card').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function showSuccess(message) {
        const successAlert = document.getElementById('successAlert');
        const successText = document.getElementById('successText');
        successText.textContent = message;
        successAlert.classList.remove('d-none');
        document.getElementById('errorAlert').classList.add('d-none');
    }

    function hideMessages() {
        document.getElementById('errorAlert').classList.add('d-none');
        document.getElementById('successAlert').classList.add('d-none');
    }

    // AJAX username/email availability check
    function checkAvailability(field, value) {
        if (value.length < 3) return;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'check.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                const input = document.getElementById(field);

                if (!response.available) {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                    const wrapper = input.parentElement;
                    const icon = wrapper.querySelector('.field-icon');
                    if (icon) {
                        icon.classList.remove('valid');
                        icon.classList.add('invalid');
                    }

                    // Show error message
                    let errorMsg = field === 'username' ? 'Username sudah digunakan!' : 'Email sudah digunakan!';
                    showError(errorMsg);
                } else {
                    // Clear any previous error if the field is now valid
                    hideMessages();
                }
            }
        };

        xhr.send(field + '=' + encodeURIComponent(value));
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = ['username', 'email', 'full_name', 'password', 'confirm_password'];

        inputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            let debounceTimer;

            // Input event for real-time validation
            input.addEventListener('input', function() {
                hideMessages();

                if (inputId === 'confirm_password') {
                    validatePasswordMatch();
                } else {
                    const type = inputId === 'full_name' ? 'fullname' : inputId;
                    validateInput(this, type);
                }
                updateSubmitButton();

                // Debounced availability check for username and email
                if (inputId === 'username' || inputId === 'email') {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        const isValid = inputId === 'username' ?
                            validateInput(this, 'username') :
                            validateInput(this, 'email');

                        if (isValid && this.value.trim() !== '') {
                            checkAvailability(inputId, this.value.trim());
                        }
                    }, 1000);
                }
            });

            // Blur event for final validation
            input.addEventListener('blur', function() {
                if (inputId === 'confirm_password') {
                    validatePasswordMatch();
                } else {
                    const type = inputId === 'full_name' ? 'fullname' : inputId;
                    validateInput(this, type);
                }
                updateSubmitButton();
            });

            // Caps Lock detection for password fields
            if (input.type === 'password') {
                input.addEventListener('keypress', function(e) {
                    checkCapsLock(e, this);
                });

                input.addEventListener('keyup', function() {
                    if (this.value === '') {
                        hideCapsLockWarning(this);
                    }
                });
            }
        });

        // Form submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');

            // Final client-side validation
            if (!updateSubmitButton()) {
                e.preventDefault();
                showError('Mohon lengkapi semua field dengan benar!');
                return;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                showError('Password dan Confirm Password tidak sama!');
                return;
            }

            // Additional security checks
            if (password.length > 128) {
                e.preventDefault();
                showError('Password terlalu panjang (maksimal 128 karakter)!');
                return;
            }

            // Check for sequential characters
            if (/(?:abc|bcd|cde|def|efg|fgh|ghi|hij|ijk|jkl|klm|lmn|mno|nop|opq|pqr|qrs|rst|stu|tuv|uvw|vwx|wxy|xyz|123|234|345|456|567|678|789)/i
                .test(password)) {
                e.preventDefault();
                showError('Password tidak boleh mengandung urutan karakter beruntun!');
                return;
            }

            // Check for common weak passwords
            const commonPasswords = [
                'password', 'Password1!', '12345678', 'qwerty123', 'admin123',
                'Password123!', '123456789', 'password123', 'admin1234'
            ];

            if (commonPasswords.includes(password)) {
                e.preventDefault();
                showError('Password terlalu umum, gunakan password yang lebih unik!');
                return;
            }

            // Show loading state
            submitText.innerHTML = '<i class="fas fa-spinner fa-spin loading-spinner"></i>Mendaftar...';
            submitBtn.disabled = true;

            // Reset loading state if form submission fails (timeout)
            setTimeout(() => {
                if (submitText.innerHTML.includes('Mendaftar...')) {
                    submitText.textContent = 'Sign Up';
                    submitBtn.disabled = false;
                }
            }, 15000);
        });

        // Initialize validation state
        updateSubmitButton();

        // Prevent form auto-fill issues
        setTimeout(() => {
            updateSubmitButton();
        }, 500);
    });

    // Security: Clear sensitive data on page unload
    window.addEventListener('beforeunload', function() {
        document.getElementById('password').value = '';
        document.getElementById('confirm_password').value = '';
    });

    // Prevent right-click on password fields (additional security)
    document.querySelectorAll('input[type="password"]').forEach(input => {
        input.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
    });

    // Prevent drag and drop on form inputs
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('dragstart', function(e) {
            e.preventDefault();
        });
        input.addEventListener('drop', function(e) {
            e.preventDefault();
        });
    });

    // Additional security: Detect and prevent automated form filling
    let humanCheck = false;
    document.addEventListener('mousemove', function() {
        humanCheck = true;
    });

    document.addEventListener('keydown', function() {
        humanCheck = true;
    });

    // Form interaction check
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (!humanCheck) {
            e.preventDefault();
            showError('Sistem mendeteksi aktivitas yang mencurigakan. Mohon coba lagi.');
            return;
        }
    });
    </script>
</body>

</html>