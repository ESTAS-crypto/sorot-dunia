<!-- Footer content - Library sudah dimuat di header.php -->

<script>
// Verify jQuery is loaded
if (typeof jQuery !== 'undefined') {
    console.log('✓ jQuery loaded:', $.fn.jquery);
} else {
    console.error('✗ jQuery not loaded!');
}

// Verify Bootstrap
if (typeof bootstrap !== 'undefined') {
    console.log('✓ Bootstrap loaded');
} else {
    console.error('✗ Bootstrap not loaded!');
}

// Verify Summernote
if (typeof $.fn.summernote !== 'undefined') {
    console.log('✓ Summernote loaded');
} else {
    console.error('✗ Summernote not loaded!');
}

// Enhanced utilities
(function() {
    'use strict';

    // Global drag and drop utilities
    window.DragDropUtils = {
        isValidImage: function(file) {
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            return validTypes.includes(file.type);
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        createPreview: function(file, callback) {
            if (this.isValidImage(file)) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    callback(e.target.result);
                };
                reader.readAsDataURL(file);
            }
        },

        validateFile: function(file, maxSize = 5242880) {
            const errors = [];
            
            if (!this.isValidImage(file)) {
                errors.push('File harus berupa gambar (JPG, PNG, GIF, WebP)');
            }
            
            if (file.size > maxSize) {
                errors.push(`Ukuran file terlalu besar. Maksimal ${this.formatFileSize(maxSize)}`);
            }
            
            return errors;
        }
    };

    // Notification system
    window.NotificationUtils = {
        show: function(type, title, message, duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            `;
            
            const icons = {
                'success': 'check-circle',
                'danger': 'exclamation-triangle',
                'warning': 'exclamation-circle',
                'info': 'info-circle'
            };
            
            notification.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="fas fa-${icons[type] || 'info-circle'} me-2 mt-1"></i>
                    <div class="flex-grow-1">
                        <strong>${title}</strong><br>
                        <small>${message}</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode && typeof bootstrap !== 'undefined') {
                    const bsAlert = new bootstrap.Alert(notification);
                    bsAlert.close();
                }
            }, duration);
            
            return notification;
        },

        success: function(title, message, duration) {
            return this.show('success', title, message, duration);
        },

        error: function(title, message, duration) {
            return this.show('danger', title, message, duration);
        },

        warning: function(title, message, duration) {
            return this.show('warning', title, message, duration);
        },

        info: function(title, message, duration) {
            return this.show('info', title, message, duration);
        }
    };

    // Form validation
    window.FormValidator = {
        validateRequired: function(form) {
            const errors = [];
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    const label = field.labels[0]?.textContent || field.name || 'Field';
                    errors.push(`${label} harus diisi`);
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });
            
            return errors;
        },

        validate: function(form) {
            return this.validateRequired(form);
        }
    };

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            console.log('✓ Bootstrap tooltips initialized');
        }

        // Check system status
        setTimeout(function() {
            const status = {
                jquery: typeof jQuery !== 'undefined' ? '✓' : '✗',
                bootstrap: typeof bootstrap !== 'undefined' ? '✓' : '✗',
                summernote: typeof $.fn.summernote !== 'undefined' ? '✓' : '✗'
            };
            console.log('System Status:', status);
        }, 500);
    });

})();

</script>

<style>
/* Mobile-specific fixes */
@media (max-width: 768px) {
    /* Prevent zoom on input focus (iOS) */
    input[type="text"],
    input[type="email"],
    input[type="password"],
    textarea,
    select {
        font-size: 16px !important;
    }
    
    /* Better touch targets */
    .btn {
        min-height: 44px;
        min-width: 44px;
    }
    
    /* Summernote mobile optimization */
    .note-editor .note-toolbar {
        padding: 0.5rem;
    }
    
    .note-btn {
        padding: 0.25rem 0.5rem !important;
        font-size: 0.875rem !important;
    }
}

/* Print styles */
@media print {
    .no-print,
    .navbar,
    .btn,
    .alert,
    .note-toolbar {
        display: none !important;
    }
}
</style>

</body>
</html>