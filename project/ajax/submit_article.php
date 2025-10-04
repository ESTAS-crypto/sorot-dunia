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

function generateUniqueSlug($title, $koneksi, $exclude_id = null) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 100);
    
    if (empty($slug)) {
        $slug = 'artikel-' . time();
    }
    
    $original_slug = $slug;
    $counter = 1;
    
    while (true) {
        if ($exclude_id) {
            $check_query = "SELECT COUNT(*) FROM slugs WHERE slug = ? AND type = 'article' AND related_id != ?";
            $stmt = mysqli_prepare($koneksi, $check_query);
            mysqli_stmt_bind_param($stmt, "si", $slug, $exclude_id);
        } else {
            $check_query = "SELECT COUNT(*) FROM slugs WHERE slug = ? AND type = 'article'";
            $stmt = mysqli_prepare($koneksi, $check_query);
            mysqli_stmt_bind_param($stmt, "s", $slug);
        }
        
        if (!$stmt) {
            throw new Exception('Database error in slug check: ' . mysqli_error($koneksi));
        }
        
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        if ($count == 0) break;
        
        $slug = $original_slug . '-' . $counter;
        $counter++;
        
        if ($counter > 1000) {
            $slug = $original_slug . '-' . time() . '-' . mt_rand(100, 999);
            break;
        }
    }
    
    return $slug;
}

// Helper function untuk menentukan folder berdasarkan status dan role
function determineImageFolder($action, $user_role) {
    if ($action === 'draft') {
        return 'draft';
    }
    
    if ($user_role === 'admin') {
        return 'published';
    }
    
    return 'pending';
}

// Helper function untuk menentukan status artikel
function determineArticleStatus($action, $user_role) {
    if ($action === 'draft') {
        return 'draft';
    }
    
    if ($user_role === 'admin') {
        return 'published';
    }
    
    return 'pending';
}

