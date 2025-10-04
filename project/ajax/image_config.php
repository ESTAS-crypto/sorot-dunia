<?php

/**
 * Enhanced Image Uploader Class
 * - Auto WebP conversion
 * - Image optimization (max 300KB, 1000x1000px)
 * - Folder structure: draft, pending, published, rejected (for articles)
 * - Direct folder for profile images
 * - Security features
 * - Enhanced logging for debugging
 * - Database URL synchronization when moving files
 */

class ImageUploader {

    private $upload_dir;
    private $max_file_size;
    private $allowed_types;
    private $allowed_extensions;
    private $max_width;
    private $max_height;
    private $webp_quality;
    private $subfolder;
    private $subdirectory;
    
    public function __construct($subdirectory = 'articles', $status_folder = 'pending') {
        $this->subdirectory = $subdirectory;
        
        // Profile image langsung ke folder profile_image tanpa subfolder status
        if ($subdirectory === 'profile_image') {
            $this->upload_dir = __DIR__ . '/../uploads/profile_image/';
            $this->subfolder = '';
            error_log("=== Profile Image Mode ===");
        } else {
            // Untuk artikel, gunakan struktur folder status
            $base_dir = __DIR__ . '/../uploads/' . $subdirectory . '/';
            
            // Validate status folder
            $valid_folders = ['draft', 'pending', 'published', 'rejected'];
            if (!in_array($status_folder, $valid_folders)) {
                error_log("WARNING: Invalid status_folder '$status_folder', defaulting to 'pending'");
                $status_folder = 'pending';
            }
            
            $this->subfolder = $status_folder;
            $this->upload_dir = $base_dir . $status_folder . '/';
        }
        
        $this->max_file_size = 300 * 1024; // 300KB final size
        $this->allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $this->allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $this->max_width = 1000;
        $this->max_height = 1000;
        $this->webp_quality = 85;
        
        error_log("=== ImageUploader Initialized ===");
        error_log("Subdirectory: " . $subdirectory);
        error_log("Status Folder: " . ($this->subfolder ?: 'N/A (direct upload)'));
        error_log("Upload Dir: " . $this->upload_dir);
        error_log("================================");
        
        $this->createUploadDirectory();
    }
    
    /**
     * Create upload directory with security measures
     */
    private function createUploadDirectory() {
        if (!file_exists($this->upload_dir)) {
            error_log("Creating directory: " . $this->upload_dir);
            
            if (!mkdir($this->upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory: ' . $this->upload_dir);
            }
            
            error_log("Directory created successfully");
            $this->createSecurityFiles();
        } else {
            error_log("Directory already exists: " . $this->upload_dir);
        }
    }
    
    /**
     * Create security files (.htaccess and index.php)
     */
    private function createSecurityFiles() {
        $htaccess_content = '# Prevent PHP execution in upload directory
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

# Only allow image files
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Deny everything else
<FilesMatch ".*">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Override for images
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>';

        file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
        
        $index_content = '<?php
// Prevent directory listing
header("Location: ../../../");
exit();
?>';
        
        file_put_contents($this->upload_dir . 'index.php', $index_content);
        
        error_log("Security files created");
    }
    
    /**
     * Comprehensive file validation
     */
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL => 'File tidak terupload dengan sempurna',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporary directory tidak tersedia',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
            ];
            
