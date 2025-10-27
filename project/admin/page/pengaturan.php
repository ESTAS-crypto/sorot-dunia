<?php
// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database dan settings manager
require_once '../config/config.php';

// Pastikan settingsManager tersedia
if (!isset($settingsManager) || !$settingsManager) {
    try {
        $settingsManager = SettingsManager::getInstance($koneksi);
    } catch (Exception $e) {
        error_log("Error initializing SettingsManager: " . $e->getMessage());
        die("Error loading settings. Please contact administrator.");
    }
}

// Cek autentikasi
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Cek role admin
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// CSRF Token Generation
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$csrf_token = generateCSRFToken();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "Token keamanan tidak valid!";
        $message_type = "danger";
    } else {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_general':
                    $site_name = sanitize_input($_POST['site_name'] ?? '');
                    $site_description = sanitize_input($_POST['site_description'] ?? '');
                    $site_keywords = sanitize_input($_POST['site_keywords'] ?? '');
                    $admin_email = sanitize_input($_POST['admin_email'] ?? '');
                    
                    if (empty($site_name) || empty($admin_email)) {
                        $message = "Nama website dan email admin harus diisi!";
                        $message_type = "danger";
                    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                        $message = "Format email admin tidak valid!";
                        $message_type = "danger";
                    } else {
                        $settingsManager->set('site_name', $site_name);
                        $settingsManager->set('site_description', $site_description);
                        $settingsManager->set('site_keywords', $site_keywords);
                        $settingsManager->set('admin_email', $admin_email);
                        
                        $message = "Pengaturan umum berhasil diperbarui!";
                        $message_type = "success";
                    }
                    break;
                    
                case 'update_display':
                    $articles_per_page = (int)sanitize_input($_POST['articles_per_page'] ?? '10');
                    $show_author = isset($_POST['show_author']) ? '1' : '0';
                    $show_date = isset($_POST['show_date']) ? '1' : '0';
                    $show_category = isset($_POST['show_category']) ? '1' : '0';
                    $enable_comments = isset($_POST['enable_comments']) ? '1' : '0';
                    
                    if ($articles_per_page < 1 || $articles_per_page > 50) {
                        $message = "Jumlah artikel per halaman harus antara 1-50!";
                        $message_type = "danger";
                    } else {
                        $settingsManager->set('articles_per_page', $articles_per_page);
                        $settingsManager->set('show_author', $show_author);
                        $settingsManager->set('show_date', $show_date);
                        $settingsManager->set('show_category', $show_category);
                        $settingsManager->set('enable_comments', $enable_comments);
                        
                        $message = "Pengaturan tampilan berhasil diperbarui!";
                        $message_type = "success";
                    }
                    break;
                    
                case 'update_maintenance':
                    $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
                    $maintenance_message = sanitize_input($_POST['maintenance_message'] ?? '');
                    
                    if (empty($maintenance_message)) {
                        $maintenance_message = 'Website sedang dalam maintenance. Silakan kembali lagi nanti.';
                    }
                    
                    $settingsManager->set('maintenance_mode', $maintenance_mode);
                    $settingsManager->set('maintenance_message', $maintenance_message);
                    
                    $message = "Pengaturan maintenance berhasil diperbarui!";
                    $message_type = "success";
                    break;
            }
        }
    }
}

// Get current settings
$current_settings = [];
try {
    if ($settingsManager && method_exists($settingsManager, 'getAllSettings')) {
        $current_settings = $settingsManager->getAllSettings();
    }
} catch (Exception $e) {
    error_log("Error getting settings: " . $e->getMessage());
    $current_settings = [
        'site_name' => 'Sorot Dunia',
        'site_description' => 'Portal berita terpercaya',
        'site_keywords' => 'berita, news, artikel',
        'admin_email' => 'admin@sorotdunia.com',
        'articles_per_page' => '10',
        'show_author' => '1',
        'show_date' => '1',
        'show_category' => '1',
        'enable_comments' => '1',
        'maintenance_mode' => '0',
        'maintenance_message' => 'Website sedang dalam maintenance. Silakan kembali lagi nanti.'
    ];
}

// Get statistics
$total_articles = 0;
$total_users = 0;
$total_categories = 0;

