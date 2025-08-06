<?php
// article_reactions.php
require_once 'config/config.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function untuk mendapatkan jumlah reaksi artikel
function getArticleReactions($article_id) {
    global $koneksi;
    $article_id = (int)$article_id;
    
    $query = "SELECT 
                reaction_type,
                COUNT(*) as count
              FROM article_reactions 
              WHERE article_id = $article_id 
              GROUP BY reaction_type";
    
    $result = mysqli_query($koneksi, $query);
    
    $reactions = [
        'like' => 0,
        'dislike' => 0
    ];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $reactions[$row['reaction_type']] = (int)$row['count'];
        }
    }
    
    return $reactions;
}

// Function untuk mendapatkan reaksi user terhadap artikel
function getUserReaction($article_id, $user_id) {
    global $koneksi;
    $article_id = (int)$article_id;
    $user_id = (int)$user_id;
    
    $query = "SELECT reaction_type FROM article_reactions 
              WHERE article_id = $article_id AND user_id = $user_id";
    
    $result = mysqli_query($koneksi, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['reaction_type'];
    }
    
    return null;
}

// Function untuk menambah atau mengupdate reaksi
function addOrUpdateReaction($article_id, $user_id, $reaction_type) {
    global $koneksi;
    $article_id = (int)$article_id;
    $user_id = (int)$user_id;
    $reaction_type = sanitize_input($reaction_type);
    
    // Cek apakah user sudah memberikan reaksi sebelumnya
    $existing_reaction = getUserReaction($article_id, $user_id);
    
    if ($existing_reaction) {
        if ($existing_reaction === $reaction_type) {
            // Jika reaksi sama, hapus reaksi (toggle off)
            $query = "DELETE FROM article_reactions 
                      WHERE article_id = $article_id AND user_id = $user_id";
            $result = mysqli_query($koneksi, $query);
            return $result ? 'removed' : false;
        } else {
            // Jika reaksi berbeda, update reaksi
            $query = "UPDATE article_reactions 
                      SET reaction_type = '$reaction_type', reacted_at = NOW() 
                      WHERE article_id = $article_id AND user_id = $user_id";
            $result = mysqli_query($koneksi, $query);
            return $result ? 'updated' : false;
        }
    } else {
        // Jika belum ada reaksi, tambah reaksi baru
        $query = "INSERT INTO article_reactions (article_id, user_id, reaction_type, reacted_at) 
                  VALUES ($article_id, $user_id, '$reaction_type', NOW())";
        $result = mysqli_query($koneksi, $query);
        return $result ? 'added' : false;
    }
}

// Handle AJAX request untuk reaksi
if (isset($_POST['action']) && $_POST['action'] === 'react') {
    header('Content-Type: application/json');
    
    // Enable error reporting for debugging
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    // Debug: Log request
    error_log("Reaction request received: " . json_encode($_POST));
    error_log("Session data: " . json_encode($_SESSION));
    
    // Cek apakah user sudah login
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode([
            'success' => false,
            'message' => 'Anda harus login untuk memberikan reaksi'
        ]);
        exit();
    }
    
    // Validasi input
    $article_id = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
    $reaction_type = isset($_POST['reaction_type']) ? trim($_POST['reaction_type']) : '';
    $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    
    // Log untuk debug
    error_log("Article ID: " . $article_id);
    error_log("Reaction Type: " . $reaction_type);
    error_log("User ID: " . $user_id);
    
    if ($article_id <= 0 || empty($reaction_type) || $user_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak lengkap',
            'debug' => [
                'article_id' => $article_id,
                'reaction_type' => $reaction_type,
                'user_id' => $user_id,
                'session_logged_in' => $_SESSION['logged_in'] ?? 'not_set',
                'session_keys' => array_keys($_SESSION)
            ]
        ]);
        exit();
    }
    
    if (!in_array($reaction_type, ['like', 'dislike'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Tipe reaksi tidak valid'
        ]);
        exit();
    }
    
    // Cek apakah artikel ada dan published
    $check_article = "SELECT article_id FROM articles WHERE article_id = $article_id AND article_status = 'published'";
    $check_result = mysqli_query($koneksi, $check_article);
    
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Artikel tidak ditemukan atau tidak dipublikasikan'
        ]);
        exit();
    }
    
    $result = addOrUpdateReaction($article_id, $user_id, $reaction_type);
    
    if ($result) {
        $reactions = getArticleReactions($article_id);
        $user_reaction = getUserReaction($article_id, $user_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Reaksi berhasil disimpan',
            'action' => $result,
            'reactions' => $reactions,
            'user_reaction' => $user_reaction
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan reaksi'
        ]);
    }
    exit();
}

// Handle AJAX request untuk mendapatkan reaksi artikel
if (isset($_GET['action']) && $_GET['action'] === 'get_reactions') {
    header('Content-Type: application/json');
    
    $article_id = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;
    $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    
    if ($article_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Article ID tidak valid'
        ]);
        exit();
    }
    
    // Cek apakah artikel ada dan published
    $check_article = "SELECT article_id FROM articles WHERE article_id = $article_id AND article_status = 'published'";
    $check_result = mysqli_query($koneksi, $check_article);
    
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Artikel tidak ditemukan atau tidak dipublikasikan'
        ]);
        exit();
    }
    
    $reactions = getArticleReactions($article_id);
    $user_reaction = $user_id > 0 ? getUserReaction($article_id, $user_id) : null;
    
    echo json_encode([
        'success' => true,
        'reactions' => $reactions,
        'user_reaction' => $user_reaction
    ]);
    exit();
}

// Function untuk menampilkan komponen reaksi artikel
function displayArticleReactions($article_id) {
    $reactions = getArticleReactions($article_id);
    $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    $user_reaction = $user_id > 0 ? getUserReaction($article_id, $user_id) : null;
    $is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    
    echo '
    <div class="article-reactions" data-article-id="' . $article_id . '">
        <div class="reaction-buttons">
            <button class="reaction-btn like-btn ' . ($user_reaction === 'like' ? 'active' : '') . '" 
                    data-reaction="like" ' . ($is_logged_in ? '' : 'disabled') . '>
                <i class="fas fa-thumbs-up"></i>
                <span class="like-count">' . $reactions['like'] . '</span>
            </button>
            <button class="reaction-btn dislike-btn ' . ($user_reaction === 'dislike' ? 'active' : '') . '" 
                    data-reaction="dislike" ' . ($is_logged_in ? '' : 'disabled') . '>
                <i class="fas fa-thumbs-down"></i>
                <span class="dislike-count">' . $reactions['dislike'] . '</span>
            </button>
        </div>
        ' . (!$is_logged_in ? '<p class="login-message">Silakan <a href="login.php">login</a> untuk memberikan reaksi</p>' : '') . '
    </div>';
}
?>