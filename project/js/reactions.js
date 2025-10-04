// reactions.js
document.addEventListener("DOMContentLoaded", function() {
    console.log("Reactions JS loaded");

    const reactionButtons = document.querySelectorAll(".reaction-btn");
    console.log("Found reaction buttons:", reactionButtons.length);

    reactionButtons.forEach(button => {
        button.addEventListener("click", function(e) {
            e.preventDefault();
            console.log("Button clicked:", this.dataset.reaction);

            if (this.disabled || this.classList.contains('loading')) {
                console.log("Button disabled or loading");
                return;
            }

            const articleReactionContainer = this.closest(".article-reactions");
            if (!articleReactionContainer) {
                console.error("Article reactions container not found");
                return;
            }

            const articleId = articleReactionContainer.dataset.articleId;
            const reactionType = this.dataset.reaction;

            console.log("Article ID:", articleId, "Reaction Type:", reactionType);

            if (!articleId || !reactionType) {
                console.error("Missing article ID or reaction type");
                return;
            }

            // Add loading state
            this.classList.add('loading');
            this.disabled = true;

            // Disable all buttons during request
            const allButtons = articleReactionContainer.querySelectorAll('.reaction-btn');
            allButtons.forEach(btn => btn.disabled = true);

            const formData = new FormData();
            formData.append('action', 'react');
            formData.append('article_id', articleId);
            formData.append('reaction_type', reactionType);

            fetch("article_reactions.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => {
                    console.log("Response status:", response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Response data:", data);

                    if (data.success) {
                        // Update counts dengan format baru
                        const likeCountElement = articleReactionContainer.querySelector(".like-count");
                        const dislikeCountElement = articleReactionContainer.querySelector(".dislike-count");

                        if (likeCountElement && data.reactions) {
                            likeCountElement.textContent = data.reactions.like;
                        }
                        if (dislikeCountElement && data.reactions) {
                            dislikeCountElement.textContent = data.reactions.dislike;
                        }

                        // Update active states
                        allButtons.forEach(btn => btn.classList.remove("active"));

                        if (data.user_reaction) {
                            const activeBtn = articleReactionContainer.querySelector(
                                `[data-reaction="${data.user_reaction}"]`);
                            if (activeBtn) {
                                activeBtn.classList.add("active");
                            }
                        }

                        // Show feedback message
                        let message = "";
                        if (data.action === "added") {
                            message = `Anda telah memberikan ${reactionType === 'like' ? 'like' : 'dislike'}`;
                        } else if (data.action === "updated") {
                            message = `Reaksi berhasil diubah ke ${reactionType === 'like' ? 'like' : 'dislike'}`;
                        } else if (data.action === "removed") {
                            message = `${reactionType === 'like' ? 'Like' : 'Dislike'} telah dihapus`;
                        }

                        if (message) {
                            showFeedback(message, "success");
                        }
                    } else {
                        console.error("Error:", data.message);
                        showFeedback(data.message || "Terjadi kesalahan", "error");

                        // Debug info jika ada
                        if (data.debug) {
                            console.error("Debug info:", data.debug);
                        }
                    }
                })
                .catch(error => {
                    console.error("Fetch error:", error);
                    showFeedback("Terjadi kesalahan saat memproses reaksi", "error");
                })
                .finally(() => {
                    // Remove loading state and re-enable buttons
                    allButtons.forEach(btn => {
                        btn.classList.remove('loading');
                        btn.disabled = false;
                    });
                });
        });
    });

    function showFeedback(message, type) {
        console.log("Showing feedback:", message, type);

        // Remove existing feedback messages
        const existingFeedback = document.querySelector('.feedback-message');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        const feedback = document.createElement("div");
        feedback.className = `feedback-message ${type}`;
        feedback.textContent = message;
        feedback.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s;
        `;

        document.body.appendChild(feedback);

        // Show feedback
        setTimeout(() => feedback.style.opacity = "1", 100);

        // Hide feedback after 3 seconds
        setTimeout(() => {
            feedback.style.opacity = "0";
            setTimeout(() => {
                if (feedback.parentNode) {
                    feedback.parentNode.removeChild(feedback);
                }
            }, 300);
        }, 3000);
    }

    // Load initial reactions when page loads
    function loadInitialReactions() {
        const reactionContainer = document.querySelector('.article-reactions');
        if (!reactionContainer) {
            console.log("No reaction container found");
            return;
        }

        const articleId = reactionContainer.dataset.articleId;
        if (!articleId) {
            console.error("No article ID found");
            return;
        }

        console.log("Loading initial reactions for article:", articleId);

        fetch(`article_reactions.php?action=get_reactions&article_id=${articleId}`)
            .then(response => response.json())
            .then(data => {
                console.log("Initial reactions data:", data);

                if (data.success) {
                    const likeCountElement = reactionContainer.querySelector(".like-count");
                    const dislikeCountElement = reactionContainer.querySelector(".dislike-count");

                    if (likeCountElement && data.reactions) {
                        likeCountElement.textContent = data.reactions.like;
                    }
                    if (dislikeCountElement && data.reactions) {
                        dislikeCountElement.textContent = data.reactions.dislike;
                    }

                    // Update active states
                    const buttonsInContainer = reactionContainer.querySelectorAll('.reaction-btn');
                    buttonsInContainer.forEach(btn => btn.classList.remove("active"));

                    if (data.user_reaction) {
                        const activeBtn = reactionContainer.querySelector(
                            `[data-reaction="${data.user_reaction}"]`);
                        if (activeBtn) {
                            activeBtn.classList.add("active");
                        }
                    }
                }
            })
            .catch(error => {
                console.error("Error loading initial reactions:", error);
            });
    }

    // Load reactions on page load
    loadInitialReactions();
});