<!-- Footer Scripts untuk Forgot Password -->
<script>
// ========================================
// GLOBAL UTILITY FUNCTIONS
// ========================================

// Cleanup Modal Backdrop Function - MUST BE DEFINED FIRST
window.cleanupModalBackdrop = function() {
    console.log('🧹 Cleaning up modal backdrops...');
    
    // Remove all modal backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
        backdrop.remove();
    });
    
    // Remove modal-open class from body
    document.body.classList.remove('modal-open');
    
    // Reset body styles
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    console.log('✅ Modal backdrops cleaned');
};

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Show alert helper
function showAlert(alertElement, type, message) {
    if (!alertElement) return;
    
    alertElement.className = `alert alert-${type}`;
    const iconClass = type === 'success' ? 'check-circle' : 
                     type === 'warning' ? 'exclamation-triangle' : 
                     'exclamation-circle';
    alertElement.innerHTML = `<i class="fas fa-${iconClass} me-2"></i>${message}`;
    alertElement.classList.remove('d-none');
    
    // Auto hide after 8 seconds
    setTimeout(() => {
        alertElement.classList.add('d-none');
    }, 8000);
}

// ========================================
// MAIN INITIALIZATION
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Footer scripts initialized');
    
    // ========================================
    // FORGOT PASSWORD HANDLER - IMPROVED
    // ========================================
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const forgotPasswordAlert = document.getElementById('forgotPasswordAlert');
    const emailStep = document.getElementById('emailStep');
    const successStep = document.getElementById('successStep');
    const sendResetLinkBtn = document.getElementById('sendResetLinkBtn');
    const resendEmailBtn = document.getElementById('resendEmailBtn');
    const countdownElement = document.getElementById('countdown');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    let countdownInterval = null;
    let lastEmail = '';
    
    // Forgot Password Form Submit with improved error handling
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('📧 Submitting forgot password form...');
            
            const formData = new FormData(forgotPasswordForm);
            const email = formData.get('email');
            lastEmail = email;
            
            // Validate email
            if (!email || !isValidEmail(email)) {
                showAlert(forgotPasswordAlert, 'danger', 'Masukkan email yang valid');
                return;
            }
            
            // Show loading
            sendResetLinkBtn.disabled = true;
            sendResetLinkBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mengirim...';
            if (loadingOverlay) loadingOverlay.classList.add('show');
            
            try {
                console.log('🔄 Sending request to server...');
                console.log('📍 URL:', 'ajax/forgot_password_handler.php');
                console.log('📧 Email:', email);
                
                const response = await fetch('ajax/forgot_password_handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                console.log('📡 Response status:', response.status);
                console.log('📡 Response statusText:', response.statusText);
                console.log('📡 Response headers:', Object.fromEntries(response.headers.entries()));
                
                // Check content type
                const contentType = response.headers.get('content-type');
                console.log('📄 Content-Type:', contentType);
                
                if (!response.ok) {
                    console.error('❌ HTTP error! status:', response.status);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response text first
                const responseText = await response.text();
                console.log('📄 Response text length:', responseText.length);
                console.log('📄 Response text (first 500 chars):', responseText.substring(0, 500));
                
                // Try to parse as JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                    console.log('✅ Parsed JSON successfully:', result);
                } catch (parseError) {
                    console.error('❌ JSON parse error:', parseError);
                    console.error('❌ Parse error message:', parseError.message);
                    console.error('❌ Full response text:', responseText);
                    
                    // Try to find JSON in response
                    const jsonMatch = responseText.match(/\{[^]*\}/);
                    if (jsonMatch) {
                        console.log('🔍 Found JSON pattern in response');
                        try {
                            result = JSON.parse(jsonMatch[0]);
                            console.log('✅ Extracted and parsed JSON:', result);
                        } catch (e) {
                            console.error('❌ Failed to parse extracted JSON:', e);
                            throw new Error('Server mengembalikan response yang tidak valid. Response: ' + responseText.substring(0, 200));
                        }
                    } else {
                        throw new Error('Server mengembalikan response yang tidak valid (bukan JSON). Response: ' + responseText.substring(0, 200));
                    }
                }
                
                if (loadingOverlay) loadingOverlay.classList.remove('show');
                
                if (result.success) {
                    console.log('✅ Password reset request successful');
                    
                    // Hide email step, show success step
                    if (emailStep) emailStep.classList.add('d-none');
                    if (successStep) successStep.classList.remove('d-none');
                    
                    // Start countdown
                    startCountdown(60);
                } else {
                    console.warn('⚠️ Request failed:', result.message);
                    showAlert(forgotPasswordAlert, 'danger', result.message || 'Terjadi kesalahan');
                    sendResetLinkBtn.disabled = false;
                    sendResetLinkBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Kirim Link Reset';
                }
            } catch (error) {
                console.error('❌ Forgot password error:', error);
                console.error('❌ Error name:', error.name);
                console.error('❌ Error message:', error.message);
                console.error('❌ Error stack:', error.stack);
                
                if (loadingOverlay) loadingOverlay.classList.remove('show');
                
                let errorMessage = 'Terjadi kesalahan: ';
                if (error.message.includes('HTTP error')) {
                    errorMessage += 'Server error (500). Silakan cek konfigurasi server atau hubungi admin.';
                } else if (error.message.includes('JSON') || error.message.includes('tidak valid')) {
                    errorMessage += 'Format response tidak valid. Silakan hubungi admin untuk memeriksa file PHP.';
                } else if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                    errorMessage += 'Gagal terhubung ke server. Periksa koneksi internet Anda.';
                } else {
                    errorMessage += error.message;
                }
                
                showAlert(forgotPasswordAlert, 'danger', errorMessage);
                sendResetLinkBtn.disabled = false;
                sendResetLinkBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Kirim Link Reset';
            }
        });
    }
    
    // Resend Email Handler
    if (resendEmailBtn) {
        resendEmailBtn.addEventListener('click', async function() {
            if (resendEmailBtn.disabled) return;
            
            console.log('🔄 Resending email...');
            resendEmailBtn.disabled = true;
            resendEmailBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mengirim...';
            if (loadingOverlay) loadingOverlay.classList.add('show');
            
            const formData = new FormData();
            formData.append('email', lastEmail);
            
            try {
                const response = await fetch('ajax/forgot_password_handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const responseText = await response.text();
                let result;
                
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ JSON parse error on resend:', parseError);
                    const jsonMatch = responseText.match(/\{[^]*\}/);
                    if (jsonMatch) {
                        result = JSON.parse(jsonMatch[0]);
                    } else {
                        throw new Error('Server response tidak valid');
                    }
                }
                
                if (loadingOverlay) loadingOverlay.classList.remove('show');
                
                if (result.success) {
                    console.log('✅ Email resent successfully');
                    
                    // Reset countdown
                    startCountdown(60);
                    
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success mt-3';
                    successAlert.innerHTML = '<i class="fas fa-check-circle me-2"></i>Email telah dikirim ulang!';
                    if (successStep) {
                        const gridElement = successStep.querySelector('.d-grid');
                        if (gridElement) {
                            gridElement.before(successAlert);
                        }
                    }
                    
                    setTimeout(() => {
                        successAlert.remove();
                    }, 3000);
                } else {
                    alert('Gagal mengirim ulang email: ' + result.message);
                    resendEmailBtn.disabled = false;
                    resendEmailBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Kirim Ulang Email';
                }
            } catch (error) {
                console.error('❌ Resend email error:', error);
                if (loadingOverlay) loadingOverlay.classList.remove('show');
                alert('Terjadi kesalahan: ' + error.message);
                resendEmailBtn.disabled = false;
                resendEmailBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Kirim Ulang Email';
            }
        });
    }
    
    // Countdown Timer
    function startCountdown(seconds) {
        let timeLeft = seconds;
        
        // Clear existing interval
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        
        // Update countdown display
        if (countdownElement) {
            countdownElement.textContent = timeLeft;
        }
        
        // Disable resend button
        if (resendEmailBtn) {
            resendEmailBtn.disabled = true;
            resendEmailBtn.style.opacity = '0.6';
            resendEmailBtn.style.cursor = 'not-allowed';
        }
        
        countdownInterval = setInterval(() => {
            timeLeft--;
            if (countdownElement) {
                countdownElement.textContent = timeLeft;
            }
            
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                
                // Enable resend button
                if (resendEmailBtn) {
                    resendEmailBtn.disabled = false;
                    resendEmailBtn.style.opacity = '1';
                    resendEmailBtn.style.cursor = 'pointer';
                    resendEmailBtn.innerHTML = '<i class="fas fa-redo me-2"></i>Kirim Ulang Email';
                }
                
                // Hide countdown timer
                const countdownTimer = document.getElementById('countdownTimer');
                if (countdownTimer) {
                    countdownTimer.style.display = 'none';
                }
            }
        }, 1000);
    }
    
    // Reset form when modal is closed
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    if (forgotPasswordModal) {
        forgotPasswordModal.addEventListener('hidden.bs.modal', function() {
            console.log('🔒 Forgot password modal closed');
            
            // Reset form
            if (forgotPasswordForm) {
                forgotPasswordForm.reset();
            }
            
            // Hide alerts
            if (forgotPasswordAlert) {
                forgotPasswordAlert.classList.add('d-none');
            }
            
            // Show email step, hide success step
            if (emailStep) {
                emailStep.classList.remove('d-none');
            }
            if (successStep) {
                successStep.classList.add('d-none');
            }
            
            // Reset button
            if (sendResetLinkBtn) {
                sendResetLinkBtn.disabled = false;
                sendResetLinkBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Kirim Link Reset';
            }
            
            // Clear countdown
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            
            // Cleanup modal backdrop
            window.cleanupModalBackdrop();
        });
    }
    
    // ========================================
    // PASSWORD STRENGTH CHECKER
    // ========================================
    const registerPassword = document.getElementById('registerPassword');
    const confirmPassword = document.getElementById('registerConfirmPassword');
    const strengthMeter = document.getElementById('strengthMeter');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const registerSubmitBtn = document.getElementById('registerSubmitBtn');
    const passwordMatchMessage = document.getElementById('passwordMatchMessage');
    
    // Requirements elements
    const reqLength = document.getElementById('req-length');
    const reqUppercase = document.getElementById('req-uppercase');
    const reqLowercase = document.getElementById('req-lowercase');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    
    let passwordStrength = 0;
    let passwordsMatch = false;
    
    // Password strength calculation
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
        
        return {
            score: strength,
            requirements: requirements
        };
    }
    
    function updateRequirement(element, isValid) {
        if (!element) return;
        const icon = element.querySelector('i');
        if (isValid) {
            element.classList.remove('invalid');
            element.classList.add('valid');
            if (icon) icon.className = 'fas fa-check-circle';
        } else {
            element.classList.remove('valid');
            element.classList.add('invalid');
            if (icon) icon.className = 'fas fa-times-circle';
        }
    }
    
    function updateStrengthUI(score) {
        if (!strengthMeter) return;
        
        // Remove all strength classes
        strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
        
        if (score === 0) {
            if (strengthText) strengthText.innerHTML = '';
            return;
        }
        
        if (score <= 2) {
            strengthMeter.classList.add('strength-weak');
            if (strengthText) strengthText.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Password Lemah</span>';
            passwordStrength = 1;
        } else if (score <= 4) {
            strengthMeter.classList.add('strength-medium');
            if (strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i><span>Password Sedang</span>';
            passwordStrength = 2;
        } else {
            strengthMeter.classList.add('strength-strong');
            if (strengthText) strengthText.innerHTML = '<i class="fas fa-shield-alt"></i><span>Password Kuat</span>';
            passwordStrength = 3;
        }
    }
    
    function checkPasswordMatch() {
        if (!registerPassword || !confirmPassword || !passwordMatchMessage) return;
        
        const password = registerPassword.value;
        const confirm = confirmPassword.value;
        
        if (confirm === '') {
            passwordMatchMessage.innerHTML = '';
            passwordsMatch = false;
            return;
        }
        
        if (password === confirm) {
            passwordMatchMessage.innerHTML = '<div class="password-match"><i class="fas fa-check-circle"></i><span>Password cocok</span></div>';
            passwordsMatch = true;
        } else {
            passwordMatchMessage.innerHTML = '<div class="password-mismatch"><i class="fas fa-times-circle"></i><span>Password tidak cocok</span></div>';
            passwordsMatch = false;
        }
        
        updateSubmitButton();
    }
    
    function updateSubmitButton() {
        if (!registerPassword || !registerSubmitBtn) return;
        
        const result = checkPasswordStrength(registerPassword.value);
        const allRequirementsMet = Object.values(result.requirements).every(req => req === true);
        
        if (allRequirementsMet && passwordsMatch) {
            registerSubmitBtn.disabled = false;
            registerSubmitBtn.style.opacity = '1';
            registerSubmitBtn.style.cursor = 'pointer';
        } else {
            registerSubmitBtn.disabled = true;
            registerSubmitBtn.style.opacity = '0.6';
            registerSubmitBtn.style.cursor = 'not-allowed';
        }
    }
    
    // Event listeners for password strength
    if (registerPassword) {
        registerPassword.addEventListener('input', function() {
            const result = checkPasswordStrength(this.value);
            updateStrengthUI(result.score);
            checkPasswordMatch();
        });
    }
    
    if (confirmPassword) {
        confirmPassword.addEventListener('input', checkPasswordMatch);
    }
    
    // ========================================
    // LOGIN FORM HANDLER
    // ========================================
    const loginForm = document.getElementById('loginForm');
    const loginAlert = document.getElementById('loginAlert');
    
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(loginForm);
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            
            try {
                const response = await fetch('auth_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(loginAlert, 'success', result.message);
                    setTimeout(() => {
                        // Close modal properly
                        const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                        if (loginModal) {
                            loginModal.hide();
                        }
                        window.cleanupModalBackdrop();
                        
                        // Reload page
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(loginAlert, 'danger', result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Login error:', error);
                showAlert(loginAlert, 'danger', 'Terjadi kesalahan. Silakan coba lagi.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // ========================================
    // REGISTER FORM HANDLER
    // ========================================
    const registerForm = document.getElementById('registerForm');
    const registerAlert = document.getElementById('registerAlert');
    
    if (registerForm) {
        registerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(registerForm);
            const submitBtn = registerForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Validate password match
            const password = formData.get('password');
            const confirmPasswordValue = formData.get('confirm_password');
            
            if (password !== confirmPasswordValue) {
                showAlert(registerAlert, 'danger', 'Password dan konfirmasi password tidak cocok!');
                return;
            }
            
            // Check password strength
            const result = checkPasswordStrength(password);
            if (result.score < 5) {
                showAlert(registerAlert, 'warning', 'Password terlalu lemah. Harap penuhi semua persyaratan password.');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            
            try {
                const response = await fetch('auth_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const resultData = await response.json();
                
                if (resultData.success) {
                    showAlert(registerAlert, 'success', resultData.message);
                    registerForm.reset();
                    
                    // Reset password strength UI
                    if (strengthMeter) {
                        strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
                        if (strengthText) strengthText.innerHTML = '';
                        if (passwordMatchMessage) passwordMatchMessage.innerHTML = '';
                    }
                    
                    setTimeout(() => {
                        const registerModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                        if (registerModal) {
                            registerModal.hide();
                        }
                        window.cleanupModalBackdrop();
                        
                        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                        loginModal.show();
                    }, 2000);
                } else {
                    showAlert(registerAlert, 'danger', resultData.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Register error:', error);
                showAlert(registerAlert, 'danger', 'Terjadi kesalahan. Silakan coba lagi.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // Clear alerts when modals are closed
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        loginModal.addEventListener('hidden.bs.modal', function() {
            if (loginAlert) loginAlert.classList.add('d-none');
            if (loginForm) loginForm.reset();
            window.cleanupModalBackdrop();
        });
    }
    
    const registerModal = document.getElementById('registerModal');
    if (registerModal) {
        registerModal.addEventListener('hidden.bs.modal', function() {
            if (registerAlert) registerAlert.classList.add('d-none');
            if (registerForm) registerForm.reset();
            
            // Reset password strength UI
            if (strengthMeter) {
                strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
                if (strengthText) strengthText.innerHTML = '';
                if (passwordMatchMessage) passwordMatchMessage.innerHTML = '';
            }
            
            window.cleanupModalBackdrop();
        });
    }
    
    // ========================================
    // CHECK FOR RESET SUCCESS MESSAGE
    // ========================================
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('reset') === 'success') {
        // Show success message
        const successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
        successAlert.style.zIndex = '9999';
        successAlert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            Password berhasil direset! Silakan login dengan password baru Anda.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(successAlert);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            successAlert.remove();
        }, 5000);
        
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
        
        // Auto open login modal
        setTimeout(() => {
            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        }, 1000);
    }
    
    console.log('✅ Footer scripts initialized successfully');
});

// Password toggle function
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
</script>
    
<!-- Comments JS - Enhanced with Pagination -->
<script src="js/comments.js?v=<?php echo time(); ?>"></script>

<!-- Reactions JS -->
<script src="js/reactions.js?v=<?php echo time(); ?>"></script>

<!-- Performance and Error Prevention Scripts -->
<script>
// Global error handler
window.addEventListener('error', function(event) {
    if (event.message.includes('Could not establish connection')) {
        console.warn('Connection error caught and handled:', event.message);
        event.preventDefault();
        return false;
    }
    
    if (event.message.includes('Extension context invalidated')) {
        console.warn('Browser extension error caught and handled');
        event.preventDefault();
        return false;
    }
});

// Prevent unhandled promise rejections
window.addEventListener('unhandledrejection', function(event) {
    if (event.reason && event.reason.message && 
        (event.reason.message.includes('Could not establish connection') ||
         event.reason.message.includes('Extension context invalidated'))) {
        console.warn('Promise rejection caught and handled:', event.reason.message);
        event.preventDefault();
    }
});

console.log('✅ Error prevention active');
</script>

</body>
</html>