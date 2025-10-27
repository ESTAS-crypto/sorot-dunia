<?php
// artikel.php - VERSI COMPLETE DENGAN PREMIUM ACCESS SYSTEM + PAGINATION COMMENTS + MODAL LOGIN FIX
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';

// Enable error logging untuk debugging
error_log("=== ARTIKEL.PHP START ===");
error_log("Session data: " . print_r($_SESSION, true));
error_log("GET params: " . print_r($_GET, true));

$article_id = 0;
$article_slug = '';
$show_404 = false;

// Cek parameter slug (prioritas utama)
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $article_slug = sanitize_input($_GET['slug']);
    error_log("Slug parameter: " . $article_slug);
    
    $slug_query = "SELECT related_id FROM slugs WHERE slug = '$article_slug' AND type = 'article' LIMIT 1";
    $slug_result = mysqli_query($koneksi, $slug_query);
    
    if ($slug_result && mysqli_num_rows($slug_result) > 0) {
        $slug_row = mysqli_fetch_assoc($slug_result);
        $article_id = (int)$slug_row['related_id'];
        error_log("Found article_id from slug: " . $article_id);
    } else {
        error_log("Slug not found in database");
        $show_404 = true;
    }
} 
elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $article_id = (int)$_GET['id'];
    error_log("ID parameter: " . $article_id);
    
    if ($article_id <= 0) {
        $show_404 = true;
    } else {
        // Redirect dari ID ke slug
        $id_to_slug_query = "SELECT slug FROM slugs WHERE related_id = $article_id AND type = 'article' LIMIT 1";
        $id_result = mysqli_query($koneksi, $id_to_slug_query);
        
        if ($id_result && $id_row = mysqli_fetch_assoc($id_result)) {
            $found_slug = $id_row['slug'];
            if (!empty($found_slug)) {
                error_log("Redirecting to slug: " . $found_slug);
                header("Location: artikel.php?slug=" . urlencode($found_slug), true, 301);
                exit();
            }
        }
    }
} else {
    error_log("No slug or id parameter");
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

// QUERY artikel dengan EXPLICIT column selection
$query = "SELECT 
            a.article_id, 
            a.title, 
            a.content, 
            a.publication_date,
            a.view_count,
            a.post_status,
            a.article_status,
            s.slug,
            u.full_name AS author_name, 
            u.username AS author_username,
            c.name AS category_name,
            c.category_id,
            i.id as image_id,
            i.url AS image_url, 
            i.filename AS image_filename, 
            i.is_external,
            i.mime as image_mime
          FROM articles a
          LEFT JOIN users u ON a.author_id = u.id
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN images i ON a.featured_image_id = i.id
          LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
          WHERE a.article_id = $article_id 
          AND a.article_status = 'published'
          LIMIT 1";

error_log("Article query: " . $query);

$result = mysqli_query($koneksi, $query);

if (!$result) {
    error_log("Query error: " . mysqli_error($koneksi));
    http_response_code(404);
    render404Page();
}

if (mysqli_num_rows($result) == 0) {
    error_log("Article not found in database");
    http_response_code(404);
    render404Page();
}

$article = mysqli_fetch_assoc($result);

error_log("Article found: ID=" . $article['article_id'] . ", post_status=" . $article['post_status']);

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
        $possible_paths = [
            'uploads/articles/published/' . $filename,
            'uploads/articles/' . $filename,
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return 'uploads/articles/' . $filename;
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
    
    error_log("=== fixUserSession START ===");
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        error_log("User not logged in");
        return false;
    }
    
    $is_logged_in = true;
    
    // Prioritas user_id
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 
              (isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0);
    
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
    $full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
    
    error_log("Session user_id: " . $user_id);
    error_log("Session username: " . $username);
    error_log("Session user_role: " . $user_role);
    
    // Jika user_id tidak ada tapi username ada, cari di database
    if ($user_id <= 0 && !empty($username)) {
        error_log("Attempting to fix session from database");
        
        $user_query = "SELECT id, username, full_name, role FROM users WHERE username = '" . mysqli_real_escape_string($koneksi, $username) . "' LIMIT 1";
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
            
            error_log("Session fixed from database - user_id: " . $user_id . ", role: " . $user_role);
        } else {
            error_log("User not found in database, destroying session");
            session_destroy();
            return false;
        }
    }
    
    error_log("Final user_id: " . $user_id . ", role: " . $user_role);
    error_log("=== fixUserSession END ===");
    
    return $user_id > 0;
}

$session_fixed = fixUserSession();

// ===== PREMIUM ACCESS CONTROL - CRITICAL SECTION =====
$is_premium_content = false;
$can_access_premium = false;

