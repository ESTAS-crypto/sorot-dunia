<?php
// Load konfigurasi dan auth check
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>SOROT DUNIA</title>
    <link rel="icon" href="../project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
    :root {
        --primary-color: #000000;
        --secondary-color: #333333;
        --accent-color: #666666;
        --light-gray: #f8f9fa;
        --medium-gray: #e9ecef;
        --border-color: #dee2e6;
        --white: #ffffff;
        --success-color: #28a745;
        --error-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #17a2b8;
        --hover-color: #f1f3f4;
        --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.08);
        --shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.12);
        --shadow-heavy: 0 8px 32px rgba(0, 0, 0, 0.16);
    }

    * {
        box-sizing: border-box;
    }

    body {
        background: var(--light-gray);
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        color: var(--primary-color);
        line-height: 1.6;
    }

    /* Navbar Styles */
    .navbar {
        background: var(--white);
        box-shadow: var(--shadow-light);
        border-bottom: 1px solid var(--border-color);
        padding: 0.75rem 0;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .navbar-brand {
        font-weight: 700;
        color: var(--primary-color) !important;
        font-size: 1.5rem;
        letter-spacing: -0.025em;
    }

    .nav-link {
        color: var(--secondary-color) !important;
        font-weight: 500;
        transition: all 0.2s ease;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        margin: 0 0.25rem;
        position: relative;
    }

    .nav-link:hover {
        color: var(--primary-color) !important;
        background: var(--hover-color);
    }

    .nav-link.active {
        color: var(--primary-color) !important;
        background: var(--light-gray);
        font-weight: 600;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        text-decoration: none;
    }

    .brand-logo img {
        height: 40px;
        width: auto;
        margin-right: 0.75rem;
    }

    /* Container Styles */
    .upload-container {
        background: var(--white);
        border-radius: 16px;
        box-shadow: var(--shadow-medium);
        padding: 2.5rem;
        margin: 2rem auto;
        border: 1px solid var(--border-color);
        max-width: 100%;
    }

    .upload-header {
        text-align: center;
        margin-bottom: 3rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .upload-header h2 {
        color: var(--primary-color);
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-size: 2rem;
        letter-spacing: -0.025em;
    }

    .upload-header p {
        color: var(--accent-color);
        font-size: 1.1rem;
        margin: 0;
    }

    /* Form Styles */
    .form-floating {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-control,
    .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        font-size: 1rem;
        transition: all 0.2s ease;
        background: var(--white);
        color: var(--primary-color);
        height: auto;
    }

    .form-floating>.form-control,
    .form-floating>.form-select {
        height: 60px;
        padding: 1.5rem 1.25rem 0.5rem;
    }

    .form-floating>label {
        padding: 1rem 1.25rem;
        color: var(--accent-color);
        font-weight: 500;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
        outline: none;
        background: var(--white);
    }

    .form-control:valid,
    .form-select:valid {
        border-color: var(--success-color);
    }

    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: var(--error-color);
    }

    /* Required field indicator */
    .required {
        color: var(--error-color);
        font-weight: 600;
    }

    /* Character Counter */
    .char-counter {
        position: absolute;
        bottom: -1.5rem;
        right: 0;
        font-size: 0.875rem;
        color: var(--accent-color);
        font-weight: 500;
    }

    /* Slug Display */
    .slug-display {
        background: var(--light-gray);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        margin-top: 0.5rem;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        color: var(--accent-color);
        word-break: break-all;
    }

    .slug-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--accent-color);
        margin-bottom: 0.25rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Button Styles */
    .btn {
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.2s ease;
        border: 2px solid transparent;
    }

    .btn-primary {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: var(--white);
    }

    .btn-primary:hover {
        background: var(--secondary-color);
        border-color: var(--secondary-color);
        color: var(--white);
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .btn-secondary {
        background: var(--white);
        border-color: var(--border-color);
        color: var(--secondary-color);
    }

    .btn-secondary:hover {
        background: var(--light-gray);
        border-color: var(--secondary-color);
        color: var(--primary-color);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    /* File Upload Styles */
    .file-upload-wrapper {
        position: relative;
        overflow: hidden;
        width: 100%;
    }

    .file-upload-input {
        position: absolute;
        left: -9999px;
    }

    .file-upload-label {
        cursor: pointer;
        border: 3px dashed var(--border-color);
        border-radius: 16px;
        padding: 3rem 2rem;
        text-align: center;
        transition: all 0.3s ease;
        background: var(--white);
        display: block;
        width: 100%;
        min-height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .file-upload-label:hover,
    .file-upload-label.dragover {
        border-color: var(--primary-color);
        background: var(--hover-color);
        transform: scale(1.02);
    }

    .file-upload-icon {
        font-size: 3rem;
        color: var(--accent-color);
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .file-upload-label:hover .file-upload-icon {
        color: var(--primary-color);
        transform: translateY(-4px);
    }

    .file-upload-text {
        margin: 0 0 0.5rem 0;
        color: var(--primary-color);
        font-weight: 600;
        font-size: 1.1rem;
    }

    .file-upload-subtext {
        margin: 0 0 1rem 0;
        color: var(--accent-color);
        font-size: 0.95rem;
    }

    .file-upload-info {
        margin: 0;
        color: var(--accent-color);
        font-size: 0.875rem;
        line-height: 1.4;
    }

    /* Preview Styles */
    .preview-container {
        margin-top: 1.5rem;
        display: none;
        text-align: center;
        position: relative;
        background: var(--light-gray);
        border-radius: 12px;
        padding: 1rem;
        border: 1px solid var(--border-color);
    }

    .preview-image {
        max-width: 100%;
        max-height: 300px;
        border-radius: 8px;
        box-shadow: var(--shadow-light);
    }

    .preview-info {
        margin-top: 1rem;
        padding: 1rem;
        background: var(--white);
        border-radius: 8px;
        font-size: 0.875rem;
        color: var(--secondary-color);
        border: 1px solid var(--border-color);
    }

    .remove-preview {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: var(--error-color);
        color: var(--white);
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        cursor: pointer;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-light);
        transition: all 0.2s ease;
    }

    .remove-preview:hover {
        transform: scale(1.1);
        box-shadow: var(--shadow-medium);
    }

    /* Tag Input Styles */
    .tag-input-container {
        position: relative;
    }

    .tag-display {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding: 0.75rem;
        background: var(--light-gray);
        border-radius: 8px;
        border: 1px solid var(--border-color);
        min-height: 50px;
    }

    .tag-item {
        background: var(--primary-color);
        color: var(--white);
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: tagAppear 0.3s ease;
    }

    .tag-remove {
        background: none;
        border: none;
        color: var(--white);
        cursor: pointer;
        padding: 0;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease;
    }

    .tag-remove:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    @keyframes tagAppear {
        from {
            opacity: 0;
            transform: scale(0.8);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .tag-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: var(--shadow-medium);
        margin-top: 0.5rem;
    }

    .tag-suggestion {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
        font-size: 0.9rem;
    }

    .tag-suggestion:hover {
        background: var(--hover-color);
    }

    .tag-suggestion:last-child {
        border-bottom: none;
    }

    /* Alert Styles */
    .alert-custom {
        border-radius: 12px;
        margin-bottom: 1.5rem;
        padding: 1rem 1.25rem;
        border: 1px solid transparent;
        display: flex;
        align-items: center;
        font-weight: 500;
    }

    .alert-success {
        background: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }

    .alert-error {
        background: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }

    .alert-warning {
        background: #fff3cd;
        border-color: #ffeaa7;
        color: #856404;
    }

    .alert-info {
        background: #d1ecf1;
        border-color: #bee5eb;
        color: #0c5460;
    }

    .btn-close {
        background: none;
        border: none;
        margin-left: auto;
        opacity: 0.7;
        cursor: pointer;
        padding: 0.25rem;
    }

    /* Loading Spinner */
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: var(--white);
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Progress Bar */
    .upload-progress {
        display: none;
        margin-top: 1.5rem;
    }

    .progress {
        height: 12px;
        border-radius: 6px;
        background: var(--light-gray);
        border: 1px solid var(--border-color);
    }

    .progress-bar {
        background: var(--primary-color);
        transition: width 0.3s ease;
        border-radius: 6px;
    }

    /* Form Validation */
    .invalid-feedback {
        color: var(--error-color);
        font-size: 0.875rem;
        margin-top: 0.5rem;
        font-weight: 500;
    }

    .valid-feedback {
        color: var(--success-color);
        font-size: 0.875rem;
        margin-top: 0.5rem;
        font-weight: 500;
    }

    /* Tooltip */
    .tooltip-custom {
        position: relative;
        cursor: help;
        color: var(--accent-color);
        margin-left: 0.25rem;
    }

    .tooltip-custom:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        background: var(--primary-color);
        color: var(--white);
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 1000;
        box-shadow: var(--shadow-light);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .upload-container {
            margin: 1rem;
            padding: 1.5rem;
            border-radius: 12px;
        }

        .upload-header {
            margin-bottom: 2rem;
        }

        .upload-header h2 {
            font-size: 1.5rem;
        }

        .file-upload-label {
            padding: 2rem 1rem;
            min-height: 150px;
        }

        .file-upload-icon {
            font-size: 2rem;
        }

        .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .navbar-brand {
            font-size: 1.25rem;
        }

        .brand-logo img {
            height: 32px;
        }
    }

    /* Print Styles */
    @media print {

        .navbar,
        .btn,
        .file-upload-wrapper {
            display: none;
        }

        .upload-container {
            box-shadow: none;
            border: 1px solid var(--border-color);
        }
    }

    /* Smooth Animations */
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Focus Styles for Accessibility */
    .form-control:focus,
    .form-select:focus,
    .btn:focus {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }

    /* High Contrast Mode Support */
    @media (prefers-contrast: high) {
        :root {
            --border-color: #000000;
            --accent-color: #000000;
        }
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="brand-logo" href="/project/index.php">
                <img src="/project/img/NewLogo.webp" alt="Sorot Dunia Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"
                            href="/project/index.php">
                            <i class="fas fa-home me-2"></i>Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'uplod.php') ? 'active' : ''; ?>"
                            href="uplod.php">
                            <i class="fas fa-plus-circle me-2"></i>Upload Berita
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage.php') ? 'active' : ''; ?>"
                            href="manage.php">
                            <i class="fas fa-cog me-2"></i>Kelola Berita
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/project/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>