<?php
// config.php - FIXED VERSION

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi error reporting untuk production
// Jangan tampilkan error ke user, tapi log ke file
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Konfigurasi database - sesuai kredensial asli Anda
$host = "103.180.162.183";
$user = "arinnapr_uinievan";
$pass = ")^YZ!dZxr{l2";
$db   = "arinnapr_dbinievan";

// Buat koneksi database
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi dan handle error
if (!$koneksi) {
    error_log("Koneksi database gagal: " . mysqli_connect_error());
    
    // Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Koneksi database gagal. Silakan coba lagi nanti.'
        ]);
        exit;
    }
    
    die("Koneksi database gagal. Silakan coba lagi nanti.");
}

// Set charset untuk mendukung karakter Indonesia
mysqli_set_charset($koneksi, "utf8");

// Load SettingsManager
require_once __DIR__ . '/SettingsManager.php';

// Inisialisasi SettingsManager dengan proper error handling
try {
    $settingsManager = SettingsManager::getInstance($koneksi);
    
    // Load semua settings
    $settingsManager->loadSettings();
    
} catch (Exception $e) {
    error_log("Error loading SettingsManager: " . $e->getMessage());
    
    // Fallback jika SettingsManager gagal
    $settingsManager = new class {
        public function get($key, $default = null) { return $default; }
        public function set($key, $value) { return true; }
        public function getAllSettings() { return []; }
        public function loadSettings() { return true; }
    };
}

// Load maintenance check
require_once __DIR__ . '/maintenance_check.php';

// Load visitor tracking system
require_once __DIR__ . '/visitor.php';

// Function untuk membersihkan input
function sanitize_input($data) {
    global $koneksi;
    if (!$koneksi) return htmlspecialchars(trim($data));
    return mysqli_real_escape_string($koneksi, htmlspecialchars(trim($data)));
}

// Function untuk hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function untuk verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Function untuk get setting dengan fallback
function getSetting($key, $default = null) {
    global $settingsManager;
    if ($settingsManager && method_exists($settingsManager, 'get')) {
        return $settingsManager->get($key, $default);
    }
    return $default;
}

// Function untuk set setting dengan error handling
function setSetting($key, $value) {
    global $settingsManager;
    if ($settingsManager && method_exists($settingsManager, 'set')) {
        return $settingsManager->set($key, $value);
    }
    return false;
}

// Function untuk get site info
function getSiteInfo() {
    return [
        'name' => getSetting('site_name', 'Sorot Dunia'),
        'description' => getSetting('site_description', 'Portal berita terpercaya Indonesia'),
        'keywords' => getSetting('site_keywords', 'berita, news, artikel, indonesia'),
        'admin_email' => getSetting('admin_email', 'admin@sorotdunia.com')
    ];
}

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_ARTICLES_DIR', UPLOAD_DIR . 'articles/');
define('MAX_FILE_SIZE', 300 * 1024); // 300KB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Function untuk validasi dan upload file gambar
function uploadImage($file, $subdirectory = 'articles') {
    $upload_dir = UPLOAD_DIR . $subdirectory . '/';
    
    // Buat folder jika belum ada
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Gagal membuat direktori upload.');
        }
    }
    
    // Validasi error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File terlalu besar.');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('File tidak terupload dengan sempurna.');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('Tidak ada file yang dipilih.');
            default:
                throw new Exception('Terjadi kesalahan saat upload.');
        }
    }
    
    // Validasi ukuran file
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Ukuran file terlalu besar. Maksimal ' . (MAX_FILE_SIZE / 1024) . 'KB.');
    }
    
    // Validasi tipe file berdasarkan MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
        throw new Exception('Format file tidak didukung. Hanya JPG, PNG, WEBP, dan GIF yang diizinkan.');
    }
    
    // Validasi ekstensi file
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
        throw new Exception('Ekstensi file tidak valid.');
    }
    
    // Generate nama file unik
    $filename = $subdirectory . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Pindahkan file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal menyimpan file.');
    }
    
    // Set permission file
    chmod($filepath, 0644);
    
    return $filename;
}

// Function untuk hapus file upload
function deleteUploadedFile($filename, $subdirectory = 'articles') {
    if (empty($filename)) return true;
    
    $filepath = UPLOAD_DIR . $subdirectory . '/' . $filename;
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return true;
}

// Function untuk get full URL gambar
function getImageUrl($filename, $subdirectory = 'articles') {
    if (empty($filename)) return null;
    
    // Assuming your site URL structure
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                "://" . $_SERVER['HTTP_HOST'];
    
    // Get the directory structure relative to document root
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $upload_url = $base_url . $script_dir . '/uploads/' . $subdirectory . '/' . $filename;
    
    return $upload_url;
}

// Function untuk resize gambar jika diperlukan
function resizeImage($source_file, $destination_file, $max_width = 800, $max_height = 600, $quality = 85) {
    $image_info = getimagesize($source_file);
    if (!$image_info) {
        throw new Exception('File bukan gambar yang valid.');
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $image_type = $image_info[2];
    
    // Jika gambar sudah kecil, tidak perlu resize
    if ($original_width <= $max_width && $original_height <= $max_height) {
        return copy($source_file, $destination_file);
    }
    
    // Hitung dimensi baru dengan mempertahankan aspect ratio
    $ratio = min($max_width / $original_width, $max_height / $original_height);
    $new_width = $original_width * $ratio;
    $new_height = $original_height * $ratio;
    
    // Buat image resource dari file sumber
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_file);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_file);
            break;
        case IMAGETYPE_WEBP:
            $source_image = imagecreatefromwebp($source_file);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_file);
            break;
        default:
            throw new Exception('Format gambar tidak didukung untuk resize.');
    }
    
    // Buat canvas baru
    $destination_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency untuk PNG dan GIF
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($destination_image, false);
        imagesavealpha($destination_image, true);
        $transparent = imagecolorallocatealpha($destination_image, 255, 255, 255, 127);
        imagefilledrectangle($destination_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize gambar
    imagecopyresampled(
        $destination_image, $source_image,
        0, 0, 0, 0,
        $new_width, $new_height,
        $original_width, $original_height
    );
    
    // Simpan gambar hasil resize
    $result = false;
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($destination_image, $destination_file, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($destination_image, $destination_file);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($destination_image, $destination_file, $quality);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($destination_image, $destination_file);
            break;
    }
    
    // Bersihkan memory
    imagedestroy($source_image);
    imagedestroy($destination_image);
    
    return $result;
}

// Variabel global untuk digunakan di seluruh aplikasi
$GLOBALS['koneksi'] = $koneksi;
$GLOBALS['settingsManager'] = $settingsManager;

// Initialize visitor tracking for public pages only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Only track visitors on public pages
    if (strpos($_SERVER['REQUEST_URI'], '/admin/') === false) {
        initVisitorTracking($koneksi);
    }
}