// Cek apakah artikel ini premium (case-insensitive comparison)
if (isset($article['post_status'])) {
    $post_status_lower = strtolower(trim($article['post_status']));
    $is_premium_content = ($post_status_lower === 'premium');
    
    error_log("=== PREMIUM CHECK ===");
    error_log("post_status raw: " . $article['post_status']);
    error_log("post_status lower: " . $post_status_lower);
    error_log("is_premium_content: " . ($is_premium_content ? 'YES' : 'NO'));
}

// Cek apakah user bisa akses premium
if ($is_logged_in && !empty($user_role)) {
    // Role yang bisa akses premium: admin dan premium
    $allowed_roles = ['admin', 'premium'];
    $can_access_premium = in_array(strtolower($user_role), $allowed_roles);
    
    error_log("User role: " . $user_role);
    error_log("Can access premium: " . ($can_access_premium ? 'YES' : 'NO'));
    error_log("Allowed roles: " . implode(', ', $allowed_roles));
} else {
    error_log("User not logged in or role not set");
}

error_log("=== ACCESS DECISION ===");
error_log("is_premium_content: " . ($is_premium_content ? 'YES' : 'NO'));
error_log("can_access_premium: " . ($can_access_premium ? 'YES' : 'NO'));
error_log("Will show lock: " . ($is_premium_content && !$can_access_premium ? 'YES' : 'NO'));

// Query artikel terkait
$related_query = "SELECT 
                    a.article_id, 
                    a.title, 
                    a.publication_date,
                    a.post_status,
                    s.slug
                  FROM articles a
                  LEFT JOIN slugs s ON (s.related_id = a.article_id AND s.type = 'article')
                  WHERE a.category_id = {$article['category_id']} 
                  AND a.article_id != $current_article_id
                  AND a.article_status = 'published'
                  ORDER BY a.publication_date DESC 
                  LIMIT 5";
$related_result = mysqli_query($koneksi, $related_query);

