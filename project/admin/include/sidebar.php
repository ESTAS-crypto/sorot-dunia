<?php
$baseUrl = 'https://inievan.my.id/project/admin';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="p-3 text-center border-bottom border-secondary">
        <h4 class="text-white mb-0">Sorot Dunia Admin</h4>
    </div>
    <div class="p-3 text-center border-bottom border-secondary">
        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2"
            style="width: 60px; height: 60px; font-size: 1.5rem;">
            <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
        </div>
        <div class="text-secondary"><?php echo $_SESSION['username'] ?? 'Admin'; ?></div>
        <small class="text-muted">Role: <?php echo $_SESSION['user_role'] ?? 'N/A'; ?></small>
    </div>
    <nav class="nav-menu p-3">
        <a href="?page=dashboard"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>
        <a href="?page=articles"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'articles') ? 'active' : ''; ?>">
            <i class="bi bi-newspaper me-2"></i>Artikel
        </a>
        <a href="?page=users"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'users') ? 'active' : ''; ?>">
            <i class="bi bi-people me-2"></i>Pengguna
        </a>
        <a href="?page=categories"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'categories') ? 'active' : ''; ?>">
            <i class="bi bi-tags me-2"></i>Kategori
        </a>
        <a href="?page=comments"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'comments') ? 'active' : ''; ?>">
            <i class="bi bi-chat-dots me-2"></i>Komentar
        </a>
        <a href="?page=analytics"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'analytics') ? 'active' : ''; ?>">
            <i class="bi bi-graph-up me-2"></i>Analitik
        </a>
        <a href="?page=token"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
            <i class="bi bi-gear me-2"></i>Token
        </a>
        <a href="?page=settings"
            class="nav-link text-secondary py-2 px-3 mb-1 rounded <?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
            <i class="bi bi-gear me-2"></i>Pengaturan
        </a>
        <hr class="my-3">
        <a href="../logout.php" class="nav-link text-danger py-2 px-3 mb-1 rounded"
            onclick="return confirm('Apakah Anda yakin ingin logout?')">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
        </a>
        <a href="../index.php" class="nav-link text-danger py-2 px-3 mb-1 rounded">
            <i class="bi bi-box-arrow-right me-2"></i>back to home
        </a>
    </nav>
</div>