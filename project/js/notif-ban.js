class BanNotificationManager {
    constructor() {
        this.checkInterval = 15000; // Check every 15 seconds
        this.intervalId = null;
        this.currentNotification = null;
        this.lastStatus = null;
        this.isInitialized = false;
        this.debugMode = true;
        this.apiEndpoint = 'API/ban.php';
        this.retryCount = 0;
        this.maxRetries = 3;
        this.consecutiveErrors = 0;
        this.maxConsecutiveErrors = 5;
        this.soundEnabled = false; // DISABLED BY DEFAULT
        this.isChecking = false;
        this.initAttempted = false;
        this.lastCheckTime = 0;
        this.minCheckInterval = 5000; // Minimum 5 seconds between checks
        this.hasShownInitialStatus = false;

        // NEW: Warning tracking untuk prevent spam
        this.shownWarnings = new Set(); // Track warning IDs yang sudah ditampilkan
        this.lastWarningShown = 0; // Timestamp warning terakhir
        this.warningCooldown = 300000; // 5 menit cooldown antara warning
        this.sessionWarnings = new Map(); // Track per session

        console.log('🛡️ [BAN-NOTIF] Initializing Ban Notification Manager with Smart Warning System...');
        this.init();
    }

    log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logMessage = `🛡️ [BAN-NOTIF ${timestamp}] ${message}`;

        if (this.debugMode) {
            switch (type) {
                case 'error':
                    console.error(logMessage);
                    break;
                case 'warn':
                    console.warn(logMessage);
                    break;
                default:
                    console.log(logMessage);
            }
        }

        // Dispatch custom event for external logging
        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('banNotificationLog', {
                detail: { message: logMessage, type }
            }));
        }
    }

    init() {
        // STRICT: Prevent multiple initialization
        if (this.initAttempted) {
            this.log('⚠️ Initialization already attempted, skipping', 'warn');
            return;
        }

        this.initAttempted = true;
        this.log('Starting initialization...');

        // Check if user is logged in
        if (!this.isUserLoggedIn()) {
            this.log('User not logged in, skipping ban notification manager');
            return;
        }

        this.log('✅ User detected as logged in, starting ban notification system');
        this.isInitialized = true;

        // Load CSS styles
        this.loadStyles();

        // Initialize warning tracking dari localStorage (optional fallback)
        this.loadWarningTrackingState();

        // DELAYED initial check to prevent conflicts
        setTimeout(() => {
            this.log('🔍 Performing initial ban status check');
            this.checkBanStatus();
        }, 3000);

        // Start periodic checking after initial check
        setTimeout(() => {
            this.startPeriodicCheck();
        }, 5000);

        // Add event listeners with debouncing
        this.addEventListeners();

        this.log('🚀 Ban notification system with smart warning tracking fully initialized');
    }

    isUserLoggedIn() {
        const indicators = [
            () => document.body.classList.contains('logged-in'),
            () => document.querySelector('.user-dropdown'),
            () => document.querySelector('[data-user-id]'),
            () => window.location.pathname.includes('admin/'),
            () => document.cookie.includes('PHPSESSID'),
            () => localStorage.getItem('user_logged_in') === 'true',
            () => document.querySelector('.logout-btn'),
            () => document.querySelector('.profile-menu'),
            () => document.querySelector('#userDropdown'),
            () => document.querySelector('.user-menu'),
            () => document.querySelector('[data-logged-in="true"]'),
            () => document.body.getAttribute('data-user-id') && document.body.getAttribute('data-user-id') !== '0'
        ];

        const isLoggedIn = indicators.some(check => {
            try {
                return check();
            } catch (e) {
                return false;
            }
        });

        this.log(`User login status: ${isLoggedIn ? 'LOGGED IN' : 'NOT LOGGED IN'}`);

        if (isLoggedIn) {
            const userId = document.body.getAttribute('data-user-id');
            const username = document.body.getAttribute('data-username');
            const page = document.body.getAttribute('data-page');
            this.log(`User details - ID: ${userId}, Username: ${username}, Page: ${page}`);
        }

        return isLoggedIn;
    }

    // NEW: Load warning tracking state dari storage
    loadWarningTrackingState() {
        try {
            const userId = document.body.getAttribute('data-user-id');
            if (!userId) return;

            const storageKey = `banNotif_warnings_${userId}`;
            const stored = sessionStorage.getItem(storageKey);

            if (stored) {
                const data = JSON.parse(stored);
                this.shownWarnings = new Set(data.shownWarnings || []);
                this.lastWarningShown = data.lastWarningShown || 0;
                this.log(`📚 Loaded warning tracking state: ${this.shownWarnings.size} warnings shown`);
            }
        } catch (e) {
            this.log(`⚠️ Failed to load warning tracking state: ${e.message}`, 'warn');
        }
    }

    // NEW: Save warning tracking state ke storage
    saveWarningTrackingState() {
        try {
            const userId = document.body.getAttribute('data-user-id');
            if (!userId) return;

            const storageKey = `banNotif_warnings_${userId}`;
            const data = {
                shownWarnings: Array.from(this.shownWarnings),
                lastWarningShown: this.lastWarningShown,
                savedAt: Date.now()
            };

            sessionStorage.setItem(storageKey, JSON.stringify(data));
            this.log(`💾 Saved warning tracking state`);
        } catch (e) {
            this.log(`⚠️ Failed to save warning tracking state: ${e.message}`, 'warn');
        }
    }

    addEventListeners() {
        // HEAVY DEBOUNCED event listeners to prevent spam
        let focusTimeout;
        window.addEventListener('focus', () => {
            if (this.isInitialized) {
                clearTimeout(focusTimeout);
                focusTimeout = setTimeout(() => {
                    if (this.canPerformCheck()) {
                        this.log('🔍 Page focused, checking ban status');
                        this.checkBanStatus();
                    }
                }, 2000);
            }
        });

        let visibilityTimeout;
        document.addEventListener('visibilitychange', () => {
            if (this.isInitialized && !document.hidden) {
                clearTimeout(visibilityTimeout);
                visibilityTimeout = setTimeout(() => {
                    if (this.canPerformCheck()) {
                        this.log('👁️ Page became visible, checking ban status');
                        this.checkBanStatus();
                    }
                }, 2000);
            }
        });

        // Network status changes
        window.addEventListener('online', () => {
            if (this.isInitialized) {
                this.log('🌐 Network reconnected, resuming checks');
                this.consecutiveErrors = 0;
                setTimeout(() => {
                    if (this.canPerformCheck()) {
                        this.startPeriodicCheck();
                        this.checkBanStatus();
                    }
                }, 3000);
            }
        });

        window.addEventListener('offline', () => {
            if (this.isInitialized) {
                this.log('📴 Network disconnected, pausing checks');
                this.stopPeriodicCheck();
            }
        });

        // Cleanup on unload
        window.addEventListener('beforeunload', () => {
            this.saveWarningTrackingState();
            this.destroy();
        });
    }

    canPerformCheck() {
        const now = Date.now();
        if (now - this.lastCheckTime < this.minCheckInterval) {
            this.log(`⏱️ Check skipped - too frequent (${now - this.lastCheckTime}ms ago)`);
            return false;
        }
        return true;
    }

    startPeriodicCheck() {
        // Clear existing interval
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }

        this.intervalId = setInterval(() => {
            if (this.canPerformCheck()) {
                this.checkBanStatus();
            }
        }, this.checkInterval);

        this.log(`⏱️ Periodic ban check started (every ${this.checkInterval/1000} seconds)`);
    }

    stopPeriodicCheck() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
            this.log('⏹️ Periodic ban check stopped');
        }
    }

    async checkBanStatus() {
        // STRICT: Prevent multiple simultaneous checks
        if (this.isChecking) {
            this.log('⚠️ Check already in progress, skipping');
            return;
        }

        if (!this.isInitialized) {
            this.log('❌ System not initialized, skipping check');
            return;
        }

        if (!navigator.onLine) {
            this.log('📴 Offline, skipping ban check');
            return;
        }

        this.isChecking = true;
        this.lastCheckTime = Date.now();
        this.log('🔍 Starting ban status check...');

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 8000);

            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin',
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (response.ok) {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error(`Invalid content type: ${contentType}`);
                }

                const data = await response.json();
                this.log(`✅ Response: ${JSON.stringify(data)}`);

                this.handleBanStatus(data);
                this.consecutiveErrors = 0;
                this.retryCount = 0;
            } else {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText.substring(0, 50)}`);
            }

        } catch (error) {
            this.consecutiveErrors++;
            this.log(`❌ Check failed (${this.consecutiveErrors}/${this.maxConsecutiveErrors}): ${error.message}`, 'error');

            if (error.name === 'AbortError') {
                this.log('⏱️ Request timed out', 'warn');
            }

            // Exponential backoff on errors
            if (this.consecutiveErrors >= this.maxConsecutiveErrors) {
                this.log('🚨 Too many errors, increasing check interval', 'warn');
                this.checkInterval = Math.min(this.checkInterval * 1.5, 60000);
                this.startPeriodicCheck();
            }

        } finally {
            this.isChecking = false;
        }
    }

    handleBanStatus(data) {
        const { status } = data;

        this.log(`📊 Processing status: ${status} (previous: ${this.lastStatus})`);

        // CRITICAL: Only process if status actually changed OR first check
        if (this.lastStatus === status && this.hasShownInitialStatus) {
            this.log('📊 Status unchanged, skipping processing');
            return;
        }

        // Mark that we've shown initial status
        if (!this.hasShownInitialStatus) {
            this.hasShownInitialStatus = true;
        }

        switch (status) {
            case 'banned':
                this.log('🚨 BAN DETECTED - User is banned!', 'error');
                this.showBanNotification(data);
                this.playSound('ban');
                this.stopPeriodicCheck();
                break;

            case 'warning':
                this.log('⚠️ WARNING DETECTED - User has warning', 'warn');

                // NEW: Smart warning handling dengan anti-spam
                if (this.shouldShowWarning(data)) {
                    this.showWarningNotification(data);
                    this.playSound('warning');

                    // Track warning sebagai sudah ditampilkan
                    this.markWarningAsShown(data);
                } else {
                    this.log('⚠️ Warning skipped due to smart filtering');
                }
                break;

            case 'active':
                this.log('✅ User status: ACTIVE');
                this.hideNotification();
                // ONLY play success sound if coming from banned/warning state
                if (this.lastStatus === 'banned' || this.lastStatus === 'warning') {
                    this.playSound('success');
                }
                if (!this.intervalId) {
                    this.startPeriodicCheck();
                }
                break;

            case 'not_logged_in':
                this.log('👤 User not logged in, stopping checks');
                this.stopPeriodicCheck();
                this.isInitialized = false;
                break;

            case 'user_not_found':
                this.log('❌ User not found in database');
                break;

            case 'invalid_user_id':
                this.log('⚠️ Invalid user ID', 'warn');
                if (data.debug) {
                    this.log(`Debug: ${JSON.stringify(data.debug)}`, 'warn');
                }
                break;

            case 'error':
                this.log(`🚨 API Error: ${data.message}`, 'error');
                break;

            default:
                this.log(`ℹ️ Unknown status: ${status}`);
        }

        // Update last status AFTER processing
        this.lastStatus = status;
    }

    // NEW: Smart warning filtering logic
    shouldShowWarning(data) {
        const now = Date.now();
        const warningId = data.warning_id || `${data.warning_level}_${data.given_at}`;

        // Check 1: Sudah pernah ditampilkan dalam session ini?
        if (this.shownWarnings.has(warningId)) {
            this.log(`⚠️ Warning ${warningId} already shown in this session`);
            return false;
        }

        // Check 2: Cooldown period (5 menit antara warning apapun)
        if (now - this.lastWarningShown < this.warningCooldown) {
            const remaining = Math.ceil((this.warningCooldown - (now - this.lastWarningShown)) / 1000);
            this.log(`⚠️ Warning cooldown active (${remaining}s remaining)`);
            return false;
        }

        // Check 3: Frequency limits berdasarkan level
        const warningLevel = data.warning_level;
        if (warningLevel === 'low') {
            // Low warning: maksimal 1 per 30 menit
            const lowWarningCooldown = 30 * 60 * 1000; // 30 minutes
            const lastLowWarning = this.getLastWarningTime('low');
            if (lastLowWarning && (now - lastLowWarning) < lowWarningCooldown) {
                this.log('⚠️ Low warning frequency limit exceeded');
                return false;
            }
        } else if (warningLevel === 'medium') {
            // Medium warning: maksimal 1 per 15 menit
            const mediumWarningCooldown = 15 * 60 * 1000; // 15 minutes
            const lastMediumWarning = this.getLastWarningTime('medium');
            if (lastMediumWarning && (now - lastMediumWarning) < mediumWarningCooldown) {
                this.log('⚠️ Medium warning frequency limit exceeded');
                return false;
            }
        }
        // High warnings: selalu tampilkan (dengan cooldown umum 5 menit)

        // Check 4: Maksimal 3 warning per jam
        const hourlyLimit = this.getWarningCountLastHour();
        if (hourlyLimit >= 3 && warningLevel !== 'high') {
            this.log('⚠️ Hourly warning limit exceeded (3/hour)');
            return false;
        }

        this.log(`✅ Warning ${warningId} passed all filters, will be shown`);
        return true;
    }

    // NEW: Mark warning sebagai sudah ditampilkan
    markWarningAsShown(data) {
        const now = Date.now();
        const warningId = data.warning_id || `${data.warning_level}_${data.given_at}`;

        // Add ke shown warnings set
        this.shownWarnings.add(warningId);

        // Update last warning time
        this.lastWarningShown = now;

        // Track by level untuk frequency limiting
        const levelKey = `lastWarning_${data.warning_level}`;
        this.sessionWarnings.set(levelKey, now);

        // Add ke hourly counter
        const hourlyKey = `hourly_${Math.floor(now / (60 * 60 * 1000))}`;
        const currentHourly = this.sessionWarnings.get(hourlyKey) || 0;
        this.sessionWarnings.set(hourlyKey, currentHourly + 1);

        // Save state
        this.saveWarningTrackingState();

        this.log(`📝 Warning ${warningId} marked as shown (level: ${data.warning_level})`);
    }

    // NEW: Get last warning time by level
    getLastWarningTime(level) {
        return this.sessionWarnings.get(`lastWarning_${level}`) || 0;
    }

    // NEW: Get warning count dalam 1 jam terakhir
    getWarningCountLastHour() {
        const now = Date.now();
        const currentHour = Math.floor(now / (60 * 60 * 1000));
        return this.sessionWarnings.get(`hourly_${currentHour}`) || 0;
    }

    // IMPROVED sound system with better control
    playSound(type) {
        // STRICT: Only play sounds if explicitly enabled AND status actually changed
        if (!this.soundEnabled) {
            this.log(`🔇 Sound disabled for ${type}`);
            return;
        }

        // Don't play sounds on initial page load
        if (!this.hasShownInitialStatus && type !== 'ban') {
            this.log(`🔇 Skipping initial ${type} sound`);
            return;
        }

        try {
            const audioContext = new(window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            let frequency, duration, gainValue;

            switch (type) {
                case 'ban':
                    frequency = 440;
                    duration = 0.6;
                    gainValue = 0.3;
                    oscillator.type = 'sawtooth';
                    break;
                case 'warning':
                    frequency = 600;
                    duration = 0.4;
                    gainValue = 0.2;
                    oscillator.type = 'triangle';
                    break;
                case 'success':
                    frequency = 800;
                    duration = 0.3;
                    gainValue = 0.15;
                    oscillator.type = 'sine';
                    break;
                case 'error':
                    frequency = 200;
                    duration = 0.3;
                    gainValue = 0.2;
                    oscillator.type = 'square';
                    break;
                default:
                    return; // Don't play unknown sounds
            }

            oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
            gainNode.gain.setValueAtTime(gainValue, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + duration);

            this.log(`🔊 Played ${type} sound (${frequency}Hz, ${duration}s)`);
        } catch (error) {
            this.log(`🔇 Sound failed: ${error.message}`, 'warn');
        }
    }

    setSoundEnabled(enabled) {
        this.soundEnabled = enabled;
        this.log(`🔊 Sound alerts ${enabled ? 'enabled' : 'disabled'}`);
    }

    showBanNotification(data) {
            this.hideNotification();
            this.log('🚫 Displaying ban notification');

            const overlay = document.createElement('div');
            overlay.className = 'ban-notification-overlay ban-overlay';
            overlay.innerHTML = `
            <div class="ban-notification-box ban-notification">
                <div class="ban-notification-icon ban">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="ban-notification-title">
                    🚫 Akun Anda Telah Di-ban
                </div>
                <div class="ban-notification-message">
                    <div class="ban-info-grid">
                        <div class="ban-info-item">
                            <strong>Alasan:</strong>
                            <span>${this.escapeHtml(data.ban_reason || 'Tidak ada alasan yang diberikan')}</span>
                        </div>
                        <div class="ban-info-item">
                            <strong>Ban sampai:</strong>
                            <span>${data.ban_until || 'Tidak diketahui'}</span>
                        </div>
                        <div class="ban-info-item">
                            <strong>Sisa waktu:</strong>
                            <span>${data.time_remaining || 'Tidak diketahui'}</span>
                        </div>
                        ${data.banned_by ? `
                        <div class="ban-info-item">
                            <strong>Di-ban oleh:</strong>
                            <span>${this.escapeHtml(data.banned_by)}</span>
                        </div>
                        ` : ''}
                        ${data.banned_at ? `
                        <div class="ban-info-item">
                            <strong>Tanggal ban:</strong>
                            <span>${data.banned_at}</span>
                        </div>
                        ` : ''}
                    </div>
                    <div class="ban-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Anda tidak dapat mengakses website ini sampai masa ban berakhir. Halaman akan dialihkan ke login dalam 30 detik.
                    </div>
                </div>
                <div class="ban-notification-actions">
                    <button class="btn-understand btn-ban" onclick="banNotificationManager.redirectToLogin()">
                        <i class="fas fa-sign-out-alt"></i>
                        Saya Mengerti
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        this.currentNotification = overlay;

        // Prevent interaction
        document.body.style.overflow = 'hidden';
        const mainContent = document.querySelector('main, .main-content, .container, #main') || document.body.children[0];
        if (mainContent && mainContent !== document.body) {
            mainContent.style.filter = 'blur(3px)';
            mainContent.style.pointerEvents = 'none';
        }

        // Auto redirect
        setTimeout(() => {
            this.redirectToLogin();
        }, 30000);

        this.log('🚫 Ban notification displayed');
    }

    showWarningNotification(data) {
        if (this.currentNotification) {
            this.log('⚠️ Notification already shown, skipping warning');
            return;
        }

        this.log('⚠️ Displaying warning notification');

        const overlay = document.createElement('div');
        overlay.className = 'ban-notification-overlay warning-overlay';
        overlay.innerHTML = `
            <div class="ban-notification-box warning-notification">
                <div class="ban-notification-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ban-notification-title">
                    ⚠️ Peringatan untuk Akun Anda
                </div>
                <div class="ban-notification-message">
                    <div class="warning-info-grid">
                        <div class="warning-info-item">
                            <strong>Level:</strong>
                            <span>${this.getWarningLevelText(data.warning_level)}</span>
                        </div>
                        <div class="warning-info-item">
                            <strong>Alasan:</strong>
                            <span>${this.escapeHtml(data.warning_reason || 'Tidak ada alasan')}</span>
                        </div>
                        <div class="warning-info-item">
                            <strong>Total warning:</strong>
                            <span>${data.warning_count || 0}</span>
                        </div>
                        <div class="warning-info-item">
                            <strong>Diberikan pada:</strong>
                            <span>${data.given_at || 'Tidak diketahui'}</span>
                        </div>
                        ${data.given_by ? `
                        <div class="warning-info-item">
                            <strong>Diberikan oleh:</strong>
                            <span>${this.escapeHtml(data.given_by)}</span>
                        </div>
                        ` : ''}
                    </div>
                    <div class="warning-info">
                        <i class="fas fa-info-circle"></i>
                        Mohon patuhi aturan website. Warning berlebihan dapat mengakibatkan ban permanen.
                        <br><small class="text-muted mt-2">
                            <i class="fas fa-clock"></i>
                            Notifikasi ini tidak akan muncul lagi untuk warning yang sama.
                        </small>
                    </div>
                </div>
                <div class="ban-notification-actions">
                    <button class="btn-understand btn-warning" onclick="banNotificationManager.hideNotification()">
                        <i class="fas fa-check"></i>
                        Saya Mengerti
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        this.currentNotification = overlay;

        // Auto hide setelah 20 detik untuk warning
        setTimeout(() => {
            this.hideNotification();
        }, 20000);

        this.log('⚠️ Warning notification displayed');
    }

    hideNotification() {
        if (this.currentNotification) {
            this.log('✅ Hiding notification');

            this.currentNotification.style.opacity = '0';
            this.currentNotification.style.transition = 'opacity 0.3s ease';

            setTimeout(() => {
                if (this.currentNotification && this.currentNotification.parentNode) {
                    this.currentNotification.parentNode.removeChild(this.currentNotification);
                }
                this.currentNotification = null;

                // Restore page
                document.body.style.overflow = '';
                const mainContent = document.querySelector('main, .main-content, .container, #main') || document.body.children[0];
                if (mainContent && mainContent !== document.body) {
                    mainContent.style.filter = '';
                    mainContent.style.pointerEvents = '';
                }
            }, 300);
        }
    }

    redirectToLogin() {
        this.log('🔄 Redirecting to login due to ban');

        try {
            sessionStorage.clear();
            localStorage.removeItem('user_logged_in');
        } catch (e) {
            this.log('Warning: Could not clear storage', 'warn');
        }

        if (this.currentNotification) {
            const messageDiv = this.currentNotification.querySelector('.ban-notification-message');
            if (messageDiv) {
                messageDiv.innerHTML = `
                    <div class="text-center py-4">
                        <div class="loading-spinner"></div>
                        <p class="mt-3">Mengarahkan ke halaman login...</p>
                    </div>
                `;
            }
        }

        setTimeout(() => {
            const redirectUrl = 'logout.php?reason=banned';
            this.log(`Redirecting to: ${redirectUrl}`);
            window.location.href = redirectUrl;
        }, 2000);
    }

    getWarningLevelText(level) {
        const levels = {
            'low': '<span class="badge badge-warning-low">Ringan</span>',
            'medium': '<span class="badge badge-warning-medium">Sedang</span>',
            'high': '<span class="badge badge-warning-high">Berat</span>',
            'severe': '<span class="badge badge-warning-severe">Sangat Berat</span>'
        };
        return levels[level] || '<span class="badge badge-unknown">Tidak diketahui</span>';
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // NEW: Reset warning tracking (untuk testing atau admin)
    resetWarningTracking() {
        this.shownWarnings.clear();
        this.sessionWarnings.clear();
        this.lastWarningShown = 0;
        this.saveWarningTrackingState();
        this.log('🔄 Warning tracking reset');
    }

    // NEW: Get warning statistics
    getWarningStats() {
        return {
            shownWarningsCount: this.shownWarnings.size,
            lastWarningShown: this.lastWarningShown,
            sessionWarnings: Object.fromEntries(this.sessionWarnings),
            hourlyCount: this.getWarningCountLastHour()
        };
    }

    // Public methods
    forceCheck() {
        this.log('🔍 Force checking ban status (external call)');
        this.consecutiveErrors = 0;
        this.retryCount = 0;
        this.isChecking = false;
        this.lastCheckTime = 0; // Allow immediate check
        this.checkBanStatus();
    }

    setDebugMode(enabled) {
        this.debugMode = enabled;
        this.log(`Debug mode ${enabled ? 'enabled' : 'disabled'}`);
    }

    setCheckInterval(interval) {
        if (interval >= 5000 && interval <= 300000) {
            this.checkInterval = interval;
            this.log(`Check interval updated to ${interval}ms`);
            if (this.intervalId) {
                this.stopPeriodicCheck();
                this.startPeriodicCheck();
            }
        }
    }

    getStatus() {
        return {
            isInitialized: this.isInitialized,
            lastStatus: this.lastStatus,
            hasActiveNotification: !!this.currentNotification,
            isPeriodicCheckRunning: !!this.intervalId,
            checkInterval: this.checkInterval,
            consecutiveErrors: this.consecutiveErrors,
            currentEndpoint: this.apiEndpoint,
            retryCount: this.retryCount,
            soundEnabled: this.soundEnabled,
            isChecking: this.isChecking,
            hasShownInitialStatus: this.hasShownInitialStatus,
            // NEW: Warning tracking status
            warningStats: this.getWarningStats(),
            warningCooldown: this.warningCooldown,
            shownWarningsCount: this.shownWarnings.size
        };
    }

    destroy() {
        this.log('💥 Destroying Ban Notification Manager');
        this.stopPeriodicCheck();
        this.hideNotification();
        this.saveWarningTrackingState(); // Save state before destroy
        this.isInitialized = false;
        this.isChecking = false;
        this.initAttempted = false; // Allow re-initialization if needed
        this.log('🗑️ Ban notification manager destroyed');
    }

    loadStyles() {
        if (document.getElementById('ban-notification-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'ban-notification-styles';
        style.textContent = `
            .ban-notification-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.85);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 999999;
                animation: fadeIn 0.4s ease;
                backdrop-filter: blur(2px);
            }

            .ban-notification-box {
                background: #ffffff;
                border-radius: 20px;
                padding: 40px;
                max-width: 550px;
                width: 90%;
                text-align: center;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
                animation: slideInScale 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                position: relative;
                overflow: hidden;
            }

            .ban-notification-box::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 5px;
                background: linear-gradient(90deg, #dc3545, #c82333);
            }

            .warning-notification::before {
                background: linear-gradient(90deg, #ffc107, #e0a800) !important;
            }

            .ban-notification-icon {
                font-size: 70px;
                margin-bottom: 25px;
                animation: pulse 2s infinite;
            }

            .ban-notification-icon.ban {
                color: #dc3545;
            }

            .ban-notification-icon.warning {
                color: #ffc107;
            }

            .ban-notification-title {
                font-size: 28px;
                font-weight: bold;
                margin-bottom: 25px;
                color: #333;
                line-height: 1.2;
            }

            .ban-notification-message {
                text-align: left;
                margin-bottom: 30px;
                color: #666;
                line-height: 1.6;
            }

            .ban-info-grid, .warning-info-grid {
                display: grid;
                gap: 15px;
                margin-bottom: 20px;
            }

            .ban-info-item, .warning-info-item {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #dee2e6;
            }

            .ban-info-item strong, .warning-info-item strong {
                color: #333;
                min-width: 100px;
                flex-shrink: 0;
            }

            .ban-info-item span, .warning-info-item span {
                text-align: right;
                word-break: break-word;
            }

            .ban-warning {
                background: linear-gradient(135deg, #ffe6e6, #ffcccc);
                padding: 15px;
                border-radius: 10px;
                border-left: 5px solid #dc3545;
                color: #721c24;
                font-weight: 500;
                margin-top: 15px;
            }

            .ban-warning i {
                margin-right: 8px;
                color: #dc3545;
            }

            .warning-info {
                background: linear-gradient(135deg, #fff3cd, #ffeaa7);
                padding: 15px;
                border-radius: 10px;
                border-left: 5px solid #ffc107;
                color: #856404;
                font-weight: 500;
                margin-top: 15px;
            }

            .warning-info i {
                margin-right: 8px;
                color: #ffc107;
            }

            .ban-notification-actions {
                text-align: center;
                margin-top: 25px;
            }

            .btn-understand {
                padding: 15px 35px;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin: 0 8px;
                min-width: 140px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .btn-ban {
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
                box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            }

            .btn-ban:hover {
                background: linear-gradient(135deg, #c82333, #bd2130);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
            }

            .btn-warning {
                background: linear-gradient(135deg, #ffc107, #e0a800);
                color: #212529;
                box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
            }

            .btn-warning:hover {
                background: linear-gradient(135deg, #e0a800, #d39e00);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
            }

            .badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .badge-warning-low {
                background: #d1ecf1;
                color: #0c5460;
            }

            .badge-warning-medium {
                background: #fff3cd;
                color: #856404;
            }

            .badge-warning-high {
                background: #f8d7da;
                color: #721c24;
            }

            .badge-warning-severe {
                background: #721c24;
                color: white;
            }

            .badge-unknown {
                background: #e2e3e5;
                color: #6c757d;
            }

            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #dc3545;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto;
            }

            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            @keyframes slideInScale {
                0% { 
                    opacity: 0;
                    transform: translateY(-50px) scale(0.8);
                }
                100% { 
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            @media (max-width: 768px) {
                .ban-notification-box {
                    padding: 25px 20px;
                    margin: 20px;
                    max-width: 95%;
                }
                
                .ban-notification-icon {
                    font-size: 50px;
                }
                
                .ban-notification-title {
                    font-size: 22px;
                }

                .ban-info-item, .warning-info-item {
                    flex-direction: column;
                    gap: 5px;
                    align-items: flex-start;
                }

                .ban-info-item span, .warning-info-item span {
                    text-align: left;
                }

                .btn-understand {
                    padding: 12px 25px;
                    font-size: 14px;
                    min-width: 120px;
                    margin: 5px;
                }
            }

            @media (max-width: 480px) {
                .ban-notification-actions {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .btn-understand {
                    width: 100%;
                    margin: 0;
                }
            }
        `;
        
        document.head.appendChild(style);
        this.log('🎨 Notification styles loaded');
    }
}

// GLOBAL INSTANCE WITH STRICT SINGLETON PATTERN
let banNotificationManager = null;

// PREVENT DOUBLE INITIALIZATION WITH MULTIPLE SAFETY CHECKS
document.addEventListener('DOMContentLoaded', function() {
    // STRICT: Check if already initialized
    if (banNotificationManager) {
        console.log('🛡️ [BAN-NOTIF] Manager already exists, skipping initialization');
        return;
    }

    // Check if another initialization is in progress
    if (window.banNotificationInitializing) {
        console.log('🛡️ [BAN-NOTIF] Initialization already in progress, skipping');
        return;
    }

    // Mark initialization in progress
    window.banNotificationInitializing = true;

    // Initialize with delay and safety checks
    setTimeout(() => {
        if (!banNotificationManager) { // Final safety check
            try {
                banNotificationManager = new BanNotificationManager();
                
                // Make globally available
                window.banNotificationManager = banNotificationManager;
                
                // Expose utility methods
                window.checkBanStatus = () => banNotificationManager.forceCheck();
                window.getBanNotificationStatus = () => banNotificationManager.getStatus();
                window.toggleBanSounds = (enabled) => banNotificationManager.setSoundEnabled(enabled);
                window.enableBanSounds = () => banNotificationManager.setSoundEnabled(true);
                window.disableBanSounds = () => banNotificationManager.setSoundEnabled(false);
                
                // NEW: Warning tracking utilities
                window.resetWarningTracking = () => banNotificationManager.resetWarningTracking();
                window.getWarningStats = () => banNotificationManager.getWarningStats();
                
                console.log('🚀 [BAN-NOTIF] Smart Warning System fully loaded and ready');
                console.log('🔊 [BAN-NOTIF] Sound alerts are DISABLED by default');
                console.log('📞 [BAN-NOTIF] Use enableBanSounds() to enable sound alerts');
                console.log('⚠️ [BAN-NOTIF] Smart warning filtering active to prevent spam');
                
            } catch (error) {
                console.error('❌ [BAN-NOTIF] Initialization failed:', error);
            }
        }
        
        // Clear initialization flag
        window.banNotificationInitializing = false;
        
    }, 2000); // Increased delay for stability
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (banNotificationManager) {
        banNotificationManager.destroy();
        banNotificationManager = null;
    }
    window.banNotificationInitializing = false;
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BanNotificationManager;
}