// Query berita populer
$popular_query = "SELECT 
                    a.article_id, 
                    a.title, 
                    a.publication_date, 
                    a.view_count,
                    a.post_status,
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
                        <?php if ($is_premium_content): ?>
                        <span class="premium-badge-large">
                            <i class="fas fa-crown"></i> PREMIUM CONTENT
                        </span>
                        <?php endif; ?>
                        
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

                    <?php if ($is_premium_content && !$can_access_premium): ?>
                    <!-- ===== PREMIUM LOCKED CONTENT ===== -->
                    <div class="content-preview">
                        <div class="article-content">
                            <?php echo substr(strip_tags($article['content']), 0, 600); ?>...
                        </div>
                    </div>
                    
                    <div class="premium-lock-overlay">
                        <div class="lock-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>🔒 Konten Premium Terkunci</h3>
                        <p>Artikel ini adalah konten eksklusif untuk member <strong>Premium</strong>.<br>
                        Upgrade akun Anda untuk membaca artikel lengkap dan nikmati berbagai keuntungan lainnya!</p>
                        
                        <div class="premium-benefits">
                            <h4><i class="fas fa-star"></i> Keuntungan Member Premium:</h4>
                            <ul>
                                <li><strong>Akses Unlimited</strong> - Baca semua artikel premium tanpa batas</li>
                                <li><strong>Konten Eksklusif</strong> - Artikel eksklusif dari penulis ternama</li>
                                <li><strong>Early Access</strong> - Akses dini ke artikel terbaru sebelum publik</li>
                                <li><strong>UI halaman utama</strong> - Mendapatkan UI halaman utama premium</li>
                                <li><strong>No Ads</strong> - Pengalaman membaca tanpa iklan</li>
                            </ul>
                        </div>
                        
                        <?php if ($is_logged_in): ?>
                        <p style="font-size: 16px; color: #666; margin-bottom: 20px;">
                            <i class="fas fa-user-circle"></i> Login sebagai: <strong><?php echo htmlspecialchars($username); ?></strong> 
                            (Role: <span style="color: #FF8C00;"><?php echo htmlspecialchars($user_role); ?></span>)
                        </p>
                        <a href="https://saweria.co/SorotDunia" class="upgrade-btn">
                            <i class="fas fa-crown"></i> Upgrade ke Premium
                        </a>
                        <div class="login-prompt">
                            <p class="mb-0">
                                <i class="fa-solid fa-money-bill"></i>
                                UNTUK UPGRADE TEKAN TOMBOL UPGRADE KE PREMIUM MAKA AKAN MASUK KE HALAMAN SAWERIA
                            </p>
                            <strong>
                                <i class="fa-solid fa-circle-exclamation"></i>
                                DAN INGGAT TULIS USERNAME ATAU EMAIL AKUN YANG TELAH ANDA BUAT DI SOROT DUNIA
                            </strong>
                        </div>
                        <?php else: ?>
                        <a href="#" class="upgrade-btn" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="fas fa-sign-in-alt"></i> Login untuk Lanjutkan
                        </a>
                        <div class="login-prompt">
                            <p class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Belum punya akun? <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal">Daftar sekarang</a> dan upgrade ke premium untuk menikmati fitur Eksklusif
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php else: ?>
                    <!-- ===== UNLOCKED CONTENT ===== -->
                    <?php if ($is_premium_content && $can_access_premium): ?>
                    <div class="premium-access-badge">
                        <i class="fas fa-check-circle"></i>
                        <strong>Selamat!</strong> Anda memiliki akses penuh ke konten premium ini sebagai member <strong><?php echo strtoupper($user_role); ?></strong>
                    </div>
                    <?php endif; ?>
                    
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

                <!-- ========================================
                     COMMENT SECTION - DENGAN PAGINATION
                     ======================================== -->
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
                        Silakan <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="alert-link">login</a> untuk menulis komentar.
                    </div>
                    <?php endif; ?>

                    <!-- Comments List Container - Will be loaded via AJAX -->
                    <div class="comments-list">
                        <!-- Initial loading indicator -->
                        <div class="text-center my-4" id="initial-loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Memuat komentar...</p>
                        </div>
                    </div>
                    
                    <!-- Pagination Container -->
                    <div id="comment-pagination" class="comment-pagination" style="display: none;">
                        <!-- Pagination will be rendered here by JavaScript -->
                    </div>
                </div>
                <!-- END COMMENT SECTION -->
            </div>

            <div class="col-lg-4">
                <!-- Artikel Terkait -->
                <?php if ($related_result && mysqli_num_rows($related_result) > 0): ?>
                <div class="sidebar">
                    <div class="sidebar-header text-white">
                        <i class="fas fa-newspaper me-2"></i>Artikel Terkait
                    </div>
                    <div class="sidebar-content">
                        <?php while ($related = mysqli_fetch_assoc($related_result)): ?>
                        <?php
                        $related_url = !empty($related['slug']) 
                            ? 'artikel.php?slug=' . urlencode($related['slug'])
                            : 'artikel.php?id=' . $related['article_id'];
                        
                        $related_is_premium = (isset($related['post_status']) && strtolower($related['post_status']) === 'premium');
                        ?>
                        <div class="related-item mb-3 pb-3 border-bottom">
                            <?php if ($related_is_premium): ?>
                            <span class="premium-badge-small">
                                <i class="fas fa-crown"></i> PREMIUM
                            </span>
                            <?php endif; ?>
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

                <!-- Berita Terpopuler -->
                <?php if ($popular_result && mysqli_num_rows($popular_result) > 0): ?>
                <div class="sidebar mt-3">
                    <div class="sidebar-header text-white">
                        <i class="fas fa-fire me-2"></i>Berita Terpopuler
                    </div>
                    <div class="sidebar-content p-3">
                        <?php 
                        $counter = 1;
                        while ($popular = mysqli_fetch_assoc($popular_result)): 
                            $popular_url = !empty($popular['slug']) 
                                ? 'artikel.php?slug=' . urlencode($popular['slug'])
                                : 'artikel.php?id=' . $popular['article_id'];
                            
                            $is_current = ((int)$popular['article_id'] === $current_article_id);
                            $popular_is_premium = (isset($popular['post_status']) && strtolower($popular['post_status']) === 'premium');
                        ?>
                        <div class="trending-item mb-3 pb-3 <?php echo $counter < 5 ? 'border-bottom' : ''; ?> <?php echo $is_current ? 'bg-light' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="trending-number bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" 
                                     style="width: 35px; height: 35px; font-weight: bold;">
                                    <?php echo $counter; ?>
                                </div>
                                <div class="trending-content flex-grow-1">
                                    <?php if ($popular_is_premium): ?>
                                    <span class="premium-badge-small">
                                        <i class="fas fa-crown"></i> PREMIUM
                                    </span>
                                    <?php endif; ?>
                                    
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require 'footer.php'; ?>
    
    <!-- SCRIPT KHUSUS UNTUK HANDLE LOGIN MODAL -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle semua link login untuk membuka modal
        document.querySelectorAll('a[href="#"][data-bs-toggle="modal"][data-bs-target="#loginModal"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            });
        });
        
        // Handle semua link register untuk membuka modal
        document.querySelectorAll('a[href="#"][data-bs-toggle="modal"][data-bs-target="#registerModal"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
            });
        });
    });
    </script>
</body>
</html>