<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include config
require_once 'config/config.php';

try {
    if (!$koneksi) {
        throw new Exception('Database connection failed');
    }

    // Get count of published articles
    $query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
    $result = mysqli_query($koneksi, $query);
    
    if ($result) {
        $data = mysqli_fetch_assoc($result);
        $total_articles = (int)$data['total'];
        
        // Get latest article info untuk preview
        $latest_query = "SELECT a.article_id, a.title, a.publication_date, 
                               u.username as author_name
                        FROM articles a
                        LEFT JOIN users u ON a.author_id = u.id
                        WHERE a.article_status = 'published' 
                        ORDER BY a.publication_date DESC 
                        LIMIT 1";
        
        $latest_result = mysqli_query($koneksi, $latest_query);
        $latest_article = null;
        
        if ($latest_result && mysqli_num_rows($latest_result) > 0) {
            $latest_article = mysqli_fetch_assoc($latest_result);
        }
        
        // Return simple response
        echo json_encode([
            'success' => true,
            'count' => $total_articles,
            'latest_article' => $latest_article,
            'timestamp' => time(),
            'formatted_time' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        throw new Exception('Query failed: ' . mysqli_error($koneksi));
    }

} catch (Exception $e) {
    error_log("update_artikel.php error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check articles',
        'count' => 0,
        'message' => 'Terjadi kesalahan saat memeriksa artikel terbaru'
    ]);
}
?>