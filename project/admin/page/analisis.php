<?php
// admin/page/analisis.php - Halaman Analisis
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../config/login.php");
    exit();
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'admin') {
    header("Location: ../login.php?error=access_denied");
    exit();
}

// Get date range for filtering (default: last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = sanitize_input($_GET['start_date']);
    $end_date = sanitize_input($_GET['end_date']);
}

// Get total statistics
$total_articles_query = "SELECT COUNT(*) as total FROM articles";
$total_articles_result = mysqli_query($koneksi, $total_articles_query);
$total_articles = mysqli_fetch_assoc($total_articles_result)['total'];

$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = mysqli_query($koneksi, $total_users_query);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$total_categories_query = "SELECT COUNT(*) as total FROM categories";
$total_categories_result = mysqli_query($koneksi, $total_categories_query);
$total_categories = mysqli_fetch_assoc($total_categories_result)['total'];

$total_comments_query = "SELECT COUNT(*) as total FROM comments";
$total_comments_result = mysqli_query($koneksi, $total_comments_query);
$total_comments = mysqli_fetch_assoc($total_comments_result)['total'];

// Get article statistics by status
$article_status_query = "SELECT 
    article_status, 
    COUNT(*) as count 
FROM articles 
GROUP BY article_status";
$article_status_result = mysqli_query($koneksi, $article_status_query);
$article_status_stats = [];
while ($row = mysqli_fetch_assoc($article_status_result)) {
    $article_status_stats[$row['article_status']] = $row['count'];
}

// Get articles by category
$articles_by_category_query = "SELECT 
    c.name as category_name, 
    COUNT(a.article_id) as article_count 
FROM categories c 
LEFT JOIN articles a ON c.category_id = a.category_id 
GROUP BY c.category_id, c.name 
ORDER BY article_count DESC 
LIMIT 10";
$articles_by_category_result = mysqli_query($koneksi, $articles_by_category_query);

// Get recent articles
$recent_articles_query = "SELECT 
    a.title, 
    a.publication_date, 
    a.article_status,
    u.username as author_name,
    c.name as category_name
FROM articles a 
JOIN users u ON a.author_id = u.id 
JOIN categories c ON a.category_id = c.category_id 
ORDER BY a.publication_date DESC 
LIMIT 10";
$recent_articles_result = mysqli_query($koneksi, $recent_articles_query);

// Get user statistics by role
$user_role_query = "SELECT 
    role, 
    COUNT(*) as count 
FROM users 
GROUP BY role";
$user_role_result = mysqli_query($koneksi, $user_role_query);
$user_role_stats = [];
while ($row = mysqli_fetch_assoc($user_role_result)) {
    $user_role_stats[$row['role']] = $row['count'];
}

// Get monthly article statistics (last 6 months)
$monthly_stats_query = "SELECT 
    DATE_FORMAT(publication_date, '%Y-%m') as month,
    COUNT(*) as count
FROM articles 
WHERE publication_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(publication_date, '%Y-%m')
ORDER BY month ASC";
$monthly_stats_result = mysqli_query($koneksi, $monthly_stats_query);
$monthly_stats = [];
while ($row = mysqli_fetch_assoc($monthly_stats_result)) {
    $monthly_stats[$row['month']] = $row['count'];
}

// Get top authors
$top_authors_query = "SELECT 
    u.username, 
    u.full_name,
    COUNT(a.article_id) as article_count 