            $error_msg = $error_messages[$file['error']] ?? 'Upload error: ' . $file['error'];
            throw new Exception($error_msg);
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File terlalu besar. Maksimal 5MB untuk upload.');
        }
        
        if ($file['size'] === 0) {
            throw new Exception('File kosong atau rusak.');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_types)) {
            throw new Exception('Format file tidak didukung. Hanya JPG, PNG, WEBP, dan GIF yang diizinkan.');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            throw new Exception('Ekstensi file tidak valid: ' . $extension);
        }
        
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            throw new Exception('File yang diupload bukan gambar yang valid.');
        }
        
        if ($image_info[0] > 5000 || $image_info[1] > 5000) {
            throw new Exception('Dimensi gambar terlalu besar. Maksimal 5000x5000 pixel.');
        }
        
        if ($image_info[0] < 50 || $image_info[1] < 50) {
            throw new Exception('Dimensi gambar terlalu kecil. Minimal 50x50 pixel.');
        }
        
        return true;
    }
    
    /**
     * Generate secure filename
     */
    private function generateFilename($original_filename) {
        $safe_name = preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($original_filename, PATHINFO_FILENAME));
        $safe_name = substr($safe_name, 0, 20);
        
        $prefix = $this->subdirectory === 'profile_image' ? 'profile' : 'article';
        
        return $prefix . '_' . time() . '_' . uniqid() . '_' . mt_rand(1000, 9999) . 
               (!empty($safe_name) ? '_' . $safe_name : '') . '.webp';
    }
    
    /**
     * Main upload function with optimization
     */
    public function upload($file) {
        error_log("=== Starting Upload ===");
        
        $this->validateFile($file);
        
        $filename = $this->generateFilename($file['name']);
        $filepath = $this->upload_dir . $filename;
        
        error_log("Generated filename: " . $filename);
        error_log("Target path: " . $filepath);
        
        $this->processAndSaveImage($file['tmp_name'], $filepath);
        
        $final_size = filesize($filepath);
        error_log("Initial file size: " . $final_size . " bytes");
        
        if ($final_size > $this->max_file_size) {
            error_log("File too large, compressing with quality 60");
            $this->compressWebP($filepath, 60);
            
            $final_size = filesize($filepath);
            if ($final_size > $this->max_file_size) {
                error_log("Still too large, compressing with quality 40");
                $this->compressWebP($filepath, 40);
                
                if (filesize($filepath) > $this->max_file_size) {
                    unlink($filepath);
                    throw new Exception('File terlalu besar setelah kompresi. Maksimal ' . 
                                      ($this->max_file_size / 1024) . 'KB. Coba gunakan gambar dengan resolusi lebih kecil.');
                }
            }
        }
        
        chmod($filepath, 0644);
        
        // Verify file exists
        if (!file_exists($filepath)) {
            throw new Exception('File verification failed after upload');
        }
        
        error_log("Upload completed successfully");
        error_log("Final size: " . filesize($filepath) . " bytes");
        error_log("======================");
        
        return [
            'filename' => $filename,
            'folder' => $this->subfolder,
            'relative_path' => $this->subfolder ? $this->subfolder . '/' . $filename : $filename
        ];
    }
    
    /**
     * Process image: resize, optimize and convert to WebP
     */
    private function processAndSaveImage($source_path, $destination_path) {
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            throw new Exception('Invalid image file');
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $type = $image_info[2];
        
        $source_image = $this->createImageFromFile($source_path, $type);
        if (!$source_image) {
            throw new Exception('Failed to create image resource');
        }
        
        $new_dimensions = $this->calculateNewDimensions($width, $height, $this->max_width, $this->max_height);
        
        $new_image = imagecreatetruecolor($new_dimensions['width'], $new_dimensions['height']);
        if (!$new_image) {
            imagedestroy($source_image);
            throw new Exception('Failed to create new image canvas');
        }
        
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        
        $white = imagecolorallocatealpha($new_image, 255, 255, 255, 0);
        imagefill($new_image, 0, 0, $white);
        
        imagealphablending($new_image, true);
        
        $resize_success = imagecopyresampled(
            $new_image, $source_image,
            0, 0, 0, 0,
            $new_dimensions['width'], $new_dimensions['height'],
            $width, $height
        );
        
        if (!$resize_success) {
            imagedestroy($source_image);
            imagedestroy($new_image);
            throw new Exception('Failed to resize image');
        }
        
        if (!imagewebp($new_image, $destination_path, $this->webp_quality)) {
            imagedestroy($source_image);
            imagedestroy($new_image);
            throw new Exception('Failed to save WebP image');
        }
        
        imagedestroy($source_image);
        imagedestroy($new_image);
    }
    
    /**
     * Create image resource from file based on type
     */
    private function createImageFromFile($filepath, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($filepath);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($filepath);
            case IMAGETYPE_WEBP:
                return @imagecreatefromwebp($filepath);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($filepath);
            default:
                throw new Exception('Unsupported image type: ' . $type);
        }
    }
    
    /**
     * Calculate new dimensions maintaining aspect ratio
     */
    private function calculateNewDimensions($width, $height, $max_width, $max_height) {
        if ($width <= $max_width && $height <= $max_height) {
            return ['width' => $width, 'height' => $height];
        }
        
        $ratio = min($max_width / $width, $max_height / $height);
        
        return [
            'width' => round($width * $ratio),
            'height' => round($height * $ratio)
        ];
    }
    
    /**
     * Compress existing WebP file with specified quality
     */
    private function compressWebP($filepath, $quality = 75) {
        $image = @imagecreatefromwebp($filepath);
        if ($image) {
            imagewebp($image, $filepath, $quality);
            imagedestroy($image);
            return true;
        }
        return false;
    }
    
    /**
     * Move image to different folder (when status changes) - ONLY FOR ARTICLES
     */
    public function moveToFolder($filename, $target_folder) {
        if ($this->subdirectory === 'profile_image') {
            throw new Exception('Move folder operation not supported for profile images');
        }
        
        $valid_folders = ['draft', 'pending', 'published', 'rejected'];
        if (!in_array($target_folder, $valid_folders)) {
            throw new Exception('Invalid target folder');
        }
        
        $source_path = $this->upload_dir . $filename;
        $target_dir = str_replace($this->subfolder, $target_folder, $this->upload_dir);
        
        // Create target directory if not exists
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                throw new Exception('Failed to create target directory');
            }
            $this->createSecurityFiles();
        }
        
        $target_path = $target_dir . $filename;
        
        if (file_exists($source_path)) {
            if (rename($source_path, $target_path)) {
                return [
                    'success' => true,
                    'new_path' => $target_folder . '/' . $filename,
                    'folder' => $target_folder
                ];
            } else {
                throw new Exception('Failed to move file');
            }
        } else {
            throw new Exception('Source file not found');
        }
    }
    
    /**
     * Delete uploaded file
     */
    public function delete($filename) {
        if (empty($filename)) return true;
        
        $filepath = $this->upload_dir . $filename;
        
        error_log("Attempting to delete: " . $filepath);
        
        if (file_exists($filepath)) {
            $result = unlink($filepath);
            error_log("Delete result: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        }
        
        error_log("File not found, considering as already deleted");
        return true;
    }
    
    /**
     * Get full URL for image
     */
    public function getUrl($filename, $folder = null) {
        if (empty($filename)) return null;
        
        if ($this->subdirectory === 'profile_image') {
            $folder_path = '';
        } else {
            $folder_path = $folder ?? $this->subfolder;
        }
        
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                   "://" . $_SERVER['HTTP_HOST'];
        
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        
        if ($this->subdirectory === 'profile_image') {
            return $base_url . $script_dir . '/../uploads/profile_image/' . $filename;
        } else {
            return $base_url . $script_dir . '/../uploads/articles/' . $folder_path . '/' . $filename;
        }
    }
    
    /**
     * Check if file exists
     */
    public function exists($filename) {
        return file_exists($this->upload_dir . $filename);
    }
    
    /**
     * Get file size in bytes
     */
    public function getFileSize($filename) {
        $filepath = $this->upload_dir . $filename;
        return file_exists($filepath) ? filesize($filepath) : 0;
    }
    
    /**
     * Get comprehensive file information
     */
    public function getFileInfo($filename) {
        $filepath = $this->upload_dir . $filename;
        
        if (!file_exists($filepath)) {
            return null;
        }
        
        $image_info = getimagesize($filepath);
        $file_size = filesize($filepath);
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'folder' => $this->subfolder,
            'size' => $file_size,
            'size_kb' => round($file_size / 1024, 2),
            'width' => $image_info[0] ?? 0,
            'height' => $image_info[1] ?? 0,
            'mime_type' => $image_info['mime'] ?? 'image/webp',
            'url' => $this->getUrl($filename),
            'is_webp' => true,
            'optimized' => true,
            'created' => date('Y-m-d H:i:s', filemtime($filepath))
        ];
    }
}

