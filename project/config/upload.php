<?php


class ImageUploader {
    
    private $upload_dir;
    private $max_file_size;
    private $allowed_types;
    private $allowed_extensions;
    
    public function __construct($subdirectory = 'articles') {
        $this->upload_dir = __DIR__ . '/../uploads/' . $subdirectory . '/';
        $this->max_file_size = 300 * 1024; // 300KB
        $this->allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $this->allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        // Buat folder jika belum ada
        $this->createUploadDirectory();
    }
    
    /**
     * Buat direktori upload jika belum ada
     */
    private function createUploadDirectory() {
        if (!file_exists($this->upload_dir)) {
            if (!mkdir($this->upload_dir, 0755, true)) {
                throw new Exception('Gagal membuat direktori upload.');
            }
            
            // Buat file .htaccess untuk keamanan
            $this->createSecurityFiles();
        }
    }
    
    /**
     * Buat file keamanan di direktori upload
     */
    private function createSecurityFiles() {
        // .htaccess untuk mencegah eksekusi PHP dan pembatasan file
        $htaccess_content = '# Prevent PHP execution
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.phtml">
    Order Deny,Allow  
    Deny from all
</Files>

# Only allow image files
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Deny everything else by default
<FilesMatch "^.*$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Override for images
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>';

        file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
        
        // index.php untuk mencegah directory listing
        $index_content = '<?php
// Prevent directory listing
header("Location: ../../");
exit();
?>';

file_put_contents($this->upload_dir . 'index.php', $index_content);
}

/**
* Validasi file upload
*/
private function validateFile($file) {
// Cek error upload
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

// Validasi ukuran
if ($file['size'] > $this->max_file_size) {
throw new Exception('Ukuran file terlalu besar. Maksimal ' . ($this->max_file_size / 1024) . 'KB.');
}

// Validasi tipe MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $this->allowed_types)) {
throw new Exception('Format file tidak didukung. Hanya JPG, PNG, WEBP, dan GIF yang diizinkan.');
}

// Validasi ekstensi
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $this->allowed_extensions)) {
throw new Exception('Ekstensi file tidak valid.');
}

// Validasi apakah benar-benar gambar
$image_info = getimagesize($file['tmp_name']);
if (!$image_info) {
throw new Exception('File yang diupload bukan gambar yang valid.');
}

return true;
}

/**
* Generate nama file unik
*/
private function generateFilename($original_filename) {
$extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
return 'article_' . time() . '_' . uniqid() . '_' . mt_rand(1000, 9999) . '.' . $extension;
}

/**
* Upload file gambar
*/
public function upload($file) {
$this->validateFile($file);

$filename = $this->generateFilename($file['name']);
$filepath = $this->upload_dir . $filename;

// Pindahkan file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
throw new Exception('Gagal menyimpan file.');
}

// Set permission
chmod($filepath, 0644);

// Optimasi gambar jika perlu
$this->optimizeImage($filepath);

return $filename;
}

/**
* Optimasi gambar (compress dan resize jika terlalu besar)
*/
private function optimizeImage($filepath) {
$image_info = getimagesize($filepath);
if (!$image_info) return;

$width = $image_info[0];
$height = $image_info[1];
$type = $image_info[2];

// Resize jika gambar terlalu besar
$max_width = 1200;
$max_height = 800;

if ($width > $max_width || $height > $max_height) {
$this->resizeImage($filepath, $max_width, $max_height);
}

// Compress gambar
$this->compressImage($filepath, $type);
}

/**
* Resize gambar
*/
private function resizeImage($filepath, $max_width, $max_height) {
$image_info = getimagesize($filepath);
$width = $image_info[0];
$height = $image_info[1];
$type = $image_info[2];

// Hitung dimensi baru
$ratio = min($max_width / $width, $max_height / $height);
$new_width = $width * $ratio;
$new_height = $height * $ratio;

// Buat image resource
switch ($type) {
case IMAGETYPE_JPEG:
$source = imagecreatefromjpeg($filepath);
break;
case IMAGETYPE_PNG:
$source = imagecreatefrompng($filepath);
break;
case IMAGETYPE_WEBP:
$source = imagecreatefromwebp($filepath);
break;
case IMAGETYPE_GIF:
$source = imagecreatefromgif($filepath);
break;
default:
return;
}

// Buat canvas baru
$destination = imagecreatetruecolor($new_width, $new_height);

// Preserve transparency
if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
imagealphablending($destination, false);
imagesavealpha($destination, true);
$transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
}

// Resize
imagecopyresampled($destination, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

// Simpan
switch ($type) {
case IMAGETYPE_JPEG:
imagejpeg($destination, $filepath, 85);
break;
case IMAGETYPE_PNG:
imagepng($destination, $filepath);
break;
case IMAGETYPE_WEBP:
imagewebp($destination, $filepath, 85);
break;
case IMAGETYPE_GIF:
imagegif($destination, $filepath);
break;
}

imagedestroy($source);
imagedestroy($destination);
}

/**
* Compress gambar
*/
private function compressImage($filepath, $type) {
switch ($type) {
case IMAGETYPE_JPEG:
$image = imagecreatefromjpeg($filepath);
imagejpeg($image, $filepath, 80); // Quality 80%
imagedestroy($image);
break;
case IMAGETYPE_WEBP:
$image = imagecreatefromwebp($filepath);
imagewebp($image, $filepath, 80); // Quality 80%
imagedestroy($image);
break;
}
}

/**
* Hapus file
*/
public function delete($filename) {
if (empty($filename)) return true;

$filepath = $this->upload_dir . $filename;

if (file_exists($filepath)) {
return unlink($filepath);
}

return true;
}

/**
* Get URL gambar
*/
public function getUrl($filename) {
if (empty($filename)) return null;

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
"://" . $_SERVER['HTTP_HOST'];

$script_dir = dirname($_SERVER['SCRIPT_NAME']);
return $base_url . $script_dir . '/uploads/articles/' . $filename;
}

/**
* Cek apakah file exists
*/
public function exists($filename) {
return file_exists($this->upload_dir . $filename);
}

/**
* Get file size
*/
public function getFileSize($filename) {
$filepath = $this->upload_dir . $filename;
return file_exists($filepath) ? filesize($filepath) : 0;
}

/**
* Get file info
*/
public function getFileInfo($filename) {
$filepath = $this->upload_dir . $filename;

if (!file_exists($filepath)) {
return null;
}

$image_info = getimagesize($filepath);

return [
'filename' => $filename,
'filepath' => $filepath,
'size' => filesize($filepath),
'width' => $image_info[0] ?? 0,
'height' => $image_info[1] ?? 0,
'mime_type' => $image_info['mime'] ?? '',
'url' => $this->getUrl($filename)
];
}
}

/**
* Function helper untuk mudah digunakan
*/
function uploadArticleImage($file) {
$uploader = new ImageUploader('articles');
return $uploader->upload($file);
}

function deleteArticleImage($filename) {
$uploader = new ImageUploader('articles');
return $uploader->delete($filename);
}

function getArticleImageUrl($filename) {
if (empty($filename)) return null;
$uploader = new ImageUploader('articles');
return $uploader->getUrl($filename);
}

?>