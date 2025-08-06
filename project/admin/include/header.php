<?php
$baseUrl = 'https://inievan.my.id/project/admin';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sorot Dunia</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    :root {
        --bg-primary: #1a1a1a;
        --bg-secondary: #2a2a2a;
        --bg-accent: #333;
        --text-primary: #fff;
        --text-secondary: #ccc;
        --text-muted: #999;
        --border-color: #444;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --info-color: #17a2b8;
    }

    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
    }

    .sidebar {
        background-color: var(--bg-secondary);
        min-height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        z-index: 1000;
        transition: transform 0.3s ease;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
        overflow-y: auto;
    }

    .sidebar.collapsed {
        transform: translateX(-100%);
    }

    .main-content {
        margin-left: 280px;
        padding: 1rem;
        transition: margin-left 0.3s ease;
        min-height: 100vh;
    }

    .main-content.expanded {
        margin-left: 0;
    }

    .card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background-color: var(--bg-accent);
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-color);
        border-radius: 10px 10px 0 0 !important;
    }

    .card-body {
        padding: 1.5rem;
    }

    .table-dark {
        background-color: var(--bg-secondary);
        border-color: var(--border-color);
    }

    .table-dark th {
        background-color: var(--bg-accent);
        border-color: var(--border-color);
    }

    .table-dark td {
        border-color: var(--border-color);
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
    }

    .sidebar-overlay.show {
        display: block;
    }

    .nav-link {
        color: var(--text-secondary) !important;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .nav-link.active {
        background-color: var(--bg-accent);
        color: var(--text-primary) !important;
    }

    .nav-link:hover {
        background-color: var(--bg-accent);
        color: var(--text-primary) !important;
    }

    .text-muted {
        color: var(--text-muted) !important;
    }

    .bg-success {
        background-color: var(--success-color) !important;
    }

    .bg-warning {
        background-color: var(--warning-color) !important;
    }

    .bg-danger {
        background-color: var(--danger-color) !important;
    }

    .bg-info {
        background-color: var(--info-color) !important;
    }

    .alert {
        border-radius: 8px;
    }

    .btn {
        border-radius: 6px;
    }

    /* Statistics Cards */
    .stat-card {
        background: linear-gradient(135deg, var(--bg-secondary), var(--bg-accent));
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        border: 1px solid var(--border-color);
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card.total-articles {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .stat-card.total-users {
        background: linear-gradient(135deg, #f093fb, #f5576c);
    }

    .stat-card.pending-articles {
        background: linear-gradient(135deg, #ffecd2, #fcb69f);
    }

    .stat-card.daily-visitors {
        background: linear-gradient(135deg, #a8edea, #fed6e3);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin: 0.5rem 0;
    }

    .stat-label {
        font-size: 1rem;
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
        }
    }
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark bg-dark d-md-none">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" onclick="toggleSidebar()">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0 h1">Sorot Dunia Admin</span>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');

        if (window.innerWidth > 768) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    }
    </script>
</body>

</html>