/**
 * Helper functions for backward compatibility and easy usage
 */

function uploadArticleImage($file, $status = 'pending') {
    try {
        error_log("=== uploadArticleImage() called ===");
        error_log("Status: " . $status);
        
        $uploader = new ImageUploader('articles', $status);
        $result = $uploader->upload($file);
        
        error_log("Upload result: " . json_encode($result));
        
        return $result['filename'];
    } catch (Exception $e) {
        error_log("uploadArticleImage error: " . $e->getMessage());
        throw new Exception('Image upload failed: ' . $e->getMessage());
    }
}

function deleteArticleImage($filename, $folder = 'pending') {
    try {
        error_log("=== deleteArticleImage() called ===");
        error_log("Filename: " . $filename);
        error_log("Folder: " . $folder);
        
        $uploader = new ImageUploader('articles', $folder);
        return $uploader->delete($filename);
    } catch (Exception $e) {
        error_log('Image deletion error: ' . $e->getMessage());
        return false;
    }
}

function getArticleImageUrl($filename, $folder = 'pending') {
    if (empty($filename)) return null;
    try {
        $uploader = new ImageUploader('articles', $folder);
        return $uploader->getUrl($filename);
    } catch (Exception $e) {
        error_log('Image URL error: ' . $e->getMessage());
        return null;
    }
}

