<?php
$baseUrl = 'https://inievan.my.id/project/';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth_check.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>SOROT DUNIA</title>
 <link rel="icon" href="<?php echo $baseUrl; ?>/img/icon.webp" type="image/webp" />
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
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

    /* CRITICAL FIX: Mobile Dropdown */
    .navbar-toggler {
        border: 2px solid var(--border-color);
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
    }

    .navbar-toggler:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.1);
    }

    /* Ensure dropdown works on mobile */
    @media (max-width: 991.98px) {
        .navbar-collapse {
            background: var(--white);
            padding: 1rem;
            margin-top: 0.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow-medium);
        }
        
        .navbar-nav {
            gap: 0.5rem;
        }
        
        .nav-item {
            width: 100%;
        }
        
        .nav-link {
            width: 100%;
            text-align: left;
            padding: 0.75rem 1rem;
        }
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
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
        outline: none;
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/project/">
                <img src="/project/img/NewLogo.webp" alt="Sorot Dunia Logo" style="height: 40px;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>"
                            href="/project/">
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
    
    <!-- Bootstrap Bundle JS (includes Popper) - LOAD BEFORE ANY CUSTOM SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CRITICAL FIX: Initialize Bootstrap components for mobile -->
    <script>
    // Ensure Bootstrap is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? bootstrap.Tooltip.VERSION : 'NOT LOADED');
        
        // Initialize navbar collapse functionality
        var navbarToggler = document.querySelector('.navbar-toggler');
        var navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            // Create new Collapse instance
            var bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                toggle: false
            });
            
            // Handle toggler click
            navbarToggler.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Navbar toggler clicked');
                
                if (navbarCollapse.classList.contains('show')) {
                    bsCollapse.hide();
                } else {
                    bsCollapse.show();
                }
            });
            
            console.log('✓ Navbar collapse initialized');
        }
        
        // Auto-close navbar when clicking nav links on mobile
        var navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    var bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                    if (bsCollapse && navbarCollapse.classList.contains('show')) {
                        bsCollapse.hide();
                    }
                }
            });
        });
        
        console.log('✓ Mobile nav links auto-close initialized');
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        var navbarCollapse = document.querySelector('.navbar-collapse');
        if (window.innerWidth >= 992 && navbarCollapse) {
            // Auto-show navbar on desktop
            if (!navbarCollapse.classList.contains('show')) {
                navbarCollapse.classList.add('show');
            }
        }
    });
    </script>
</body>
</html>