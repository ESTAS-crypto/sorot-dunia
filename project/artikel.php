<?php
// Mulai session di paling awal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include konfigurasi database
require_once 'config/config.php';

// Debug: Log session data
error_log("Session data at start: " . print_r($_SESSION, true));

// Periksa apakah ada parameter id
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($article_id <= 0) {
    header("Location: berita.php?error=invalid_article_id");
    exit();
}

// Query untuk mengambil artikel
$query = "SELECT a.article_id, a.title, a.content, a.image_url, a.publication_date, 
                 u.full_name AS author_name, c.name AS category_name
          FROM articles a
          JOIN users u ON a.author_id = u.id
          JOIN categories c ON a.category_id = c.category_id
          WHERE a.article_id = $article_id AND a.article_status = 'published'";

$result = mysqli_query($koneksi, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: berita.php?error=article_not_found");
    exit();
}

$article = mysqli_fetch_assoc($result);

// PERBAIKAN UTAMA: Cek dan perbaiki session user
$is_logged_in = false;
$user_id = 0;
$username = '';
$user_role = '';
$full_name = '';

// Fungsi untuk memperbaiki session user
function fixUserSession() {
    global $koneksi, $is_logged_in, $user_id, $username, $user_role, $full_name;
    
    // Cek apakah user sudah login
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    $is_logged_in = true;
    
    // Ambil data user dari session
    $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    $full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
    
    // Jika user_id tidak ada tapi username ada, ambil dari database
    if ($user_id <= 0 && !empty($username)) {
        $user_query = "SELECT id, username, full_name, role FROM users WHERE username = '" . sanitize_input($username) . "'";
        $user_result = mysqli_query($koneksi, $user_query);
        
        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $user_data = mysqli_fetch_assoc($user_result);
            $user_id = (int)$user_data['id'];
            $user_role = $user_data['role'];
            $full_name = $user_data['full_name'];
            
            // Update session dengan data yang benar
            $_SESSION['id'] = $user_id;
            $_SESSION['user_role'] = $user_role;
            $_SESSION['full_name'] = $full_name;
            
            error_log("Fixed session data for user: " . $username);
        } else {
            // Jika user tidak ditemukan di database, hapus session
            session_destroy();
            return false;
        }
    }
    
    // Jika masih tidak ada user_id, coba cari berdasarkan session lain
    if ($user_id <= 0) {
        // Coba cari berdasarkan email jika ada
        if (isset($_SESSION['email'])) {
            $email = sanitize_input($_SESSION['email']);
            $user_query = "SELECT id, username, full_name, role FROM users WHERE email = '$email'";
            $user_result = mysqli_query($koneksi, $user_query);
            
            if ($user_result && mysqli_num_rows($user_result) > 0) {
                $user_data = mysqli_fetch_assoc($user_result);
                $user_id = (int)$user_data['id'];
                $username = $user_data['username'];
                $user_role = $user_data['role'];
                $full_name = $user_data['full_name'];
                
                // Update session
                $_SESSION['id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['user_role'] = $user_role;
                $_SESSION['full_name'] = $full_name;
                
                error_log("Fixed session data using email for user: " . $username);
            }
        }
    }
    
    // Validasi final
    if ($user_id <= 0) {
        error_log("Unable to fix session - user_id still 0");
        return false;
    }
    
    return true;
}

// Jalankan fungsi perbaikan session
$session_fixed = fixUserSession();

// Debug log hasil perbaikan
error_log("Session fix result: " . ($session_fixed ? 'SUCCESS' : 'FAILED'));
error_log("Final user data - ID: $user_id, Username: $username, Role: $user_role");

// Query untuk mengambil komentar
$comments_query = "SELECT c.content, c.created_at, u.full_name, u.username
                   FROM comments c
                   JOIN users u ON c.user_id = u.id
                   WHERE c.article_id = $article_id
                   ORDER BY c.created_at DESC";
$comments_result = mysqli_query($koneksi, $comments_query);

// Sertakan header
require 'header.php';
?>

