<!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // ========== SIDEBAR TOGGLE FUNCTIONALITY ==========
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        if (window.innerWidth <= 768) {
            // Mobile behavior
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        } else {
            // Desktop behavior
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    }

    // ========== CLICK OUTSIDE TO CLOSE (MOBILE) ==========
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.querySelector('.navbar-toggler');

        if (window.innerWidth <= 768 &&
            sidebar.classList.contains('show') &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }
    });

    // ========== WINDOW RESIZE HANDLER ==========
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        if (window.innerWidth > 768) {
            // Desktop mode
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            
            if (sidebar.classList.contains('collapsed')) {
                mainContent.classList.add('expanded');
            } else {
                mainContent.classList.remove('expanded');
            }
        } else {
            // Mobile mode
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        }
    });

    // ========== AUTO DISMISS ALERTS ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Auto dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                if (alert && document.contains(alert)) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            }, 5000);
        });

        // Initialize articles page specific functionality
        if (document.getElementById('articleTableBody') || document.getElementById('mobileArticlesList')) {
            initializeArticlesPage();
        }

        // Initialize dashboard specific functionality
        if (document.getElementById('welcomeAlert')) {
            initializeDashboard();
        }
    });

    // ========== ARTICLES PAGE FUNCTIONALITY ==========
    let currentArticleForView = null;

    function initializeArticlesPage() {
        initializeDragAndDrop();
        initializeStatusFilter();
    }

    // Image upload functions
    function switchImageOption(option, button) {
        document.querySelectorAll('#addArticleModal .image-option-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        button.classList.add('active');

        document.getElementById('image-upload-option').classList.toggle('active', option === 'upload');
        document.getElementById('image-url-option').classList.toggle('active', option === 'url');

        if (option === 'upload') {
            document.querySelector('#addArticleModal input[name="image_url"]').value = '';
        } else {
            document.getElementById('add_image_file').value = '';
            document.getElementById('add_image_preview').classList.add('d-none');
        }
    }

    function switchImageOptionEdit(option, button) {
        document.querySelectorAll('#editArticleModal .image-option-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        button.classList.add('active');

        document.getElementById('edit-image-upload-option').classList.toggle('active', option === 'upload');
        document.getElementById('edit-image-url-option').classList.toggle('active', option === 'url');

        if (option === 'upload') {
            document.querySelector('#editArticleModal input[name="image_url"]').value = '';
        } else {
            document.getElementById('edit_image_file').value = '';
            document.getElementById('edit_image_preview').classList.add('d-none');
        }
    }

    function previewImage(input, previewId) {
        const file = input.files[0];
        const preview = document.getElementById(previewId);

        if (file) {
            if (file.size > 300 * 1024) {
                showAlert('danger', 'Ukuran file terlalu besar. Maksimal 300KB.');
                input.value = '';
                preview.classList.add('d-none');
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                showAlert('danger', 'Format file tidak didukung. Hanya JPG, PNG, WEBP, dan GIF yang diizinkan.');
                input.value = '';
                preview.classList.add('d-none');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('d-none');
        }
    }

    function initializeStatusFilter() {
        document.querySelectorAll('#statusFilter a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                document.querySelectorAll('#statusFilter a').forEach(l => l.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                
                // Filter for desktop table
                const tableRows = document.querySelectorAll('#articleTableBody tr[data-status]');
                tableRows.forEach(row => {
                    if (filter === 'all' || row.dataset.status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Filter for mobile cards
                const mobileCards = document.querySelectorAll('#mobileArticlesList .card[data-status]');
                mobileCards.forEach(card => {
                    if (filter === 'all' || card.dataset.status === filter) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    }

    function viewArticle(article) {
        currentArticleForView = article;

        const statusBadges = {
            'published': '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Published</span>',
            'draft': '<span class="badge bg-warning text-dark"><i class="bi bi-pencil me-1"></i>Draft</span>',
            'pending': '<span class="badge bg-info"><i class="bi bi-clock me-1"></i>Pending Review</span>',
            'rejected': '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>',
            'archived': '<span class="badge bg-secondary"><i class="bi bi-archive me-1"></i>Archived</span>'
        };

        const statusBadge = statusBadges[article.article_status] || `<span class="badge bg-secondary">${article.article_status}</span>`;

        let imageHtml = '';
        if (article.image_filename) {
            imageHtml = `<div class="text-center mb-4">
                    <img src="../uploads/articles/${article.image_filename}" 
                         class="img-fluid rounded shadow" 
                         style="max-height: 400px; object-fit: cover;" 
                         alt="Article Image">
                </div>`;
        } else if (article.image_url) {
            imageHtml = `<div class="text-center mb-4">
                    <img src="${article.image_url}" 
                         class="img-fluid rounded shadow" 
                         style="max-height: 400px; object-fit: cover;" 
                         alt="Article Image">
                </div>`;
        }

        let contentHtml = `
                <div class="article-meta">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-hash text-muted me-2"></i>
                                <strong>ID:</strong>
                                <span class="badge bg-secondary ms-2">${article.article_id}</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-person text-muted me-2"></i>
                                <strong>Penulis:</strong>
                                <span class="ms-2">${article.author_name || 'Unknown'}</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-folder text-muted me-2"></i>
                                <strong>Kategori:</strong>
                                <span class="badge bg-info ms-2">${article.category_name || 'Uncategorized'}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-flag text-muted me-2"></i>
                                <strong>Status:</strong>
                                <span class="ms-2">${statusBadge}</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock text-muted me-2"></i>
                                <strong>Tanggal:</strong>
                                <span class="ms-2">${formatDate(article.publication_date)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="article-preview">
                    <h2 class="article-title mb-4">${article.title}</h2>
                    ${imageHtml}
                    <div class="article-content">
                        ${article.content.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;

        if (article.article_status === 'rejected' && article.rejection_reason) {
            contentHtml += `
                    <div class="alert alert-danger mt-4">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Alasan Penolakan:</strong><br>
                                ${article.rejection_reason}
                                <br><small class="text-muted">
                                    <i class="bi bi-person me-1"></i>Ditolak oleh: ${article.rejected_by_name || 'Unknown'}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
        }

        if (article.article_status === 'published' && article.admin_notes) {
            contentHtml += `
                    <div class="alert alert-success mt-4">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-check-circle fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Catatan Admin:</strong><br>
                                ${article.admin_notes}
                                <br><small class="text-muted">
                                    <i class="bi bi-person me-1"></i>Diapprove oleh: ${article.approved_by_name || 'Unknown'}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
        }

        document.getElementById('viewArticleContent').innerHTML = contentHtml;

        const approvalActions = document.getElementById('approvalActions');
        if (approvalActions) {
            if (article.article_status === 'pending') {
                approvalActions.style.display = 'block';
            } else {
                approvalActions.style.display = 'none';
            }
        }
    }

    function editArticle(article) {
        document.getElementById('edit_article_id').value = article.article_id;
        document.getElementById('edit_title').value = article.title;
        document.getElementById('edit_category_id').value = article.category_id;
        document.getElementById('edit_article_status').value = article.article_status;
        document.getElementById('edit_content').value = article.content;
        document.getElementById('edit_image_url').value = article.image_url || '';
        document.getElementById('edit_old_image_filename').value = article.image_filename || '';

        const currentImageDiv = document.getElementById('edit_current_image');
        if (article.image_filename) {
            currentImageDiv.innerHTML = `
                    <div class="current-image-display">
                        <div class="d-flex align-items-center">
                            <img src="../uploads/articles/${article.image_filename}" 
                                 style="max-height: 80px; object-fit: cover;" 
                                 class="rounded me-3" alt="Current Image">
                            <div>
                                <strong>Gambar saat ini:</strong><br>
                                <small class="text-muted">Upload file baru untuk mengganti gambar ini</small>
                            </div>
                        </div>
                    </div>
                `;
        } else if (article.image_url) {
            currentImageDiv.innerHTML = `
                    <div class="current-image-display">
                        <div class="d-flex align-items-center">
                            <img src="${article.image_url}" 
                                 style="max-height: 80px; object-fit: cover;" 
                                 class="rounded me-3" alt="Current Image">
                            <div>
                                <strong>Gambar saat ini (URL):</strong><br>
                                <small class="text-muted">${article.image_url}</small>
                            </div>
                        </div>
                    </div>
                `;
        } else {
            currentImageDiv.innerHTML = '';
        }

        document.getElementById('edit_image_preview').classList.add('d-none');
        document.getElementById('edit_image_file').value = '';
    }

    function editFromView() {
        if (currentArticleForView) {
            bootstrap.Modal.getInstance(document.getElementById('viewArticleModal')).hide();
            setTimeout(() => {
                editArticle(currentArticleForView);
                new bootstrap.Modal(document.getElementById('editArticleModal')).show();
            }, 300);
        }
    }

    function approveArticle(articleId, title) {
        document.getElementById('approve_article_id').value = articleId;
        document.getElementById('approve_article_title').textContent = title;
    }

    function rejectArticle(articleId, title) {
        document.getElementById('reject_article_id').value = articleId;
        document.getElementById('reject_article_title').textContent = title;
    }

    function deleteArticle(articleId, title) {
        document.getElementById('delete_article_id').value = articleId;
        document.getElementById('delete_article_title').textContent = title;
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteArticleModal'));
        deleteModal.show();
    }

    function quickApprove() {
        if (currentArticleForView) {
            bootstrap.Modal.getInstance(document.getElementById('viewArticleModal')).hide();
            setTimeout(() => {
                approveArticle(currentArticleForView.article_id, currentArticleForView.title);
                new bootstrap.Modal(document.getElementById('approveArticleModal')).show();
            }, 300);
        }
    }

    function quickReject() {
        if (currentArticleForView) {
            bootstrap.Modal.getInstance(document.getElementById('viewArticleModal')).hide();
            setTimeout(() => {
                rejectArticle(currentArticleForView.article_id, currentArticleForView.title);
                new bootstrap.Modal(document.getElementById('rejectArticleModal')).show();
            }, 300);
        }
    }

    function initializeDragAndDrop() {
        const uploadSections = document.querySelectorAll('.image-upload-section');

        uploadSections.forEach(section => {
            section.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            section.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            section.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const fileInput = this.querySelector('input[type="file"]');
                    fileInput.files = files;
                    const event = new Event('change', {bubbles: true});
                    fileInput.dispatchEvent(event);
                }
            });
        });
    }

    // ========== DASHBOARD FUNCTIONALITY ==========
    function initializeDashboard() {
        const welcomeAlert = document.getElementById('welcomeAlert');

        if (welcomeAlert) {
            // Auto-hide after 3 seconds
            setTimeout(function() {
                welcomeAlert.classList.add('fade-out');

                // Remove element after fade transition
                setTimeout(function() {
                    welcomeAlert.remove();
                }, 500);
            }, 3000);

            // Allow manual close by clicking
            welcomeAlert.addEventListener('click', function() {
                welcomeAlert.classList.add('fade-out');
                setTimeout(function() {
                    welcomeAlert.remove();
                }, 500);
            });
        }

        // Add smooth animations to stats cards
        const statsCards = document.querySelectorAll('.stats-card');
        statsCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('animate__animated', 'animate__fadeInUp');
        });
    }

    // ========== UTILITY FUNCTIONS ==========
    function showAlert(type, message, duration = 5000) {
        const alertContainer = document.querySelector('.container-fluid');
        if (!alertContainer) return;

        const alertId = 'alert-' + Date.now();
        const iconMap = {
            'success': 'bi-check-circle',
            'danger': 'bi-exclamation-circle',
            'warning': 'bi-exclamation-triangle',
            'info': 'bi-info-circle'
        };

        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                <i class="bi ${iconMap[type] || 'bi-info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        alertContainer.insertAdjacentHTML('afterbegin', alertHtml);

        // Auto dismiss
        if (duration > 0) {
            setTimeout(() => {
                const alert = document.getElementById(alertId);
                if (alert) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            }, duration);
        }
    }

    // Format numbers with K notation
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

    // Format date to readable format
    function formatDate(dateString) {
        if (!dateString || dateString === '0000-00-00 00:00:00') return '-';
        
        const date = new Date(dateString);
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleDateString('id-ID', options);
    }

    // Confirm delete action
    function confirmDelete(itemName, callback) {
        const result = confirm(`Apakah Anda yakin ingin menghapus "${itemName}"?\n\nTindakan ini tidak dapat dibatalkan.`);
        if (result && typeof callback === 'function') {
            callback();
        }
        return result;
    }

    // Handle form validation
    function validateForm(formElement) {
        const requiredFields = formElement.querySelectorAll('[required]');
        let isValid = true;
        let firstInvalidField = null;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!isValid && firstInvalidField) {
            firstInvalidField.focus();
            showAlert('danger', 'Mohon lengkapi semua field yang wajib diisi.');
        }

        return isValid;
    }

    // ========== KEYBOARD SHORTCUTS ==========
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + B = Toggle Sidebar
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            toggleSidebar();
        }
        
        // Escape = Close modals and overlays
        if (e.key === 'Escape') {
            const overlay = document.getElementById('sidebarOverlay');
            const sidebar = document.getElementById('sidebar');
            
            if (overlay && overlay.classList.contains('show')) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        }
    });

    // ========== LOADING STATE UTILITY ==========
    function setLoadingState(button, isLoading = true) {
        if (!button) return;
        
        if (isLoading) {
            button.disabled = true;
            const originalText = button.innerHTML;
            button.dataset.originalText = originalText;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading...';
        } else {
            button.disabled = false;
            if (button.dataset.originalText) {
                button.innerHTML = button.dataset.originalText;
                delete button.dataset.originalText;
            }
        }
    }

    // ========== SMOOTH SCROLL UTILITY ==========
    function smoothScrollTo(element) {
        if (element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }
    </script>
</body>
</html>