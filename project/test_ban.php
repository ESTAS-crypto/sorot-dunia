<?php
session_start();

// Simulasi user login untuk testing - pastikan sesuai dengan user yang ada di database
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = 6; // ID user Kelvin - sesuaikan dengan database Anda
    $_SESSION['id'] = 6;
    $_SESSION['username'] = 'Kelvin';
}

// Function untuk mengecek file yang dibutuhkan
function checkRequiredFiles() {
    $files = [
        'API/ban.php' => 'API Ban Check',
        'js/notif-ban.js' => 'Notification JavaScript',
        'config/config.php' => 'Database Config (Optional)'
    ];
    
    $results = [];
    foreach ($files as $file => $desc) {
        $results[$file] = [
            'exists' => file_exists($file),
            'readable' => file_exists($file) && is_readable($file),
            'description' => $desc
        ];
    }
    return $results;
}

$fileCheck = checkRequiredFiles();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ban Notification System - Complete Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .test-container {
        background: white;
        border-radius: 25px;
        padding: 35px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        max-width: 1000px;
        margin: 0 auto;
    }

    .status-indicator {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        margin-right: 10px;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .status-active {
        background: linear-gradient(45deg, #28a745, #20c997);
        box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
    }

    .status-warning {
        background: linear-gradient(45deg, #ffc107, #fd7e14);
        box-shadow: 0 0 10px rgba(255, 193, 7, 0.3);
    }

    .status-banned {
        background: linear-gradient(45deg, #dc3545, #e83e8c);
        box-shadow: 0 0 10px rgba(220, 53, 69, 0.3);
    }

    .status-loading {
        background: linear-gradient(45deg, #6c757d, #adb5bd);
        animation: pulse 1.5s infinite;
    }

    .status-error {
        background: linear-gradient(45deg, #dc3545, #fd7e14);
        animation: blink 1s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.7;
            transform: scale(1.1);
        }
    }

    @keyframes blink {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.3;
        }
    }

    .log-area {
        background: #1e1e1e;
        border-radius: 15px;
        padding: 20px;
        height: 400px;
        overflow-y: auto;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
        border: 3px solid #333;
        color: #00ff00;
        position: relative;
    }

    .log-area::-webkit-scrollbar {
        width: 8px;
    }

    .log-area::-webkit-scrollbar-track {
        background: #2d2d2d;
        border-radius: 4px;
    }

    .log-area::-webkit-scrollbar-thumb {
        background: #555;
        border-radius: 4px;
    }

    .log-area::-webkit-scrollbar-thumb:hover {
        background: #777;
    }

    .btn-test {
        margin: 8px;
        border-radius: 25px;
        padding: 12px 25px;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        position: relative;
        overflow: hidden;
    }

    .btn-test:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .btn-test:active {
        transform: translateY(0);
    }

    .endpoint-status {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 12px;
        margin-left: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .endpoint-success {
        background: linear-gradient(45deg, #d4edda, #c3e6cb);
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .endpoint-error {
        background: linear-gradient(45deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .endpoint-testing {
        background: linear-gradient(45deg, #d1ecf1, #bee5eb);
        color: #0c5460;
        border: 1px solid #bee5eb;
        animation: pulse 1s infinite;
    }

    .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        border-radius: 20px 20px 0 0 !important;
        font-weight: 600;
        padding: 20px;
    }

    .file-check-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        margin: 5px 0;
        background: #f8f9fa;
        border-radius: 10px;
        border-left: 4px solid transparent;
    }

    .file-check-item.exists {
        border-left-color: #28a745;
        background: linear-gradient(90deg, #d4edda, #f8f9fa);
    }

    .file-check-item.missing {
        border-left-color: #dc3545;
        background: linear-gradient(90deg, #f8d7da, #f8f9fa);
    }

    .log-entry {
        margin-bottom: 3px;
        padding: 2px 0;
        font-size: 12px;
        line-height: 1.4;
    }

    .log-info {
        color: #17a2b8;
    }

    .log-success {
        color: #28a745;
    }

    .log-warning {
        color: #ffc107;
    }

    .log-danger {
        color: #dc3545;
    }

    .log-primary {
        color: #007bff;
    }

    .log-secondary {
        color: #6c757d;
    }

    .system-info {
        background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        border-left: 5px solid #2196f3;
    }

    .real-time-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        background: #ff4444;
        border-radius: 50%;
        animation: pulse 1s infinite;
        margin-right: 8px;
    }

    .performance-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .metric-item {
        text-align: center;
        padding: 15px;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 12px;
        border: 1px solid #dee2e6;
    }

    .metric-value {
        font-size: 24px;
        font-weight: bold;
        color: #495057;
        display: block;
    }

    .metric-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    </style>
</head>

<body class="logged-in" data-user-id="<?php echo $_SESSION['user_id']; ?>">
    <div class="test-container">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1><i class="fas fa-shield-alt text-primary"></i> Ban Notification System</h1>
            <h2 class="h4 text-primary">Complete Test & Monitoring Dashboard</h2>
            <p class="text-muted">
                Real-time testing untuk user: <strong><?php echo $_SESSION['username']; ?></strong>
                (ID: <?php echo $_SESSION['user_id']; ?>)
            </p>
        </div>

        <!-- System Info -->
        <div class="system-info">
            <h6><i class="fas fa-info-circle"></i> System Information</h6>
            <div class="row">
                <div class="col-md-6">
                    <small><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></small><br>
                    <small><strong>Session ID:</strong> <?php echo session_id(); ?></small><br>
                    <small><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></small>
                </div>
                <div class="col-md-6">
                    <small><strong>User Agent:</strong> <span id="user-agent">Loading...</span></small><br>
                    <small><strong>Page URL:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></small><br>
                    <small><strong>Real-time Status:</strong> <span class="real-time-indicator"></span>Active</small>
                </div>
            </div>
        </div>

        <!-- File Check Results -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-file-check"></i> File System Check
                    </div>
                    <div class="card-body">
                        <?php foreach ($fileCheck as $file => $info): ?>
                        <div class="file-check-item <?php echo $info['exists'] ? 'exists' : 'missing'; ?>">
                            <div>
                                <strong><?php echo $info['description']; ?></strong><br>
                                <small class="text-muted"><?php echo $file; ?></small>
                            </div>
                            <div>
                                <?php if ($info['exists']): ?>
                                <i class="fas fa-check-circle text-success"></i>
                                <small class="text-success">EXISTS</small>
                                <?php else: ?>
                                <i class="fas fa-times-circle text-danger"></i>
                                <small class="text-danger">MISSING</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-tachometer-alt"></i> System Status
                    </div>
                    <div class="card-body">
                        <p><span class="status-indicator status-loading" id="system-status"></span><span
                                id="system-text">Initializing...</span></p>
                        <p><strong>Current Status:</strong> <span id="current-status"
                                class="badge bg-secondary">Unknown</span></p>
                        <p><strong>Last Check:</strong> <span id="last-check">Never</span></p>

                        <div class="performance-metrics">
                            <div class="metric-item">
                                <span class="metric-value" id="check-count">0</span>
                                <span class="metric-label">Total Checks</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-value" id="success-rate">0%</span>
                                <span class="metric-label">Success Rate</span>
                            </div>
                            <div class="metric-item">
                                <span class="metric-value" id="avg-response">0ms</span>
                                <span class="metric-label">Avg Response</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">API Endpoints Status:</small><br>
                            <small>API/ban.php: <span id="api-ban-status"
                                    class="endpoint-status endpoint-testing">Testing...</span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-gamepad"></i> Control Panel
                    </div>
                    <div class="card-body text-center">
                        <button class="btn btn-primary btn-test" onclick="manualCheck()">
                            <i class="fas fa-search"></i> Manual Check
                        </button>
                        <button class="btn btn-info btn-test" onclick="testAllAPIs()">
                            <i class="fas fa-plug"></i> Test APIs
                        </button>
                        <button class="btn btn-warning btn-test" onclick="simulateResponse('banned')">
                            <i class="fas fa-ban"></i> Simulate Ban
                        </button>
                        <button class="btn btn-orange btn-test" onclick="simulateResponse('warning')"
                            style="background: linear-gradient(45deg, #ff8c00, #ffa500); color: white;">
                            <i class="fas fa-exclamation-triangle"></i> Simulate Warning
                        </button>
                        <button class="btn btn-secondary btn-test" onclick="clearLogs()">
                            <i class="fas fa-trash"></i> Clear Logs
                        </button>
                        <button class="btn btn-dark btn-test" onclick="toggleDebugMode()">
                            <i class="fas fa-bug"></i> Debug Mode
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-cogs"></i> Settings
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Check Interval:</label>
                            <select class="form-select form-select-sm" id="check-interval"
                                onchange="updateCheckInterval()">
                                <option value="5000">5 seconds</option>
                                <option value="15000" selected>15 seconds</option>
                                <option value="30000">30 seconds</option>
                                <option value="60000">1 minute</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto-scroll" checked>
                                <label class="form-check-label" for="auto-scroll">Auto-scroll logs</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sound-alerts">
                                <label class="form-check-label" for="sound-alerts">Sound alerts</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Logs -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-terminal"></i> Live System Logs</span>
                <div>
                    <span class="real-time-indicator"></span>
                    <small>Real-time monitoring active</small>
                    <button class="btn btn-sm btn-outline-light ms-2" onclick="exportLogs()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="log-area" id="log-area">
                    <div class="log-entry log-secondary">System initializing... Please wait</div>
                </div>
            </div>
        </div>

        <!-- Instructions & Troubleshooting -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb"></i> Testing Instructions:</h6>
                    <ol class="mb-0">
                        <li>Sistem akan otomatis check ban status setiap 15 detik</li>
                        <li>Klik <strong>"Manual Check"</strong> untuk test immediate</li>
                        <li>Klik <strong>"Test APIs"</strong> untuk verify all endpoints</li>
                        <li>Gunakan <strong>"Simulate Ban/Warning"</strong> untuk test UI</li>
                        <li>Monitor logs untuk detailed debugging info</li>
                        <li>Untuk test real ban: login admin → ban user Kelvin</li>
                    </ol>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Troubleshooting:</h6>
                    <ul class="mb-0">
                        <li><strong>HTTP 404:</strong> Check if <code>API/ban.php</code> exists</li>
                        <li><strong>Database Error:</strong> Verify config.php connection</li>
                        <li><strong>Session Error:</strong> Ensure user is logged in properly</li>
                        <li><strong>JavaScript Error:</strong> Check browser console (F12)</li>
                        <li><strong>No Response:</strong> Check server error logs</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Advanced Debug Info -->
        <div class="mt-4" id="debug-panel" style="display: none;">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-bug"></i> Advanced Debug Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Session Data:</h6>
                            <pre
                                class="bg-light p-2 rounded"><code><?php echo htmlspecialchars(print_r($_SESSION, true)); ?></code></pre>
                        </div>
                        <div class="col-md-6">
                            <h6>System Status:</h6>
                            <div id="system-debug-info">
                                <small class="text-muted">Debug info will appear here...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Load Ban Notification System -->
    <script src="js/notif-ban.js?v=<?php echo time(); ?>"></script>

    <script>
    // System variables
    let checkCount = 0;
    let successCount = 0;
    let errorCount = 0;
    let totalResponseTime = 0;
    let debugMode = false;
    let soundAlertsEnabled = false;

    // System metrics
    const metrics = {
        checks: 0,
        successes: 0,
        errors: 0,
        totalResponseTime: 0,
        lastCheckTime: null
    };

    // Initialize system
    document.addEventListener('DOMContentLoaded', function() {
        initializeSystem();
        setupEventListeners();
        updateSystemInfo();

        // Initial API test
        setTimeout(testAllAPIs, 2000);
    });

    function initializeSystem() {
        addLog('🚀 Ban Notification Test System started', 'success');
        addLog('👤 Current user: <?php echo $_SESSION['username']; ?> (ID: <?php echo $_SESSION['user_id']; ?>)',
            'info');
        addLog('📍 Test page loaded: test_ban.php', 'secondary');
        addLog('🌐 Monitoring endpoint: API/ban.php', 'info');

        // Set user agent
        document.getElementById('user-agent').textContent = navigator.userAgent.substring(0, 50) + '...';
    }

    function setupEventListeners() {
        // Listen for ban notification events
        window.addEventListener('banNotificationLog', function(event) {
            addLog(event.detail.message, event.detail.type);
        });

        // Settings change listeners
        document.getElementById('auto-scroll').addEventListener('change', function() {
            addLog(`Auto-scroll ${this.checked ? 'enabled' : 'disabled'}`, 'info');
        });

        document.getElementById('sound-alerts').addEventListener('change', function() {
            soundAlertsEnabled = this.checked;
            addLog(`Sound alerts ${this.checked ? 'enabled' : 'disabled'}`, 'info');
        });
    }

    function addLog(message, type = 'secondary') {
        const logArea = document.getElementById('log-area');
        const timestamp = new Date().toLocaleTimeString();
        const autoScroll = document.getElementById('auto-scroll').checked;

        const logEntry = document.createElement('div');
        logEntry.className = `log-entry log-${type}`;
        logEntry.innerHTML = `<small style="color: #666;">[${timestamp}]</small> ${message}`;

        logArea.appendChild(logEntry);

        if (autoScroll) {
            logArea.scrollTop = logArea.scrollHeight;
        }

        // Keep only last 200 entries for performance
        while (logArea.children.length > 200) {
            logArea.removeChild(logArea.firstChild);
        }

        // Play sound for important events
        if (soundAlertsEnabled && (type === 'danger' || type === 'warning')) {
            playNotificationSound();
        }
    }

    function updateStatus(status, text) {
        const statusEl = document.getElementById('system-status');
        const textEl = document.getElementById('system-text');
        const statusBadge = document.getElementById('current-status');

        statusEl.className = `status-indicator status-${status}`;
        textEl.textContent = text;

        // Update status badge
        statusBadge.className = `badge bg-${getBootstrapColor(status)}`;
        statusBadge.textContent = status.toUpperCase();

        document.getElementById('last-check').textContent = new Date().toLocaleTimeString();
        updateMetrics();
    }

    function getBootstrapColor(status) {
        const colors = {
            'active': 'success',
            'banned': 'danger',
            'warning': 'warning',
            'loading': 'secondary',
            'error': 'danger'
        };
        return colors[status] || 'secondary';
    }

    function updateMetrics() {
        document.getElementById('check-count').textContent = metrics.checks;

        const successRate = metrics.checks > 0 ? Math.round((metrics.successes / metrics.checks) * 100) : 0;
        document.getElementById('success-rate').textContent = successRate + '%';

        const avgResponse = metrics.checks > 0 ? Math.round(metrics.totalResponseTime / metrics.checks) : 0;
        document.getElementById('avg-response').textContent = avgResponse + 'ms';
    }

    function updateEndpointStatus(endpoint, success) {
        const statusEl = document.getElementById(endpoint + '-status');
        if (statusEl) {
            statusEl.textContent = success ? 'OK' : 'ERROR';
            statusEl.className = `endpoint-status ${success ? 'endpoint-success' : 'endpoint-error'}`;
        }
    }

    function manualCheck() {
        addLog('🔍 Manual check triggered by user', 'info');
        if (window.banNotificationManager) {
            const startTime = performance.now();
            window.banNotificationManager.forceCheck();
            metrics.checks++;
            updateMetrics();
        } else {
            addLog('❌ Ban notification manager not available!', 'danger');
        }
    }

    async function testAllAPIs() {
        addLog('🔌 Testing all API endpoints...', 'info');
        document.getElementById('api-ban-status').className = 'endpoint-status endpoint-testing';
        document.getElementById('api-ban-status').textContent = 'Testing...';

        const endpoint = 'API/ban.php';
        const startTime = performance.now();

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });

            const endTime = performance.now();
            const responseTime = Math.round(endTime - startTime);

            metrics.totalResponseTime += responseTime;

            if (response.ok) {
                const data = await response.json();
                addLog(`✅ ${endpoint}: Status=${data.status}, Response=${responseTime}ms`, 'success');
                addLog(`📊 Data: ${JSON.stringify(data).substring(0, 100)}...`, 'info');
                updateEndpointStatus('api-ban', true);
                metrics.successes++;
            } else {
                addLog(`❌ ${endpoint}: HTTP ${response.status}, Response=${responseTime}ms`, 'danger');
                updateEndpointStatus('api-ban', false);
                metrics.errors++;
            }
        } catch (error) {
            const endTime = performance.now();
            const responseTime = Math.round(endTime - startTime);
            addLog(`❌ ${endpoint}: ${error.message}, Response=${responseTime}ms`, 'danger');
            updateEndpointStatus('api-ban', false);
            metrics.errors++;
        }

        metrics.checks++;
        updateMetrics();
        addLog('📊 API testing completed', 'info');
    }

    function simulateResponse(status) {
        addLog(`🎭 Simulating ${status} response...`, 'warning');

        const mockData = {
            'banned': {
                status: 'banned',
                ban_reason: 'Test ban for demonstration purposes',
                ban_until: new Date(Date.now() + 86400000).toLocaleDateString('id-ID'),
                time_remaining: '23 jam, 59 menit',
                username: '<?php echo $_SESSION['username']; ?>'
            },
            'warning': {
                status: 'warning',
                warning_level: 'medium',
                warning_reason: 'Test warning for demonstration purposes',
                warning_count: 2,
                given_at: new Date().toLocaleDateString('id-ID'),
                username: '<?php echo $_SESSION['username']; ?>'
            }
        };

        if (window.banNotificationManager && mockData[status]) {
            window.banNotificationManager.handleBanStatus(mockData[status]);
            addLog(`✅ ${status} simulation triggered`, 'success');
        } else {
            addLog('❌ Could not trigger simulation', 'danger');
        }
    }

    function clearLogs() {
        document.getElementById('log-area').innerHTML =
            '<div class="log-entry log-secondary">Logs cleared by user</div>';
        addLog('🗑️ Log history cleared', 'info');
    }

    function toggleDebugMode() {
        debugMode = !debugMode;
        const debugPanel = document.getElementById('debug-panel');
        debugPanel.style.display = debugMode ? 'block' : 'none';

        addLog(`🐛 Debug mode ${debugMode ? 'enabled' : 'disabled'}`, 'info');

        if (debugMode && window.banNotificationManager) {
            window.banNotificationManager.setDebugMode(true);
            updateDebugInfo();
        }
    }

    function updateDebugInfo() {
        if (debugMode && window.banNotificationManager) {
            const status = window.banNotificationManager.getStatus();
            const debugInfo = document.getElementById('system-debug-info');
            debugInfo.innerHTML = `
                <pre class="bg-light p-2 rounded"><code>${JSON.stringify(status, null, 2)}</code></pre>
                <small><strong>Last Update:</strong> ${new Date().toLocaleTimeString()}</small>
            `;
        }
    }

    function updateCheckInterval() {
        const interval = document.getElementById('check-interval').value;
        addLog(`⏱️ Check interval updated to ${interval}ms`, 'info');

        if (window.banNotificationManager) {
            window.banNotificationManager.checkInterval = parseInt(interval);
            window.banNotificationManager.stopPeriodicCheck();
            window.banNotificationManager.startPeriodicCheck();
        }
    }

    function exportLogs() {
        const logArea = document.getElementById('log-area');
        const logs = Array.from(logArea.children).map(el => el.textContent).join('\n');

        const blob = new Blob([logs], {
            type: 'text/plain'
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ban-system-logs-${new Date().toISOString().slice(0,19)}.txt`;
        a.click();
        URL.revokeObjectURL(url);

        addLog('📥 Logs exported successfully', 'success');
    }

    function playNotificationSound() {
        // Create a simple beep sound
        const audioContext = new(window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.frequency.value = 800;
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    }

    function updateSystemInfo() {
        setInterval(() => {
            if (debugMode) {
                updateDebugInfo();
            }
        }, 5000);
    }

    // Monitor ban notification manager initialization
    let initCheckCount = 0;
    const initChecker = setInterval(() => {
        initCheckCount++;

        if (window.banNotificationManager) {
            addLog('✅ Ban notification manager found and connected', 'success');
            updateStatus('active', 'System ready');

            // Override manager methods for monitoring
            const originalCheckStatus = window.banNotificationManager.checkBanStatus;
            window.banNotificationManager.checkBanStatus = function() {
                metrics.checks++;
                updateMetrics();
                const startTime = performance.now();

                const result = originalCheckStatus.call(this);

                // Monitor response time
                if (result && result.then) {
                    result.then(() => {
                        const responseTime = performance.now() - startTime;
                        metrics.totalResponseTime += responseTime;
                        metrics.successes++;
                        updateMetrics();
                    }).catch(() => {
                        metrics.errors++;
                        updateMetrics();
                    });
                }

                return result;
            };

            const originalHandleStatus = window.banNotificationManager.handleBanStatus;
            window.banNotificationManager.handleBanStatus = function(data) {
                addLog(`📊 Status received: ${data.status}`, 'info');

                if (data.status === 'banned') {
                    updateStatus('banned', 'USER IS BANNED!');
                    addLog('🚨 BAN DETECTED - Notification displayed!', 'danger');
                } else if (data.status === 'warning') {
                    updateStatus('warning', 'User has warning');
                    addLog('⚠️ WARNING DETECTED - Notification displayed!', 'warning');
                } else if (data.status === 'active') {
                    updateStatus('active', 'User is active');
                    addLog('✅ User status: ACTIVE', 'success');
                } else {
                    updateStatus('loading', `Status: ${data.status}`);
                    addLog(`ℹ️ Status: ${data.status}`, 'secondary');
                }

                return originalHandleStatus.call(this, data);
            };

            clearInterval(initChecker);
        } else if (initCheckCount > 20) {
            addLog('❌ Ban notification manager not found after 20 attempts!', 'danger');
            updateStatus('error', 'System not available');
            clearInterval(initChecker);
        }
    }, 500);

    // Auto-update system clock
    setInterval(() => {
        document.querySelector('.system-info small:nth-child(3)').innerHTML =
            '<strong>Server Time:</strong> ' + new Date().toLocaleString('id-ID');
    }, 1000);
    </script>
</body>

</html>