/**
 * CRITICAL FIX: Move article image WITH database URL update
 */
function moveArticleImageWithDbUpdate($filename, $from_folder, $to_folder, $koneksi) {
    try {
        error_log("=== moveArticleImageWithDbUpdate() called ===");
        error_log("Filename: " . $filename);
        error_log("From: " . $from_folder);
        error_log("To: " . $to_folder);
        
        // Verify source file exists
        $source_path = __DIR__ . '/../uploads/articles/' . $from_folder . '/' . $filename;
        error_log("Source path: " . $source_path);
        
        if (!file_exists($source_path)) {
            error_log("ERROR: Source file does not exist!");
            throw new Exception("Source file not found: " . $source_path);
        }
        
        error_log("Source file exists, size: " . filesize($source_path) . " bytes");
        
        // Create target directory if needed
        $target_dir = __DIR__ . '/../uploads/articles/' . $to_folder . '/';
        error_log("Target dir: " . $target_dir);
        
        if (!is_dir($target_dir)) {
            error_log("Creating target directory...");
            if (!mkdir($target_dir, 0755, true)) {
                throw new Exception("Failed to create target directory: " . $target_dir);
            }
            error_log("Target directory created");
            
            // Create security files
            $htaccess_content = '# Prevent PHP execution
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>';
            
            file_put_contents($target_dir . '.htaccess', $htaccess_content);
            file_put_contents($target_dir . 'index.php', '<?php header("Location: ../../../../"); exit(); ?>');
        }
        
        $target_path = $target_dir . $filename;
        error_log("Target path: " . $target_path);
        
        // Perform the move
        if (rename($source_path, $target_path)) {
            error_log("SUCCESS: File moved");
            
            // Verify target file exists
            if (file_exists($target_path)) {
                error_log("VERIFIED: Target file exists, size: " . filesize($target_path) . " bytes");
                
                // Set correct permissions
                chmod($target_path, 0644);
                
                // CRITICAL: UPDATE DATABASE URL
                $new_url = 'https://inievan.my.id/project/uploads/articles/' . $to_folder . '/' . $filename;
                
                error_log("Updating database URL to: " . $new_url);
                
                $update_query = "UPDATE images SET url = ? WHERE filename = ? AND source_type = 'article'";
                $stmt = mysqli_prepare($koneksi, $update_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ss", $new_url, $filename);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $affected_rows = mysqli_stmt_affected_rows($stmt);
                        error_log("SUCCESS: Database URL updated. Affected rows: " . $affected_rows);
                        
                        if ($affected_rows === 0) {
                            error_log("WARNING: No rows were updated. Image might not exist in database.");
                        }
                    } else {
                        error_log("ERROR: Failed to update database URL: " . mysqli_error($koneksi));
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    error_log("ERROR: Failed to prepare update statement: " . mysqli_error($koneksi));
                }
                
                error_log("============================");
                return [
                    'success' => true,
                    'source' => $from_folder,
                    'target' => $to_folder,
                    'filename' => $filename,
                    'new_url' => $new_url
                ];
            } else {
                error_log("WARNING: Target file verification failed!");
                error_log("============================");
                return false;
            }
        } else {
            error_log("ERROR: rename() failed");
            error_log("============================");
            throw new Exception("Failed to move file from {$from_folder} to {$to_folder}");
        }
        
    } catch (Exception $e) {
        error_log('CRITICAL ERROR in moveArticleImageWithDbUpdate: ' . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("============================");
        return false;
    }
}

/**
 * Legacy function - now calls the DB-aware version
 */
function moveArticleImage($filename, $from_folder, $to_folder) {
    global $koneksi;
    
    if (!isset($koneksi)) {
        error_log("WARNING: Database connection not available for moveArticleImage");
        return moveArticleImageLegacy($filename, $from_folder, $to_folder);
    }
    
    return moveArticleImageWithDbUpdate($filename, $from_folder, $to_folder, $koneksi);
}

/**
 * Legacy file-only move (no DB update) - kept for compatibility
 */
function moveArticleImageLegacy($filename, $from_folder, $to_folder) {
    try {
        error_log("=== moveArticleImageLegacy() called (no DB update) ===");
        
        $source_path = __DIR__ . '/../uploads/articles/' . $from_folder . '/' . $filename;
        
        if (!file_exists($source_path)) {
            throw new Exception("Source file not found: " . $source_path);
        }
        
        $target_dir = __DIR__ . '/../uploads/articles/' . $to_folder . '/';
        
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                throw new Exception("Failed to create target directory: " . $target_dir);
            }
        }
        
        $target_path = $target_dir . $filename;
        
        if (rename($source_path, $target_path)) {
            chmod($target_path, 0644);
            
            return [
                'success' => true,
                'source' => $from_folder,
                'target' => $to_folder,
                'filename' => $filename
            ];
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log('ERROR in moveArticleImageLegacy: ' . $e->getMessage());
        return false;
    }
}

