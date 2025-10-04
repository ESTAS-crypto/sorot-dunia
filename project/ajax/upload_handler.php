<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

error_log("=== UPLOAD HANDLER STARTED ===");

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

function sendUploadResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

try {
    $config_path = __DIR__ . '/../config/config.php';
    if (!file_exists($config_path)) {
        throw new Exception('Config file not found at: ' . $config_path);
    }
    require_once $config_path;
    error_log("Config loaded successfully");
    
    $image_config_path = __DIR__ . '/image_config.php';
    if (file_exists($image_config_path)) {
        require_once $image_config_path;
        error_log("Image config loaded from ajax folder");
    } else {
        $fallback_path = __DIR__ . '/../config/image_config.php';
        if (file_exists($fallback_path)) {
            require_once $fallback_path;
            error_log("Image config loaded from config folder");
        } else {
            error_log("WARNING: image_config.php not found in either location");
        }
    }
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('User not logged in');
    }
    
    if (!in_array($_SESSION['user_role'], ['admin', 'penulis'])) {
        throw new Exception('Access denied. Need admin or penulis role');
    }
    
    error_log("User authenticated: " . $_SESSION['user_role']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token'])) {
        throw new Exception('CSRF token missing');
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['image'];
    error_log("File received: " . $file['name'] . " (" . $file['size'] . " bytes)");
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File tidak terupload dengan sempurna',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder tidak tersedia',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi'
        ];
        
        $error_msg = $error_messages[$file['error']] ?? 'Upload error code: ' . $file['error'];
        throw new Exception($error_msg);
    }
    
    // ===== PERBAIKAN: Tentukan folder berdasarkan action =====
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'publish';
    $user_role = $_SESSION['user_role'];
    
    error_log("=== UPLOAD HANDLER FOLDER DECISION ===");
    error_log("Action: " . $action);
    error_log("User role: " . $user_role);
    
    // Determine folder
    if ($action === 'draft') {
        $image_folder = 'draft';
        error_log("Folder: DRAFT (user requested draft)");
    } else {
        // Action adalah 'publish' atau default
        if ($user_role === 'admin') {
            $image_folder = 'published';
            error_log("Folder: PUBLISHED (admin direct publish)");
        } else {
            $image_folder = 'pending';
            error_log("Folder: PENDING (penulis submission)");
        }
    }
    
    error_log("Final folder: " . $image_folder);
    error_log("=====================================");
    
    // Use ImageUploader if available
    if (class_exists('ImageUploader')) {
        error_log("Using ImageUploader class for optimization with folder: " . $image_folder);
        
        try {
            $uploader = new ImageUploader('articles', $image_folder);
            $upload_result = $uploader->upload($file);
            
            $filename = $upload_result['filename'];
            $folder = $upload_result['folder'];
            $relative_path = $upload_result['relative_path'];
            
            $file_info = $uploader->getFileInfo($filename);
            $image_url = $uploader->getUrl($filename, $folder);
            $final_mime = 'image/webp';
            
            error_log("ImageUploader success: " . $filename . " in folder: " . $folder);
            
            $original_size = $file['size'];
            $compressed_size = $file_info['size'];
            $compression_ratio = round((1 - ($compressed_size / $original_size)) * 100, 1);
            
            $response_data = [
                'image_id' => null,
                'filename' => $filename,
                'folder' => $folder,
                'relative_path' => $relative_path,
                'url' => $image_url,
                'size' => $file_info['size_kb'] . ' KB',
                'original_size' => round($original_size / 1024, 2) . ' KB',
                'dimensions' => $file_info['width'] . 'x' . $file_info['height'],
                'mime_type' => $final_mime,
                'compression_ratio' => $compression_ratio . '%',
                'optimized' => true,
                'webp_converted' => true,
                'upload_time' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("ImageUploader error: " . $e->getMessage());
            throw new Exception('Image optimization failed: ' . $e->getMessage());
        }
        
    } else {
        error_log("ImageUploader class not available, using basic upload");
        
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File terlalu besar (maksimal 5MB)');
        }
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Invalid file type: ' . $mime_type);
        }
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_extensions)) {
            throw new Exception('Invalid file extension: ' . $extension);
        }
        
        $upload_dir = __DIR__ . '/../uploads/articles/' . $image_folder . '/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        $filename = 'article_' . time() . '_' . uniqid() . '_' . mt_rand(1000, 9999) . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        chmod($filepath, 0644);
        
        $width = 0;
        $height = 0;
        if ($image_info = getimagesize($filepath)) {
            $width = $image_info[0];
            $height = $image_info[1];
        }
        
        $size_kb = round(filesize($filepath) / 1024, 2);
        $image_url = 'https://inievan.my.id/project/uploads/articles/' . $image_folder . '/' . $filename;
        $final_mime = $mime_type;
        $folder = $image_folder;
        
        $response_data = [
            'image_id' => null,
            'filename' => $filename,
            'folder' => $folder,
            'relative_path' => $image_folder . '/' . $filename,
            'url' => $image_url,
            'size' => $size_kb . ' KB',
            'dimensions' => $width . 'x' . $height,
            'mime_type' => $final_mime,
            'optimized' => false,
            'webp_converted' => false,
            'upload_time' => date('Y-m-d H:i:s')
        ];
        
        error_log("Basic upload success: " . $filename . " in folder: " . $folder);
    }
    
    // Save to database
    try {
        $insert_query = "INSERT INTO images (filename, url, mime, source_type, is_external, created_at) 
                         VALUES (?, ?, ?, 'article', 0, NOW())";
        
        $stmt = mysqli_prepare($koneksi, $insert_query);
        if (!$stmt) {
            throw new Exception('Database prepare failed: ' . mysqli_error($koneksi));
        }
        
        mysqli_stmt_bind_param($stmt, "sss", $filename, $response_data['url'], $final_mime);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Database insert failed: ' . mysqli_error($koneksi));
        }
        
        $image_id = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);
        
        error_log("Image saved to database with ID: " . $image_id);
        
    } catch (Exception $db_error) {
        // Clean up uploaded file on database error
        if (isset($uploader)) {
            $uploader->delete($filename);
        } else if (isset($filepath) && file_exists($filepath)) {
            unlink($filepath);
        }
        throw $db_error;
    }
    
    $response_data['image_id'] = $image_id;
    
    $success_message = 'Image uploaded successfully';
    if (isset($response_data['optimized']) && $response_data['optimized']) {
        $success_message .= ' and optimized (WebP conversion, compression)';
    }
    $success_message .= ' - Folder: ' . $response_data['folder'];
    
    sendUploadResponse(true, $success_message, $response_data);
    
} catch (Exception $e) {
    error_log("Upload handler error: " . $e->getMessage());
    
    http_response_code(500);
    sendUploadResponse(false, $e->getMessage(), [
        'debug_info' => [
            'script_path' => __FILE__,
            'working_directory' => getcwd(),
            'php_version' => PHP_VERSION,
            'imageuploader_available' => class_exists('ImageUploader'),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>