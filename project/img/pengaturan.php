<?php
// admin/page/pengaturan.php - Halaman Pengaturan Website
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../config/login.php");
    exit();
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general':
                $site_name = sanitize_input($_POST['site_name']);
                $site_description = sanitize_input($_POST['site_description']);
                $site_keywords = sanitize_input($_POST['site_keywords']);
                $admin_email = sanitize_input($_POST['admin_email']);
                
                if (empty($site_name) || empty($admin_email)) {
                    $error = "Nama website dan email admin harus diisi!";
                } else {
                    // Update atau insert pengaturan
                    $settings = [
                        'site_name' => $site_name,
                        'site_description' => $site_description,
                        'site_keywords' => $site_keywords,
                        'admin_email' => $admin_email
                    ];
                    
                    $success_count = 0;
                    foreach ($settings as $key => $value) {
                        // Check if setting exists
                        $check_query = "SELECT setting_key FROM settings WHERE setting_key = '$key'";
                        $check_result = mysqli_query($koneksi, $check_query);
                        
                        if (mysqli_num_rows($check_result) > 0) {
                            // Update existing setting
                            $update_query = "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'";
                            if (mysqli_query($koneksi, $update_query)) {
                                $success_count++;
                            }
                        } else {
                            // Insert new setting
                            $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')";
                            if (mysqli_query($koneksi, $insert_query)) {
                                $success_count++;
                            }
                        }
                    }
                    
                    if ($success_count > 0) {
                        $success = "Pengaturan umum berhasil diperbarui!";
                    } else {
                        $error = "Gagal memperbarui pengaturan: " . mysqli_error($koneksi);
                    }
                }
                break;
                
            case 'update_display':
                $articles_per_page = (int)sanitize_input($_POST['articles_per_page']);
                $show_author = isset($_POST['show_author']) ? '1' : '0';
                $show_date = isset($_POST['show_date']) ? '1' : '0';
                $show_category = isset($_POST['show_category']) ? '1' : '0';
                $enable_comments = isset($_POST['enable_comments']) ? '1' : '0';
                
                if ($articles_per_page < 1) {
                    $error = "Jumlah artikel per halaman minimal 1!";
                } else {
                    $display_settings = [
                        'articles_per_page' => $articles_per_page,
                        'show_author' => $show_author,
                        'show_date' => $show_date,
                        'show_category' => $show_category,
                        'enable_comments' => $enable_comments
                    ];
                    
                    $success_count = 0;
                    foreach ($display_settings as $key => $value) {
                        $check_query = "SELECT setting_key FROM settings WHERE setting_key = '$key'";
                        $check_result = mysqli_query($koneksi, $check_query);
                        
                        if (mysqli_num_rows($check_result) > 0) {
                            $update_query = "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'";
                            if (mysqli_query($koneksi, $update_query)) {
                                $success_count++;
                            }
                        } else {
                            $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')";
                            if (mysqli_query($koneksi, $insert_query)) {
                                $success_count++;
                            }
                        }
                    }
                    
                    if ($success_count > 0) {
                        $success = "Pengaturan tampilan berhasil diperbarui!";
                    } else {
                        $error = "Gagal memperbarui pengaturan tampilan: " . mysqli_error($koneksi);
                    }
                }
                break;
                
            case 'update_maintenance':
                $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
                $maintenance_message = sanitize_input($_POST['maintenance_message']);
                
                $maintenance_settings = [
                    'maintenance_mode' => $maintenance_mode,
                    'maintenance_message' => $maintenance_message
                ];
                
                $success_count = 0;
                foreach ($maintenance_settings as $key => $value) {
                    $check_query = "SELECT setting_key FROM settings WHERE setting_key = '$key'";
                    $check_result = mysqli_query($koneksi, $check_query);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $update_query = "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'";
                        if (mysqli_query($koneksi, $update_query)) {
                            $success_count++;
                        }
                    } else {
                        $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')";
                        if (mysqli_query($koneksi, $insert_query)) {
                            $success_count++;
                        }
                    }
                }
                
                if ($success_count > 0) {
                    $success = "Pengaturan maintenance berhasil diperbarui!";
                } else {
                    $error = "Gagal memperbarui pengaturan maintenance: " . mysqli_error($koneksi);
                }
                break;
        }
    }
}

// Get current settings from database
$settings_query = "SELECT setting_key, setting_value FROM settings";
$settings_result = mysqli_query($koneksi, $settings_query);
$current_settings = [];

if ($settings_result) {
    while ($setting = mysqli_fetch_assoc($settings_result)) {
        $current_settings[$setting['setting_key']] = $setting['setting_value'];
    }
}

// Set default values if not exists
$defaults = [
    'site_name' => 'Website Berita',
    'site_description' => 'Portal berita terpercaya',
    'site_keywords' => 'berita, news, artikel',
    'admin_email' => 'admin@example.com',
    'articles_per_page' => '10',
    'show_author' => '1',
    'show_date' => '1',
    'show_category' => '1',
    'enable_comments' => '1',
    'maintenance_mode' => '0',
    'maintenance_message' => 'Website sedang dalam maintenance. Silakan kembali lagi nanti.'
];

