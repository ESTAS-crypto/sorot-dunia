<?php
// Function to fetch statistics with real visitor data
function getStats() {
    global $koneksi;
    $stats = array();
    
    // Total articles
    $query = "SELECT COUNT(*) as total FROM articles";
    $result = mysqli_query($koneksi, $query);
    $stats['total_articles'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Total writers (penulis)
    $query = "SELECT COUNT(*) as total FROM users WHERE role = 'penulis'";
    $result = mysqli_query($koneksi, $query);
    $stats['total_users'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Pending articles
    $query = "SELECT COUNT(*) as total FROM articles WHERE article_status = 'pending'";
    $result = mysqli_query($koneksi, $query);
    $stats['pending_articles'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Get real visitor stats from database
    $visitor_stats = getVisitorStats($koneksi);
    $stats['daily_visitors'] = $visitor_stats['today']['unique_visitors'];
    $stats['total_visits_today'] = $visitor_stats['today']['total_visits'];
    $stats['yesterday_visitors'] = $visitor_stats['yesterday']['unique_visitors'];
    $stats['weekly_visitors'] = $visitor_stats['last_7_days']['unique_visitors'];
    $stats['monthly_visitors'] = $visitor_stats['this_month']['unique_visitors'];
    
    return $stats;
}

// Function to fetch recent articles
function getRecentArticles($limit = 5) {
    global $koneksi;
    
    $query = "SELECT a.*, u.username as author_name 
              FROM articles a 
              LEFT JOIN users u ON a.author_id = u.id 
              ORDER BY a.publication_date DESC 
              LIMIT " . intval($limit);
    
    $result = mysqli_query($koneksi, $query);
    if (!$result) {
        error_log("Query failed: " . mysqli_error($koneksi));
        return array();
    }
    
    $articles = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $articles[] = $row;
    }
    
    return $articles;
}

// Function to get popular pages from visitor tracking
function getPopularPagesData($limit = 5) {
    global $koneksi;
    return getPopularPages($koneksi, $limit);
}

// Helper functions
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

function getStatusBadge($status) {
    switch ($status) {
        case 'published':
            return '<span class="badge bg-success">Published</span>';
        case 'draft':
            return '<span class="badge bg-warning text-dark">Draft</span>';
        case 'pending':
            return '<span class="badge bg-danger">Pending</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}

// Function to format number with K notation
function formatNumber($number) {
    if ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

// Fetch data for dashboard
$stats = getStats();
$recent_articles = getRecentArticles(5);
$popular_pages = getPopularPagesData(5);
$visitor_stats = getVisitorStats($koneksi);
?>


<!-- Header -->
<div class="mb-3 mb-md-4">
    <div class="card bg-secondary text-white">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <h2 class="mb-2 mb-md-0 h4 h-md-2" style="color: white;">Dashboard Admin</h2>
                <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                    <span class="badge bg-success">
                        <i class="bi bi-circle-fill"></i> Online
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Welcome Message with Auto-hide -->
<div class="alert alert-success welcome-alert" role="alert" id="welcomeAlert">
    <i class="bi bi-check-circle"style="color: white;"></i>
    <strong style="color: white;">Selamat datang, <?php echo $_SESSION['username'] ?? 'Admin'; ?>!</strong>
    <span class="d-none d-sm-inline" style="color: white;">Anda berhasil login sebagai administrator.</span>
    <span class="d-sm-none">Login berhasil!</span>
</div>

<!-- Statistics Cards -->
<div class="row mb-3 mb-md-4">
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white stats-card visitors">
            <div class="card-body text-center p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Visitor Hari Ini</h5>
                    <i class="bi bi-people-fill"></i>
                </div>
                <p class="card-text display-6 mb-1"><?php echo formatNumber($stats['daily_visitors']); ?></p>
                <small class="text-muted">
                    <?php 
                    $yesterday = $stats['yesterday_visitors'];
                    $today = $stats['daily_visitors'];
                    if ($today > $yesterday) {
                        echo '<i class="bi bi-arrow-up trend-up"></i> +' . ($today - $yesterday);
                    } elseif ($today < $yesterday) {
                        echo '<i class="bi bi-arrow-down trend-down"></i> -' . ($yesterday - $today);
                    } else {
                        echo '<i class="bi bi-dash trend-stable"></i> Stabil';
                    }
                    ?>
                </small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white stats-card articles">
            <div class="card-body text-center p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Total Artikel</h5>
                    <i class="bi bi-newspaper"></i>
                </div>
                <p class="card-text display-6 mb-1"><?php echo formatNumber($stats['total_articles']); ?></p>
                <small class="text-muted">Published & Draft</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white stats-card users">
            <div class="card-body text-center p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Total Penulis</h5>
                    <i class="bi bi-person-fill"></i>
                </div>
                <p class="card-text display-6 mb-1"><?php echo formatNumber($stats['total_users']); ?></p>
                <small class="text-muted">Active Writers</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="card text-white stats-card pending">
            <div class="card-body text-center p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Artikel Pending</h5>
                    <i class="bi bi-clock-fill"></i>
                </div>
                <p class="card-text display-6 mb-1"><?php echo formatNumber($stats['pending_articles']); ?></p>
                <small class="text-muted">Need Review</small>
            </div>
        </div>
    </div>
</div>

<!-- Visitor Analytics Row -->
<div class="row mb-4">
    <div class="col-lg-8">
        <!-- Recent Articles -->
        <div class="card text-white">
            <div class="card-header">
                <h5 class="mb-0">Artikel Terbaru</h5>
            </div>
            <div class="card-body p-2 p-md-3">
                <div class="table-responsive">
                    <table class="table table-dark table-striped">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Judul</th>
                                <th class="d-none d-md-table-cell">Penulis</th>
                                <th class="text-nowrap">Status</th>
                                <th class="d-none d-lg-table-cell">Tanggal</th>
                                <th class="text-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_articles)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada artikel</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_articles as $article): ?>
                            <tr>
                                <td class="text-truncate" style="max-width: 150px;">
                                    <?php echo htmlspecialchars($article['title'] ?? ''); ?>
                                    <div class="d-md-none text-muted small">
                                        <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                                </td>
                                <td><?php echo getStatusBadge($article['article_status'] ?? ''); ?></td>
                                <td class="d-none d-lg-table-cell">
                                    <?php echo formatDate($article['publication_date'] ?? ''); ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="index.php?page=articles" class="btn btn-sm btn-primary" title="Kelola">
                                            <i class="bi bi-gear"style="color: black;"></i>
                                        </a>
                                        <a href="../artikel.php?id=<?php echo $article['article_id'] ?? ''; ?>"
                                            class="btn btn-sm btn-success" title="Lihat" target="_blank">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Visitor Statistics -->
        <div class="card text-white mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-graph-up"></i> Statistik Visitor
                </h5>
            </div>
            <div class="card-body">
                <div class="visitor-trend">
                    <div class="trend-icon">
                        <i class="bi bi-calendar-day trend-up"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Hari Ini</div>
                        <div class="text-muted small">
                            <?php echo number_format($visitor_stats['today']['unique_visitors']); ?> unique visitors
                        </div>
                        <div class="text-muted small">
                            <?php echo number_format($visitor_stats['today']['total_visits']); ?> total visits
                        </div>
                    </div>
                </div>

                <div class="visitor-trend">
                    <div class="trend-icon">
                        <i class="bi bi-calendar-week trend-stable"></i>
                    </div>
                    <div>
                        <div class="fw-bold">7 Hari Terakhir</div>
                        <div class="text-muted small">
                            <?php echo number_format($visitor_stats['last_7_days']['unique_visitors']); ?> unique
                            visitors
                        </div>
                        <div class="text-muted small">
                            <?php echo number_format($visitor_stats['last_7_days']['total_visits']); ?> total visits
                        </div>
                    </div>
                </div>

                <div class="visitor-trend">
                    <div class="trend-icon">
                        <i class="bi bi-calendar-month trend-up"></i>
                    </div>
                    <div>
                        <div class="fw-bold">Bulan Ini</div>
                        <div class="text-muted small">
                            <?php echo number_format($visitor_stats['this_month']['unique_visitors']); ?> unique
                            visitors
                        </div>
                        <div class="text-muted small">
                            <?php echo number_format($visitor_stats['this_month']['total_visits']); ?> total visits
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Pages -->
        <div class="card text-white">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-fire"></i> Halaman Populer
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($popular_pages)): ?>
                <div class="text-center text-muted">
                    <i class="bi bi-info-circle"></i>
                    <p class="mb-0 mt-2">Belum ada data halaman populer</p>
                </div>
                <?php else: ?>
                <?php foreach ($popular_pages as $index => $page): ?>
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-dark rounded">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                        <small class="text-truncate" style="max-width: 150px;">
                            <?php 
                            $page_name = $page['page'];
                            if ($page_name == '/' || $page_name == '/index.php') {
                                echo 'Beranda';
                            } elseif (strpos($page_name, '/artikel.php') !== false) {
                                echo 'Detail Artikel';
                            } else {
                                echo htmlspecialchars($page_name);
                            }
                            ?>
                        </small>
                    </div>
                    <span class="badge bg-secondary"><?php echo number_format($page['visits']); ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Auto-hide Welcome Alert and Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const welcomeAlert = document.getElementById('welcomeAlert');

    if (welcomeAlert) {
        // Auto-hide after 3 seconds
        setTimeout(function() {
            welcomeAlert.classList.add('fade-out');

            // Remove element after fade transition
            setTimeout(function() {
                welcomeAlert.remove();
            }, 500);
        }, 3000);

        // Allow manual close by clicking
        welcomeAlert.addEventListener('click', function() {
            welcomeAlert.classList.add('fade-out');
            setTimeout(function() {
                welcomeAlert.remove();
            }, 500);
        });
    }

    // Add smooth animations to stats cards
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate__animated', 'animate__fadeInUp');
    });
});
</script>