try {
    if ($koneksi) {
        $total_articles_query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'published'";
        $total_articles_result = mysqli_query($koneksi, $total_articles_query);
        if ($total_articles_result) {
            $total_articles = mysqli_fetch_assoc($total_articles_result)['total'] ?? 0;
        }

        $total_users_query = "SELECT COUNT(*) as total FROM users";
        $total_users_result = mysqli_query($koneksi, $total_users_query);
        if ($total_users_result) {
            $total_users = mysqli_fetch_assoc($total_users_result)['total'] ?? 0;
        }

        $total_categories_query = "SELECT COUNT(*) as total FROM categories";
        $total_categories_result = mysqli_query($koneksi, $total_categories_query);
        if ($total_categories_result) {
            $total_categories = mysqli_fetch_assoc($total_categories_result)['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error getting statistics: " . $e->getMessage());
}
?>

<style>
/* Settings Page Styling - Matching User Page */
.settings-stats-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.settings-stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
}

.settings-stats-card.primary::before {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.settings-stats-card.success::before {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.settings-stats-card.warning::before {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.settings-stats-card.danger::before {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.settings-stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.settings-stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.settings-stat-icon.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.settings-stat-icon.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.settings-stat-icon.warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.settings-stat-icon.danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.maintenance-warning {
    background: linear-gradient(45deg, rgba(220, 53, 69, 0.2), rgba(185, 27, 47, 0.2));
    border: 1px solid rgba(220, 53, 69, 0.5);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.settings-info-card {
    background: var(--bg-tertiary);
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .settings-stats-card {
        padding: 1rem;
    }
    
    .settings-stat-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }
}
</style>

<!-- Page Header -->
<div class="mb-4">
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <h2 class="mb-1 h4">
                        <i class="bi bi-gear-fill me-2"></i>
                        Pengaturan Website
                    </h2>
                    <small class="text-muted">Kelola pengaturan dan konfigurasi sistem</small>
                </div>
                <a href="../" class="btn btn-secondary" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-2"></i>Lihat Website
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Maintenance Warning -->
<?php if (($current_settings['maintenance_mode'] ?? '0') == '1'): ?>
<div class="maintenance-warning">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle fa-2x me-3"></i>
        <div class="flex-grow-1">
            <h5 class="mb-1">Mode Maintenance Aktif!</h5>
            <p class="mb-0">Website sedang dalam mode maintenance dan tidak dapat diakses oleh pengunjung umum.</p>
        </div>
        <a href="../" class="btn btn-warning" target="_blank">
            <i class="bi bi-eye me-2"></i>Preview
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="settings-stats-card primary">
            <div class="settings-stat-icon primary">
                <i class="bi bi-newspaper"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo number_format($total_articles); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Artikel</div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="settings-stats-card success">
            <div class="settings-stat-icon success">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo number_format($total_users); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Users</div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="settings-stats-card warning">
            <div class="settings-stat-icon warning">
                <i class="bi bi-tags-fill"></i>
            </div>
            <div class="fs-2 fw-bold mb-1"><?php echo number_format($total_categories); ?></div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Kategori</div>
        </div>
    </div>
    
    <div class="col-6 col-md-3">
        <div class="settings-stats-card danger">
            <div class="settings-stat-icon danger">
                <i class="bi bi-tools"></i>
            </div>
            <div class="fs-2 fw-bold mb-1">
                <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'ON' : 'OFF'; ?>
            </div>
            <div class="text-muted text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Maintenance</div>
        </div>
    </div>
</div>

<!-- Settings Tabs -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-sliders me-2"></i>Konfigurasi Website
        </h5>
    </div>
    <div class="card-body">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general"
                    type="button" role="tab">
                    <i class="bi bi-globe me-2"></i>Pengaturan Umum
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="display-tab" data-bs-toggle="tab" data-bs-target="#display"
                    type="button" role="tab">
                    <i class="bi bi-display me-2"></i>Tampilan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance"
                    type="button" role="tab">
                    <i class="bi bi-tools me-2"></i>Maintenance
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_general">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_name" class="form-label">
                                    <i class="bi bi-globe me-2"></i>Nama Website <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="site_name" name="site_name"
                                    value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>"
                                    required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">
                                    <i class="bi bi-envelope me-2"></i>Email Admin <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email"
                                    value="<?php echo htmlspecialchars($current_settings['admin_email'] ?? ''); ?>"
                                    required maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="site_description" class="form-label">
                            <i class="bi bi-info-circle me-2"></i>Deskripsi Website
                        </label>
                        <textarea class="form-control" id="site_description" name="site_description" rows="3"
                            maxlength="500"><?php echo htmlspecialchars($current_settings['site_description'] ?? ''); ?></textarea>
                        <small class="text-muted">Deskripsi ini akan muncul di meta description untuk SEO</small>
                    </div>

                    <div class="mb-4">
                        <label for="site_keywords" class="form-label">
                            <i class="bi bi-tags me-2"></i>Keywords SEO
                        </label>
                        <input type="text" class="form-control" id="site_keywords" name="site_keywords"
                            value="<?php echo htmlspecialchars($current_settings['site_keywords'] ?? ''); ?>"
                            maxlength="500">
                        <small class="text-muted">Kata kunci untuk SEO, pisahkan dengan koma</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Pengaturan
                    </button>
                </form>
            </div>

            <!-- Display Settings -->
            <div class="tab-pane fade" id="display" role="tabpanel">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_display">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="articles_per_page" class="form-label">
                                    <i class="bi bi-list-ul me-2"></i>Artikel per Halaman
                                </label>
                                <select class="form-select" id="articles_per_page" name="articles_per_page">
                                    <option value="5" <?php echo ($current_settings['articles_per_page'] ?? '10') == '5' ? 'selected' : ''; ?>>5 Artikel</option>
                                    <option value="10" <?php echo ($current_settings['articles_per_page'] ?? '10') == '10' ? 'selected' : ''; ?>>10 Artikel</option>
                                    <option value="15" <?php echo ($current_settings['articles_per_page'] ?? '10') == '15' ? 'selected' : ''; ?>>15 Artikel</option>
                                    <option value="20" <?php echo ($current_settings['articles_per_page'] ?? '10') == '20' ? 'selected' : ''; ?>>20 Artikel</option>
                                    <option value="25" <?php echo ($current_settings['articles_per_page'] ?? '10') == '25' ? 'selected' : ''; ?>>25 Artikel</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="bi bi-eye me-2"></i>Opsi Tampilan
                            </label>
                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="show_author" name="show_author"
                                        <?php echo ($current_settings['show_author'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_author">
                                        <i class="bi bi-person me-1"></i>Tampilkan Penulis
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="show_date" name="show_date"
                                        <?php echo ($current_settings['show_date'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_date">
                                        <i class="bi bi-calendar me-1"></i>Tampilkan Tanggal
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="show_category" name="show_category"
                                        <?php echo ($current_settings['show_category'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="show_category">
                                        <i class="bi bi-folder me-1"></i>Tampilkan Kategori
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enable_comments" name="enable_comments"
                                        <?php echo ($current_settings['enable_comments'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_comments">
                                        <i class="bi bi-chat me-1"></i>Aktifkan Komentar
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Simpan Pengaturan
                    </button>
                </form>
            </div>

            <!-- Maintenance Settings -->
            <div class="tab-pane fade" id="maintenance" role="tabpanel">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_maintenance">

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode"
                                style="transform: scale(1.5);"
                                <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2" for="maintenance_mode">
                                <strong><i class="bi bi-tools me-2"></i>Aktifkan Mode Maintenance</strong>
                            </label>
                        </div>
                        <small class="text-muted ms-4">
                            <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                            Pengunjung tidak akan dapat mengakses website. Admin tetap dapat mengakses.
                        </small>
                    </div>

                    <div class="mb-4">
                        <label for="maintenance_message" class="form-label">
                            <i class="bi bi-chat-text me-2"></i>Pesan Maintenance
                        </label>
                        <textarea class="form-control" id="maintenance_message" name="maintenance_message"
                            rows="4" maxlength="1000"><?php echo htmlspecialchars($current_settings['maintenance_message'] ?? ''); ?></textarea>
                        <small class="text-muted">Pesan yang ditampilkan saat mode maintenance aktif</small>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="bi bi-info-circle me-2"></i>Informasi:</h6>
                        <ul class="mb-0 mt-2">
                            <li>Admin dapat tetap mengakses website</li>
                            <li>Gunakan parameter <code>?bypass=evan123</code> untuk akses sementara</li>
                            <li>Pengunjung akan melihat halaman maintenance</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-tools me-2"></i>Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-server me-2"></i>Informasi Sistem
                </h5>
            </div>
            <div class="card-body">
                <div class="settings-info-card">
                    <strong><i class="bi bi-database me-2"></i>Database Host:</strong>
                    <span class="text-muted">103.180.162.183</span>
                </div>
                <div class="settings-info-card">
                    <strong><i class="bi bi-code me-2"></i>PHP Version:</strong>
                    <span class="text-muted"><?php echo phpversion(); ?></span>
                </div>
                <div class="settings-info-card">
                    <strong><i class="bi bi-plug me-2"></i>Status Koneksi:</strong>
                    <span class="badge bg-success"><i class="bi bi-check me-1"></i>Terhubung</span>
                </div>
                <div class="settings-info-card">
                    <strong><i class="bi bi-gear me-2"></i>Settings Manager:</strong>
                    <span class="badge bg-success"><i class="bi bi-check me-1"></i>Aktif</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>Ringkasan Pengaturan
                </h5>
            </div>
            <div class="card-body">
                <div class="settings-info-card">
                    <strong><i class="bi bi-globe me-2"></i>Nama Website:</strong>
                    <span class="text-muted"><?php echo htmlspecialchars($current_settings['site_name'] ?? 'Belum diset'); ?></span>
                </div>
                <div class="settings-info-card">
                    <strong><i class="bi bi-list me-2"></i>Artikel per Halaman:</strong>
                    <span class="badge bg-primary"><?php echo ($current_settings['articles_per_page'] ?? '10'); ?></span>
                </div>
                <div class="settings-info-card">
                    <strong><i class="bi bi-tools me-2"></i>Mode Maintenance:</strong>
                    <span class="badge bg-<?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'danger' : 'success'; ?>">
                        <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'Aktif' : 'Nonaktif'; ?>
                    </span>
                </div>
                <div class="settings-info-card">
                    <strong><i class="bi bi-clock me-2"></i>Terakhir Diperbarui:</strong>
                    <span class="text-muted"><?php echo date('d M Y H:i'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for DOM and Bootstrap to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Settings page loaded');
    
    // Check if Bootstrap is loaded
    const checkBootstrap = () => {
        return typeof bootstrap !== 'undefined';
    };
    
    // Auto-save indication
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Menyimpan...';
                submitBtn.disabled = true;

                // Re-enable after 3 seconds if form doesn't redirect
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });

    // Maintenance mode warning
    const maintenanceModeCheckbox = document.getElementById('maintenance_mode');
    if (maintenanceModeCheckbox) {
        maintenanceModeCheckbox.addEventListener('change', function() {
            if (this.checked) {
                if (!confirm(
                        'Apakah Anda yakin ingin mengaktifkan mode maintenance?\n\n' +
                        'Pengunjung tidak akan dapat mengakses website, tetapi admin tetap dapat mengakses.\n' +
                        'Gunakan parameter ?bypass=evan123 untuk akses sementara.'
                    )) {
                    this.checked = false;
                }
            }
        });
    }

    // Form validation
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    field.style.borderColor = '#dc3545';
                } else {
                    field.classList.remove('is-invalid');
                    field.style.borderColor = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Mohon lengkapi semua field yang wajib diisi!');
            }
        });
    });

    // Auto-dismiss success alerts with Bootstrap check
    setTimeout(function() {
        const successAlerts = document.querySelectorAll('.alert-success');
        successAlerts.forEach(alert => {
            if (checkBootstrap() && bootstrap.Alert) {
                try {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                } catch (e) {
                    console.warn('Bootstrap Alert error:', e);
                    // Fallback
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            } else {
                // Fallback if Bootstrap not loaded
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);

    // Character counter for textareas
    document.querySelectorAll('textarea[maxlength]').forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        const counter = document.createElement('small');
        counter.className = 'text-muted float-end mt-1';
        textarea.parentNode.appendChild(counter);

        function updateCounter() {
            const remaining = maxLength - textarea.value.length;
            counter.textContent = `${textarea.value.length}/${maxLength} karakter`;
            counter.style.color = remaining < 50 ? '#dc3545' : '#6c757d';
        }

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    });

    // Smooth tab transitions
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const targetPane = document.querySelector(e.target.getAttribute('data-bs-target'));
            if (targetPane) {
                targetPane.style.opacity = '0';
                setTimeout(() => {
                    targetPane.style.transition = 'opacity 0.3s ease';
                    targetPane.style.opacity = '1';
                }, 10);
            }
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S to save current form
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const activeTab = document.querySelector('.tab-pane.active');
            if (activeTab) {
                const form = activeTab.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        }

        // Ctrl+1, Ctrl+2, Ctrl+3 to switch tabs
        if (e.ctrlKey && ['1', '2', '3'].includes(e.key)) {
            e.preventDefault();
            const tabIndex = parseInt(e.key) - 1;
            const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
            if (tabs[tabIndex]) {
                if (checkBootstrap() && bootstrap.Tab) {
                    try {
                        const tab = new bootstrap.Tab(tabs[tabIndex]);
                        tab.show();
                    } catch (e) {
                        console.warn('Bootstrap Tab error:', e);
                        tabs[tabIndex].click();
                    }
                } else {
                    tabs[tabIndex].click();
                }
            }
        }
    });

    // Animate stat cards on load
    const statCards = document.querySelectorAll('.settings-stats-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });

    // Real-time preview update
    const siteNameInput = document.getElementById('site_name');
    if (siteNameInput) {
        siteNameInput.addEventListener('input', function() {
            const siteName = this.value || 'Website';
            const previewElements = document.querySelectorAll('.site-name-preview');
            previewElements.forEach(el => {
                el.textContent = siteName;
            });
        });
    }

    // Initialize tooltips with Bootstrap check
    if (checkBootstrap() && bootstrap.Tooltip) {
        try {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        } catch (e) {
            console.warn('Tooltip initialization error:', e);
        }
    }

    // Settings validation rules
    const validationRules = {
        site_name: {
            minLength: 3,
            maxLength: 100,
            required: true
        },
        admin_email: {
            pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
            required: true
        },
        articles_per_page: {
            min: 1,
            max: 50,
            required: true
        }
    };

    // Apply real-time validation
    Object.keys(validationRules).forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field) {
            field.addEventListener('blur', function() {
                validateField(this, validationRules[fieldName]);
            });
        }
    });

    function validateField(field, rules) {
        let isValid = true;
        let errorMessage = '';
        
        if (rules.required && !field.value.trim()) {
            isValid = false;
            errorMessage = 'Field ini wajib diisi';
        } else if (rules.minLength && field.value.length < rules.minLength) {
            isValid = false;
            errorMessage = `Minimal ${rules.minLength} karakter`;
        } else if (rules.maxLength && field.value.length > rules.maxLength) {
            isValid = false;
            errorMessage = `Maksimal ${rules.maxLength} karakter`;
        } else if (rules.pattern && !rules.pattern.test(field.value)) {
            isValid = false;
            errorMessage = 'Format tidak valid';
        } else if (rules.min && parseFloat(field.value) < rules.min) {
            isValid = false;
            errorMessage = `Minimal ${rules.min}`;
        } else if (rules.max && parseFloat(field.value) > rules.max) {
            isValid = false;
            errorMessage = `Maksimal ${rules.max}`;
        }
        
        if (!isValid) {
            field.classList.add('is-invalid');
            let feedback = field.nextElementSibling;
            if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentNode.appendChild(feedback);
            }
            feedback.textContent = errorMessage;
        } else {
            field.classList.remove('is-invalid');
            const feedback = field.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.remove();
            }
        }
        
        return isValid;
    }

    // Confirmation before leaving with unsaved changes
    let formChanged = false;
    forms.forEach(form => {
        form.addEventListener('change', () => formChanged = true);
        form.addEventListener('submit', () => formChanged = false);
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Add fade animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tab-pane.active {
            animation: fadeIn 0.3s ease;
        }
    `;
    document.head.appendChild(style);

    // Save timestamp on form submission
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            try {
                localStorage.setItem('settings_last_saved', Date.now().toString());
            } catch (e) {
                console.warn('LocalStorage error:', e);
            }
        });
    });

    console.log('%c⚙️ Settings Page Loaded Successfully', 'color: #10b981; font-size: 14px; font-weight: bold;');
    console.log('%cKeyboard Shortcuts:', 'color: #3b82f6; font-weight: bold;');
    console.log('  Ctrl+S: Save current form');
    console.log('  Ctrl+1/2/3: Switch between tabs');
    console.log('%cBootstrap Status:', 'color: #f59e0b; font-weight: bold;', checkBootstrap() ? '✓ Loaded' : '✗ Not Loaded');
});
</script>