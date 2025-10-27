<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/image_config.php';
    
    // Check authentication
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendResponse(false, 'User not logged in');
    }
    
    // Only admin can bulk delete
    if ($_SESSION['user_role'] !== 'admin') {
        sendResponse(false, 'Access denied. Admin only.');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method');
    }
    
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!isset($data['csrf_token']) || empty($data['csrf_token'])) {
        sendResponse(false, 'Invalid CSRF token');
    }
    
    $status = $data['status'] ?? '';
    $days_old = (int)($data['days_old'] ?? 7);
    
    // Validate status
    $valid_statuses = ['pending', 'draft', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        sendResponse(false, 'Invalid status');
    }
    
    error_log("=== BULK DELETE START ===");
    error_log("Status: " . $status);
    error_log("Days old: " . $days_old);
    error_log("User: " . $_SESSION['username']);
    
    // Get articles older than specified days
    $query = "SELECT a.article_id, a.title, a.featured_image_id, a.publication_date,
              i.filename as image_filename, i.url as image_url
              FROM articles a 
              LEFT JOIN images i ON a.featured_image_id = i.id
              WHERE a.article_status = ? 
              AND DATEDIFF(NOW(), a.publication_date) > ?";
    
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "si", $status, $days_old);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $articles_to_delete = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $articles_to_delete[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    $total_found = count($articles_to_delete);
    
    if ($total_found === 0) {
        sendResponse(true, "Tidak ada artikel {$status} yang lebih dari {$days_old} hari.", [
            'deleted_count' => 0,
            'skipped_count' => 0
        ]);
    }
    
    error_log("Found {$total_found} articles to delete");
    
    // Begin transaction
    mysqli_begin_transaction($koneksi);
    
    $deleted_count = 0;
    $failed_count = 0;
    
    try {
        foreach ($articles_to_delete as $article) {
            $article_id = $article['article_id'];
            $image_filename = $article['image_filename'];
            $image_id = $article['featured_image_id'];
            
            error_log("Processing article ID: {$article_id}");
            
            // Delete image file if exists
            if ($image_id && !empty($image_filename)) {
                $folders = ['draft', 'pending', 'published', 'rejected'];
                
                foreach ($folders as $folder) {
                    $image_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $image_filename;
                    
                    if (file_exists($image_path)) {
                        if (unlink($image_path)) {
                            error_log("✓ Deleted image file from {$folder}: {$image_filename}");
                        } else {
                            error_log("✗ Failed to delete image file from {$folder}");
                        }
                        break;
                    }
                }
                
                // Delete image record from database
                $delete_image_query = "DELETE FROM images WHERE id = ?";
                $img_stmt = mysqli_prepare($koneksi, $delete_image_query);
                mysqli_stmt_bind_param($img_stmt, "i", $image_id);
                mysqli_stmt_execute($img_stmt);
                mysqli_stmt_close($img_stmt);
            }
            
            // Delete related data
            $delete_queries = [
                "DELETE FROM article_tags WHERE article_id = ?",
                "DELETE FROM comments WHERE article_id = ?",
                "DELETE FROM article_reactions WHERE article_id = ?",
                "DELETE FROM slugs WHERE type = 'article' AND related_id = ?"
            ];
            
            foreach ($delete_queries as $query) {
                $del_stmt = mysqli_prepare($koneksi, $query);
                mysqli_stmt_bind_param($del_stmt, "i", $article_id);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);
            }
            
            // Delete article
            $delete_article_query = "DELETE FROM articles WHERE article_id = ?";
            $art_stmt = mysqli_prepare($koneksi, $delete_article_query);
            mysqli_stmt_bind_param($art_stmt, "i", $article_id);
            
            if (mysqli_stmt_execute($art_stmt)) {
                $deleted_count++;
                error_log("✓ Article {$article_id} deleted successfully");
            } else {
                $failed_count++;
                error_log("✗ Failed to delete article {$article_id}");
            }
            mysqli_stmt_close($art_stmt);
        }
        
        // Commit transaction
        mysqli_commit($koneksi);
        
        error_log("=== BULK DELETE COMPLETE ===");
        error_log("Deleted: {$deleted_count}");
        error_log("Failed: {$failed_count}");
        
        $message = "{$deleted_count} artikel {$status} berhasil dihapus.";
        if ($failed_count > 0) {
            $message .= " {$failed_count} artikel gagal dihapus.";
        }
        
        sendResponse(true, $message, [
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count,
            'total_processed' => $total_found
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        error_log("Transaction rollback: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Bulk delete error: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>