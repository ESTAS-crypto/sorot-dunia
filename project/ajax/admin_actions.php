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
        // Get article info including image
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
        error_log("User ID: " . $_SESSION['user_id']);
        error_log("Author ID: " . $article['author_id']);
        
        // ===== VALIDASI PERMISSION BERDASARKAN ACTION =====
        
        switch ($action) {
            case 'approve':
                // Hanya admin yang bisa approve
                if ($_SESSION['user_role'] !== 'admin') {
                    throw new Exception('Access denied. Admin only.');
                }
                
                // Change status to published
                $update_query = "UPDATE articles SET 
                                article_status = 'published',
                                approved_by = ?,
                                approved_date = NOW(),
                                rejected_by = NULL,
                                rejected_date = NULL,
                                rejection_reason = NULL
                                WHERE article_id = ?";
                
                $stmt = mysqli_prepare($koneksi, $update_query);
                mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $article_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to approve article');
                }
                mysqli_stmt_close($stmt);
                
                // Move image to published folder WITH DATABASE UPDATE
                if (!empty($article['image_filename'])) {
                    try {
                        $folders = ['draft', 'pending', 'rejected'];
                        $moved = false;
                        
                        foreach ($folders as $folder) {
                            $check_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $article['image_filename'];
                            if (file_exists($check_path)) {
                                error_log("Found image in {$folder}, moving to published WITH DB UPDATE");
                                
                                $move_result = moveArticleImageWithDbUpdate(
                                    $article['image_filename'], 
                                    $folder, 
                                    'published',
                                    $koneksi
                                );
                                
                                if ($move_result && isset($move_result['success']) && $move_result['success']) {
                                    error_log("✓ Successfully moved image from {$folder} to published");
                                    error_log("✓ Database URL updated to: " . $move_result['new_url']);
                                    $moved = true;
                                    break;
                                } else {
                                    error_log("✗ Failed to move image from {$folder}");
                                }
                            }
                        }
                        
                        if (!$moved) {
                            // Check if already in published
                            $check_published = __DIR__ . '/../uploads/articles/published/' . $article['image_filename'];
                            if (file_exists($check_published)) {
                                error_log("Image already in published folder");
                                
                                // Ensure database URL is correct
                                $correct_url = 'https://inievan.my.id/project/uploads/articles/published/' . $article['image_filename'];
                                if ($article['current_url'] !== $correct_url) {
                                    error_log("Correcting database URL to: " . $correct_url);
                                    $update_url = "UPDATE images SET url = ? WHERE filename = ? AND source_type = 'article'";
                                    $url_stmt = mysqli_prepare($koneksi, $update_url);
                                    mysqli_stmt_bind_param($url_stmt, "ss", $correct_url, $article['image_filename']);
                                    mysqli_stmt_execute($url_stmt);
                                    mysqli_stmt_close($url_stmt);
                                }
                            } else {
                                error_log("WARNING: Image not found in any folder");
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Image move error on approve: ' . $e->getMessage());
                        // Don't throw - allow article approval to succeed even if image move fails
                    }
                }
                
                $message = 'Article approved and published successfully';
                break;
                
            case 'reject':
                // Hanya admin yang bisa reject
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
                
                // Move image to rejected folder WITH DATABASE UPDATE
                if (!empty($article['image_filename'])) {
                    try {
                        $folders = ['draft', 'pending', 'published'];
                        $moved = false;
                        
                        foreach ($folders as $folder) {
                            $check_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $article['image_filename'];
                            if (file_exists($check_path)) {
                                error_log("Found image in {$folder}, moving to rejected WITH DB UPDATE");
                                
                                $move_result = moveArticleImageWithDbUpdate(
                                    $article['image_filename'], 
                                    $folder, 
                                    'rejected',
                                    $koneksi
                                );
                                
                                if ($move_result && isset($move_result['success']) && $move_result['success']) {
                                    error_log("✓ Successfully moved image from {$folder} to rejected");
                                    error_log("✓ Database URL updated to: " . $move_result['new_url']);
                                    $moved = true;
                                    break;
                                } else {
                                    error_log("✗ Failed to move image from {$folder}");
                                }
                            }
                        }
                        
                        if (!$moved) {
                            $check_rejected = __DIR__ . '/../uploads/articles/rejected/' . $article['image_filename'];
                            if (file_exists($check_rejected)) {
                                error_log("Image already in rejected folder");
                                
                                $correct_url = 'https://inievan.my.id/project/uploads/articles/rejected/' . $article['image_filename'];
                                if ($article['current_url'] !== $correct_url) {
                                    error_log("Correcting database URL to: " . $correct_url);
                                    $update_url = "UPDATE images SET url = ? WHERE filename = ? AND source_type = 'article'";
                                    $url_stmt = mysqli_prepare($koneksi, $update_url);
                                    mysqli_stmt_bind_param($url_stmt, "ss", $correct_url, $article['image_filename']);
                                    mysqli_stmt_execute($url_stmt);
                                    mysqli_stmt_close($url_stmt);
                                }
                            } else {
                                error_log("WARNING: Image not found in any folder");
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
                
                // ===== VALIDASI PERMISSION DELETE =====
                if ($_SESSION['user_role'] !== 'admin') {
                    // Penulis harus memiliki artikel
                    if ($article['author_id'] != $_SESSION['user_id']) {
                        error_log("DELETE DENIED: User is not the author");
                        throw new Exception('Anda tidak memiliki izin untuk menghapus artikel ini');
                    }
                    
                    // Penulis hanya bisa delete draft atau pending
                    $allowed_statuses = ['draft', 'pending'];
                    if (!in_array($article['current_status'], $allowed_statuses)) {
                        error_log("DELETE DENIED: Status is " . $article['current_status']);
                        error_log("Allowed statuses for penulis: " . implode(', ', $allowed_statuses));
                        throw new Exception('Anda hanya bisa menghapus artikel dengan status draft atau pending. Artikel dengan status "' . $article['current_status'] . '" tidak dapat dihapus.');
                    }
                    
                    error_log("DELETE ALLOWED: Penulis deleting own " . $article['current_status'] . " article");
                } else {
                    error_log("DELETE ALLOWED: Admin can delete any article");
                }
                
                $image_filename = $article['image_filename'];
                $image_id = $article['image_id'];
                
                // Step 1: Delete related data
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
                    error_log("Deleted " . mysqli_stmt_affected_rows($stmt) . " rows from related table");
                    mysqli_stmt_close($stmt);
                }
                
                // Step 2: Delete image record
                if ($image_id) {
                    $delete_image_query = "DELETE FROM images WHERE id = ?";
                    $stmt = mysqli_prepare($koneksi, $delete_image_query);
                    mysqli_stmt_bind_param($stmt, "i", $image_id);
                    mysqli_stmt_execute($stmt);
                    error_log("Deleted image record from database");
                    mysqli_stmt_close($stmt);
                }
                
                // Step 3: Delete article
                $delete_article_query = "DELETE FROM articles WHERE article_id = ?";
                $stmt = mysqli_prepare($koneksi, $delete_article_query);
                mysqli_stmt_bind_param($stmt, "i", $article_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to delete article: ' . mysqli_error($koneksi));
                }
                
                error_log("Article deleted, affected rows: " . mysqli_stmt_affected_rows($stmt));
                mysqli_stmt_close($stmt);
                
                // Step 4: Delete image file
                if (!empty($image_filename)) {
                    $folders = ['draft', 'pending', 'published', 'rejected'];
                    $deleted_file = false;
                    
                    foreach ($folders as $folder) {
                        $image_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $image_filename;
                        
                        if (file_exists($image_path)) {
                            if (unlink($image_path)) {
                                error_log("✓ Deleted image file from {$folder} folder");
                                $deleted_file = true;
                                break;
                            } else {
                                error_log("✗ Failed to delete image file from {$folder} folder");
                            }
                        }
                    }
                    
                    if (!$deleted_file) {
                        error_log("WARNING: Image file not found in any folder");
                    }
                }
                
                error_log("=== DELETE ARTICLE PROCESS COMPLETE ===");
                
                $message = 'Article deleted successfully';
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        mysqli_commit($koneksi);
        
        error_log("=== ADMIN ACTION SUCCESS ===");
        error_log("Database transaction committed");
        
        sendResponse(true, $message, [
            'article_id' => $article_id,
            'action' => $action,
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