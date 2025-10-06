<?php
$baseUrl = 'https://inievan.my.id/project';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Get site info from settings
$siteInfo = getSiteInfo();
?>
<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <!-- Brand and Description -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="mb-3">
                    <a class="brand-logo" href="<?php echo $baseUrl; ?>">
                        <img src="<?php echo $baseUrl; ?>/img/NewLogo.webp"
                            alt="<?php echo htmlspecialchars($siteInfo['name']); ?> Logo"
                            loading="lazy">
                    </a>
                </div>
                <p><?php echo htmlspecialchars($siteInfo['description']); ?></p>
                
                <!-- Social Media Icons -->
                <div class="social-icons justify-content-start">
                    <a href="https://github.com/ESTAS-crypto" target="_blank" rel="noopener noreferrer" title="GitHub">
                        <i class="bi bi-github"></i>
                    </a>
                    <a href="https://www.instagram.com/evanatharasya.x/" target="_blank" rel="noopener noreferrer" title="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="https://x.com/EAtharasya" target="_blank" rel="noopener noreferrer" title="Twitter/X">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                </div>
            </div>

            <!-- Categories -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <h5><i class="fas fa-list me-2"></i>Kategori</h5>
                <ul class="list-unstyled">
                    <?php
                    if ($koneksi) {
                        $categories_query = "SELECT name FROM categories ORDER BY name LIMIT 5";
                        $categories_result = mysqli_query($koneksi, $categories_query);
                        
                        if ($categories_result && mysqli_num_rows($categories_result) > 0) {
                            while ($category = mysqli_fetch_assoc($categories_result)) {
                                echo '<li><a href="#">' . htmlspecialchars($category['name']) . '</a></li>';
                            }
                        } else {
                            echo '<li><a href="#">Politik</a></li>';
                            echo '<li><a href="#">Ekonomi</a></li>';
                            echo '<li><a href="#">Olahraga</a></li>';
                            echo '<li><a href="#">Teknologi</a></li>';
                            echo '<li><a href="#">Hiburan</a></li>';
                        }
                    }
                    ?>
                </ul>
            </div>

            <!-- Pages -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-4">
                <h5><i class="fas fa-sitemap me-2"></i>Halaman</h5>
                <ul class="list-unstyled">
                    <li><a href="<?php echo $baseUrl; ?>"><i class="fas fa-home me-2"></i>Beranda</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/berita.php"><i class="fas fa-newspaper me-2"></i>Berita</a></li>
                    <li><a href="#"><i class="fas fa-envelope me-2"></i>Kontak</a></li>
                </ul>
            </div>

            <!-- Contact Information -->
            <div class="col-lg-4 col-md-6 mb-4">
                <h5><i class="fas fa-address-book me-2"></i>Kontak</h5>
                <div class="contact-info">
                    <p><i class="fas fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($siteInfo['admin_email']); ?></p>
                    <p><i class="fas fa-phone me-2 text-success"></i>+62 895-3858-90629</p>
                    <p><i class="fas fa-map-marker-alt me-2 text-danger"></i>Surabaya, Indonesia</p>
                    <p><i class="fas fa-school me-2 text-info"></i>SMKN 2 SURABAYA</p>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p>&copy; 2025 <?php echo htmlspecialchars($siteInfo['name']); ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p>Created by <strong>Evan Atharasya</strong> - SMKN 2 SURABAYA</p>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Modal Authentication Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
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
        // Remove all strength classes
        strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
        
        if (score === 0) {
            strengthText.innerHTML = '';
            return;
        }
        
        if (score <= 2) {
            strengthMeter.classList.add('strength-weak');
            strengthText.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Password Lemah</span>';
            passwordStrength = 1;
        } else if (score <= 4) {
            strengthMeter.classList.add('strength-medium');
            strengthText.innerHTML = '<i class="fas fa-shield-alt"></i><span>Password Sedang</span>';
            passwordStrength = 2;
        } else {
            strengthMeter.classList.add('strength-strong');
            strengthText.innerHTML = '<i class="fas fa-shield-alt"></i><span>Password Kuat</span>';
            passwordStrength = 3;
        }
    }
    
    function checkPasswordMatch() {
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
                showAlert(registerAlert, 'warning', 'Password terlalu lemah. Harap penuhi semua persyaratan password untuk keamanan akun Anda.');
                return;
            }
            
            // Validate username
            const username = formData.get('username');
            if (username.length < 3) {
                showAlert(registerAlert, 'danger', 'Username harus minimal 3 karakter');
                return;
            }
            
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                showAlert(registerAlert, 'danger', 'Username hanya boleh mengandung huruf, angka, dan underscore');
                return;
            }
            
            // Validate full name
            const fullName = formData.get('full_name');
            if (fullName.length < 3) {
                showAlert(registerAlert, 'danger', 'Nama lengkap harus minimal 3 karakter');
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
                    strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
                    strengthText.innerHTML = '';
                    passwordMatchMessage.innerHTML = '';
                    
                    // Reset all requirements
                    [reqLength, reqUppercase, reqLowercase, reqNumber, reqSpecial].forEach(req => {
                        updateRequirement(req, false);
                    });
                    
                    setTimeout(() => {
                        const registerModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                        registerModal.hide();
                        
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
    
    // ========================================
    // HELPER FUNCTIONS
    // ========================================
    function showAlert(alertElement, type, message) {
        alertElement.className = `alert alert-${type}`;
        const iconClass = type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle';
        alertElement.innerHTML = `<i class="fas fa-${iconClass} me-2"></i>${message}`;
        alertElement.classList.remove('d-none');
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            alertElement.classList.add('d-none');
        }, 5000);
    }
    
    // Clear alerts when modals are closed
    document.getElementById('loginModal')?.addEventListener('hidden.bs.modal', function() {
        loginAlert?.classList.add('d-none');
        loginForm?.reset();
    });
    
    document.getElementById('registerModal')?.addEventListener('hidden.bs.modal', function() {
        registerAlert?.classList.add('d-none');
        registerForm?.reset();
        
        // Reset password strength UI
        if (strengthMeter) {
            strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
            strengthText.innerHTML = '';
            passwordMatchMessage.innerHTML = '';
        }
        
        // Reset all requirements
        if (reqLength) {
            [reqLength, reqUppercase, reqLowercase, reqNumber, reqSpecial].forEach(req => {
                updateRequirement(req, false);
            });
        }
    });
    
    // Character count for comment textarea
    const textarea = document.querySelector('textarea[name="comment_content"]');
    const charCount = document.getElementById('char-count');
    
    if (textarea && charCount) {
        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            if (this.value.length > 900) {
                charCount.style.color = 'red';
            } else if (this.value.length > 800) {
                charCount.style.color = 'orange';
            } else {
                charCount.style.color = '';
            }
        });
    }
    
    // ========================================
    // HERO CAROUSEL ENHANCEMENTS - PERBAIKAN
    // ========================================
    const carouselItems = document.querySelectorAll('#heroCarousel .carousel-item');
    
    if (carouselItems.length > 0) {
        console.log('Hero carousel enhancement initialized with', carouselItems.length, 'items');
        
        carouselItems.forEach(item => {
            // Prevent carousel controls from triggering item click
            const controls = document.querySelectorAll('#heroCarousel .carousel-control-prev, #heroCarousel .carousel-control-next');
            controls.forEach(control => {
                control.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
            
            // Add visual feedback on hover
            item.addEventListener('mouseenter', function() {
                this.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.opacity = '1';
            });
            
            // Add click feedback
            item.addEventListener('click', function(e) {
                // Ignore if clicking on control buttons
                if (e.target.closest('.carousel-control-prev') || e.target.closest('.carousel-control-next')) {
                    return;
                }
                
                // Visual feedback
                this.style.opacity = '0.9';
                setTimeout(() => {
                    this.style.opacity = '1';
                }, 150);
            });
        });
        
        // Pause carousel on hover
        const carousel = document.getElementById('heroCarousel');
        if (carousel) {
            carousel.addEventListener('mouseenter', function() {
                const bsCarousel = bootstrap.Carousel.getInstance(carousel);
                if (bsCarousel) {
                    bsCarousel.pause();
                }
            });
            
            carousel.addEventListener('mouseleave', function() {
                const bsCarousel = bootstrap.Carousel.getInstance(carousel);
                if (bsCarousel) {
                    bsCarousel.cycle();
                }
            });
        }
    }
});
</script>

