<?php
// multi-view.php
require_once 'config/config.php';
require_once 'config/auth_check.php';

// Cek apakah user adalah admin
checkAdminRole();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-View Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    body {
        background-color: #1a1a1a;
        color: #ffffff;
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden;
    }

    .multi-view-container {
        display: flex;
        height: 100vh;
        width: 100vw;
    }

    .sidebar {
        width: 250px;
        background-color: #2d2d2d;
        border-right: 1px solid #404040;
        padding: 1rem;
        overflow-y: auto;
    }

    .main-content {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .tabs-container {
        background-color: #2d2d2d;
        border-bottom: 1px solid #404040;
        padding: 0.5rem 1rem;
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
    }

    .tab-button {
        background-color: #3a3a3a;
        border: 1px solid #555555;
        color: #ffffff;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        cursor: pointer;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .tab-button:hover {
        background-color: #4a4a4a;
    }

    .tab-button.active {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .tab-close {
        margin-left: 0.5rem;
        opacity: 0.7;
        cursor: pointer;
    }

    .tab-close:hover {
        opacity: 1;
    }

    .content-frame {
        flex: 1;
        border: none;
        background-color: #1a1a1a;
    }

    .menu-item {
        display: block;
        color: #ffffff;
        text-decoration: none;
        padding: 0.75rem 1rem;
        border-radius: 0.25rem;
        margin-bottom: 0.5rem;
        transition: all 0.2s;
    }

    .menu-item:hover {
        background-color: #3a3a3a;
        color: #ffffff;
    }

    .menu-item i {
        margin-right: 0.5rem;
    }

    .user-info {
        background-color: #3a3a3a;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 200px;
        }

        .tab-button {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
    }
    </style>
</head>

<body>
    <div class="multi-view-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="user-info">
                <div class="d-flex align-items-center">
                    <i class="bi bi-person-circle fs-4 me-2"></i>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                        <small class="text-muted">Administrator</small>
                    </div>
                </div>
            </div>

            <nav>
                <a href="#" class="menu-item" onclick="openTab('dashboard', 'Dashboard', 'dashboard.php')">
                    <i class="bi bi-house"></i>Dashboard
                </a>
                <a href="#" class="menu-item"
                    onclick="openTab('articles', 'Kelola Artikel', 'admin/page/articles.php')">
                    <i class="bi bi-file-text"></i>Kelola Artikel
                </a>
                <a href="#" class="menu-item"
                    onclick="openTab('add-article', 'Tambah Artikel', 'admin/page/add-artikel.php')">
                    <i class="bi bi-plus-circle"></i>Tambah Artikel
                </a>
                <a href="#" class="menu-item" onclick="openTab('users', 'Kelola User', 'admin/page/users.php')">
                    <i class="bi bi-people"></i>Kelola User
                </a>
                <a href="#" class="menu-item" onclick="openTab('categories', 'Kategori', 'admin/page/categories.php')">
                    <i class="bi bi-tags"></i>Kategori
                </a>
                <a href="#" class="menu-item" onclick="openTab('settings', 'Pengaturan', 'admin/page/settings.php')">
                    <i class="bi bi-gear"></i>Pengaturan
                </a>
                <hr>
                <a href="logout.php" class="menu-item text-danger">
                    <i class="bi bi-box-arrow-right"></i>Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Tabs -->
            <div class="tabs-container" id="tabsContainer">
                <button class="tab-button active" id="tab-welcome" onclick="switchTab('welcome')">
                    <i class="bi bi-house"></i>
                    Welcome
                </button>
            </div>

            <!-- Content Frame -->
            <iframe id="contentFrame" class="content-frame" src="about:blank">
                <div class="d-flex justify-content-center align-items-center h-100">
                    <div class="text-center">
                        <i class="bi bi-house fs-1 mb-3"></i>
                        <h3>Selamat Datang di Admin Panel</h3>
                        <p class="text-muted">Pilih menu dari sidebar untuk mulai bekerja</p>
                    </div>
                </div>
            </iframe>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let tabs = {
        'welcome': {
            name: 'Welcome',
            url: 'about:blank',
            icon: 'bi-house'
        }
    };
    let activeTab = 'welcome';

    function openTab(tabId, tabName, url) {
        // Cek apakah tab sudah ada
        if (tabs[tabId]) {
            switchTab(tabId);
            return;
        }

        // Buat tab baru
        tabs[tabId] = {
            name: tabName,
            url: url,
            icon: getIconForTab(tabId)
        };

        // Buat button tab
        const tabButton = document.createElement('button');
        tabButton.className = 'tab-button';
        tabButton.id = `tab-${tabId}`;
        tabButton.onclick = () => switchTab(tabId);
        tabButton.innerHTML = `
                <i class="${tabs[tabId].icon}"></i>
                ${tabName}
                <span class="tab-close" onclick="closeTab('${tabId}', event)">
                    <i class="bi bi-x"></i>
                </span>
            `;

        document.getElementById('tabsContainer').appendChild(tabButton);
        switchTab(tabId);
    }

    function switchTab(tabId) {
        // Update active tab
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById(`tab-${tabId}`).classList.add('active');

        // Update iframe content
        const iframe = document.getElementById('contentFrame');
        if (tabId === 'welcome') {
            iframe.src = 'about:blank';
            iframe.style.display = 'none';
            setTimeout(() => {
                iframe.style.display = 'block';
                iframe.contentDocument.body.innerHTML = `
                        <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #1a1a1a; color: #ffffff; font-family: Arial, sans-serif;">
                            <div style="text-align: center;">
                                <i class="bi bi-house" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                <h3>Selamat Datang di Admin Panel</h3>
                                <p style="color: #888;">Pilih menu dari sidebar untuk mulai bekerja</p>
                            </div>
                        </div>
                    `;
            }, 100);
        } else {
            iframe.src = tabs[tabId].url;
            iframe.style.display = 'block';
        }

        activeTab = tabId;
    }

    function closeTab(tabId, event) {
        event.stopPropagation();

        if (tabId === 'welcome') {
            return; // Tidak bisa tutup tab welcome
        }

        // Hapus tab
        delete tabs[tabId];
        document.getElementById(`tab-${tabId}`).remove();

        // Jika tab yang ditutup adalah tab aktif, pindah ke tab lain
        if (activeTab === tabId) {
            const remainingTabs = Object.keys(tabs);
            if (remainingTabs.length > 0) {
                switchTab(remainingTabs[remainingTabs.length - 1]);
            }
        }
    }

    function getIconForTab(tabId) {
        const icons = {
            'dashboard': 'bi-house',
            'articles': 'bi-file-text',
            'add-article': 'bi-plus-circle',
            'users': 'bi-people',
            'categories': 'bi-tags',
            'settings': 'bi-gear'
        };
        return icons[tabId] || 'bi-file';
    }

    // Handle iframe load errors
    document.getElementById('contentFrame').addEventListener('error', function() {
        this.contentDocument.body.innerHTML = `
                <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #1a1a1a; color: #ffffff; font-family: Arial, sans-serif;">
                    <div style="text-align: center;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 4rem; margin-bottom: 1rem; color: #dc3545;"></i>
                        <h3>Halaman Tidak Dapat Dimuat</h3>
                        <p style="color: #888;">Terjadi kesalahan saat memuat halaman</p>
                    </div>
                </div>
            `;
    });

    // Initialize welcome tab
    switchTab('welcome');
    </script>
</body>

</html>