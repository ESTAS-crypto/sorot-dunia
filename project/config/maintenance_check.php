<?php
/**
 * Mode maintenance check dengan error handling yang proper
 * PERBAIKAN: Mengatasi dependency issues dan null checks
 */

function checkMaintenanceMode($koneksi) {
    // Jangan load di halaman admin atau login
    $current_page = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($current_page, '/admin/') !== false || strpos($current_page, 'login.php') !== false) {
        return;
    }

    // Load settings manager dengan proper error handling
    global $settingsManager;
    
    // Jika settingsManager belum ada, coba load
    if (!isset($settingsManager) || !$settingsManager) {
        try {
            require_once __DIR__ . '/SettingsManager.php';
            $settingsManager = SettingsManager::getInstance($koneksi);
        } catch (Exception $e) {
            error_log("Error loading SettingsManager in maintenance check: " . $e->getMessage());
            return; // Skip maintenance check jika error
        }
    }
    
    // Check if maintenance mode is active
    if ($settingsManager && method_exists($settingsManager, 'isMaintenanceMode')) {
        if ($settingsManager->isMaintenanceMode()) {
            // Check if user can bypass
            if (!$settingsManager->canBypassMaintenance()) {
                // Show maintenance page
                $message = $settingsManager->get('maintenance_message', 'Website sedang dalam maintenance. Silakan kembali lagi nanti.');
                showMaintenancePage($message);
                exit();
            }
        }
    }
}

function showMaintenancePage($message) {
    http_response_code(503);
    header('Retry-After: 3600'); // Retry after 1 hour
    ?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Maintenance - Sorot Dunia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: #ffffff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .maintenance-container {
        text-align: center;
        background: rgba(45, 45, 45, 0.9);
        padding: 60px 40px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        max-width: 600px;
        margin: 20px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .maintenance-icon {
        font-size: 80px;
        color: #00d9ff;
        margin-bottom: 30px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    h1 {
        color: #ffffff;
        margin-bottom: 20px;
        font-weight: bold;
    }

    p {
        color: #cccccc;
        line-height: 1.8;
        margin-bottom: 20px;
        font-size: 1.1rem;
    }

    .back-link {
        color: #00d9ff;
        text-decoration: none;
        font-weight: bold;
        padding: 12px 24px;
        border: 2px solid#00d9ff;
        border-radius: 25px;
        display: inline-block;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        background-color: #00d9ff;
        color: #1a1a1a;
        text-decoration: none;
        transform: translateY(-2px);
    }

    .progress-bar {
        width: 100%;
        height: 4px;
        background-color: rgba(255, 255, 255, 0.2);
        border-radius: 2px;
        margin: 30px 0;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, rgba(45, 45, 45, 0.9), #00d9ff);
        border-radius: 2px;
        animation: loading 3s infinite;
    }

    @keyframes loading {
        0% {
            width: 0%;
        }

        50% {
            width: 70%;
        }

        100% {
            width: 100%;
        }
    }
    </style>
</head>

<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1>Website Sedang Maintenance</h1>
        <p><?php echo htmlspecialchars($message); ?></p>

        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>

        <p class="mb-4">Kami sedang melakukan peningkatan untuk memberikan pengalaman yang lebih baik. Silakan kembali
            lagi nanti.</p>

        <a href="mailto:eatharasya@gmail.com" class="back-link">
            <i class="fas fa-envelope me-2"></i>Hubungi Admin
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
}

// PERBAIKAN: Safe execution dengan global variables
if (isset($GLOBALS['koneksi']) && $GLOBALS['koneksi']) {
    checkMaintenanceMode($GLOBALS['koneksi']);
} elseif (isset($koneksi) && $koneksi) {
    checkMaintenanceMode($koneksi);
}
?>