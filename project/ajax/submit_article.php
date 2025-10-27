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

function determineImageFolder($action, $user_role) {
    if ($action === 'draft') {
        return 'draft';
    }
    
    if ($user_role === 'admin') {
        return 'published';
    }
    
    return 'pending';
}

function determineArticleStatus($action, $user_role) {
    if ($action === 'draft') {
        return 'draft';
    }
    
    if ($user_role === 'admin') {
        return 'published';
    }
    
    return 'pending';
}

// ===== CRITICAL FIX: Validate Image ID =====
function validateImageId($image_id, $koneksi) {
    if (empty($image_id) || $image_id <= 0) {
        return null;
    }
    
    $check_query = "SELECT id FROM images WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $check_query);
    
    if (!$stmt) {
        error_log("Failed to prepare image validation query: " . mysqli_error($koneksi));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $image_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $validated_id);
    
    $exists = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if ($exists) {
        error_log("✓ Image ID {$image_id} validated successfully");
        return $validated_id;
    } else {
        error_log("✗ WARNING: Image ID {$image_id} does not exist in database!");
        return null;
    }
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
    error_log("Image ID (raw): " . ($image_id ?? 'NULL'));
    
    // ===== BASIC VALIDATION =====
    if (empty($title)) sendResponse(false, 'Judul wajib diisi');
    if (empty($summary)) sendResponse(false, 'Ringkasan wajib diisi');
    if (empty($content)) sendResponse(false, 'Konten wajib diisi');
    if (empty($category)) sendResponse(false, 'Kategori wajib dipilih');
    
    if (strlen($title) > 200) sendResponse(false, 'Judul terlalu panjang (maksimal 200 karakter)');
    if (strlen($summary) > 300) sendResponse(false, 'Ringkasan terlalu panjang (maksimal 300 karakter)');
    
    // ===== NEW: MANDATORY IMAGE VALIDATION FOR PUBLISH =====
    if ($action === 'publish') {
        error_log("=== VALIDATING PUBLISH REQUIREMENTS ===");
        
        // Check if image is provided
        if (empty($image_id) || $image_id <= 0) {
            error_log("✗ PUBLISH DENIED: No image provided");
            sendResponse(false, 'Gambar berita wajib diupload untuk publish artikel!');
        }
        
        // Validate image exists in database
        $validated_image_id = validateImageId($image_id, $koneksi);
        
        if (!$validated_image_id) {
            error_log("✗ PUBLISH DENIED: Image ID {$image_id} not found in database");
            sendResponse(false, 'Gambar yang dipilih tidak valid. Mohon upload ulang gambar berita!');
        }
        
        // Validate image file exists physically
        $img_query = "SELECT filename FROM images WHERE id = ?";
        $img_stmt = mysqli_prepare($koneksi, $img_query);
        mysqli_stmt_bind_param($img_stmt, "i", $validated_image_id);
        mysqli_stmt_execute($img_stmt);
        mysqli_stmt_bind_result($img_stmt, $image_filename);
        
        if (!mysqli_stmt_fetch($img_stmt)) {
            mysqli_stmt_close($img_stmt);
            error_log("✗ PUBLISH DENIED: Image record not found");
            sendResponse(false, 'Gambar berita tidak ditemukan. Mohon upload ulang!');
        }
        mysqli_stmt_close($img_stmt);
        
        // Check if physical file exists
        $folders = ['draft', 'pending', 'published', 'rejected'];
        $file_found = false;
        
        foreach ($folders as $folder) {
            $image_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $image_filename;
            if (file_exists($image_path)) {
                $file_found = true;
                error_log("✓ Image file found in {$folder} folder");
                break;
            }
        }
        
        if (!$file_found) {
            error_log("✗ PUBLISH DENIED: Image file not found in any folder");
            sendResponse(false, 'File gambar berita tidak ditemukan di server. Mohon upload ulang gambar!');
        }
        
        // Additional validation: Check if tags are provided
        if (empty($tags)) {
            error_log("⚠ WARNING: No tags provided for publish");
            sendResponse(false, 'Tag artikel wajib diisi minimal 1 tag untuk publish!');
        }
        
        // Validate minimum content length
        $plain_content = strip_tags($content);
        if (strlen($plain_content) < 100) {
            error_log("✗ PUBLISH DENIED: Content too short (" . strlen($plain_content) . " chars)");
            sendResponse(false, 'Konten artikel terlalu pendek! Minimal 100 karakter untuk publish (saat ini: ' . strlen($plain_content) . ' karakter)');
        }
        
        $image_id = $validated_image_id;
        error_log("✓ ALL PUBLISH REQUIREMENTS MET");
        
    } else if ($action === 'draft') {
        // Draft: Image is optional but if provided must be valid
        if ($image_id && $image_id > 0) {
            $validated_image_id = validateImageId($image_id, $koneksi);
            
            if (!$validated_image_id) {
                error_log("✗ DRAFT WARNING: Invalid image ID {$image_id}, setting to NULL");
                $image_id = null;
            } else {
                $image_id = $validated_image_id;
                error_log("✓ Draft with valid image ID: {$image_id}");
            }
        } else {
            $image_id = null;
            error_log("ℹ Draft without image");
        }
    } else {
        // Other actions: validate if image is provided
        if ($image_id && $image_id > 0) {
            $validated_image_id = validateImageId($image_id, $koneksi);
            
            if (!$validated_image_id) {
                error_log("✗ WARNING: Invalid image ID {$image_id}, setting to NULL");
                $image_id = null;
            } else {
                $image_id = $validated_image_id;
            }
        } else {
            $image_id = null;
        }
    }
    
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
        // ===== CRITICAL: Handle image movement with validation =====
        if ($image_id) {
            // Double-check image exists before proceeding
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
                
                // Find current location
                $folders = ['draft', 'pending', 'published', 'rejected'];
                $current_folder = null;
                
                foreach ($folders as $folder) {
                    $check_path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $current_filename;
                    if (file_exists($check_path)) {
                        $current_folder = $folder;
                        error_log("✓ Current location: " . $folder);
                        break;
                    }
                }
                
                if (!$current_folder) {
                    error_log("✗ WARNING: Image file not found in any folder!");
                    throw new Exception('File gambar tidak ditemukan di server!');
                } else {
                    // Move image if needed
                    if ($current_folder !== $target_folder) {
                        error_log("→ Moving image: {$current_folder} → {$target_folder}");
                        
                        try {
                            $move_result = moveArticleImageWithDbUpdate(
                                $current_filename, 
                                $current_folder, 
                                $target_folder,
                                $koneksi
                            );
                            
                            if ($move_result && isset($move_result['success']) && $move_result['success']) {
                                error_log("✓ Image moved successfully");
                            } else {
                                error_log("✗ Image move failed");
                                throw new Exception('Gagal memindahkan gambar ke folder yang sesuai!');
                            }
                        } catch (Exception $e) {
                            error_log("✗ IMAGE MOVE EXCEPTION: " . $e->getMessage());
                            throw $e;
                        }
                    } else {
                        error_log("✓ Image already in correct folder");
                    }
                }
                
                error_log("=== IMAGE MOVEMENT END ===");
                
            } else {
                mysqli_stmt_close($img_stmt);
                error_log("✗ CRITICAL: Image ID {$image_id} not found during fetch!");
                throw new Exception('Gambar tidak ditemukan dalam database!');
            }
        }
        
        // ===== Update or insert article =====
        if ($article_id) {
            error_log("Updating existing article ID: " . $article_id);
            
            // CRITICAL FIX: Handle NULL image_id properly
            if ($image_id) {
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
            } else {
                // Update without image
                $article_query = "UPDATE articles SET 
                    title = ?, 
                    content = ?, 
                    meta_description = ?, 
                    category_id = ?, 
                    article_status = ?, 
                    approved_by = ?, 
                    approved_date = ?, 
                    featured_image_id = NULL,
                    publication_date = NOW()
                    WHERE article_id = ? AND author_id = ?";
                
                $stmt = mysqli_prepare($koneksi, $article_query);
                
                if (!$stmt) {
                    throw new Exception('Database error: ' . mysqli_error($koneksi));
                }
                
                mysqli_stmt_bind_param($stmt, "ssisssii",
                    $title, 
                    $content, 
                    $meta_desc, 
                    $category_id, 
                    $new_status, 
                    $approved_by, 
                    $approved_date,
                    $article_id,
                    $_SESSION['user_id']
                );
            }
            
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
                mysqli_stmt_close($slug_stmt);
            }
            
        } else {
            error_log("Inserting new article");
            
            // CRITICAL FIX: Handle NULL image_id
            if ($image_id) {
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
            } else {
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())";
                
                $stmt = mysqli_prepare($koneksi, $article_query);
                
                if (!$stmt) {
                    throw new Exception('Database error: ' . mysqli_error($koneksi));
                }
                
                mysqli_stmt_bind_param($stmt, "ssssisss",
                    $title, 
                    $content, 
                    $meta_desc, 
                    $category_id,
                    $_SESSION['user_id'], 
                    $new_status, 
                    $approved_by, 
                    $approved_date
                );
            }
            
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
            mysqli_stmt_close($slug_stmt);
        }
        
        // Update image source
        if ($image_id) {
            $img_query = "UPDATE images SET source_type = 'article', source_id = ? WHERE id = ?";
            $img_stmt = mysqli_prepare($koneksi, $img_query);
            if ($img_stmt) {
                mysqli_stmt_bind_param($img_stmt, "ii", $article_id, $image_id);
                mysqli_stmt_execute($img_stmt);
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
                    mysqli_stmt_close($delete_stmt);
                }
            }
            
            $tag_array = array_filter(array_map('trim', explode(',', $tags)));
            
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
                            mysqli_stmt_close($tag_stmt);
                        }
                    } else {
                        mysqli_stmt_close($tag_stmt);
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
        
        mysqli_commit($koneksi);
        error_log("=== TRANSACTION COMMITTED SUCCESSFULLY ===");
        
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