foreach ($defaults as $key => $default_value) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $default_value;
    }
}

// Get statistics
$total_articles_query = "SELECT COUNT(*) as total FROM articles";
$total_articles_result = mysqli_query($koneksi, $total_articles_query);
$total_articles = mysqli_fetch_assoc($total_articles_result)['total'];

$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = mysqli_query($koneksi, $total_users_query);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$total_categories_query = "SELECT COUNT(*) as total FROM categories";
$total_categories_result = mysqli_query($koneksi, $total_categories_query);
$total_categories = mysqli_fetch_assoc($total_categories_result)['total'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Website</title>
    <link rel="icon" href="../project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Dark theme colors - Same as katagori.php */
    body {
        background-color: #1a1a1a;
        color: #ffffff;
    }

    .card {
        background-color: #2d2d2d;
        border: 1px solid #404040;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .card-header {
        background-color: #404040;
        border-bottom: 1px solid #555555;
        color: #ffffff;
    }

    .card-body {
        background-color: #2d2d2d;
        color: #ffffff;
    }

    .form-control {
        background-color: #404040;
        border: 1px solid #555555;
        color: #ffffff;
    }

    .form-control:focus {
        background-color: #404040;
        border-color: #6c757d;
        color: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    }

    .form-select {
        background-color: #404040;
        border: 1px solid #555555;
        color: #ffffff;
    }

    .form-select:focus {
        background-color: #404040;
        border-color: #6c757d;
        color: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    }

    .form-label {
        color: #ffffff;
    }

    .form-check-input {
        background-color: #404040;
        border-color: #555555;
    }

    .form-check-input:checked {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .form-check-label {
        color: #ffffff;
    }

    .alert-danger {
        background-color: #3d3d3d;
        border-color: #555555;
        color: #ffffff;
    }

    .alert-success {
        background-color: #3d3d3d;
        border-color: #555555;
        color: #ffffff;
    }

    .text-muted {
        color: #adb5bd !important;
    }

    .btn-primary {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .btn-primary:hover {
        background-color: #5a6268;
        border-color: #5a6268;
    }

    .btn-secondary {
        background-color: #495057;
        border-color: #495057;
    }

    .btn-secondary:hover {
        background-color: #343a40;
        border-color: #343a40;
    }

    .btn-danger {
        background-color: #495057;
        border-color: #495057;
    }

    .btn-danger:hover {
        background-color: #343a40;
        border-color: #343a40;
    }

    .btn-warning {
        background-color: #6c757d;
        border-color: #6c757d;
        color: #ffffff;
    }

    .btn-warning:hover {
        background-color: #5a6268;
        border-color: #5a6268;
        color: #ffffff;
    }

    .badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
        background-color: #495057 !important;
        color: #ffffff;
    }

    .stats-card {
        background: linear-gradient(45deg, #404040, #555555);
        border: 1px solid #666666;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        margin-bottom: 20px;
    }

    .stats-number {
        font-size: 2rem;
        font-weight: bold;
        color: #ffffff;
    }

    .stats-label {
        color: #adb5bd;
        font-size: 0.9rem;
    }

    .maintenance-warning {
        background-color: #6c2e00;
        border-color: #8b3a00;
        color: #ffffff;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .tab-content {
        margin-top: 20px;
    }

    .nav-tabs {
        border-bottom: 1px solid #555555;
    }

    .nav-tabs .nav-link {
        background-color: #2d2d2d;
        border: 1px solid #555555;
        color: #adb5bd;
        margin-right: 5px;
    }

    .nav-tabs .nav-link:hover {
        background-color: #404040;
        color: #ffffff;
    }

    .nav-tabs .nav-link.active {
        background-color: #404040;
        border-color: #555555;
        color: #ffffff;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 15px;
        }

        .stats-card {
            margin-bottom: 15px;
        }

        .card {
            margin-bottom: 15px;
        }
    }
    </style>
</head>

<body>
    <div class="container-fluid p-4">
        <!-- Alert Messages -->
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Maintenance Warning -->
        <?php if ($current_settings['maintenance_mode'] == '1'): ?>
        <div class="maintenance-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Mode Maintenance Aktif!</strong> Website sedang dalam mode maintenance dan tidak dapat diakses oleh
            pengunjung.
        </div>
        <?php endif; ?>

        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_articles; ?></div>
                    <div class="stats-label">Total Artikel</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_users; ?></div>
                    <div class="stats-label">Total Pengguna</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_categories; ?></div>
                    <div class="stats-label">Total Kategori</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $current_settings['maintenance_mode'] == '1' ? 'ON' : 'OFF'; ?>
                    </div>
                    <div class="stats-label">Mode Maintenance</div>
                </div>
            </div>
        </div>

        <!-- Settings Tabs -->
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-cog"></i> Pengaturan Website
                </h4>
            </div>
            <div class="card-body">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general"
                            type="button" role="tab" aria-controls="general" aria-selected="true">
                            <i class="fas fa-globe"></i> Umum
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="display-tab" data-bs-toggle="tab" data-bs-target="#display"
                            type="button" role="tab" aria-controls="display" aria-selected="false">
                            <i class="fas fa-eye"></i> Tampilan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance"
                            type="button" role="tab" aria-controls="maintenance" aria-selected="false">
                            <i class="fas fa-tools"></i> Maintenance
                        </button>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content" id="settingsTabContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_general">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">Nama Website</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name"
                                            value="<?php echo htmlspecialchars($current_settings['site_name']); ?>"
                                            required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="admin_email" class="form-label">Email Admin</label>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email"
                                            value="<?php echo htmlspecialchars($current_settings['admin_email']); ?>"
                                            required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="site_description" class="form-label">Deskripsi Website</label>
                                <textarea class="form-control" id="site_description" name="site_description"
                                    rows="3"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="site_keywords" class="form-label">Keywords (dipisah koma)</label>
                                <input type="text" class="form-control" id="site_keywords" name="site_keywords"
                                    value="<?php echo htmlspecialchars($current_settings['site_keywords']); ?>">
                                <small class="text-muted">Kata kunci untuk SEO, pisahkan dengan koma</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Pengaturan Umum
                            </button>
                        </form>
                    </div>

                    <!-- Display Settings -->
                    <div class="tab-pane fade" id="display" role="tabpanel" aria-labelledby="display-tab">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_display">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="articles_per_page" class="form-label">Artikel per Halaman</label>
                                        <select class="form-select" id="articles_per_page" name="articles_per_page">
                                            <option value="5"
                                                <?php echo $current_settings['articles_per_page'] == '5' ? 'selected' : ''; ?>>
                                                5 Artikel</option>
                                            <option value="10"
                                                <?php echo $current_settings['articles_per_page'] == '10' ? 'selected' : ''; ?>>
                                                10 Artikel</option>
                                            <option value="15"
                                                <?php echo $current_settings['articles_per_page'] == '15' ? 'selected' : ''; ?>>
                                                15 Artikel</option>
                                            <option value="20"
                                                <?php echo $current_settings['articles_per_page'] == '20' ? 'selected' : ''; ?>>
                                                20 Artikel</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Opsi Tampilan Artikel</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="show_author"
                                                name="show_author"
                                                <?php echo $current_settings['show_author'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="show_author">
                                                Tampilkan Penulis Artikel
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="show_date"
                                                name="show_date"
                                                <?php echo $current_settings['show_date'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="show_date">
                                                Tampilkan Tanggal Artikel
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="show_category"
                                                name="show_category"
                                                <?php echo $current_settings['show_category'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="show_category">
                                                Tampilkan Kategori Artikel
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_comments"
                                                name="enable_comments"
                                                <?php echo $current_settings['enable_comments'] == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_comments">
                                                Aktifkan Komentar
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Pengaturan Tampilan
                            </button>
                        </form>
                    </div>

                    <!-- Maintenance Settings -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="maintenance-tab">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_maintenance">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode"
                                        name="maintenance_mode"
                                        <?php echo $current_settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        <strong>Aktifkan Mode Maintenance</strong>
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Jika diaktifkan, pengunjung tidak akan dapat mengakses website dan akan melihat
                                    pesan maintenance.
                                </small>
                            </div>
                            <div class="mb-3">
                                <label for="maintenance_message" class="form-label">Pesan Maintenance</label>
                                <textarea class="form-control" id="maintenance_message" name="maintenance_message"
                                    rows="4"><?php echo htmlspecialchars($current_settings['maintenance_message']); ?></textarea>
                                <small class="text-muted">Pesan yang akan ditampilkan kepada pengunjung saat mode
                                    maintenance aktif.</small>
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-tools"></i> Simpan Pengaturan Maintenance
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-database"></i> Informasi Database
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Host:</strong> <span class="text-muted">64.235.41.175</span></p>
                        <p><strong>Database:</strong> <span class="text-muted">arinnapr_dbinievan</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status Koneksi:</strong>
                            <span class="badge bg-success">Terhubung</span>
                        </p>
                        <p><strong>Character Set:</strong> <span class="text-muted">UTF-8</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-save indication
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
                submitBtn.disabled = true;

                // Re-enable after 3 seconds (in case form doesn't redirect)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        });
    });

    // Maintenance mode warning
    document.getElementById('maintenance_mode').addEventListener('change', function() {
        if (this.checked) {
            if (!confirm(
                    'Apakah Anda yakin ingin mengaktifkan mode maintenance? Pengunjung tidak akan dapat mengakses website.'
                )) {
                this.checked = false;
            }
        }
    });
    </script>
</body>

</html>