<?php
// ajax/load_comments.php - Load komentar dengan pagination
session_start();
require_once '../config/config.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Validasi request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Ambil dan validasi parameter
$article_id = isset($_GET['article_id']) ? (int)$_GET['article_id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? min(50, max(5, (int)$_GET['per_page'])) : 10; // Max 50, min 5, default 10

// Log untuk debugging
error_log("load_comments.php - article_id: $article_id, page: $page, per_page: $per_page");

// Validasi article_id
if ($article_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid article ID'
    ]);
    exit();
}

// Cek apakah artikel exists dan published
$check_article = "SELECT article_id FROM articles 
                  WHERE article_id = $article_id 
                  AND article_status = 'published' 
                  LIMIT 1";
$check_result = mysqli_query($koneksi, $check_article);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Article not found'
    ]);
    exit();
}

// Hitung total komentar
$count_query = "SELECT COUNT(*) as total 
                FROM comments 
                WHERE article_id = $article_id";
$count_result = mysqli_query($koneksi, $count_query);
$total_comments = 0;

if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_comments = (int)$count_row['total'];
}

// Hitung offset untuk pagination
$offset = ($page - 1) * $per_page;

// Query komentar dengan LIMIT dan OFFSET
$comments_query = "SELECT 
                    c.comment_id,
                    c.content, 
                    c.created_at, 
                    u.full_name, 
                    u.username,
                    u.id as user_id
                   FROM comments c
                   INNER JOIN users u ON c.user_id = u.id
                   WHERE c.article_id = $article_id
                   ORDER BY c.created_at DESC
                   LIMIT $per_page OFFSET $offset";

$comments_result = mysqli_query($koneksi, $comments_query);

if (!$comments_result) {
    error_log("Query error: " . mysqli_error($koneksi));
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit();
}

// Format komentar ke array
$comments = [];
while ($comment = mysqli_fetch_assoc($comments_result)) {
    // Format tanggal yang user-friendly
    $created_at = strtotime($comment['created_at']);
    $now = time();
    $diff = $now - $created_at;
    
    // Hitung time ago
    if ($diff < 60) {
        $time_ago = 'Baru saja';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        $time_ago = $minutes . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        $time_ago = $hours . ' jam yang lalu';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        $time_ago = $days . ' hari yang lalu';
    } else {
        $time_ago = date('d M Y H:i', $created_at);
    }
    
    $comments[] = [
        'id' => (int)$comment['comment_id'],
        'user_name' => htmlspecialchars($comment['full_name'] ?: $comment['username']),
        'user_id' => (int)$comment['user_id'],
        'content' => htmlspecialchars($comment['content']),
        'created_at' => date('d M Y H:i', $created_at),
        'time_ago' => $time_ago,
        'created_timestamp' => $created_at
    ];
}

// Hitung apakah ada halaman berikutnya
$total_pages = ceil($total_comments / $per_page);
$has_more = $page < $total_pages;
$remaining = max(0, $total_comments - ($page * $per_page));

// Log hasil
error_log("Loaded " . count($comments) . " comments, page $page/$total_pages, has_more: " . ($has_more ? 'yes' : 'no'));

// Return response
echo json_encode([
    'success' => true,
    'comments' => $comments,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $per_page,
        'total_comments' => $total_comments,
        'total_pages' => $total_pages,
        'has_more' => $has_more,
        'remaining' => $remaining,
        'loaded' => count($comments)
    ]
]);
?>