<?php
// article_reactions.php - HANDLER LENGKAP UNTUK REACTIONS + MODAL LOGIN FIX
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function untuk mendapatkan jumlah reaksi artikel
function getArticleReactions($article_id) {
    global $koneksi;
    $article_id = (int)$article_id;
    
    $query = "SELECT 
                SUM(CASE WHEN reaction_type = 'like' THEN 1 ELSE 0 END) as like_count,
                SUM(CASE WHEN reaction_type = 'dislike' THEN 1 ELSE 0 END) as dislike_count
              FROM article_reactions 
              WHERE article_id = $article_id";
    
    $result = mysqli_query($koneksi, $query);
    
    $reactions = [
        'like' => 0,
        'dislike' => 0
    ];
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $reactions['like'] = (int)($row['like_count'] ?? 0);
        $reactions['dislike'] = (int)($row['dislike_count'] ?? 0);
    }
    
    return $reactions;
}

// Function untuk mendapatkan reaksi user
function getUserReaction($article_id, $user_id) {
    global $koneksi;
    $article_id = (int)$article_id;
    $user_id = (int)$user_id;
    
    if ($user_id <= 0) {
        return null;
    }
    
    $query = "SELECT reaction_type FROM article_reactions 
              WHERE article_id = $article_id AND user_id = $user_id
              LIMIT 1";
    
    $result = mysqli_query($koneksi, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['reaction_type'];
    }
    
    return null;
}

// Handle AJAX request untuk react (PERBAIKAN: action === 'react')
if (isset($_POST['action']) && $_POST['action'] === 'react') {
    header('Content-Type: application/json');
    
    // Cek login
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode([
            'success' => false,
            'message' => 'Anda harus login untuk memberikan reaksi',
            'require_login' => true
        ]);
        exit();
    }
    
    // Get user ID dengan berbagai kemungkinan
    $user_id = 0;
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
    } elseif (isset($_SESSION['id'])) {
        $user_id = (int)$_SESSION['id'];
    }
    
    // Validasi input
    $article_id = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;
    $reaction_type = isset($_POST['reaction_type']) ? trim($_POST['reaction_type']) : '';
    
    // Log untuk debugging
    error_log("Reaction request - user_id: $user_id, article_id: $article_id, type: $reaction_type");
    
    if ($article_id <= 0 || empty($reaction_type) || $user_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak lengkap',
            'require_login' => ($user_id <= 0),
            'debug' => [
                'article_id' => $article_id,
                'reaction_type' => $reaction_type,
                'user_id' => $user_id,
                'session_logged_in' => isset($_SESSION['logged_in']) ? $_SESSION['logged_in'] : 'not set',
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
    
    // Cek artikel exists dan published
    $check_article = "SELECT article_id FROM articles 
                      WHERE article_id = $article_id AND article_status = 'published'";
    $check_result = mysqli_query($koneksi, $check_article);
    
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Artikel tidak ditemukan'
        ]);
        exit();
    }
    
    // Cek existing reaction
    $existing_reaction = getUserReaction($article_id, $user_id);
    
    $action_performed = '';
    
    if ($existing_reaction) {
        if ($existing_reaction === $reaction_type) {
            // Toggle off - hapus reaction
            $delete_query = "DELETE FROM article_reactions 
                           WHERE article_id = $article_id AND user_id = $user_id";
            mysqli_query($koneksi, $delete_query);
            $user_reaction = null;
            $action_performed = 'removed';
            error_log("Reaction removed for user $user_id on article $article_id");
        } else {
            // Update ke reaction berbeda
            $update_query = "UPDATE article_reactions 
                           SET reaction_type = '$reaction_type', reacted_at = NOW() 
                           WHERE article_id = $article_id AND user_id = $user_id";
            mysqli_query($koneksi, $update_query);
            $user_reaction = $reaction_type;
            $action_performed = 'updated';
            error_log("Reaction updated to $reaction_type for user $user_id on article $article_id");
        }
    } else {
        // Insert reaction baru
        $insert_query = "INSERT INTO article_reactions (article_id, user_id, reaction_type, reacted_at) 
                        VALUES ($article_id, $user_id, '$reaction_type', NOW())";
        mysqli_query($koneksi, $insert_query);
        $user_reaction = $reaction_type;
        $action_performed = 'added';
        error_log("New reaction $reaction_type added for user $user_id on article $article_id");
    }
    
    // Get updated counts
    $reactions = getArticleReactions($article_id);
    
    echo json_encode([
        'success' => true,
        'action' => $action_performed,
        'reactions' => $reactions,
        'user_reaction' => $user_reaction,
        'message' => 'Reaksi berhasil diperbarui'
    ]);
    exit();
}

