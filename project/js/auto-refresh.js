class AutoRefreshSystem {
    constructor() {
        this.currentArticleCount = window.initialArticleCount || 0;
        this.refreshInterval = null;
        this.notificationTimeout = null;
        this.isPageVisible = true;
        this.lastCheckTime = Date.now();
        this.checkIntervalMs = 30000; // 30 detik

        this.init();
    }

    init() {
        // Hanya aktif di halaman utama
        if (!this.isHomePage()) {
            return;
        }

        console.log('🔄 Auto-refresh system initialized');
        console.log('📊 Current article count:', this.currentArticleCount);

        this.setupEventListeners();
        this.startAutoRefresh();
        this.createRefreshButton();
        this.setupVisibilityChange();
    }

    isHomePage() {
        const path = window.location.pathname;
        return path === '/' || path.endsWith('index.php') || path.endsWith('/') || path.includes('index');
    }

    setupEventListeners() {
        // Stop auto-refresh ketika user navigasi
        window.addEventListener('beforeunload', () => this.stopAutoRefresh());

        // Track article clicks untuk analytics
        this.setupArticleClickTracking();

        // Setup scroll tracking
        this.setupScrollTracking();
    }

    setupVisibilityChange() {
        document.addEventListener('visibilitychange', () => {
            this.isPageVisible = !document.hidden;

            if (this.isPageVisible) {
                console.log('👁️ Tab visible - resuming auto-refresh');
                this.startAutoRefresh();
                // Check immediately when tab becomes visible
                setTimeout(() => this.checkForNewArticles(), 1000);
            } else {
                console.log('👁️ Tab hidden - pausing auto-refresh');
                this.stopAutoRefresh();
            }
        });
    }

    async checkForNewArticles() {
        if (!this.isPageVisible) {
            console.log('⏸️ Tab not visible, skipping check');
            return;
        }

        try {
            console.log('🔍 Checking for new articles...');

            const url = `update_artikel.php?last_count=${this.currentArticleCount}&t=${Date.now()}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('📡 API Response:', data);

            if (data.success) {
                if (data.new_articles && data.new_count > 0) {
                    console.log(`✨ Found ${data.new_count} new articles!`);
                    this.showNewArticleNotification(data.new_count, data.latest_articles);
                    this.currentArticleCount = data.count;
                } else {
                    console.log('📰 No new articles');
                }

                this.lastCheckTime = Date.now();
                this.updateLastCheckIndicator();
            } else {
                console.error('❌ API Error:', data.error);
            }

        } catch (error) {
            console.error('🚫 Check failed:', error);
            this.handleCheckError(error);
        }
    }

    showNewArticleNotification(newCount, latestArticles = []) {
        // Remove existing notification
        this.removeExistingNotification();

        // Create notification
        const notification = this.createNotificationElement(newCount, latestArticles);
        document.body.appendChild(notification);

        // Show with animation
        setTimeout(() => notification.classList.add('show'), 100);

        // Auto hide after 15 seconds
        clearTimeout(this.notificationTimeout);
        this.notificationTimeout = setTimeout(() => {
            this.hideNotification(notification);
        }, 15000);

        // Play notification sound (optional)
        this.playNotificationSound();
    }

    createNotificationElement(newCount, latestArticles) {
        const notification = document.createElement('div');
        notification.className = 'new-article-notification alert alert-info alert-dismissible fade position-fixed';
        notification.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 1060;
            min-width: 350px;
            max-width: 400px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border: none;
            border-left: 4px solid #0d6efd;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.95);
        `;

        // Create preview content
        let previewHTML = '';
        if (latestArticles.length > 0) {
            const latest = latestArticles[0];
            previewHTML = `
                <div class="mt-2 p-2 bg-light rounded small">
                    <strong>Artikel Terbaru:</strong><br>
                    <span class="text-truncate d-block">${this.escapeHtml(latest.title)}</span>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        ${this.formatDate(latest.publication_date)}
                    </small>
                </div>
            `;
        }

        notification.innerHTML = `
            <div class="d-flex align-items-start">
                <div class="me-3">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                        <i class="fas fa-newspaper"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">
                        <i class="fas fa-sparkles text-warning me-1"></i>
                        Berita Baru Tersedia!
                    </h6>
                    <p class="mb-2">${newCount} artikel baru telah dipublikasikan</p>
                    ${previewHTML}
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary btn-sm me-2" onclick="autoRefresh.refreshPageContent()">
                            <i class="fas fa-sync-alt me-1"></i> Muat Sekarang
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="autoRefresh.dismissNotification()">
                            Nanti Saja
                        </button>
                    </div>
                </div>
                <button type="button" class="btn-close" onclick="autoRefresh.dismissNotification()"></button>
            </div>
        `;

        return notification;
    }

    refreshPageContent() {
        console.log('🔄 Refreshing page content...');

        // Show loading overlay
        this.showLoadingOverlay();

        // Add cache buster to URL
        const url = new URL(window.location);
        url.searchParams.set('refresh', Date.now());

        // Refresh setelah delay singkat untuk UX yang lebih baik
        setTimeout(() => {
            window.location.href = url.toString();
        }, 800);
    }

    showLoadingOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'refresh-loading-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        `;

        overlay.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                <h5 class="text-primary">Memuat berita terbaru...</h5>
                <p class="text-muted">Mohon tunggu sebentar</p>
            </div>
        `;

        document.body.appendChild(overlay);
    }

    dismissNotification() {
        const notification = document.querySelector('.new-article-notification');
        if (notification) {
            this.hideNotification(notification);
        }
    }

    hideNotification(notification) {
        if (notification && notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }

    removeExistingNotification() {
        const existing = document.querySelector('.new-article-notification');
        if (existing) {
            existing.remove();
        }
    }

    createRefreshButton() {
        const refreshButton = document.createElement('button');
        refreshButton.className = 'btn btn-primary refresh-floating-btn position-fixed';
        refreshButton.style.cssText = `
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        `;
        refreshButton.innerHTML = '<i class="fas fa-sync-alt"></i>';
        refreshButton.title = 'Periksa berita terbaru';
        refreshButton.onclick = () => this.checkForNewArticles();

        document.body.appendChild(refreshButton);

        // Show/hide on scroll
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            refreshButton.style.display = 'flex';
            refreshButton.style.alignItems = 'center';
            refreshButton.style.justifyContent = 'center';

            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                refreshButton.style.display = 'none';
            }, 4000);
        });
    }

    updateLastCheckIndicator() {
        let indicator = document.querySelector('.last-check-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'last-check-indicator position-fixed';
            indicator.style.cssText = `
                bottom: 100px;
                right: 30px;
                z-index: 999;
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 11px;
                display: none;
            `;
            document.body.appendChild(indicator);
        }

        indicator.textContent = `Last check: ${new Date().toLocaleTimeString()}`;
        indicator.style.display = 'block';

        setTimeout(() => {
            indicator.style.display = 'none';
        }, 3000);
    }

    handleCheckError(error) {
        console.error('Check error:', error);

        // Show subtle error indicator
        const errorIndicator = document.createElement('div');
        errorIndicator.className = 'error-indicator position-fixed';
        errorIndicator.style.cssText = `
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
        `;
        errorIndicator.textContent = 'Gagal memeriksa artikel baru';

        document.body.appendChild(errorIndicator);

        setTimeout(() => {
            if (errorIndicator.parentNode) {
                errorIndicator.remove();
            }
        }, 5000);
    }

    setupArticleClickTracking() {
        const articleLinks = document.querySelectorAll('a[href*="artikel.php"]');
        articleLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const articleId = link.href.match(/id=(\d+)/);
                if (articleId) {
                    this.trackEvent('article_click', {
                        article_id: articleId[1],
                        href: link.href,
                        text: link.textContent.trim().substring(0, 50)
                    });
                }
            });
        });
    }

    setupScrollTracking() {
        let maxScroll = 0;
        let milestones = [25, 50, 75, 100];
        let tracked = new Set();

        window.addEventListener('scroll', () => {
            const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);

            if (scrollPercent > maxScroll) {
                maxScroll = scrollPercent;

                milestones.forEach(milestone => {
                    if (scrollPercent >= milestone && !tracked.has(milestone)) {
                        tracked.add(milestone);
                        this.trackEvent(`scroll_${milestone}`, {
                            scroll_percent: scrollPercent,
                            max_scroll: maxScroll
                        });
                    }
                });
            }
        });
    }

    trackEvent(action, data = {}) {
        if (typeof window.trackVisit === 'function') {
            window.trackVisit(action, data);
        } else if (typeof fetch !== 'undefined') {
            fetch('track_visit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    data: data,
                    page: window.location.pathname,
                    timestamp: Date.now()
                })
            }).catch(() => {
                // Silent fail
            });
        }
    }

    playNotificationSound() {
        // Optional: play subtle notification sound
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+DyvmzyKC');
            audio.volume = 0.1;
            audio.play().catch(() => {
                // Silent fail if audio not allowed
            });
        } catch (e) {
            // Silent fail
        }
    }

    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear existing interval

        console.log(`⏰ Starting auto-refresh (${this.checkIntervalMs/1000}s intervals)`);

        // Initial check setelah 5 detik
        setTimeout(() => this.checkForNewArticles(), 5000);

        // Set interval untuk check berkala
        this.refreshInterval = setInterval(() => {
            this.checkForNewArticles();
        }, this.checkIntervalMs);
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
            console.log('⏹️ Auto-refresh stopped');
        }

        if (this.notificationTimeout) {
            clearTimeout(this.notificationTimeout);
            this.notificationTimeout = null;
        }
    }

    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'Baru saja';
        if (diffMins < 60) return `${diffMins} menit yang lalu`;

        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours} jam yang lalu`;

        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }
}

// Initialize auto-refresh system
let autoRefresh;

document.addEventListener('DOMContentLoaded', () => {
    autoRefresh = new AutoRefreshSystem();

    // Make it globally accessible for debugging
    window.autoRefresh = autoRefresh;

    // Animate existing content
    animateExistingContent();
});

function animateExistingContent() {
    const items = document.querySelectorAll('.news-card, .carousel-item, .trending-item');
    items.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';

        setTimeout(() => {
            item.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });
}