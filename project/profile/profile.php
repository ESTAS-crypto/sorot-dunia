<?php
require_once '../config/auth_check.php';
require_once '../config/config.php';
require_once '../ajax/image_config.php';

// Pastikan hanya user yang login yang bisa akses
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../project/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// PERBAIKAN: Regenerate CSRF token jika belum ada atau expired
if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
    (time() - $_SESSION['csrf_token_time']) > 3600) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
    error_log("[PROFILE] New CSRF token generated for user ID: $user_id");
}

$csrf_token = $_SESSION['csrf_token'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PERBAIKAN: Logging untuk debugging
    error_log("[PROFILE] POST request received");
    error_log("[PROFILE] POST data: " . print_r($_POST, true));
    error_log("[PROFILE] Session CSRF: " . ($_SESSION['csrf_token'] ?? 'NOT SET'));
    error_log("[PROFILE] POST CSRF: " . ($_POST['csrf_token'] ?? 'NOT SET'));
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token'])) {
        $error_message = 'Token keamanan tidak ditemukan. Silakan refresh halaman.';
        error_log("[PROFILE] CSRF token not found in POST data");
    } elseif (!isset($_SESSION['csrf_token'])) {
        $error_message = 'Session tidak valid. Silakan login ulang.';
        error_log("[PROFILE] CSRF token not found in session");
    } elseif ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Token keamanan tidak cocok. Silakan refresh halaman dan coba lagi.';
        error_log("[PROFILE] CSRF token mismatch - Session: " . $_SESSION['csrf_token'] . " vs POST: " . $_POST['csrf_token']);
    } else {
        error_log("[PROFILE] CSRF token validated successfully");
        
        // ========== HANDLE PROFILE DATA UPDATE (WITH BIO) ==========
        if (isset($_POST['update_profile'])) {
            try {
                error_log("========== PROFILE UPDATE START ==========");
                error_log("[PROFILE] User ID: $user_id attempting profile update");
                
                $username = sanitize_input($_POST['username']);
                $full_name = sanitize_input($_POST['full_name']);
                $email = sanitize_input($_POST['email']);
                $bio = sanitize_input($_POST['bio'] ?? '');
                
                error_log("[PROFILE] Sanitized data - Username: '$username', Full Name: '$full_name', Email: '$email', Bio: '$bio'");
                
                // Basic validation
                if (empty($username) || empty($full_name) || empty($email)) {
                    throw new Exception('Username, nama lengkap, dan email wajib diisi.');
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Format email tidak valid.');
                }
                
                // Username validation
                if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    throw new Exception('Username minimal 3 karakter dan hanya boleh huruf, angka, dan underscore.');
                }
                
                // Check username uniqueness
                $check_username_query = "SELECT id FROM users WHERE username = ? AND id != ?";
                $stmt = mysqli_prepare($koneksi, $check_username_query);
                if (!$stmt) {
                    throw new Exception('Gagal mempersiapkan query validasi username: ' . mysqli_error($koneksi));
                }
                mysqli_stmt_bind_param($stmt, "si", $username, $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    throw new Exception('Username sudah digunakan pengguna lain.');
                }
                mysqli_stmt_close($stmt);
                
                // Check email uniqueness
                $check_email_query = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt = mysqli_prepare($koneksi, $check_email_query);
                if (!$stmt) {
                    throw new Exception('Gagal mempersiapkan query validasi email: ' . mysqli_error($koneksi));
                }
                mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    throw new Exception('Email sudah digunakan pengguna lain.');
                }
                mysqli_stmt_close($stmt);
                
                error_log("[PROFILE] Validation passed, updating database");
                
                // Update user data WITH BIO field
                $update_query = "UPDATE users SET username = ?, full_name = ?, email = ?, bio = ? WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $update_query);
                
                if (!$stmt) {
                    error_log("[PROFILE] ✗ Failed to prepare statement: " . mysqli_error($koneksi));
                    throw new Exception('Gagal mempersiapkan query: ' . mysqli_error($koneksi));
                }
                
                mysqli_stmt_bind_param($stmt, "ssssi", $username, $full_name, $email, $bio, $user_id);
                
                error_log("[PROFILE] Executing UPDATE query");
                
                if (mysqli_stmt_execute($stmt)) {
                    $affected_rows = mysqli_stmt_affected_rows($stmt);
                    error_log("[PROFILE] Query executed successfully. Affected rows: $affected_rows");
                    mysqli_stmt_close($stmt);
                    
                    // Reload data dari database
                    $verify_query = "SELECT username, full_name, email, bio, role FROM users WHERE id = ?";
                    $verify_stmt = mysqli_prepare($koneksi, $verify_query);
                    mysqli_stmt_bind_param($verify_stmt, "i", $user_id);
                    mysqli_stmt_execute($verify_stmt);
                    $verify_result = mysqli_stmt_get_result($verify_stmt);
                    $updated_data = mysqli_fetch_assoc($verify_result);
                    mysqli_stmt_close($verify_stmt);
                    
                    if ($updated_data) {
                        // Update session
                        $_SESSION['username'] = $updated_data['username'];
                        $_SESSION['full_name'] = $updated_data['full_name'];
                        $_SESSION['user_email'] = $updated_data['email'];
                        $_SESSION['bio'] = $updated_data['bio'];
                        $_SESSION['user_role'] = $updated_data['role'];
                        
                        error_log("[PROFILE] Session updated with fresh data");
                        
                        $success_message = 'Profile berhasil diperbarui!';
                        error_log("[PROFILE] ✓ Profile updated successfully for user ID: $user_id");
                        logUserActivity('profile_update', 'Profile updated successfully');
                        
                        // Force reload user_data
                        $user_data = $updated_data;
                    } else {
                        throw new Exception('Gagal memverifikasi data yang diupdate.');
                    }
                    
                } else {
                    $error_msg = mysqli_stmt_error($stmt);
                    error_log("[PROFILE] ✗ Execute failed: $error_msg");
                    mysqli_stmt_close($stmt);
                    throw new Exception('Gagal memperbarui profile: ' . $error_msg);
                }
                
                error_log("========== PROFILE UPDATE END ==========");
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                error_log("[PROFILE] ✗ Profile update error: " . $e->getMessage());
                logSecurityEvent('profile_update_error', $e->getMessage());
            }
        }
        
        // ========== HANDLE PROFILE IMAGE UPLOAD ==========
        if (isset($_POST['update_image'])) {
            try {
                error_log("========== IMAGE UPDATE START ==========");
                error_log("[IMAGE] User ID: $user_id attempting image update");
                
                // Get current avatar_image_id dan filename
                $current_query = "SELECT avatar_image_id FROM users WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $current_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $current_result = mysqli_stmt_get_result($stmt);
                $current_data = mysqli_fetch_assoc($current_result);
                $old_image_id = $current_data['avatar_image_id'] ?? null;
                mysqli_stmt_close($stmt);
                
                error_log("[IMAGE] Current avatar_image_id: " . ($old_image_id ?: 'NULL'));
                
                // Get old filename if exists
                $old_filename = null;
                if (!empty($old_image_id)) {
                    $old_img_query = "SELECT filename FROM images WHERE id = ?";
                    $old_stmt = mysqli_prepare($koneksi, $old_img_query);
                    mysqli_stmt_bind_param($old_stmt, "i", $old_image_id);
                    mysqli_stmt_execute($old_stmt);
                    $old_img_result = mysqli_stmt_get_result($old_stmt);
                    
                    if ($old_img_data = mysqli_fetch_assoc($old_img_result)) {
                        $old_filename = $old_img_data['filename'];
                        error_log("[IMAGE] Old filename found: " . $old_filename);
                    }
                    mysqli_stmt_close($old_stmt);
                }
                
                // Check if new image file is uploaded
                if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Tidak ada file gambar yang dipilih atau terjadi error saat upload.');
                }
                
                $file = $_FILES['profile_image'];
                error_log("[IMAGE] File detected: " . $file['name']);
                
                // PERBAIKAN: Upload ke folder profile_image langsung (tanpa subfolder status)
                $uploader = new ImageUploader('profile_image');
                $upload_result = $uploader->upload($file);
                $new_image_filename = $upload_result['filename'];
                
                error_log("[IMAGE] ✓ Upload successful! New filename: $new_image_filename");
                
                // Insert ke tabel images
                $image_url = $uploader->getUrl($new_image_filename);
                $insert_image_query = "INSERT INTO images (filename, url, mime, source_type, source_id, is_external, created_at) 
                                      VALUES (?, ?, 'image/webp', 'profile', ?, 0, NOW())";
                $stmt = mysqli_prepare($koneksi, $insert_image_query);
                mysqli_stmt_bind_param($stmt, "ssi", $new_image_filename, $image_url, $user_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    error_log("[IMAGE] ✗ Failed to insert image record");
                    $uploader->delete($new_image_filename);
                    throw new Exception('Gagal menyimpan informasi gambar ke database');
                }
                
                $new_image_id = mysqli_insert_id($koneksi);
                mysqli_stmt_close($stmt);
                error_log("[IMAGE] ✓ Image record created with ID: $new_image_id");
                
                // Update user table
                $update_user_query = "UPDATE users SET avatar_image_id = ? WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $update_user_query);
                mysqli_stmt_bind_param($stmt, "ii", $new_image_id, $user_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    // Rollback: hapus record image yang baru dibuat
                    $delete_img_query = "DELETE FROM images WHERE id = ?";
                    $del_stmt = mysqli_prepare($koneksi, $delete_img_query);
                    mysqli_stmt_bind_param($del_stmt, "i", $new_image_id);
                    mysqli_stmt_execute($del_stmt);
                    mysqli_stmt_close($del_stmt);
                    
                    // Hapus file fisik
                    $uploader->delete($new_image_filename);
                    
                    mysqli_stmt_close($stmt);
                    throw new Exception('Gagal menyimpan gambar ke database');
                }
                
                mysqli_stmt_close($stmt);
                error_log("[IMAGE] ✓ User table updated successfully");
                
                // PERBAIKAN: Delete old image (file fisik DAN database record) SETELAH upload berhasil
                if (!empty($old_image_id) && !empty($old_filename)) {
                    error_log("[IMAGE] Deleting old image...");
                    
                    // Delete file fisik menggunakan ImageUploader untuk profile_image
                    $old_uploader = new ImageUploader('profile_image');
                    $delete_result = $old_uploader->delete($old_filename);
                    error_log("[IMAGE] Old file deletion result: " . ($delete_result ? 'SUCCESS' : 'FAILED'));
                    
                    // Delete database record
                    $delete_old_query = "DELETE FROM images WHERE id = ?";
                    $del_stmt = mysqli_prepare($koneksi, $delete_old_query);
                    mysqli_stmt_bind_param($del_stmt, "i", $old_image_id);
                    $db_delete_result = mysqli_stmt_execute($del_stmt);
                    mysqli_stmt_close($del_stmt);
                    
                    error_log("[IMAGE] Old database record deletion result: " . ($db_delete_result ? 'SUCCESS' : 'FAILED'));
                    error_log("[IMAGE] ✓ Old image cleaned up");
                }
                
                $success_message = 'Foto profile berhasil diperbarui!';
                error_log("[IMAGE] ✓ Image update completed");
                logUserActivity('profile_image_updated', 'Profile image updated');
                
                error_log("========== IMAGE UPDATE END ==========");
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                error_log("[IMAGE] ✗ Image update error: " . $e->getMessage());
                logSecurityEvent('profile_image_update_error', $e->getMessage());
            }
        }
        
        // ========== HANDLE REMOVE PROFILE IMAGE ==========
        if (isset($_POST['remove_image'])) {
            try {
                error_log("========== IMAGE REMOVAL START ==========");
                
                $current_query = "SELECT avatar_image_id FROM users WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $current_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $current_result = mysqli_stmt_get_result($stmt);
                $current_data = mysqli_fetch_assoc($current_result);
                $old_image_id = $current_data['avatar_image_id'] ?? null;
                mysqli_stmt_close($stmt);
                
                if (empty($old_image_id)) {
                    throw new Exception('Tidak ada foto profile yang perlu dihapus.');
                }
                
                // Get image filename
                $img_query = "SELECT filename FROM images WHERE id = ?";
                $img_stmt = mysqli_prepare($koneksi, $img_query);
                mysqli_stmt_bind_param($img_stmt, "i", $old_image_id);
                mysqli_stmt_execute($img_stmt);
                $img_result = mysqli_stmt_get_result($img_stmt);
                $img_data = mysqli_fetch_assoc($img_result);
                $old_filename = $img_data['filename'] ?? '';
                mysqli_stmt_close($img_stmt);
                
                // Remove avatar_image_id from users table
                $update_query = "UPDATE users SET avatar_image_id = NULL WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Gagal menghapus foto dari database');
                }
                mysqli_stmt_close($stmt);
                
                // PERBAIKAN: Delete physical file using profile_image uploader
                if (!empty($old_filename)) {
                    $uploader = new ImageUploader('profile_image');
                    $uploader->delete($old_filename);
                    error_log("[REMOVE] File deleted: " . $old_filename);
                }
                
                // Delete image record from database
                if ($old_image_id) {
                    $delete_img_query = "DELETE FROM images WHERE id = ?";
                    $del_stmt = mysqli_prepare($koneksi, $delete_img_query);
                    mysqli_stmt_bind_param($del_stmt, "i", $old_image_id);
                    mysqli_stmt_execute($del_stmt);
                    mysqli_stmt_close($del_stmt);
                    error_log("[REMOVE] Database record deleted: ID " . $old_image_id);
                }
                
                $success_message = 'Foto profile berhasil dihapus!';
                error_log("[REMOVE] ✓ Image removal completed");
                logUserActivity('profile_image_removed', 'Profile image removed');
                
                error_log("========== IMAGE REMOVAL END ==========");
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                error_log("[REMOVE] ✗ Image removal error: " . $e->getMessage());
                logSecurityEvent('profile_image_remove_error', $e->getMessage());
            }
        }
        
        // ========== HANDLE PASSWORD CHANGE ==========
        if (isset($_POST['change_password'])) {
            try {
                error_log("========== PASSWORD CHANGE START ==========");
                
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception('Semua field password wajib diisi.');
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception('Password baru dan konfirmasi password tidak cocok.');
                }
                
                if (strlen($new_password) < 6) {
                    throw new Exception('Password baru minimal 6 karakter.');
                }
                
                // Get current password hash
                $pass_query = "SELECT password FROM users WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $pass_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $pass_result = mysqli_stmt_get_result($stmt);
                $pass_data = mysqli_fetch_assoc($pass_result);
                mysqli_stmt_close($stmt);
                
                if (!verify_password($current_password, $pass_data['password'])) {
                    throw new Exception('Password saat ini salah.');
                }
                
                // Update password
                $new_password_hash = hash_password($new_password);
                $update_pass_query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = mysqli_prepare($koneksi, $update_pass_query);
                mysqli_stmt_bind_param($stmt, "si", $new_password_hash, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Password berhasil diubah!';
                    error_log("[PASSWORD] ✓ Password changed successfully");
                    logUserActivity('password_changed', 'Password changed successfully');
                } else {
                    throw new Exception('Gagal mengubah password');
                }
                
                mysqli_stmt_close($stmt);
                error_log("========== PASSWORD CHANGE END ==========");
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                error_log("[PASSWORD] ✗ Password change error: " . $e->getMessage());
                logSecurityEvent('password_change_error', $e->getMessage());
            }
        }
    }
}

