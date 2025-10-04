// js/comments.js - Handle AJAX comment submission
document.addEventListener('DOMContentLoaded', function() {
    console.log('Comments JS loaded');

    const commentForm = document.getElementById('comment-form');

    if (!commentForm) {
        console.log('Comment form not found');
        return;
    }

    console.log('Comment form found, attaching event listener');

    commentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Comment form submitted');

        const submitBtn = this.querySelector('button[type="submit"]');
        const textarea = this.querySelector('textarea[name="comment_content"]');
        const commentContent = textarea.value.trim();

        // Validasi
        if (!commentContent) {
            showMessage('Komentar tidak boleh kosong', 'error');
            return;
        }

        if (commentContent.length > 1000) {
            showMessage('Komentar terlalu panjang. Maksimal 1000 karakter.', 'error');
            return;
        }

        // Disable button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Mengirim...';

        // Get form data
        const formData = new FormData(this);

        // Send AJAX request
        fetch('add_comment.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);

                if (data.success) {
                    // Clear textarea
                    textarea.value = '';

                    // Add comment to list
                    addCommentToList(data.comment);

                    // Show success message
                    showMessage(data.message, 'success');

                    // Scroll to new comment
                    setTimeout(() => {
                        const newComment = document.querySelector('.comment-item:first-child');
                        if (newComment) {
                            newComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            newComment.classList.add('highlight-new');
                            setTimeout(() => {
                                newComment.classList.remove('highlight-new');
                            }, 2000);
                        }
                    }, 100);

                } else {
                    showMessage(data.message || 'Gagal menambahkan komentar', 'error');

                    // Redirect jika perlu login
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showMessage('Terjadi kesalahan. Silakan coba lagi.', 'error');
            })
            .finally(() => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Kirim Komentar';
            });
    });

    function addCommentToList(comment) {
        const commentsList = document.querySelector('.comments-list');

        if (!commentsList) {
            console.error('Comments list not found');
            return;
        }

        // Cek apakah ada pesan "Belum ada komentar"
        const noCommentAlert = commentsList.querySelector('.alert-light');
        if (noCommentAlert) {
            noCommentAlert.remove();
        }

        // Cek apakah sudah ada header "Komentar (X)"
        let commentsHeader = commentsList.querySelector('h5');
        if (!commentsHeader) {
            commentsHeader = document.createElement('h5');
            commentsHeader.className = 'mb-3';
            commentsHeader.innerHTML = '<i class="fas fa-comments me-2"></i>Komentar (<span class="comment-count">0</span>)';
            commentsList.insertBefore(commentsHeader, commentsList.firstChild);
        }

        // Update counter
        const counterSpan = commentsHeader.querySelector('.comment-count');
        if (counterSpan) {
            const currentCount = parseInt(counterSpan.textContent) || 0;
            counterSpan.textContent = currentCount + 1;
        }

        // Create comment HTML
        const commentHTML = `
            <div class="comment-item new-comment">
                <div class="comment-author mb-2">
                    <i class="fas fa-user-circle fa-lg text-primary me-2"></i>
                    <strong>${escapeHtml(comment.user_name)}</strong>
                    <small class="comment-date text-muted d-block ms-4">
                        <i class="fas fa-clock me-1"></i>
                        ${comment.created_at}
                    </small>
                </div>
                <div class="comment-content">
                    ${escapeHtml(comment.content).replace(/\n/g, '<br>')}
                </div>
            </div>
        `;

        // Insert at the beginning (after header if exists)
        if (commentsHeader) {
            commentsHeader.insertAdjacentHTML('afterend', commentHTML);
        } else {
            commentsList.insertAdjacentHTML('afterbegin', commentHTML);
        }
    }

    function showMessage(message, type) {
        // Remove existing messages
        const existingMsg = document.querySelector('.comment-message');
        if (existingMsg) {
            existingMsg.remove();
        }

        const msgDiv = document.createElement('div');
        msgDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show comment-message`;
        msgDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const commentSection = document.querySelector('.comment-section');
        if (commentSection) {
            commentSection.insertBefore(msgDiv, commentSection.firstChild);

            // Scroll to message
            msgDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Auto remove after 5 seconds
            setTimeout(() => {
                msgDiv.remove();
            }, 5000);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});