// Handle GET request untuk mendapatkan reactions
if (isset($_GET['action']) && $_GET['action'] === 'get_reactions') {
    header('Content-Type: application/json');
    
    $article_id = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;
    
    // Get user ID
    $user_id = 0;
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
    } elseif (isset($_SESSION['id'])) {
        $user_id = (int)$_SESSION['id'];
    }
    
    if ($article_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Article ID tidak valid'
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

// Function untuk display reactions (digunakan di artikel.php)
function displayArticleReactions($article_id) {
    $reactions = getArticleReactions($article_id);
    
    // Get user ID dengan berbagai kemungkinan
    $user_id = 0;
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
    } elseif (isset($_SESSION['id'])) {
        $user_id = (int)$_SESSION['id'];
    }
    
    $user_reaction = $user_id > 0 ? getUserReaction($article_id, $user_id) : null;
    $is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    
    // Get article slug for redirect
    global $koneksi;
    $slug_query = "SELECT GetArticleSlug($article_id) as slug";
    $slug_result = mysqli_query($koneksi, $slug_query);
    $article_slug = '';
    if ($slug_result && $slug_row = mysqli_fetch_assoc($slug_result)) {
        $article_slug = $slug_row['slug'];
    }
    
    echo '
    <div class="article-reactions" data-article-id="' . $article_id . '">
        <div class="reaction-title">Bagaimana pendapat Anda tentang artikel ini?</div>
        <div class="reaction-buttons">';
    
    if ($is_logged_in && $user_id > 0) {
        echo '
            <button class="reaction-btn like-btn ' . ($user_reaction === 'like' ? 'active' : '') . '" 
                    data-article-id="' . $article_id . '" 
                    data-reaction="like">
                <i class="fas fa-thumbs-up"></i>
                <span>Suka</span>
                <span class="reaction-count like-count">' . $reactions['like'] . '</span>
            </button>
            <button class="reaction-btn dislike-btn ' . ($user_reaction === 'dislike' ? 'active' : '') . '" 
                    data-article-id="' . $article_id . '" 
                    data-reaction="dislike">
                <i class="fas fa-thumbs-down"></i>
                <span>Tidak Suka</span>
                <span class="reaction-count dislike-count">' . $reactions['dislike'] . '</span>
            </button>';
    } else {
        // PERBAIKAN: Ganti href dengan data-bs-toggle untuk buka modal
        echo '
            <button class="reaction-btn like-btn" 
                    data-bs-toggle="modal" 
                    data-bs-target="#loginModal"
                    data-require-login="true">
                <i class="fas fa-thumbs-up"></i>
                <span>Suka</span>
                <span class="reaction-count">' . $reactions['like'] . '</span>
            </button>
            <button class="reaction-btn dislike-btn" 
                    data-bs-toggle="modal" 
                    data-bs-target="#loginModal"
                    data-require-login="true">
                <i class="fas fa-thumbs-down"></i>
                <span>Tidak Suka</span>
                <span class="reaction-count">' . $reactions['dislike'] . '</span>
            </button>';
    }
    
    echo '
        </div>
    </div>';
}
?>