FROM users u 
LEFT JOIN articles a ON u.id = a.author_id 
WHERE u.role IN ('admin', 'penulis')
GROUP BY u.id, u.username, u.full_name 
ORDER BY article_count DESC 
LIMIT 10";
$top_authors_result = mysqli_query($koneksi, $top_authors_query);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Website</title>
    <link rel="icon" href="../project/img/icon.webp" type="image/webp" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    /* Dark theme colors - Same as katagori.php */
    body {
        background-color: #1a1a1a;
        color: #ffffff;
    }

    .card {
        background-color: #2d2d2d;
        border: 1px solid #404040;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .card-header {
        background-color: #404040;
        border-bottom: 1px solid #555555;
        color: #ffffff;
    }

    .card-body {
        background-color: #2d2d2d;
        color: #ffffff;
    }

    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        background-color: #2d2d2d;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        margin-bottom: 0;
        background-color: #2d2d2d;
        color: #ffffff;
        min-width: 600px;
    }

    .table th {
        background-color: #404040;
        border-bottom: 2px solid #555555;
        font-weight: 600;
        color: #ffffff;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table td {
        vertical-align: middle;
        padding: 12px;
        border-bottom: 1px solid #555555;
        background-color: #2d2d2d;
        color: #ffffff;
        white-space: nowrap;
    }

    .table tbody tr:hover {
        background-color: #3d3d3d;
    }

    .table tbody tr:hover td {
        background-color: #3d3d3d;
    }

    .badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 4px;
        color: #ffffff;
    }

    .badge-published {
        background-color: #28a745 !important;
    }

    .badge-pending {
        background-color: #ffc107 !important;
        color: #000 !important;
    }

    .badge-draft {
        background-color: #6c757d !important;
    }

    .badge-rejected {
        background-color: #dc3545 !important;
    }

    .form-control {
        background-color: #404040;
        border: 1px solid #555555;
        color: #ffffff;
    }

    .form-control:focus {
        background-color: #404040;
        border-color: #6c757d;
        color: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    }

    .form-select {
        background-color: #404040;
        border: 1px solid #555555;
        color: #ffffff;
    }

    .form-select:focus {
        background-color: #404040;
        border-color: #6c757d;
        color: #ffffff;
        box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25);
    }

    .form-label {
        color: #ffffff;
    }

    .btn-primary {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .btn-primary:hover {
        background-color: #5a6268;
        border-color: #5a6268;
    }

    .text-muted {
        color: #adb5bd !important;
    }

    .stats-card {
        background: linear-gradient(135deg, #404040 0%, #2d2d2d 100%);
        border: 1px solid #555555;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        text-align: center;
    }

    .stats-card .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #ffffff;
        margin-bottom: 10px;
    }

    .stats-card .stats-label {
        color: #adb5bd;
        font-size: 1rem;
        margin-bottom: 5px;
    }

    .stats-card .stats-icon {
        font-size: 3rem;
        color: #6c757d;
        margin-bottom: 10px;
    }

    .chart-container {
        position: relative;
        height: 400px;
        background-color: #2d2d2d;
        border-radius: 8px;
        padding: 20px;
    }

    .no-data {
        color: #adb5bd !important;
    }

    .scroll-hint {
        display: none;
        background-color: #404040;
        color: #adb5bd;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        text-align: center;
        margin-bottom: 10px;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding: 15px;
        }

        .scroll-hint {
            display: block;
        }

        .table-responsive {
            font-size: 13px;
            border: 1px solid #555555;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .table {
            min-width: 700px;
        }

        .table th,
        .table td {
            padding: 10px 8px;
            font-size: 12px;
        }

        .stats-card .stats-number {
            font-size: 2rem;
        }

        .stats-card .stats-icon {
            font-size: 2.5rem;
        }

        .chart-container {
            height: 300px;
        }
    }

    @media (max-width: 576px) {
        .table-responsive {
            font-size: 12px;
            overflow-x: scroll;
        }

        .table {
            min-width: 800px;
        }


        .table th,
        .table td {
            padding: 8px 6px;
            font-size: 11px;
        }

        .stats-card .stats-number {
            font-size: 1.8rem;
        }

        .stats-card .stats-icon {
            font-size: 2rem;
        }

        .chart-container {
            height: 250px;
            position: relative;
        }
    }

    /* Scrollbar styling */
    .table-responsive::-webkit-scrollbar {
        height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background-color: #404040;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background-color: #6c757d;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background-color: #5a6268;
    }
    </style>