<!-- Comments JS -->
<script src="js/comments.js"></script>

<!-- Reactions JS -->
<script src="js/reactions.js"></script>

<!-- Ban Notification for Logged In Users -->
<?php if (isset($is_logged_in) && $is_logged_in === true): ?>
<script>
(function() {
    'use strict';

    if (window.banNotificationSystemLoaded) {
        console.log('Ban notification system already loaded, skipping...');
        return;
    }

    window.banNotificationSystemLoaded = true;
    console.log('Initializing ban notification for logged in user');

    try {
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo intval($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0); ?>');
        document.body.setAttribute('data-username', '<?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES); ?>');
        document.body.setAttribute('data-page', 'footer');
    } catch (e) {
        console.warn('Could not set body attributes:', e);
    }

    function loadBanNotificationScript() {
        return new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[src*="notif-ban.js"]');
            if (existingScript) {
                console.log('Ban notification script already exists');
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = '<?php echo $baseUrl; ?>/js/notif-ban.js?v=' + Date.now();
            script.async = true;
            script.defer = true;
            
            script.onload = function() {
                console.log('Ban notification script loaded successfully');
                resolve();
            };
            
            script.onerror = function(error) {
                console.log('Ban notification script not available');
                resolve();
            };
            
            document.head.appendChild(script);
        });
    }

    async function initializeBanNotification() {
        try {
            await loadBanNotificationScript();
            
            let attempts = 0;
            const maxAttempts = 10;
            
            const checkManager = setInterval(() => {
                attempts++;
                
                if (window.banNotificationManager && window.banNotificationManager.isInitialized) {
                    clearInterval(checkManager);
                    
                    setTimeout(() => {
                        console.log('Starting initial ban check');
                        try {
                            window.banNotificationManager.forceCheck();
                        } catch (e) {
                            console.log('Ban check failed:', e);
                        }
                    }, 2000);
                    
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkManager);
                    console.log('Ban notification manager not available after', maxAttempts, 'attempts');
                }
            }, 500);
            
        } catch (error) {
            console.log('Ban notification initialization failed:', error);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBanNotification);
    } else {
        initializeBanNotification();
    }

})();
</script>
<?php endif; ?>

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

console.log('Footer scripts initialized successfully');
console.log('Hero carousel enhancements loaded');
</script>

</body>
</html>