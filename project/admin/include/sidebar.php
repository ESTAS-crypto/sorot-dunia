<?php
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Header -->
    <div class="text-center py-4 border-bottom border-secondary">
        <h4 class="text-white mb-0 fw-bold">Sorot Dunia</h4>
        <small class="text-muted">Admin Panel</small>
    </div>
    
    <!-- User Profile -->
    <div class="p-4 text-center border-bottom border-secondary">
        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
            style="width: 60px; height: 60px; font-size: 1.5rem;">
            <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
        </div>
        <div class="fw-semibold"><?php echo $_SESSION['username'] ?? 'Admin'; ?></div>
        <small class="text-muted"><?php echo $_SESSION['user_role'] ?? 'Administrator'; ?></small>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="p-4">
        <a href="?page=dashboard" 
           class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        
        <a href="?page=articles" 
           class="nav-link <?php echo ($current_page == 'articles') ? 'active' : ''; ?>">
            <i class="bi bi-newspaper"></i>Artikel
        </a>
        
        <a href="?page=users" 
           class="nav-link <?php echo ($current_page == 'users') ? 'active' : ''; ?>">
            <i class="bi bi-people"></i>Pengguna
        </a>
        
        <a href="?page=categories" 
           class="nav-link <?php echo ($current_page == 'categories') ? 'active' : ''; ?>">
            <i class="bi bi-tags"></i>Kategori
        </a>
        
        <a href="?page=comments" 
           class="nav-link <?php echo ($current_page == 'comments') ? 'active' : ''; ?>">
            <i class="bi bi-chat-dots"></i>Komentar
        </a>
        
        <a href="?page=analytics" 
           class="nav-link <?php echo ($current_page == 'analytics') ? 'active' : ''; ?>">
            <i class="bi bi-graph-up"></i>Analitik
        </a>
        
        <a href="?page=token" 
           class="nav-link <?php echo ($current_page == 'token') ? 'active' : ''; ?>">
            <i class="bi bi-key"></i>Token
        </a>
        
        <a href="?page=settings" 
           class="nav-link <?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
            <i class="bi bi-gear"></i>Pengaturan
        </a>
        
        <!-- Divider -->
        <hr class="my-3" style="border-color: var(--border-color);">
        
        <!-- Logout Section -->
        <a href="../logout.php" 
           class="nav-link text-danger"
           onclick="return confirm('Apakah Anda yakin ingin logout?')">
            <i class="bi bi-box-arrow-right"></i>Logout
        </a>
        
        <a href="../index.php" class="nav-link text-muted">
            <i class="bi bi-house"></i>Kembali ke Home
        </a>
    </nav>
</div>