</head>

<body>
    <div class="container-fluid p-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Analitik Website
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">Dashboard analitik dan statistik website</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stats-number"><?php echo $total_articles; ?></div>
                    <div class="stats-label">Total Artikel</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo $total_users; ?></div>
                    <div class="stats-label">Total User</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stats-number"><?php echo $total_categories; ?></div>
                    <div class="stats-label">Total Kategori</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stats-number"><?php echo $total_comments; ?></div>
                    <div class="stats-label">Total Komentar</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Status Artikel</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="articleStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">User Berdasarkan Role</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userRoleChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Article Statistics -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Artikel Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <div class="scroll-hint">
                            <i class="fas fa-arrows-alt-h"></i> Geser ke kiri/kanan untuk melihat seluruh tabel
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Judul</th>
                                        <th>Penulis</th>
                                        <th>Kategori</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_articles_result && mysqli_num_rows($recent_articles_result) > 0): ?>
                                    <?php while ($article = mysqli_fetch_assoc($recent_articles_result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($article['title']); ?></td>
                                        <td><?php echo htmlspecialchars($article['author_name']); ?></td>
                                        <td><?php echo htmlspecialchars($article['category_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $article['article_status']; ?>">
                                                <?php echo ucfirst($article['article_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($article['publication_date'])); ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center no-data">Tidak ada artikel ditemukan</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Top Penulis</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Penulis</th>
                                        <th>Artikel</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($top_authors_result && mysqli_num_rows($top_authors_result) > 0): ?>
                                    <?php while ($author = mysqli_fetch_assoc($top_authors_result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($author['username']); ?></td>
                                        <td>
                                            <span class="badge">
                                                <?php echo $author['article_count']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center no-data">Tidak ada data penulis</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Statistics -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Artikel Berdasarkan Kategori</h5>
                    </div>
                    <div class="card-body">
                        <div class="scroll-hint">
                            <i class="fas fa-arrows-alt-h"></i> Geser ke kiri/kanan untuk melihat seluruh tabel
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Kategori</th>
                                        <th>Jumlah Artikel</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($articles_by_category_result && mysqli_num_rows($articles_by_category_result) > 0): ?>
                                    <?php while ($category = mysqli_fetch_assoc($articles_by_category_result)): ?>
                                    <?php $percentage = $total_articles > 0 ? round(($category['article_count'] / $total_articles) * 100, 1) : 0; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                        <td>
                                            <span class="badge">
                                                <?php echo $category['article_count']; ?> artikel
                                            </span>
                                        </td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center no-data">Tidak ada kategori ditemukan</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Article Status Chart
    const articleStatusData = <?php echo json_encode($article_status_stats); ?>;
    const statusLabels = Object.keys(articleStatusData);
    const statusData = Object.values(articleStatusData);
    const statusColors = {
        'published': '#28a745',
        'pending': '#ffc107',
        'draft': '#6c757d',
        'rejected': '#dc3545'
    };

    const ctx1 = document.getElementById('articleStatusChart').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: statusLabels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
            datasets: [{
                data: statusData,
                backgroundColor: statusLabels.map(label => statusColors[label] || '#6c757d'),
                borderColor: '#2d2d2d',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#ffffff',
                        padding: 20
                    }
                }
            }
        }
    });

    // User Role Chart
    const userRoleData = <?php echo json_encode($user_role_stats); ?>;
    const roleLabels = Object.keys(userRoleData);
    const roleData = Object.values(userRoleData);
    const roleColors = ['#6c757d', '#495057', '#343a40'];

    const ctx2 = document.getElementById('userRoleChart').getContext('2d');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: roleLabels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
            datasets: [{
                label: 'Jumlah User',
                data: roleData,
                backgroundColor: roleColors,
                borderColor: '#2d2d2d',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: '#555555'
                    }
                },
                x: {
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: '#555555'
                    }
                }
            }
        }
    });
    </script>
</body>

</html>