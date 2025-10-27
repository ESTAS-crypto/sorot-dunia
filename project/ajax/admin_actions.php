<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// ===== CRITICAL FIX: Validate Image ID =====
function validateImageExists($image_id, $koneksi) {
    if (empty($image_id) || $image_id <= 0) {
        return false;
    }
    
    $check_query = "SELECT id FROM images WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $check_query);
    
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $image_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $validated_id);
    
    $exists = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    return $exists;
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/image_config.php';
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendResponse(false, 'User not logged in');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method');
    }
    
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!isset($data['csrf_token']) || empty($data['csrf_token'])) {
        sendResponse(false, 'Invalid CSRF token');
    }
    
    $action = $data['action'] ?? '';
    $article_id = (int)($data['article_id'] ?? 0);
    
    if (empty($action) || $article_id <= 0) {
        sendResponse(false, 'Invalid parameters');
    }
    
    mysqli_begin_transaction($koneksi);
    
    try {
        // Get article info
        $article_query = "SELECT a.*, a.article_status as current_status, 
                         i.filename as image_filename, i.id as image_id, i.url as current_url
                         FROM articles a 
                         LEFT JOIN images i ON a.featured_image_id = i.id 
                         WHERE a.article_id = ?";
        $stmt = mysqli_prepare($koneksi, $article_query);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $article = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$article) {
            throw new Exception('Article not found');
        }
        
        error_log("=== ADMIN ACTION START ===");
        error_log("Action: " . $action);
        error_log("Article ID: " . $article_id);
        error_log("Current Status: " . $article['current_status']);
        error_log("User Role: " . $_SESSION['user_role']);
        error_log("Image ID: " . ($article['image_id'] ?? 'NULL'));
        
        switch ($action) {
            case 'approve':
                if ($_SESSION['user_role'] !== 'admin') {
                    throw new Exception('Access denied. Admin only.');
                }
                
                $post_status = isset($data['post_status']) ? $data['post_status'] : 'Free';
                
                if (!in_array($post_status, ['Free', 'Premium'])) {
                    $post_status = 'Free';
                }
                
                error_log("Post Status: " . $post_status);
                
                // ===== CRITICAL FIX: Validate image before approval =====
                $featured_image_id = $article['image_id'];
                
                if ($featured_image_id) {
                    $image_exists = validateImageExists($featured_image_id, $koneksi);
                    
                    if (!$image_exists) {
                        error_log("✗ WARNING: Featured image ID {$featured_image_id} does not exist!");
                        error_log("→ Setting featured_image_id to NULL to prevent FK violation");
                        
                        // Clear invalid image reference
                        $clear_image = "UPDATE articles SET featured_image_id = NULL WHERE article_id = ?";
                        $clear_stmt = mysqli_prepare($koneksi, $clear_image);
                        mysqli_stmt_bind_param($clear_stmt, "i", $article_id);
                        mysqli_stmt_execute($clear_stmt);
                        mysqli_stmt_close($clear_stmt);
                        
                        $featured_image_id = null;
                    } else {
                        error_log("✓ Featured image ID {$featured_image_id} validated");
                    }
                }
                
                // Approve article
                $update_query = "UPDATE articles SET 
                                article_status = 'published',
                                post_status = ?,
                                approved_by = ?,
                                approved_date = NOW(),
                                rejected_by = NULL,
                                rejected_date = NULL,
                                rejection_reason = NULL
                                WHERE article_id = ?";
                
                $stmt = mysqli_prepare($koneksi, $update_query);
                mysqli_stmt_bind_param($stmt, "sii", $post_status, $_SESSION['user_id'], $article_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to approve article');
                }
                mysqli_stmt_close($stmt);
                
                // Move image to published folder
                if (!empty($article['image_filename']) && $featured_image_id) {
                    try {
                        $folders = ['draft', 'pending', 'rejected'];
                        $moved = false;
                        
                        foreach ($folders as $folder) {
                            $check_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $article['image_filename'];
                            if (file_exists($check_path)) {
                                error_log("Found image in {$folder}, moving to published");
                                
                                $move_result = moveArticleImageWithDbUpdate(
                                    $article['image_filename'], 
                                    $folder, 
                                    'published',
                                    $koneksi
                                );
                                
                                if ($move_result && isset($move_result['success']) && $move_result['success']) {
                                    error_log("✓ Image moved successfully");
                                    $moved = true;
                                    break;
                                }
                            }
                        }
                        
                        if (!$moved) {
                            $check_published = __DIR__ . '/../uploads/articles/published/' . $article['image_filename'];
                            if (file_exists($check_published)) {
                                error_log("Image already in published folder");
                                
                                $correct_url = 'https://inievan.my.id/project/uploads/articles/published/' . $article['image_filename'];
                                if ($article['current_url'] !== $correct_url) {
                                    $update_url = "UPDATE images SET url = ? WHERE filename = ? AND source_type = 'article'";
                                    $url_stmt = mysqli_prepare($koneksi, $update_url);
                                    mysqli_stmt_bind_param($url_stmt, "ss", $correct_url, $article['image_filename']);
                                    mysqli_stmt_execute($url_stmt);
                                    mysqli_stmt_close($url_stmt);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Image move error on approve: ' . $e->getMessage());
                    }
                }
                
                $message = "Artikel berhasil diapprove dan dipublikasikan sebagai {$post_status}";
                break;
                
            case 'reject':
                if ($_SESSION['user_role'] !== 'admin') {
                    throw new Exception('Access denied. Admin only.');
                }
                
                $reason = $data['reason'] ?? 'Tidak memenuhi standar konten';
                
                $update_query = "UPDATE articles SET 
                                article_status = 'rejected',
                                rejected_by = ?,
                                rejected_date = NOW(),
                                rejection_reason = ?,
                                approved_by = NULL,
                                approved_date = NULL
                                WHERE article_id = ?";
                
                $stmt = mysqli_prepare($koneksi, $update_query);
                mysqli_stmt_bind_param($stmt, "isi", $_SESSION['user_id'], $reason, $article_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to reject article');
                }
                mysqli_stmt_close($stmt);
                
                // Move image to rejected folder
                if (!empty($article['image_filename']) && $article['image_id']) {
                    try {
                        $folders = ['draft', 'pending', 'published'];
                        $moved = false;
                        
                        foreach ($folders as $folder) {
                            $check_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $article['image_filename'];
                            if (file_exists($check_path)) {
                                error_log("Found image in {$folder}, moving to rejected");
                                
                                $move_result = moveArticleImageWithDbUpdate(
                                    $article['image_filename'], 
                                    $folder, 
                                    'rejected',
                                    $koneksi
                                );
                                
                                if ($move_result && isset($move_result['success']) && $move_result['success']) {
                                    error_log("✓ Image moved successfully");
                                    $moved = true;
                                    break;
                                }
                            }
                        }
                        
                        if (!$moved) {
                            $check_rejected = __DIR__ . '/../uploads/articles/rejected/' . $article['image_filename'];
                            if (file_exists($check_rejected)) {
                                error_log("Image already in rejected folder");
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Image move error on reject: ' . $e->getMessage());
                    }
                }
                
                $message = 'Article rejected successfully';
                break;
                
            case 'delete':
                error_log("=== DELETE ARTICLE PROCESS START ===");
                
                // Permission check
                if ($_SESSION['user_role'] !== 'admin') {
                    if ($article['author_id'] != $_SESSION['user_id']) {
                        throw new Exception('Anda tidak memiliki izin untuk menghapus artikel ini');
                    }
                    
                    $allowed_statuses = ['draft', 'pending'];
                    if (!in_array($article['current_status'], $allowed_statuses)) {
                        throw new Exception('Anda hanya bisa menghapus artikel dengan status draft atau pending');
                    }
                }
                
                $image_filename = $article['image_filename'];
                $image_id = $article['image_id'];
                
                error_log("Image ID to delete: " . ($image_id ?? 'NULL'));
                
                // ===== CRITICAL FIX: Delete in correct order =====
                // 1. DELETE IMAGE FIRST (before article to avoid FK constraint)
                if ($image_id && !empty($image_filename)) {
                    error_log("=== DELETING IMAGE ===");
                    
                    // Delete file from folder
                    $folders = ['draft', 'pending', 'published', 'rejected'];
                    $file_deleted = false;
                    
                    foreach ($folders as $folder) {
                        $image_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $image_filename;
                        
                        if (file_exists($image_path)) {
                            if (unlink($image_path)) {
                                error_log("✓ Image file deleted from {$folder}");
                                $file_deleted = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$file_deleted) {
                        error_log("⚠ WARNING: Image file not found");
                    }
                    
                    // Delete from database
                    $delete_image_query = "DELETE FROM images WHERE id = ?";
                    $stmt = mysqli_prepare($koneksi, $delete_image_query);
                    mysqli_stmt_bind_param($stmt, "i", $image_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        error_log("✓ Image record deleted from database");
                    } else {
                        error_log("✗ Failed to delete image: " . mysqli_error($koneksi));
                    }
                    mysqli_stmt_close($stmt);
                }
                
                // 2. DELETE RELATED DATA
                $delete_queries = [
                    "DELETE FROM article_tags WHERE article_id = ?",
                    "DELETE FROM comments WHERE article_id = ?",
                    "DELETE FROM article_reactions WHERE article_id = ?",
                    "DELETE FROM slugs WHERE type = 'article' AND related_id = ?"
                ];
                
                foreach ($delete_queries as $query) {
                    $stmt = mysqli_prepare($koneksi, $query);
                    mysqli_stmt_bind_param($stmt, "i", $article_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
                
                // 3. DELETE ARTICLE
                $delete_article_query = "DELETE FROM articles WHERE article_id = ?";
                $stmt = mysqli_prepare($koneksi, $delete_article_query);
                mysqli_stmt_bind_param($stmt, "i", $article_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to delete article: ' . mysqli_error($koneksi));
                }
                
                mysqli_stmt_close($stmt);
                
                error_log("=== DELETE ARTICLE COMPLETE ===");
                
                $message = 'Artikel berhasil dihapus beserta gambarnya';
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        mysqli_commit($koneksi);
        error_log("=== ADMIN ACTION SUCCESS ===");
        
        sendResponse(true, $message, [
            'article_id' => $article_id,
            'action' => $action,
            'post_status' => isset($post_status) ? $post_status : null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        error_log("Transaction rollback: " . $e->getMessage());
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Admin action error: " . $e->getMessage());
    sendResponse(false, $e->getMessage());
}
?>