try {
    $config_paths = [
        __DIR__ . '/../config/config.php',
        dirname(__DIR__) . '/config/config.php',
        '../config/config.php'
    ];
    
    $config_loaded = false;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $config_loaded = true;
            break;
        }
    }
    
    if (!$config_loaded) {
        sendResponse(false, 'Configuration file not found');
    }
    
    require_once __DIR__ . '/image_config.php';
    
    if (!isset($koneksi) || !$koneksi) {
        sendResponse(false, 'Database connection not available');
    }
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        sendResponse(false, 'User not logged in');
    }
    
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'penulis'])) {
        sendResponse(false, 'Access denied');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method');
    }
    
    if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token'])) {
        sendResponse(false, 'Invalid CSRF token');
    }
    
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $summary = isset($_POST['summary']) ? trim($_POST['summary']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
    $image_id = isset($_POST['featured_image_id']) ? (int)$_POST['featured_image_id'] : null;
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'publish';
    $article_id = isset($_POST['article_id']) ? (int)$_POST['article_id'] : null;
    
    error_log("=== SUBMIT ARTICLE START ===");
    error_log("Action: " . $action);
    error_log("User role: " . $_SESSION['user_role']);
    error_log("Article ID: " . ($article_id ?? 'NEW'));
    error_log("Image ID: " . ($image_id ?? 'NULL'));
    
    // Validation
    if (empty($title)) sendResponse(false, 'Judul wajib diisi');
    if (empty($summary)) sendResponse(false, 'Ringkasan wajib diisi');
    if (empty($content)) sendResponse(false, 'Konten wajib diisi');
    if (empty($category)) sendResponse(false, 'Kategori wajib dipilih');
    
    if (strlen($title) > 200) sendResponse(false, 'Judul terlalu panjang (maksimal 200 karakter)');
    if (strlen($summary) > 300) sendResponse(false, 'Ringkasan terlalu panjang (maksimal 300 karakter)');
    
    $slug = generateUniqueSlug($title, $koneksi, $article_id);
    
    // Find or create category
    $category_query = "SELECT category_id FROM categories WHERE name = ?";
    $stmt = mysqli_prepare($koneksi, $category_query);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . mysqli_error($koneksi));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $category);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $category_id);
    
    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        
        $create_cat_query = "INSERT INTO categories (name, created_at) VALUES (?, NOW())";
        $stmt = mysqli_prepare($koneksi, $create_cat_query);
        
        if (!$stmt) {
            sendResponse(false, 'Database error: ' . mysqli_error($koneksi));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $category);
        
        if (!mysqli_stmt_execute($stmt)) {
            sendResponse(false, 'Failed to create category: ' . mysqli_error($koneksi));
        }
        
        $category_id = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);
    } else {
        mysqli_stmt_close($stmt);
    }
    
    // Get current article info if editing
    $current_status = null;
    $old_status = null;
    $old_image_id = null;
    $old_image_filename = null;
    $old_image_url = null;
    
    if ($article_id) {
        $status_query = "SELECT a.article_status, a.author_id, a.featured_image_id, i.filename, i.url
                        FROM articles a 
                        LEFT JOIN images i ON a.featured_image_id = i.id
                        WHERE a.article_id = ?";
        $stmt = mysqli_prepare($koneksi, $status_query);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $current_status, $author_id, $old_image_id, $old_image_filename, $old_image_url);
        
        if (!mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            sendResponse(false, 'Article not found');
        }
        mysqli_stmt_close($stmt);
        
        // Verify ownership
        if ($_SESSION['user_role'] !== 'admin' && $author_id != $_SESSION['user_id']) {
            sendResponse(false, 'Anda tidak memiliki akses untuk mengedit artikel ini');
        }
        
        $old_status = $current_status;
        
        error_log("Editing existing article:");
        error_log("  Old status: " . $old_status);
        error_log("  Old image ID: " . ($old_image_id ?? 'NULL'));
        error_log("  Old image filename: " . ($old_image_filename ?? 'NULL'));
        error_log("  Old image URL: " . ($old_image_url ?? 'NULL'));
    }
    
    // Determine new status and folder
    $new_status = determineArticleStatus($action, $_SESSION['user_role']);
    $target_folder = determineImageFolder($action, $_SESSION['user_role']);
    
    $approved_by = null;
    $approved_date = null;
    
    if ($new_status === 'published') {
        $approved_by = $_SESSION['user_id'];
        $approved_date = date('Y-m-d H:i:s');
    }
    
    error_log("New status: " . $new_status);
    error_log("Target folder: " . $target_folder);
    
    $meta_desc = strlen($summary) <= 160 ? $summary : substr($summary, 0, 157) . '...';
    
    mysqli_begin_transaction($koneksi);
    
    try {
        // ==========================================
        // CRITICAL: Handle image movement FIRST
        // ==========================================
        if ($image_id) {
            $img_query = "SELECT filename, url FROM images WHERE id = ?";
            $img_stmt = mysqli_prepare($koneksi, $img_query);
            mysqli_stmt_bind_param($img_stmt, "i", $image_id);
            mysqli_stmt_execute($img_stmt);
            mysqli_stmt_bind_result($img_stmt, $current_filename, $current_url);
            
            if (mysqli_stmt_fetch($img_stmt)) {
                mysqli_stmt_close($img_stmt);
                
                error_log("=== IMAGE MOVEMENT START ===");
                error_log("Image ID: " . $image_id);
                error_log("Filename: " . $current_filename);
                error_log("Current URL in DB: " . $current_url);
                error_log("Target folder: " . $target_folder);
                
                // Cari lokasi file saat ini
                $folders = ['draft', 'pending', 'published', 'rejected'];
                $current_folder = null;
                
                foreach ($folders as $folder) {
                    $check_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $current_filename;
                    if (file_exists($check_path)) {
                        $current_folder = $folder;
                        error_log("✓ Current image location: " . $folder);
                        error_log("  File path: " . $check_path);
                        error_log("  File size: " . filesize($check_path) . " bytes");
                        break;
                    }
                }
                
                if (!$current_folder) {
                    error_log("✗ WARNING: Image file not found in any folder!");
                    error_log("  Searched folders: " . implode(', ', $folders));
                    error_log("  This might cause issues. Continuing anyway...");
                } else {
                    // Move image jika folder berubah - DENGAN DATABASE UPDATE
                    if ($current_folder !== $target_folder) {
                        error_log("➜ Image needs to be moved:");
                        error_log("  From: '{$current_folder}'");
                        error_log("  To: '{$target_folder}'");
                        
                        try {
                            $move_result = moveArticleImageWithDbUpdate(
                                $current_filename, 
                                $current_folder, 
                                $target_folder,
                                $koneksi
                            );
                            
                            if ($move_result && isset($move_result['success']) && $move_result['success']) {
                                error_log("✓ IMAGE MOVE SUCCESSFUL");
                                error_log("  From: {$current_folder}");
                                error_log("  To: {$target_folder}");
                                error_log("  New URL in DB: " . $move_result['new_url']);
                                
                                // Verify the physical move
                                $new_path = __DIR__ . '/../uploads/articles/' . $target_folder . '/' . $current_filename;
                                if (file_exists($new_path)) {
                                    error_log("✓ Physical file verification: OK");
                                    error_log("  Location: " . $new_path);
                                    error_log("  Size: " . filesize($new_path) . " bytes");
                                } else {
                                    error_log("✗ Physical file verification: FAILED!");
                                    error_log("  Expected at: " . $new_path);
                                    throw new Exception("File verification failed after move");
                                }
                                
                                // Verify database was updated
                                $verify_query = "SELECT url FROM images WHERE id = ?";
                                $verify_stmt = mysqli_prepare($koneksi, $verify_query);
                                mysqli_stmt_bind_param($verify_stmt, "i", $image_id);
                                mysqli_stmt_execute($verify_stmt);
                                mysqli_stmt_bind_result($verify_stmt, $verified_url);
                                
                                if (mysqli_stmt_fetch($verify_stmt)) {
                                    error_log("✓ Database URL verification: OK");
                                    error_log("  URL in DB: " . $verified_url);
                                    
                                    if ($verified_url !== $move_result['new_url']) {
                                        error_log("✗ WARNING: URL mismatch!");
                                        error_log("  Expected: " . $move_result['new_url']);
                                        error_log("  Got: " . $verified_url);
                                    }
                                } else {
                                    error_log("✗ Database URL verification: FAILED!");
                                }
                                mysqli_stmt_close($verify_stmt);
                                
                            } else {
                                error_log("✗ IMAGE MOVE FAILED");
                                error_log("  Move result: " . json_encode($move_result));
                                throw new Exception("Failed to move image from {$current_folder} to {$target_folder}");
                            }
                        } catch (Exception $e) {
                            error_log("✗ IMAGE MOVE EXCEPTION");
                            error_log("  Error: " . $e->getMessage());
                            error_log("  Trace: " . $e->getTraceAsString());
                            throw $e;
                        }
                    } else {
                        error_log("✓ Image already in correct folder: " . $target_folder);
                        
                        // Verify URL is correct even if file is in right folder
                        $expected_url = 'https://inievan.my.id/project/uploads/articles/' . $target_folder . '/' . $current_filename;
                        
                        if ($current_url !== $expected_url) {
                            error_log("⚠ URL MISMATCH DETECTED (file in correct folder but wrong URL)");
                            error_log("  Current URL in DB: " . $current_url);
                            error_log("  Expected URL: " . $expected_url);
                            error_log("  Correcting URL in database...");
                            
                            $update_url = "UPDATE images SET url = ? WHERE id = ?";
                            $url_stmt = mysqli_prepare($koneksi, $update_url);
                            mysqli_stmt_bind_param($url_stmt, "si", $expected_url, $image_id);
                            
                            if (mysqli_stmt_execute($url_stmt)) {
                                $affected = mysqli_stmt_affected_rows($url_stmt);
                                error_log("✓ Database URL corrected (affected rows: {$affected})");
                            } else {
                                error_log("✗ Failed to correct URL: " . mysqli_error($koneksi));
                            }
                            mysqli_stmt_close($url_stmt);
                        } else {
                            error_log("✓ URL in database is already correct");
                        }
                    }
                }
                
                error_log("=== IMAGE MOVEMENT END ===");
                
            } else {
                mysqli_stmt_close($img_stmt);
                error_log("✗ WARNING: Image ID provided but couldn't fetch image details");
            }
        } else {
            error_log("No image associated with this article");
        }
        
        // ==========================================
        // Update or insert article
        // ==========================================
        if ($article_id) {
            // UPDATE existing article
            error_log("Updating existing article ID: " . $article_id);
            
            $article_query = "UPDATE articles SET 
                title = ?, 
                content = ?, 
                meta_description = ?, 
                category_id = ?, 
                article_status = ?, 
                approved_by = ?, 
                approved_date = ?, 
                featured_image_id = ?,
                publication_date = NOW()
                WHERE article_id = ? AND author_id = ?";
            
            $stmt = mysqli_prepare($koneksi, $article_query);
            
            if (!$stmt) {
                throw new Exception('Database error: ' . mysqli_error($koneksi));
            }
            
            mysqli_stmt_bind_param($stmt, "sssisssiii",
                $title, 
                $content, 
                $meta_desc, 
                $category_id, 
                $new_status, 
                $approved_by, 
                $approved_date, 
                $image_id,
                $article_id,
                $_SESSION['user_id']
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update article: ' . mysqli_error($koneksi));
            }
            
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            error_log("Article updated. Affected rows: " . $affected_rows);
            mysqli_stmt_close($stmt);
            
            // Update slug
            $slug_update_query = "UPDATE slugs SET slug = ? WHERE related_id = ? AND type = 'article'";
            $slug_stmt = mysqli_prepare($koneksi, $slug_update_query);
            
            if ($slug_stmt) {
                mysqli_stmt_bind_param($slug_stmt, "si", $slug, $article_id);
                mysqli_stmt_execute($slug_stmt);
                error_log("Slug updated: " . $slug);
                mysqli_stmt_close($slug_stmt);
            }
            
        } else {
            // INSERT new article
            error_log("Inserting new article");
            
            $article_query = "INSERT INTO articles (
                title, 
                content, 
                meta_description, 
                category_id, 
                author_id,
                article_status, 
                approved_by, 
                approved_date, 
                featured_image_id, 
                publication_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($koneksi, $article_query);
            
            if (!$stmt) {
                throw new Exception('Database error: ' . mysqli_error($koneksi));
            }
            
            mysqli_stmt_bind_param($stmt, "ssssisssi",
                $title, 
                $content, 
                $meta_desc, 
                $category_id,
                $_SESSION['user_id'], 
                $new_status, 
                $approved_by, 
                $approved_date, 
                $image_id
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to save article: ' . mysqli_error($koneksi));
            }
            
            $article_id = mysqli_insert_id($koneksi);
            error_log("New article created with ID: " . $article_id);
            mysqli_stmt_close($stmt);
            
            // Insert slug
            $slug_query = "INSERT INTO slugs (slug, type, related_id, created_at) VALUES (?, 'article', ?, NOW())";
            $slug_stmt = mysqli_prepare($koneksi, $slug_query);
            mysqli_stmt_bind_param($slug_stmt, "si", $slug, $article_id);
            
            if (!mysqli_stmt_execute($slug_stmt)) {
                throw new Exception('Failed to save slug: ' . mysqli_error($koneksi));
            }
            error_log("Slug created: " . $slug);
            mysqli_stmt_close($slug_stmt);
        }
        
        // Update image source
        if ($image_id) {
            $img_query = "UPDATE images SET source_type = 'article', source_id = ? WHERE id = ?";
            $img_stmt = mysqli_prepare($koneksi, $img_query);
            if ($img_stmt) {
                mysqli_stmt_bind_param($img_stmt, "ii", $article_id, $image_id);
                mysqli_stmt_execute($img_stmt);
                error_log("Image source updated to article ID: " . $article_id);
                mysqli_stmt_close($img_stmt);
            }
        }
        
        // Process tags
        if (!empty($tags)) {
            if ($article_id) {
                $delete_tags_query = "DELETE FROM article_tags WHERE article_id = ?";
                $delete_stmt = mysqli_prepare($koneksi, $delete_tags_query);
                if ($delete_stmt) {
                    mysqli_stmt_bind_param($delete_stmt, "i", $article_id);
                    mysqli_stmt_execute($delete_stmt);
                    error_log("Old tags deleted");
                    mysqli_stmt_close($delete_stmt);
                }
            }
            
            $tag_array = array_filter(array_map('trim', explode(',', $tags)));
            error_log("Processing " . count($tag_array) . " tags");
            
            foreach ($tag_array as $tag_name) {
                if (empty($tag_name) || strlen($tag_name) > 50) continue;
                
                $tag_slug = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $tag_name));
                $tag_slug = preg_replace('/\s+/', '-', trim($tag_slug));
                $tag_slug = substr($tag_slug, 0, 50);
                
                if (empty($tag_slug)) continue;
                
                $tag_query = "SELECT id FROM tags WHERE slug = ?";
                $tag_stmt = mysqli_prepare($koneksi, $tag_query);
                
                if ($tag_stmt) {
                    mysqli_stmt_bind_param($tag_stmt, "s", $tag_slug);
                    mysqli_stmt_execute($tag_stmt);
                    mysqli_stmt_bind_result($tag_stmt, $tag_id);
                    
                    if (!mysqli_stmt_fetch($tag_stmt)) {
                        mysqli_stmt_close($tag_stmt);
                        
                        $create_tag_query = "INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW())";
                        $tag_stmt = mysqli_prepare($koneksi, $create_tag_query);
                        if ($tag_stmt) {
                            mysqli_stmt_bind_param($tag_stmt, "ss", $tag_name, $tag_slug);
                            mysqli_stmt_execute($tag_stmt);
                            $tag_id = mysqli_insert_id($koneksi);
                            error_log("Created new tag: " . $tag_name);
                            mysqli_stmt_close($tag_stmt);
                        }
                    } else {
                        mysqli_stmt_close($tag_stmt);
                        error_log("Using existing tag: " . $tag_name);
                    }
                    
                    if (isset($tag_id)) {
                        $link_query = "INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)";
                        $link_stmt = mysqli_prepare($koneksi, $link_query);
                        if ($link_stmt) {
                            mysqli_stmt_bind_param($link_stmt, "ii", $article_id, $tag_id);
                            mysqli_stmt_execute($link_stmt);
                            mysqli_stmt_close($link_stmt);
                        }
                    }
                }
            }
        }
        
        // COMMIT TRANSACTION
        mysqli_commit($koneksi);
        
        error_log("=== TRANSACTION COMMITTED SUCCESSFULLY ===");
        
        // Final verification
        if ($image_id) {
            $final_check = "SELECT url FROM images WHERE id = ?";
            $check_stmt = mysqli_prepare($koneksi, $final_check);
            mysqli_stmt_bind_param($check_stmt, "i", $image_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_bind_result($check_stmt, $final_url);
            
            if (mysqli_stmt_fetch($check_stmt)) {
                error_log("✓ FINAL VERIFICATION:");
                error_log("  Image ID: " . $image_id);
                error_log("  Final URL in DB: " . $final_url);
                error_log("  Expected folder: " . $target_folder);
                
                if (strpos($final_url, '/' . $target_folder . '/') !== false) {
                    error_log("  Status: ✓ CORRECT");
                } else {
                    error_log("  Status: ✗ MISMATCH!");
                }
            }
            mysqli_stmt_close($check_stmt);
        }
        
        error_log("=== SUBMIT ARTICLE SUCCESS ===");
        
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        error_log("=== TRANSACTION ROLLBACK ===");
        error_log("Reason: " . $e->getMessage());
        throw $e;
    }
    
    // Success messages
    $isUpdate = isset($_POST['article_id']) && !empty($_POST['article_id']);
    
    if ($action === 'draft') {
        $message = $isUpdate ? 'Draft berhasil diperbarui!' : 'Artikel berhasil disimpan sebagai draft!';
    } else {
        if ($new_status === 'published') {
            $message = $isUpdate ? 'Artikel berhasil diperbarui dan dipublikasikan!' : 'Artikel berhasil dipublikasikan!';
        } else if ($new_status === 'pending') {
            $message = $isUpdate ? 'Artikel berhasil diperbarui dan menunggu persetujuan admin.' : 'Artikel berhasil disubmit dan menunggu persetujuan admin.';
        }
    }
    
    $article_url = null;
    if ($new_status === 'published') {
        $article_url = "https://inievan.my.id/project/artikel.php?slug=" . urlencode($slug);
    }
    
    sendResponse(true, $message, [
        'article_id' => $article_id,
        'title' => $title,
        'slug' => $slug,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'folder' => $target_folder,
        'url' => $article_url,
        'action' => $action,
        'is_update' => $isUpdate,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Submit article error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse(false, 'System error: ' . $e->getMessage());
}

sendResponse(false, 'Unknown error occurred');
?>