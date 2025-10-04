<?php
// artikel.php - VERSI FINAL (Trending tetap tampil termasuk artikel yang dibuka)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';

error_log("Session data at start: " . print_r($_SESSION, true));

$article_id = 0;
$article_slug = '';
$show_404 = false;

// Cek parameter slug (prioritas utama)
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $article_slug = sanitize_input($_GET['slug']);
    $slug_query = "SELECT GetArticleIdBySlug('$article_slug') as article_id";
    $slug_result = mysqli_query($koneksi, $slug_query);
    
    if ($slug_result && $slug_row = mysqli_fetch_assoc($slug_result)) {
        $article_id = (int)$slug_row['article_id'];
    }
    
    if ($article_id <= 0) {
        $show_404 = true;
    }
} 
elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $article_id = (int)$_GET['id'];
    
    if ($article_id <= 0) {
        $show_404 = true;
    } else {
        $id_to_slug_query = "SELECT GetArticleSlug($article_id) as slug";
        $id_result = mysqli_query($koneksi, $id_to_slug_query);
        
        if ($id_result && $id_row = mysqli_fetch_assoc($id_result)) {
            $found_slug = $id_row['slug'];
            if (!empty($found_slug)) {
                header("Location: artikel.php?slug=" . urlencode($found_slug), true, 301);
                exit();
            }
        }
    }
} else {
    $show_404 = true;
}

