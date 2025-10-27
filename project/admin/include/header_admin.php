<?php
$baseUrl = 'https://inievan.my.id/project/admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sorot Dunia</title>
    <link rel="icon" href="/project/img/icon.webp" type="image/webp" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
    /* ========== ELEGANT BLACK & WHITE THEME ========== */
    :root {
        --bg-primary: #0d0d0d;
        --bg-secondary: #1a1a1a;
        --bg-tertiary: #262626;
        --bg-hover: #333333;
        --border-color: #404040;
        --text-primary: #ffffff;
        --text-secondary: #b3b3b3;
        --text-muted: #808080;
        --accent: #ffffff;
        --accent-hover: #f0f0f0;
        --shadow: rgba(0, 0, 0, 0.3);
    }

    /* ========== BASE STYLES ========== */
    * {
        box-sizing: border-box;
        color: white;
    }

    body {
        background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        color: var(--text-primary);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        margin: 0;
        min-height: 100vh;
    }

    /* ========== LAYOUT ========== */
    .sidebar {
        background: var(--bg-secondary);
        backdrop-filter: blur(10px);
        border-right: 1px solid var(--border-color);
        min-height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        z-index: 1000;
        transition: transform 0.3s ease;
        overflow-y: auto;
    }

    .sidebar.collapsed {
        transform: translateX(-100%);
    }

    .main-content {
        margin-left: 280px;
        padding: 1.5rem;
        transition: margin-left 0.3s ease;
        min-height: 100vh;
    }

    .main-content.expanded {
        margin-left: 0;
    }

    /* ========== SIDEBAR STYLES ========== */
    .sidebar .nav-link {
        color: var(--text-secondary);
        border-radius: 8px;
        margin-bottom: 4px;
        padding: 12px 16px;
        transition: all 0.3s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .sidebar .nav-link:hover {
        color: var(--text-primary);
        background: var(--bg-hover);
        transform: translateX(4px);
    }

    .sidebar .nav-link.active {
        color: var(--text-primary);
        background: var(--bg-tertiary);
        border-left: 3px solid var(--accent);
    }

    .sidebar .nav-link i {
        width: 20px;
        text-align: center;
        margin-right: 12px;
    }

    /* ========== CARDS ========== */
    .card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 4px 20px var(--shadow);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 40px var(--shadow);
    }

    .card-header {
        background: var(--bg-tertiary);
        border-bottom: 1px solid var(--border-color);
        border-radius: 12px 12px 0 0 !important;
        padding: 1.5rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    .card-title {
        color: var(--text-primary);
        margin-bottom: 0;
    }

    /* ========== BUTTONS ========== */
    .btn {
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
    }

    .btn-primary {
        background: var(--accent);
        color: var(--bg-primary);
    }

    .btn-primary:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
    }

    .btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
        background: var(--bg-hover);
        border-color: var(--accent);
    }

    .btn-danger {
        background: #dc2626;
        color: white;
    }

    .btn-danger:hover {
        background: #b91c1c;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    .btn-warning {
        background: #f59e0b;
        color: var(--accent);
    }

    .btn-warning:hover {
        background: #d97706;
    }

    .btn-info {
        background: #3b82f6;
        color: white;
    }

    .btn-info:hover {
        background: #2563eb;
    }

    /* ========== FORMS ========== */
    .form-control,
    .form-select {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
        background: var(--bg-hover);
        border-color: var(--accent);
        color: var(--text-primary);
        box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.1);
    }

    .form-control::placeholder {
        color: var(--text-muted);
    }

    .form-label {
        color: var(--text-primary);
        font-weight: 500;
        margin-bottom: 8px;
    }

    .form-text {
        color: var(--text-muted);
    }

    .input-group-text {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
    }

    /* ========== TABLES ========== */
    .table {
        background: transparent;
        color: black;

    }

    .table th {
        background: var(--bg-tertiary);
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        padding: 12px;
        white-space: nowrap;
    }

    .table td {
        border-color: var(--border-color);
        vertical-align: middle;
        padding: 12px;
    }

    .table tbody tr:hover {
        background: var(--bg-hover);
    }

    .table-responsive {
        background: var(--bg-secondary);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        overflow: hidden;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background: rgba(255, 255, 255, 0.02);
    }

    /* ========== BADGES ========== */
    .badge {
        font-weight: 500;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 0.75rem;
    }

    .badge.bg-success {
        background: #10b981 !important;
    }

    .badge.bg-warning {
        background: #f59e0b !important;
        color: var(--bg-primary) !important;
    }

    .badge.bg-danger {
        background: #ef4444 !important;
    }

    .badge.bg-info {
        background: #3b82f6 !important;
    }

    .badge.bg-secondary {
        background: var(--bg-tertiary) !important;
        color: var(--text-secondary) !important;
    }

    /* ========== ALERTS ========== */
    .alert {
        border-radius: 8px;
        border: none;
        font-weight: 500;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .alert-info {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    /* ========== MODALS ========== */
    .modal-content {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 20px 60px var(--shadow);
    }

    .modal-header {
        background: var(--bg-tertiary);
        border-bottom: 1px solid var(--border-color);
        border-radius: 12px 12px 0 0;
    }

    .modal-title {
        color: var(--text-primary);
    }

    .modal-footer {
        background: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
        border-radius: 0 0 12px 12px;
    }

    .modal-body {
        color: var(--text-primary);
    }

    /* ========== OVERLAY ========== */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 999;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.show {
        display: block;
        opacity: 1;
    }

    /* ========== STATS CARDS ========== */
    .stats-card {
        border-radius: 12px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        padding: 1.5rem;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .stats-card.visitors {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stats-card.articles {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-card.users {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stats-card.pending {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    /* ========== ACTION BUTTONS ========== */
    .action-buttons {
        display: flex;
        gap: 4px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.3s ease;
        cursor: pointer;
        padding: 6px 10px;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .btn-view {
        background: #3b82f6;
        color: white;
    }

    .btn-edit {
        background: #10b981;
        color: white;
    }

    .btn-approve {
        background: #22c55e;
        color: white;
    }

    .btn-reject {
        background: #ef4444;
        color: white;
    }

    .btn-delete {
        background: #dc2626;
        color: white;
    }

    /* ========== SCROLL HINT ========== */
    .scroll-hint {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 8px 12px;
        margin-bottom: 16px;
        font-size: 0.875rem;
        color: var(--text-muted);
        text-align: center;
        display: none;
    }

    /* ========== NAV PILLS ========== */
    .nav-pills .nav-link {
        color: var(--text-secondary);
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: 25px;
        padding: 8px 16px;
        margin-right: 8px;
        transition: all 0.3s ease;
    }

    .nav-pills .nav-link:hover {
        color: var(--text-primary);
        background: var(--bg-hover);
        border-color: var(--accent);
    }

    .nav-pills .nav-link.active {
        color: var(--bg-primary);
        background: var(--accent);
        border-color: var(--accent);
    }

    /* ========== WELCOME ALERT ========== */
    .welcome-alert {
        animation: slideIn 0.5s ease-out;
        transition: opacity 0.5s ease-out;
    }

    .welcome-alert.fade-out {
        opacity: 0;
        transform: translateY(-20px);
    }

    /* ========== VISITOR TREND ========== */
    .visitor-trend {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .visitor-trend:last-child {
        border-bottom: none;
    }

    .trend-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
        font-size: 1.2rem;
    }

    .trend-up {
        color: #22c55e;
    }

    .trend-down {
        color: #ef4444;
    }

    .trend-stable {
        color: #6b7280;
    }

    /* ========== IMAGE UPLOAD ========== */
    .image-option-tabs {
        display: flex;
        margin-bottom: 16px;
        gap: 8px;
    }

    .image-option-tab {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .image-option-tab:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }

    .image-option-tab.active {
        background: var(--accent);
        color: var(--bg-primary);
        border-color: var(--accent);
    }

    .image-option-content {
        display: none;
    }

    .image-option-content.active {
        display: block;
    }

    .image-upload-section {
        background: var(--bg-tertiary);
        border: 2px dashed var(--border-color);
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .image-upload-section:hover,
    .image-upload-section.dragover {
        border-color: var(--accent);
        background: var(--bg-hover);
    }

    .image-preview {
        max-width: 200px;
        max-height: 200px;
        margin-top: 16px;
        border-radius: 8px;
    }

    .current-image-display {
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 16px;
    }

    /* ========== ARTICLE PREVIEW ========== */
    .article-meta {
        background: var(--bg-tertiary);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
    }

    .article-title {
        color: var(--text-primary);
        font-weight: 600;
        line-height: 1.3;
    }

    .article-content {
        color: var(--text-secondary);
        line-height: 1.6;
        font-size: 1rem;
    }

    .article-preview {
        background: var(--bg-secondary);
        border-radius: 8px;
        padding: 24px;
    }

    /* ========== MOBILE RESPONSIVE ========== */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
            padding: 1rem;
        }

        .card-body {
            padding: 1rem;
        }

        .card-header {
            padding: 1rem;
        }

        .scroll-hint {
            display: block;
        }

        .table {
            font-size: 0.875rem;
        }

        .table th,
        .table td {
            padding: 8px;
        }

        .btn-action {
            min-width: 30px;
            height: 30px;
            padding: 5px 8px;
        }

        .stats-card {
            padding: 1rem;
        }

        .stats-card h5 {
            font-size: 0.875rem;
        }

        .stats-card .display-6 {
            font-size: 1.5rem;
        }
    }

    @media (max-width: 576px) {
        .main-content {
            padding: 0.75rem;
        }

        .card-header h4,
        .card-header h5 {
            font-size: 1rem;
        }

        .table {
            font-size: 0.75rem;
        }

        .table th,
        .table td {
            padding: 6px;
        }

        .btn {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }

        .btn-action {
            min-width: 28px;
            height: 28px;
            padding: 4px 6px;
        }

        .badge {
            font-size: 0.65rem;
            padding: 4px 8px;
        }
    }

    /* ========== ANIMATIONS ========== */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ========== UTILITIES ========== */
    .text-muted {
        color: var(--accent) !important;
    }

    .border-secondary {
        border-color: var(--border-color) !important;
    }

    .bg-secondary {
        background-color: var(--bg-secondary) !important;
    }

    .bg-dark {
        background-color: var(--bg-primary) !important;
    }

    /* ========== SCROLLBAR ========== */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--bg-tertiary);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--accent);
    }

    /* ========== CLOSE BUTTON ========== */
    .btn-close {
        filter: invert(1);
    }
    </style>
</head>

<body>
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark d-md-none" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
        <div class="container-fluid">
            <button class="navbar-toggler border-0" type="button" onclick="toggleSidebar()">
                <span class="navbar-toggler-icon"></span>
            </button>
            <span class="navbar-brand mb-0 h1">Sorot Dunia Admin</span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>