/**
 * Find image in any folder
 */
function findArticleImage($filename) {
    $folders = ['draft', 'pending', 'published', 'rejected'];
    
    foreach ($folders as $folder) {
        $path = __DIR__ . '/../uploads/articles/' . $folder . '/' . $filename;
        if (file_exists($path)) {
            return [
                'found' => true,
                'folder' => $folder,
                'path' => $path,
                'size' => filesize($path)
            ];
        }
    }
    
    return ['found' => false];
}

/**
 * Get folder based on article status
 */
function getStatusFolder($status) {
    $folder_map = [
        'draft' => 'draft',
        'pending' => 'pending',
        'published' => 'published',
        'rejected' => 'rejected'
    ];
    
    return $folder_map[$status] ?? 'pending';
}

/**
 * Sync image with article status - WITH DB UPDATE
 */
function syncImageWithArticleStatus($article_id, $koneksi) {
    try {
        error_log("=== syncImageWithArticleStatus() called ===");
        error_log("Article ID: " . $article_id);
        
        // Get article status and image
        $query = "SELECT a.article_status, i.filename 
                  FROM articles a 
                  LEFT JOIN images i ON a.featured_image_id = i.id 
                  WHERE a.article_id = ?";
        
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $status, $filename);
        
        if (!mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            error_log("Article not found");
            return false;
        }
        mysqli_stmt_close($stmt);
        
        if (empty($filename)) {
            error_log("No image to sync");
            return true;
        }
        
        error_log("Status: " . $status);
        error_log("Filename: " . $filename);
        
        // Determine target folder
        $target_folder = getStatusFolder($status);
        error_log("Target folder: " . $target_folder);
        
        // Find current location
        $current_location = findArticleImage($filename);
        
        if (!$current_location['found']) {
            error_log("WARNING: Image file not found");
            return false;
        }
        
        error_log("Current folder: " . $current_location['folder']);
        
        // Move if needed - WITH DB UPDATE
        if ($current_location['folder'] !== $target_folder) {
            error_log("Moving image from {$current_location['folder']} to {$target_folder}");
            $result = moveArticleImageWithDbUpdate($filename, $current_location['folder'], $target_folder, $koneksi);
            
            if ($result && isset($result['success']) && $result['success']) {
                error_log("Move result: SUCCESS");
                error_log("New URL: " . $result['new_url']);
                return true;
            } else {
                error_log("Move result: FAILED");
                return false;
            }
        }
        
        error_log("Image already in correct folder");
        return true;
        
    } catch (Exception $e) {
        error_log("syncImageWithArticleStatus error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up orphaned images (not linked to any article)
 */
function cleanupOrphanedImages($koneksi) {
    try {
        error_log("=== Cleanup Orphaned Images ===");
        
        $folders = ['draft', 'pending', 'published', 'rejected'];
        $deleted_count = 0;
        
        foreach ($folders as $folder) {
            $dir = __DIR__ . '/../uploads/articles/' . $folder . '/';
            
            if (!is_dir($dir)) continue;
            
            $files = array_diff(scandir($dir), ['.', '..', '.htaccess', 'index.php']);
            
            foreach ($files as $file) {
                // Check if image exists in database
                $query = "SELECT COUNT
                (*) FROM images WHERE filename = ?";
                $stmt = mysqli_prepare($koneksi, $query);
                mysqli_stmt_bind_param($stmt, "s", $file);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $count);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);
                
                if ($count == 0) {
                    // File not in database, delete it
                    $filepath = $dir . $file;
                    if (unlink($filepath)) {
                        $deleted_count++;
                        error_log("Deleted orphaned file: " . $file);
                    }
                }
            }
        }
        
        error_log("Cleanup complete. Deleted: " . $deleted_count);
        return $deleted_count;
        
    } catch (Exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get storage statistics
 */
function getStorageStats() {
    $stats = [
        'total_size' => 0,
        'total_files' => 0,
        'by_folder' => []
    ];
    
    $folders = ['draft', 'pending', 'published', 'rejected'];
    
    foreach ($folders as $folder) {
        $dir = __DIR__ . '/../uploads/articles/' . $folder . '/';
        
        if (!is_dir($dir)) {
            $stats['by_folder'][$folder] = [
                'size' => 0,
                'files' => 0
            ];
            continue;
        }
        
        $files = array_diff(scandir($dir), ['.', '..', '.htaccess', 'index.php']);
        $folder_size = 0;
        
        foreach ($files as $file) {
            $filepath = $dir . $file;
            if (is_file($filepath)) {
                $folder_size += filesize($filepath);
            }
        }
        
        $stats['by_folder'][$folder] = [
            'size' => $folder_size,
            'size_mb' => round($folder_size / 1024 / 1024, 2),
            'files' => count($files)
        ];
        
        $stats['total_size'] += $folder_size;
        $stats['total_files'] += count($files);
    }
    
    $stats['total_size_mb'] = round($stats['total_size'] / 1024 / 1024, 2);
    
    return $stats;
}

?>