<body>
    <div class="container main-content mt-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="main-article">
                    <div class="article-header">
                        <h1><?php echo htmlspecialchars($article['title']); ?></h1>
                        <div class="article-meta">
                            <span><i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($article['author_name']); ?></span> |
                            <span><i class="fas fa-calendar"></i>
                                <?php echo date('d F Y', strtotime($article['publication_date'])); ?></span> |
                            <span><i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($article['category_name']); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($article['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($article['image_url']); ?>"
                        alt="<?php echo htmlspecialchars($article['title']); ?>" class="article-image img-fluid">
                    <?php endif; ?>

                    <div class="article-content">
                        <?php echo nl2br(htmlspecialchars($article['content'])); ?>
                    </div>

                    <!-- Display Reactions Component -->
                    <?php
                    if (file_exists('article_reactions.php')) {
                        require 'article_reactions.php';
                        displayArticleReactions($article_id);
                    }
                    ?>
                </div>

                <!-- Comment Section -->
                <div class="comment-section mt-5">
                    <div class="comment-header">
                        <h4>Komentar</h4>

                        <!-- Debug Info (hapus di production) -->
                        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                        <div class="alert alert-info">
                            <strong>Debug Info:</strong><br>
                            Logged in: <?php echo $is_logged_in ? 'Yes' : 'No'; ?><br>
                            User ID: <?php echo $user_id; ?><br>
                            Username: <?php echo $username; ?><br>
                            Role: <?php echo $user_role; ?><br>
                            Session Fix: <?php echo $session_fixed ? 'Success' : 'Failed'; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Show success/error messages -->
                    <?php if (isset($_GET['comment']) && $_GET['comment'] === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Komentar berhasil ditambahkan!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        switch($_GET['error']) {
                            case 'invalid_input':
                                echo 'Data komentar tidak valid!';
                                break;
                            case 'comment_failed':
                                echo 'Gagal menambahkan komentar. Silakan coba lagi.';
                                break;
                            case 'session_error':
                                echo 'Terjadi kesalahan session. Silakan login kembali.';
                                break;
                            default:
                                echo 'Terjadi kesalahan!';
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_logged_in && $user_id > 0): ?>
                    <div class="comment-form">
                        <div class="user-info mb-3">
                            <small class="text-muted">
                                <i class="fas fa-user"></i>
                                Berkomentar sebagai:
                                <strong><?php echo htmlspecialchars($full_name ?: $username); ?></strong>
                            </small>
                        </div>
                        <form action="add_comment.php" method="POST">
                            <input type="hidden" name="article_id" value="<?php echo $article_id; ?>">
                            <div class="form-group mb-3">
                                <textarea name="comment_content" class="form-control" rows="4"
                                    placeholder="Tulis komentar Anda di sini..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Kirim Komentar
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="comment-login-message">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?php if (!$is_logged_in): ?>
                            Silakan <a href="login.php" class="alert-link">login</a> untuk menulis komentar.
                            <?php else: ?>
                            Terjadi kesalahan session. Silakan <a href="logout.php" class="alert-link">logout</a> dan
                            login kembali.
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="comments-list mt-4">
                        <?php if ($comments_result && mysqli_num_rows($comments_result) > 0): ?>
                        <h5>Komentar (<?php echo mysqli_num_rows($comments_result); ?>)</h5>
                        <?php while ($comment = mysqli_fetch_assoc($comments_result)): ?>
                        <div class="comment-item mb-3 p-3 bg-light rounded">
                            <div class="comment-author mb-2">
                                <i class="fas fa-user-circle text-primary"></i>
                                <strong><?php echo htmlspecialchars($comment['full_name'] ?: $comment['username']); ?></strong>
                                <span class="comment-date text-muted ms-2">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('d M Y H:i', strtotime($comment['created_at'])); ?>
                                </span>
                            </div>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="alert alert-light text-center">
                            <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Belum ada komentar. Jadilah yang pertama berkomentar!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Popular News Sidebar -->
                <?php
                $popular_query = "SELECT article_id, title, publication_date 
                                 FROM articles 
                                 WHERE article_status = 'published' AND article_id != $article_id
                                 ORDER BY publication_date DESC 
                                 LIMIT 4";
                $popular_result = mysqli_query($koneksi, $popular_query);
                ?>

                <div class="sidebar mb-4">
                    <div class="sidebar-header bg-primary text-white p-3">
                        <i class="fas fa-fire"></i> Berita Terpopuler
                    </div>
                    <div class="sidebar-content p-3">
                        <?php if ($popular_result && mysqli_num_rows($popular_result) > 0): ?>
                        <?php $counter = 1; ?>
                        <?php while ($popular = mysqli_fetch_assoc($popular_result)): ?>
                        <div class="trending-item mb-3 pb-3 <?php echo $counter < 4 ? 'border-bottom' : ''; ?>">
                            <div class="d-flex">
                                <div class="trending-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                    style="width: 30px; height: 30px; font-size: 14px;">
                                    <?php echo $counter; ?>
                                </div>
                                <div class="trending-content">
                                    <a href="artikel.php?id=<?php echo $popular['article_id']; ?>"
                                        class="text-decoration-none">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($popular['title']); ?></h6>
                                    </a>
                                    <div class="text-muted small">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d M Y', strtotime($popular['publication_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php $counter++; ?>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="alert alert-info">Belum ada berita populer</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Advertisement Sidebar -->
                <div class="sidebar">
                    <div class="sidebar-header bg-warning text-dark p-3">
                        <i class="fas fa-ad"></i> Iklan
                    </div>
                    <div class="sidebar-content p-3">
                        <div class="text-center">
                            <div class="bg-light p-4 rounded">
                                <i class="fas fa-ad fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Ruang untuk iklan</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Reactions JS -->
    <?php if (file_exists('js/reactions.js')): ?>
    <script src="js/reactions.js"></script>
    <?php endif; ?>

    <!-- === IMPLEMENTASI BAN NOTIFICATION SYSTEM === -->
    <?php if ($is_logged_in && $user_id > 0): ?>
    <!-- PREVENT DOUBLE LOADING -->
    <script>
    // PREVENT DOUBLE INITIALIZATION
    if (!window.banNotificationLoaded) {
        window.banNotificationLoaded = true;

        console.log('🛡️ [ARTIKEL] Loading ban notification for logged in user');

        // Mark body as logged-in for ban notification detection
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo $user_id; ?>');
        document.body.setAttribute('data-username', '<?php echo $username; ?>');
        document.body.setAttribute('data-page', 'artikel');
        document.body.setAttribute('data-article-id', '<?php echo $article_id; ?>');

        // Debug session info
        console.log('🔍 [ARTIKEL] Session Info:', {
            logged_in: <?php echo json_encode($is_logged_in); ?>,
            user_id: <?php echo json_encode($user_id); ?>,
            username: <?php echo json_encode($username); ?>,
            user_role: <?php echo json_encode($user_role); ?>,
            session_fixed: <?php echo json_encode($session_fixed); ?>,
            article_id: <?php echo json_encode($article_id); ?>
        });

        // Load the ban notification script
        const banScript = document.createElement('script');
        banScript.src = 'js/notif-ban.js?v=<?php echo time(); ?>';
        banScript.onload = function() {
            console.log('✅ [ARTIKEL] Ban notification script loaded successfully');

            // Wait for the manager to initialize
            let checkAttempts = 0;
            const maxAttempts = 15;

            const checkManager = setInterval(() => {
                checkAttempts++;

                if (window.banNotificationManager && window.banNotificationManager.isInitialized) {
                    console.log('✅ [ARTIKEL] Ban notification manager is ready');
                    clearInterval(checkManager);

                    // Force initial check after initialization
                    setTimeout(() => {
                        console.log('🔍 [ARTIKEL] Performing initial ban status check');
                        window.banNotificationManager.forceCheck();
                    }, 3000);

                } else if (checkAttempts >= maxAttempts) {
                    console.error('❌ [ARTIKEL] Ban notification manager failed to initialize after',
                        maxAttempts, 'attempts');
                    clearInterval(checkManager);
                }
            }, 1000);
        };

        banScript.onerror = function() {
            console.error('❌ [ARTIKEL] Failed to load ban notification script');
        };

        document.head.appendChild(banScript);

        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && window.banNotificationManager) {
                console.log('👁️ [ARTIKEL] Page became visible, checking ban status');
                setTimeout(() => {
                    window.banNotificationManager.forceCheck();
                }, 1000);
            }
        });

        // Handle focus events
        window.addEventListener('focus', function() {
            if (window.banNotificationManager) {
                console.log('🔍 [ARTIKEL] Page focused, checking ban status');
                setTimeout(() => {
                    window.banNotificationManager.forceCheck();
                }, 1000);
            }
        });
    }
    </script>
    <?php else: ?>
    <script>
    console.log('👥 [ARTIKEL] User not logged in, ban notification not loaded');
    </script>
    <?php endif; ?>

    <!-- Session Debug Script -->
    <script>
    console.log('Session Debug Info:', {
        logged_in: <?php echo json_encode($is_logged_in); ?>,
        user_id: <?php echo json_encode($user_id); ?>,
        username: <?php echo json_encode($username); ?>,
        user_role: <?php echo json_encode($user_role); ?>,
        session_fixed: <?php echo json_encode($session_fixed); ?>
    });

    // Background visitor tracking for artikel page (silent)
    <?php if ($koneksi && isset($_SESSION['logged_in'])): ?>
    setTimeout(function() {
        if (typeof fetch !== 'undefined') {
            fetch('track.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    page: window.location.pathname,
                    referrer: document.referrer,
                    timestamp: Date.now(),
                    action: 'article_view',
                    article_id: <?php echo $article_id; ?>
                })
            }).catch(() => {
                // Silent fail
            });
        }
    }, 2000);
    <?php endif; ?>
    </script>

    <?php require 'footer.php'; ?>
</body>




</html>