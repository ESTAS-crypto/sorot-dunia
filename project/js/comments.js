// js/comments.js - Enhanced comment system with classic pagination + MODAL LOGIN SUPPORT
(function() {
    'use strict';

    console.log('=== Enhanced Comments JS with Modal Login loaded ===');

    // ========================================
    // VARIABLES & CONFIGURATION
    // ========================================
    let currentPage = 1;
    const perPage = 5;
    let totalPages = 1;
    let totalComments = 0;
    let isLoading = false;
    let articleId = null;

    // DOM Elements
    let commentForm = null;
    let commentsContainer = null;
    let commentsHeader = null;
    let initialLoading = null;
    let paginationContainer = null;

    // ========================================
    // INITIALIZATION
    // ========================================
    function init() {
        console.log('Initializing comments system...');

        commentForm = document.getElementById('comment-form');
        commentsContainer = document.querySelector('.comments-list');
        commentsHeader = document.querySelector('.comments-list h5');
        initialLoading = document.getElementById('initial-loading');
        paginationContainer = document.getElementById('comment-pagination');

        articleId = getArticleId();
        console.log('Article ID:', articleId);

        setupEventListeners();
        loadComments(1);
    }

    // ========================================
    // GET ARTICLE ID
    // ========================================
    function getArticleId() {
        const reactionContainer = document.querySelector('.article-reactions');
        if (reactionContainer && reactionContainer.dataset.articleId) {
            return parseInt(reactionContainer.dataset.articleId);
        }

        const articleIdInput = document.querySelector('input[name="article_id"]');
        if (articleIdInput && articleIdInput.value) {
            return parseInt(articleIdInput.value);
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('id')) {
            return parseInt(urlParams.get('id'));
        }

        return null;
    }

    // ========================================
    // SETUP EVENT LISTENERS
    // ========================================
    function setupEventListeners() {
        if (commentForm) {
            console.log('Setting up comment form listener');
            commentForm.addEventListener('submit', handleCommentSubmit);
        }

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
    }

    // ========================================
    // LOAD COMMENTS
    // ========================================
    function loadComments(page = 1) {
        if (isLoading) {
            console.log('Already loading, skipping...');
            return;
        }

        isLoading = true;
        currentPage = page;
        console.log(`Loading comments - Page: ${page}`);

        showLoadingState();

        const url = `ajax/load_comments.php?article_id=${articleId}&page=${page}&per_page=${perPage}`;
        console.log('Fetching:', url);

        fetch(url)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                removeLoadingIndicators();

                if (data.success) {
                    totalPages = data.pagination.total_pages;
                    totalComments = data.pagination.total_comments;

                    renderComments(data.comments);
                    updateCommentsHeader(totalComments);
                    renderPagination(data.pagination);
                    scrollToComments();

                    console.log('Comments loaded successfully');
                } else {
                    showError(data.message || 'Gagal memuat komentar');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                removeLoadingIndicators();
                showError('Terjadi kesalahan saat memuat komentar. Silakan refresh halaman.');
            })
            .finally(() => {
                isLoading = false;
            });
    }

    // ========================================
    // SHOW LOADING STATE
    // ========================================
    function showLoadingState() {
        if (commentsContainer) {
            const existingComments = commentsContainer.querySelectorAll('.comment-item');
            existingComments.forEach(item => item.remove());

            if (!initialLoading) {
                const loadingDiv = document.createElement('div');
                loadingDiv.id = 'initial-loading';
                loadingDiv.className = 'text-center my-4';
                loadingDiv.innerHTML = `
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="text-muted mt-2">Memuat komentar...</p>
                `;

                if (commentsHeader) {
                    commentsHeader.insertAdjacentElement('afterend', loadingDiv);
                } else {
                    commentsContainer.insertAdjacentElement('afterbegin', loadingDiv);
                }

                initialLoading = loadingDiv;
            }
        }

        if (paginationContainer) {
            paginationContainer.style.display = 'none';
        }
    }

    // ========================================
    // REMOVE LOADING INDICATORS
    // ========================================
    function removeLoadingIndicators() {
        if (initialLoading) {
            initialLoading.remove();
            initialLoading = null;
        }

        const loadingIndicators = document.querySelectorAll('.loading-indicator');
        loadingIndicators.forEach(indicator => indicator.remove());
    }

    // ========================================
    // RENDER COMMENTS
    // ========================================
    function renderComments(comments) {
        if (!commentsContainer) {
            console.error('Comments container not found');
            return;
        }

        console.log('Rendering', comments.length, 'comments');

        const existingComments = commentsContainer.querySelectorAll('.comment-item');
        existingComments.forEach(item => item.remove());

        const noCommentAlert = commentsContainer.querySelector('.alert-light');
        if (noCommentAlert) {
            noCommentAlert.remove();
        }

        if (comments.length === 0) {
            const emptyHtml = `
                <div class="alert alert-light text-center empty-comments">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0" style="font-size: 16px;">Belum ada komentar. Jadilah yang pertama!</p>
                </div>
            `;

            if (commentsHeader) {
                commentsHeader.insertAdjacentHTML('afterend', emptyHtml);
            } else {
                commentsContainer.insertAdjacentHTML('afterbegin', emptyHtml);
            }
        } else {
            comments.forEach((comment, index) => {
                const commentHtml = createCommentHtml(comment, index);
                if (commentsHeader) {
                    commentsHeader.insertAdjacentHTML('afterend', commentHtml);
                } else {
                    commentsContainer.insertAdjacentHTML('afterbegin', commentHtml);
                }
            });
        }
    }

    // ========================================
    // CREATE COMMENT HTML
    // ========================================
    function createCommentHtml(comment, index) {
        const content = escapeHtml(comment.content).replace(/\n/g, '<br>');
        const animationDelay = index * 0.05;

        return `
            <div class="comment-item" data-comment-id="${comment.id}" style="animation-delay: ${animationDelay}s">
                <div class="comment-author mb-2">
                    <i class="fas fa-user-circle fa-lg text-primary me-2"></i>
                    <strong>${escapeHtml(comment.user_name)}</strong>
                    <small class="comment-date text-muted d-block ms-4">
                        <i class="fas fa-clock me-1"></i>
                        ${comment.time_ago}
                    </small>
                </div>
                <div class="comment-content">
                    ${content}
                </div>
            </div>
        `;
    }

    // ========================================
    // UPDATE COMMENTS HEADER
    // ========================================
    function updateCommentsHeader(totalComments) {
        if (!commentsHeader) {
            const header = document.createElement('h5');
            header.className = 'mb-3';
            header.innerHTML = `
                <i class="fas fa-comments me-2"></i>
                Komentar (<span class="comment-count">${totalComments}</span>)
            `;

            if (commentsContainer) {
                commentsContainer.insertBefore(header, commentsContainer.firstChild);
                commentsHeader = header;
            }
        } else {
            const counter = commentsHeader.querySelector('.comment-count');
            if (counter) {
                counter.textContent = totalComments;
            }
        }
    }

    // ========================================
    // RENDER PAGINATION
    // ========================================
    function renderPagination(pagination) {
        if (!paginationContainer) return;

        if (pagination.total_comments === 0 || pagination.total_pages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }

        paginationContainer.style.display = 'flex';

        let html = '';

        const startItem = ((pagination.current_page - 1) * pagination.per_page) + 1;
        const endItem = Math.min(pagination.current_page * pagination.per_page, pagination.total_comments);

        html += `
            <div class="pagination-info">
                Menampilkan <strong>${startItem}-${endItem}</strong> dari <strong>${pagination.total_comments}</strong> komentar
            </div>
        `;

        if (pagination.current_page > 1) {
            html += `
                <a href="#" class="pagination-btn" data-page="${pagination.current_page - 1}">
                    <i class="fas fa-chevron-left"></i>
                    <span>Sebelumnya</span>
                </a>
            `;
        } else {
            html += `
                <span class="pagination-btn disabled">
                    <i class="fas fa-chevron-left"></i>
                    <span>Sebelumnya</span>
                </span>
            `;
        }

        html += '<div class="pagination-pages">';

        const maxPagesToShow = 5;
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxPagesToShow - 1);

        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        if (startPage > 1) {
            html += `<a href="#" class="pagination-number" data-page="1">1</a>`;
            if (startPage > 2) {
                html += `<span class="pagination-number disabled">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            if (i === pagination.current_page) {
                html += `<span class="pagination-number active">${i}</span>`;
            } else {
                html += `<a href="#" class="pagination-number" data-page="${i}">${i}</a>`;
            }
        }

        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                html += `<span class="pagination-number disabled">...</span>`;
            }
            html += `<a href="#" class="pagination-number" data-page="${pagination.total_pages}">${pagination.total_pages}</a>`;
        }

        html += '</div>';

        if (pagination.current_page < pagination.total_pages) {
            html += `
                <a href="#" class="pagination-btn" data-page="${pagination.current_page + 1}">
                    <span>Selanjutnya</span>
                    <i class="fas fa-chevron-right"></i>
                </a>
            `;
        } else {
            html += `
                <span class="pagination-btn disabled">
                    <span>Selanjutnya</span>
                    <i class="fas fa-chevron-right"></i>
                </span>
            `;
        }

        paginationContainer.innerHTML = html;

        const pageLinks = paginationContainer.querySelectorAll('[data-page]');
        pageLinks.forEach(link => {
            link.addEventListener('click', handlePageClick);
        });
    }

    // ========================================
    // HANDLE PAGE CLICK
    // ========================================
    function handlePageClick(e) {
        e.preventDefault();
        const page = parseInt(this.dataset.page);
        if (page && page !== currentPage) {
            console.log('Page clicked:', page);
            loadComments(page);
        }
    }

    // ========================================
    // SCROLL TO COMMENTS
    // ========================================
    function scrollToComments() {
        if (currentPage > 1 && commentsHeader) {
            commentsHeader.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    // ========================================
    // HANDLE COMMENT SUBMIT - WITH MODAL LOGIN
    // ========================================
    function handleCommentSubmit(e) {
        e.preventDefault();
        console.log('Comment form submitted');

        const submitBtn = commentForm.querySelector('button[type="submit"]');
        const textarea = commentForm.querySelector('textarea[name="comment_content"]');
        const commentContent = textarea.value.trim();

        if (!commentContent) {
            showMessage('Komentar tidak boleh kosong', 'error');
            return;
        }

        if (commentContent.length > 1000) {
            showMessage('Komentar terlalu panjang. Maksimal 1000 karakter.', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Mengirim...';

        const formData = new FormData(commentForm);

        fetch('add_comment.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Submit response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Submit response data:', data);

                if (data.success) {
                    textarea.value = '';

                    const charCount = document.getElementById('char-count');
                    if (charCount) {
                        charCount.textContent = '0';
                        charCount.style.color = '';
                    }

                    showMessage(data.message, 'success');

                    setTimeout(() => {
                        loadComments(1);
                    }, 1000);

                } else {
                    // ===== MODAL LOGIN SUPPORT =====
                    if (data.require_login) {
                        openLoginModal();
                        showMessage('Silakan login untuk mengirim komentar', 'info');
                    } else {
                        showMessage(data.message || 'Gagal menambahkan komentar', 'error');
                    }

                    if (data.redirect && !data.require_login) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
                }
            })
            .catch(error => {
                console.error('Submit error:', error);
                showMessage('Terjadi kesalahan. Silakan coba lagi.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Kirim Komentar';
            });
    }

    // ========================================
    // OPEN LOGIN MODAL
    // ========================================
    function openLoginModal() {
        const loginModal = document.getElementById('loginModal');
        if (loginModal && typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(loginModal);
            bsModal.show();
            console.log('Login modal opened from comments');
        } else {
            console.error('Login modal or Bootstrap not found');
            window.location.href = 'index.php?error=login_required';
        }
    }

    // ========================================
    // SHOW MESSAGE
    // ========================================
    function showMessage(message, type) {
        const existingMsg = document.querySelector('.comment-message');
        if (existingMsg) {
            existingMsg.remove();
        }

        const msgDiv = document.createElement('div');
        msgDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'info' ? 'info' : 'danger'} alert-dismissible fade show comment-message`;
        msgDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'info' ? 'info-circle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const commentSection = document.querySelector('.comment-section');
        if (commentSection) {
            commentSection.insertBefore(msgDiv, commentSection.firstChild);
            msgDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            setTimeout(() => {
                msgDiv.remove();
            }, 5000);
        }
    }

    // ========================================
    // SHOW ERROR
    // ========================================
    function showError(message) {
        removeLoadingIndicators();

        if (commentsContainer) {
            commentsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                    <button type="button" class="btn btn-primary btn-sm ms-3" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>
            `;
        }
    }

    // ========================================
    // ESCAPE HTML
    // ========================================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========================================
    // INITIALIZE ON DOM READY
    // ========================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();