// Function untuk render 404 page
function render404Page() {
    require_once 'header.php';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Halaman Tidak Ditemukan</title>
        <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .error-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .error-content {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-number {
            font-size: 150px;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .error-title {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
        }
        
        .error-description {
            font-size: 18px;
            color: #718096;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .error-actions .btn {
            padding: 15px 35px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-actions .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .error-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .error-actions .btn-outline {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
        }
        
        .error-actions .btn-outline:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .error-content {
                padding: 40px 20px;
            }
            
            .error-number {
                font-size: 100px;
            }
            
            .error-title {
                font-size: 24px;
            }
            
            .error-description {
                font-size: 16px;
            }
            
            .error-actions {
                flex-direction: column;
            }
            
            .error-actions .btn {
                width: 100%;
            }
        }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-content">
                <div class="error-number">404</div>
                <h1 class="error-title">Not Found</h1>
                <p class="error-description">
                    The resource requested could not be found on this server!
                </p>
                
                <div class="error-actions">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        <span>Kembali ke Beranda</span>
                    </a>
                    <a href="berita.php" class="btn btn-outline">
                        <i class="fas fa-newspaper"></i>
                        <span>Lihat Semua Berita</span>
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    require_once 'footer.php';
    exit();
}

// Jika perlu tampilkan 404
if ($show_404) {
    http_response_code(404);
    render404Page();
}

// QUERY artikel
$query = "SELECT 
            a.article_id, 
            a.title, 
            a.content, 
            a.publication_date,
            a.view_count,
            s.slug,
            u.full_name AS author_name, 
            u.username AS author_username,
            c.name AS category_name,
            c.category_id,
            i.id as image_id,
            i.url AS image_url, 
            i.filename AS image_filename, 
            i.is_external,
            i.mime as image_mime,
            a.post_status
          FROM articles a
          LEFT JOIN users u ON a.author_id = u.id
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN images i ON a.featured_image_id = i.id
          LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
          WHERE a.article_id = $article_id 
          AND a.article_status IN ('published', 'Premium')
          LIMIT 1";

$result = mysqli_query($koneksi, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    http_response_code(404);
    render404Page();
}

$article = mysqli_fetch_assoc($result);

// Simpan article_id saat ini
$current_article_id = (int)$article['article_id'];

// Update view count
$update_view_query = "UPDATE articles SET view_count = view_count + 1 WHERE article_id = $current_article_id";
mysqli_query($koneksi, $update_view_query);

function getCorrectImageUrl($image_url, $is_external = 0, $filename = '') {
    if (empty($image_url) && empty($filename)) {
        return null;
    }
    
    if ($is_external == 1 && !empty($image_url)) {
        return $image_url;
    }
    
    if (!empty($image_url) && strpos($image_url, 'http') === 0) {
        return $image_url;
    }
    
    if (!empty($image_url) && strpos($image_url, '/') === 0) {
        return $image_url;
    }
    
    if (!empty($filename)) {
        return '/uploads/articles/' . $filename;
    }
    
    return $image_url;
}

$is_logged_in = false;
$user_id = 0;
$username = '';
$user_role = '';
$full_name = '';

function fixUserSession() {
    global $koneksi, $is_logged_in, $user_id, $username, $user_role, $full_name;
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    $is_logged_in = true;
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 
              (isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0);
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    $full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
    
    if ($user_id <= 0 && !empty($username)) {
        $user_query = "SELECT id, username, full_name, role FROM users WHERE username = '" . sanitize_input($username) . "'";
        $user_result = mysqli_query($koneksi, $user_query);
        
        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $user_data = mysqli_fetch_assoc($user_result);
            $user_id = (int)$user_data['id'];
            $user_role = $user_data['role'];
            $full_name = $user_data['full_name'];
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['id'] = $user_id;
            $_SESSION['user_role'] = $user_role;
            $_SESSION['full_name'] = $full_name;
            
            error_log("Fixed session data for user: " . $username);
        } else {
            session_destroy();
            return false;
        }
    }
    
    return $user_id > 0;
}

$session_fixed = fixUserSession();

// Query komentar
$comments_query = "SELECT c.content, c.created_at, u.full_name, u.username
                   FROM comments c
                   JOIN users u ON c.user_id = u.id
                   WHERE c.article_id = $current_article_id
                   ORDER BY c.created_at DESC";
$comments_result = mysqli_query($koneksi, $comments_query);

// Query artikel terkait - EXCLUDE artikel yang sedang dibuka
$related_query = "SELECT 
                    a.article_id, 
                    a.title, 
                    a.publication_date,
                    s.slug,
                    a.post_status
                  FROM articles a
                  LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
                  WHERE a.category_id = {$article['category_id']} 
                  AND a.article_id != $current_article_id
                  AND a.article_status IN ('published', 'Premium')
                  ORDER BY a.publication_date DESC 
                  LIMIT 5";
$related_result = mysqli_query($koneksi, $related_query);

// PERBAIKAN: Query berita populer TANPA EXCLUDE (tetap tampilkan artikel yang sedang dibuka)
$popular_query = "SELECT 
                    a.article_id, 
                    a.title, 
                    a.publication_date, 
                    a.view_count, 
                    s.slug
                  FROM articles a
                  LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
                  WHERE a.article_status = 'published'
                  ORDER BY a.view_count DESC, a.publication_date DESC 
                  LIMIT 5";

$popular_result = mysqli_query($koneksi, $popular_query);

require 'header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> | Sorot Dunia</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr(strip_tags($article['content']), 0, 160)); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/artikel.css">
</head>

<body>
    <div class="container main-content mt-4">
        <div class="row">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="berita.php">Berita</a></li>
                        <?php if ($article['category_name']): ?>
                        <li class="breadcrumb-item">
                            <a href="kategori.php?id=<?php echo $article['category_id']; ?>">
                                <?php echo htmlspecialchars($article['category_name']); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars(strlen($article['title']) > 50 ? substr($article['title'], 0, 50) . '...' : $article['title']); ?>
                        </li>
                    </ol>
                </nav>

                <div class="main-article">
                    <div class="article-header">
                        <h1><?php echo htmlspecialchars($article['title']); ?></h1>
                        <div class="article-meta">
                            <span class="badge bg-primary me-2">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($article['category_name']); ?>
                            </span>
                            <span class="text-muted">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name']); ?>
                            </span>
                            <span class="text-muted mx-2">•</span>
                            <span class="text-muted">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('d F Y', strtotime($article['publication_date'])); ?>
                            </span>
                            <span class="text-muted mx-2">•</span>
                            <span class="text-muted">
                                <i class="fas fa-eye"></i> <?php echo number_format($article['view_count']); ?> views
                            </span>
                        </div>
                    </div>

                    <?php 
                    if (!empty($article['image_url']) || !empty($article['image_filename'])):
                        $correct_image_url = getCorrectImageUrl(
                            $article['image_url'], 
                            $article['is_external'] ?? 0, 
                            $article['image_filename'] ?? ''
                        );
                    ?>
                    <div class="article-image-container">
                        <img src="<?php echo htmlspecialchars($correct_image_url); ?>" 
                             alt="<?php echo htmlspecialchars($article['title']); ?>" 
                             class="article-image"
                             onerror="this.style.display='none';">
                    </div>
                    <?php endif; ?>

                    <?php if (isset($article['post_status']) && strtolower($article['post_status']) === 'premium') : ?>
                    <div class="premium-lock">
                        <span class="badge bg-danger">
                            <i class="fas fa-star"></i> Premium
                        </span>
                        <p class="text-muted">Konten ini hanya untuk pengguna Premium.</p>
                    </div>
                    <?php else : ?>
                    <div class="article-content">
                        <?php echo $article['content']; ?>
                    </div>
                    <?php endif; ?>

                    <?php
                    if (file_exists('article_reactions.php')) {
                        require_once 'article_reactions.php';
                        displayArticleReactions($current_article_id);
                    }
                    ?>
                </div>

                <div class="comment-section">
                    <div class="comment-header">
                        <h4><i class="fas fa-comments me-2"></i>Komentar</h4>
                    </div>

                    <?php if (isset($_GET['comment']) && $_GET['comment'] === 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>Komentar berhasil ditambahkan!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_logged_in && $user_id > 0): ?>
                    <div class="comment-form mb-4">
                        <form id="comment-form" method="POST">
                            <input type="hidden" name="article_id" value="<?php echo $current_article_id; ?>">
                            <textarea name="comment_content" class="form-control" rows="4"
                                placeholder="Tulis komentar Anda..." required maxlength="1000"></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted">
                                    <span id="char-count">0</span>/1000 karakter
                                </small>
                                <button type="submit" class="btn btn-primary mt-2">
                                    <i class="fas fa-paper-plane me-1"></i> Kirim Komentar
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Silakan <a href="login.php?redirect=<?php echo urlencode('artikel.php?slug=' . $article['slug']); ?>" class="alert-link">login</a> untuk menulis komentar.
                    </div>
                    <?php endif; ?>

                    <div class="comments-list">
                        <?php if ($comments_result && mysqli_num_rows($comments_result) > 0): ?>
                        <h5 class="mb-3">
                            <i class="fas fa-comments me-2"></i>
                            Komentar (<?php echo mysqli_num_rows($comments_result); ?>)
                        </h5>
                        <?php while ($comment = mysqli_fetch_assoc($comments_result)): ?>
                        <div class="comment-item">
                            <div class="comment-author mb-2">
                                <i class="fas fa-user-circle fa-lg text-primary me-2"></i>
                                <strong><?php echo htmlspecialchars($comment['full_name'] ?: $comment['username']); ?></strong>
                                <small class="comment-date text-muted d-block ms-4">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('d M Y H:i', strtotime($comment['created_at'])); ?>
                                </small>
                            </div>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="alert alert-light text-center">
                            <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Belum ada komentar. Jadilah yang pertama!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <?php if ($related_result && mysqli_num_rows($related_result) > 0): ?>
                <div class="sidebar">
                    <div class="sidebar-header bg-primary text-white">
                        <i class="fas fa-newspaper me-2"></i>Artikel Terkait
                    </div>
                    <div class="sidebar-content">
                        <?php while ($related = mysqli_fetch_assoc($related_result)): ?>
                        <?php
                        $related_url = !empty($related['slug']) 
                            ? 'artikel.php?slug=' . urlencode($related['slug'])
                            : 'artikel.php?id=' . $related['article_id'];
                        ?>
                        <div class="related-item mb-3 pb-3 border-bottom">
                            <h6 class="mb-2">
                                <a href="<?php echo $related_url; ?>" 
                                   class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($related['title']); ?>
                                </a>
                            </h6>
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php echo date('d M Y', strtotime($related['publication_date'])); ?>
                            </small>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Berita Terpopuler - TETAP TAMPILKAN ARTIKEL YANG SEDANG DIBUKA -->
                <?php if ($popular_result && mysqli_num_rows($popular_result) > 0): ?>
                <div class="sidebar mt-3">
                    <div class="sidebar-header bg-danger text-white">
                        <i class="fas fa-fire me-2"></i>Berita Terpopuler
                    </div>
                    <div class="sidebar-content p-3">
                        <?php 
                        $counter = 1;
                        while ($popular = mysqli_fetch_assoc($popular_result)): 
                            $popular_url = !empty($popular['slug']) 
                                ? 'artikel.php?slug=' . urlencode($popular['slug'])
                                : 'artikel.php?id=' . $popular['article_id'];
                            
                            // Tandai jika ini artikel yang sedang dibuka
                            $is_current = ((int)$popular['article_id'] === $current_article_id);
                        ?>
                        <div class="trending-item mb-3 pb-3 <?php echo $counter < 5 ? 'border-bottom' : ''; ?> <?php echo $is_current ? 'bg-light' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="trending-number bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" 
                                     style="width: 35px; height: 35px; font-weight: bold;">
                                    <?php echo $counter; ?>
                                </div>
                                <div class="trending-content flex-grow-1">
                                    <?php if ($is_current): ?>
                                    <div class="text-dark">
                                        <h6 class="mb-1 fw-bold" style="line-height: 1.4;">
                                            <?php echo htmlspecialchars($popular['title']); ?>
                                            <span class="badge bg-success ms-2" style="font-size: 10px;">Sedang Dibaca</span>
                                        </h6>
                                    </div>
                                    <?php else: ?>
                                    <a href="<?php echo $popular_url; ?>"
                                        class="text-decoration-none text-dark">
                                        <h6 class="mb-1 fw-bold" style="line-height: 1.4;">
                                            <?php echo htmlspecialchars($popular['title']); ?>
                                        </h6>
                                    </a>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-2">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo date('d M Y', strtotime($popular['publication_date'])); ?>
                                        <span class="mx-1">•</span>
                                        <i class="fas fa-eye me-1"></i>
                                        <?php echo number_format($popular['view_count']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php 
                            $counter++;
                            if ($counter > 5) break;
                        endwhile; 
                        ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="sidebar mt-3">
                    <div class="sidebar-header bg-danger text-white">
                        <i class="fas fa-fire me-2"></i>Berita Terpopuler
                    </div>
                    <div class="sidebar-content p-3">
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-newspaper fa-2x mb-2"></i>
                            <p class="mb-0">Belum ada berita populer</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require 'footer.php'; ?>
</body>
</html>