// Get user data FRESH from database
error_log("[PROFILE] Loading FRESH user data for user ID: $user_id");

$user_query = "SELECT id, username, email, full_name, bio, role, avatar_url, avatar_image_id FROM users WHERE id = ?";
$stmt = mysqli_prepare($koneksi, $user_query);

if (!$stmt) {
    error_log("[PROFILE] ✗ Failed to prepare select query: " . mysqli_error($koneksi));
    die('Gagal memuat data user');
}

mysqli_stmt_bind_param($stmt, "i", $user_id);

if (!mysqli_stmt_execute($stmt)) {
    error_log("[PROFILE] ✗ Failed to execute select query");
    die('Gagal mengeksekusi query');
}

$user_result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($user_result);

if (!$user_data) {
    error_log("[PROFILE] ✗ User not found for ID: $user_id");
    die('User tidak ditemukan');
}

error_log("[PROFILE] User data loaded successfully");
mysqli_stmt_close($stmt);

// Get profile image URL
$profile_image_url = '';

if (!empty($user_data['avatar_image_id'])) {
    $img_query = "SELECT filename, url FROM images WHERE id = ?";
    $img_stmt = mysqli_prepare($koneksi, $img_query);
    mysqli_stmt_bind_param($img_stmt, "i", $user_data['avatar_image_id']);
    mysqli_stmt_execute($img_stmt);
    $img_result = mysqli_stmt_get_result($img_stmt);
    
    if ($img_data = mysqli_fetch_assoc($img_result)) {
        $profile_image_url = $img_data['url'];
    }
    mysqli_stmt_close($img_stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?= htmlspecialchars($user_data['full_name']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: #e0e0e0;
        min-height: 100vh;
        line-height: 1.6;
    }

    .container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .header h1 {
        color: #ffffff;
        margin-bottom: 10px;
        font-size: 2.2em;
        font-weight: 700;
    }

    .breadcrumb {
        color: #a0a0a0;
        font-size: 0.9em;
    }

    .breadcrumb a {
        color: #4a9eff;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .breadcrumb a:hover {
        color: #66b3ff;
    }

    .alert {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .profile-card {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .profile-header {
        display: flex;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .profile-image {
        position: relative;
        margin-right: 25px;
    }

    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid rgba(255, 255, 255, 0.2);
    }

    .default-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4a9eff 0%, #0066cc 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: white;
        font-weight: bold;
        border: 4px solid rgba(255, 255, 255, 0.2);
    }

    .profile-info h2 {
        color: #ffffff;
        margin-bottom: 5px;
        font-size: 1.8em;
    }

    .profile-info .role {
        background: linear-gradient(135deg, #4a9eff 0%, #0066cc 100%);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 10px;
    }

    .form-tabs {
        display: flex;
        margin-bottom: 30px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        padding: 5px;
    }

    .tab-button {
        flex: 1;
        padding: 12px;
        background: none;
        border: none;
        color: #a0a0a0;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-size: 0.95em;
        font-weight: 500;
    }

    .tab-button.active {
        background: linear-gradient(135deg, #4a9eff 0%, #0066cc 100%);
        color: white;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #ffffff;
        font-weight: 600;
        font-size: 0.95em;
    }

    .form-control {
        width: 100%;
        padding: 15px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        color: #ffffff;
        font-size: 1em;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #4a9eff;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 0 0 3px rgba(74, 158, 255, 0.1);
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    .file-input-wrapper {
        position: relative;
        display: inline-block;
        cursor: pointer;
        width: 100%;
    }

    .file-input {
        opacity: 0;
        position: absolute;
        z-index: -1;
    }

    .file-input-label {
        display: block;
        padding: 15px;
        background: rgba(255, 255, 255, 0.05);
        border: 2px dashed rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        text-align: center;
        color: #a0a0a0;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .file-input-label:hover {
        border-color: #4a9eff;
        background: rgba(74, 158, 255, 0.05);
        color: #4a9eff;
    }

    .image-preview {
        margin-top: 15px;
        text-align: center;
    }

    .image-preview img {
        max-width: 200px;
        max-height: 200px;
        border-radius: 10px;
        border: 2px solid rgba(255, 255, 255, 0.1);
    }

    .btn {
        display: inline-block;
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.95em;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease;
        min-width: 120px;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4a9eff 0%, #0066cc 100%);
        color: white;
    }

    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(74, 158, 255, 0.3);
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .btn-danger:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: #e0e0e0;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .form-note {
        font-size: 0.85em;
        color: #a0a0a0;
        margin-top: 5px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #4a9eff;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        color: #66b3ff;
        transform: translateX(-5px);
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .profile-header {
            flex-direction: column;
            text-align: center;
        }

        .profile-image {
            margin-right: 0;
            margin-bottom: 20px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <a href="../" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Home
        </a>

        <div class="header">
            <h1>Profile Saya</h1>
            <div class="breadcrumb">
                <a href="../">Home</a> / Profile
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-image">
                    <?php if ($profile_image_url): ?>
                        <img src="<?= htmlspecialchars($profile_image_url) ?>" alt="Profile Image" class="profile-avatar">
                    <?php else: ?>
                        <div class="default-avatar">
                            <?= strtoupper(substr($user_data['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($user_data['full_name']) ?></h2>
                    <div class="role"><?= ucfirst(htmlspecialchars($user_data['role'])) ?></div>
                </div>
            </div>

            <div class="form-tabs">
                <button class="tab-button active" onclick="showTab('profile')">
                    <i class="fas fa-user"></i> Data Profile
                </button>
                <button class="tab-button" onclick="showTab('image')">
                    <i class="fas fa-camera"></i> Foto Profile
                </button>
                <button class="tab-button" onclick="showTab('password')">
                    <i class="fas fa-lock"></i> Ubah Password
                </button>
            </div>

            <!-- Profile Data Tab -->
            <div id="profile-tab" class="tab-content active">
                <form method="POST" action="" id="profileDataForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?= htmlspecialchars($user_data['username']) ?>" required>
                        <div class="form-note">Username minimal 3 karakter (huruf, angka, underscore)</div>
                    </div>

                    <div class="form-group">
                        <label for="full_name">Nama Lengkap *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?= htmlspecialchars($user_data['full_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($user_data['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" class="form-control" rows="4"><?= htmlspecialchars($user_data['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>

            <!-- Image Tab -->
            <div id="image-tab" class="tab-content">
                <form method="POST" action="" enctype="multipart/form-data" id="imageUploadForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-group">
                        <label for="profile_image">Foto Profile</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="profile_image" name="profile_image" class="file-input" 
                                   accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImage(this)">
                            <label for="profile_image" class="file-input-label">
                                <i class="fas fa-cloud-upload-alt"></i><br>
                                Klik untuk pilih gambar<br>
                                <small>JPG, PNG, WebP, GIF (Max: 300KB)</small>
                            </label>
                        </div>
                        
                        <div class="image-preview" id="image-preview">
                            <?php if ($profile_image_url): ?>
                                <img src="<?= htmlspecialchars($profile_image_url) ?>?v=<?= time() ?>" alt="Current Profile Image">
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" name="update_image" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Foto
                    </button>
                    
                    <?php if ($profile_image_url): ?>
                    <button type="button" class="btn btn-danger" onclick="removeImage()">
                        <i class="fas fa-trash"></i> Hapus Foto
                    </button>
                    <?php endif; ?>
                </form>
                
                <form method="POST" action="" id="removeImageForm" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="remove_image" value="1">
                </form>
            </div>

            <!-- Password Tab -->
            <div id="password-tab" class="tab-content">
                <form method="POST" action="" id="passwordChangeForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-group">
                        <label for="current_password">Password Saat Ini *</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">Password Baru *</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <div class="form-note">Password minimal 6 karakter</div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password Baru *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Ubah Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            if (confirm('Yakin ingin menghapus foto profile?')) {
                document.getElementById('removeImageForm').submit();
            }
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Form validation
        document.getElementById('profileDataForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const fullName = document.getElementById('full_name').value;
            const email = document.getElementById('email').value;
            
            if (!username || !fullName || !email) {
                e.preventDefault();
                alert('Semua field wajib harus diisi!');
                return false;
            }
        });
    </script>
</body>
</html>