<?php
// Get date range (default last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = mysqli_real_escape_string($koneksi, $_GET['start_date']);
    $end_date = mysqli_real_escape_string($koneksi, $_GET['end_date']);
}

$date_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;

// Get visitor statistics
$visitor_stats = ['total_unique' => 0, 'total_visits' => 0, 'total_pageviews' => 0, 'avg_daily_visitors' => 0];
$visitor_query = "SELECT SUM(unique_visitors) as total_unique, SUM(total_visits) as total_visits, SUM(page_views) as total_pageviews, AVG(unique_visitors) as avg_daily_visitors FROM visitor_stats WHERE stat_date BETWEEN '$start_date' AND '$end_date'";
$visitor_result = mysqli_query($koneksi, $visitor_query);
if ($visitor_result) $visitor_stats = mysqli_fetch_assoc($visitor_result);

// Get daily breakdown
$daily_stats = [];
$daily_query = "SELECT stat_date, unique_visitors, total_visits, page_views FROM visitor_stats WHERE stat_date BETWEEN '$start_date' AND '$end_date' ORDER BY stat_date ASC";
$daily_result = mysqli_query($koneksi, $daily_query);
if ($daily_result) $daily_stats = mysqli_fetch_all($daily_result, MYSQLI_ASSOC);

// Get article statistics
$article_stats = ['total_articles' => 0, 'total_views' => 0];
$article_query = "SELECT COUNT(*) as total_articles, SUM(view_count) as total_views FROM articles WHERE article_status = 'published'";
$article_result = mysqli_query($koneksi, $article_query);
if ($article_result) $article_stats = mysqli_fetch_assoc($article_result);

// Get top articles
$top_articles = [];
$top_query = "SELECT a.title, a.view_count, a.publication_date, c.name as category_name, u.full_name as author_name FROM articles a LEFT JOIN categories c ON a.category_id = c.category_id LEFT JOIN users u ON a.author_id = u.id WHERE a.article_status = 'published' ORDER BY a.view_count DESC LIMIT 10";
$top_result = mysqli_query($koneksi, $top_query);
if ($top_result) $top_articles = mysqli_fetch_all($top_result, MYSQLI_ASSOC);

// Get category statistics
$category_stats = [];
$cat_query = "SELECT c.name, COUNT(a.article_id) as article_count, COALESCE(SUM(a.view_count), 0) as total_views FROM categories c LEFT JOIN articles a ON c.category_id = a.category_id AND a.article_status = 'published' GROUP BY c.category_id, c.name ORDER BY total_views DESC";
$cat_result = mysqli_query($koneksi, $cat_query);
if ($cat_result) $category_stats = mysqli_fetch_all($cat_result, MYSQLI_ASSOC);

$max_category_views = 1;
foreach ($category_stats as $cat) if ($cat['total_views'] > $max_category_views) $max_category_views = $cat['total_views'];

// Get engagement
$engagement_stats = ['active_users' => 0, 'total_comments' => 0];
$eng_query = "SELECT COUNT(DISTINCT user_id) as active_users, COUNT(*) as total_comments FROM comments WHERE created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
$eng_result = mysqli_query($koneksi, $eng_query);
if ($eng_result) $engagement_stats = mysqli_fetch_assoc($eng_result);

// Get reactions
$total_likes = 0;
$total_dislikes = 0;
$react_query = "SELECT reaction_type, COUNT(*) as count FROM article_reactions WHERE reacted_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' GROUP BY reaction_type";
$react_result = mysqli_query($koneksi, $react_query);
if ($react_result) {
    while ($row = mysqli_fetch_assoc($react_result)) {
        if ($row['reaction_type'] == 'like') $total_likes = $row['count'];
        else if ($row['reaction_type'] == 'dislike') $total_dislikes = $row['count'];
    }
}
$total_reactions = $total_likes + $total_dislikes;

// Get popular pages
$popular_pages = [];
$pages_query = "SELECT page_visited, COUNT(*) as visit_count FROM visitors WHERE visit_time BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' AND page_visited IS NOT NULL AND page_visited != '' GROUP BY page_visited ORDER BY visit_count DESC LIMIT 5";
$pages_result = mysqli_query($koneksi, $pages_query);
if ($pages_result) $popular_pages = mysqli_fetch_all($pages_result, MYSQLI_ASSOC);

// Get author stats
$author_stats = [];
$author_query = "SELECT u.full_name, u.username, COUNT(a.article_id) as article_count, COALESCE(SUM(a.view_count), 0) as total_views FROM users u LEFT JOIN articles a ON u.id = a.author_id AND a.article_status = 'published' WHERE u.role IN ('penulis', 'admin') GROUP BY u.id HAVING article_count > 0 ORDER BY total_views DESC LIMIT 5";
$author_result = mysqli_query($koneksi, $author_query);
if ($author_result) $author_stats = mysqli_fetch_all($author_result, MYSQLI_ASSOC);
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --info-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.stats-card-modern {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stats-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-gradient);
}

.stats-card-modern.success::before { background: var(--success-gradient); }
.stats-card-modern.warning::before { background: var(--warning-gradient); }
.stats-card-modern.info::before { background: var(--info-gradient); }

.stats-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.stat-icon.primary { background: var(--primary-gradient); }
.stat-icon.success { background: var(--success-gradient); }
.stat-icon.warning { background: var(--warning-gradient); }
.stat-icon.info { background: var(--info-gradient); }

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-change {
    font-size: 0.75rem;
    margin-top: 0.5rem;
    color: #10b981;
}

.chart-container {
    position: relative;
    height: 350px;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 0.875rem;
}

.rank-badge.gold { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #000; }
.rank-badge.silver { background: linear-gradient(135deg, #e5e7eb 0%, #9ca3af 100%); color: #000; }
.rank-badge.bronze { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: #fff; }

.category-bar {
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    overflow: hidden;
    margin: 0.5rem 0;
}

.category-bar-fill {
    height: 100%;
    background: var(--primary-gradient);
    transition: width 0.5s ease;
}

.engagement-metric {
    padding: 1rem;
    background: var(--bg-tertiary);
    border-radius: 12px;
    margin-bottom: 1rem;
    border-left: 4px solid;
}

.engagement-metric.likes { border-left-color: #10b981; }
.engagement-metric.dislikes { border-left-color: #ef4444; }
.engagement-metric.comments { border-left-color: #3b82f6; }
.engagement-metric.users { border-left-color: #f59e0b; }

@media (max-width: 768px) {
    .stats-card-modern { padding: 1rem; }
    .stat-icon { width: 48px; height: 48px; font-size: 1.25rem; }
    .stat-value { font-size: 1.5rem; }
    .chart-container { height: 250px; }
}
</style>

<div class="mb-4">
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                <div>
                    <h2 class="mb-1 h4"><i class="bi bi-graph-up-arrow me-2"></i>Analytics Dashboard</h2>
                    <small class="text-muted">Analisis mendalam tentang performa website</small>
                    <div class="mt-2">
                        <span class="badge bg-secondary"><i class="bi bi-calendar-range me-1"></i><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></span>
                        <span class="badge bg-info ms-2"><i class="bi bi-clock me-1"></i><?php echo number_format($date_diff); ?> hari</span>
                    </div>
                </div>
                <form method="GET" action="" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="page" value="analytics">
                    <div>
                        <label class="form-label text-muted mb-1" style="font-size: 0.75rem;">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label class="form-label text-muted mb-1" style="font-size: 0.75rem;">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-filter me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stats-card-modern">
            <div class="stat-icon primary"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?php echo number_format($visitor_stats['total_unique']); ?></div>
            <div class="stat-label">Unique Visitors</div>
            <div class="stat-change"><i class="bi bi-arrow-up me-1"></i><?php echo number_format($visitor_stats['avg_daily_visitors'], 1); ?> avg/day</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card-modern success">
            <div class="stat-icon success"><i class="bi bi-eye-fill"></i></div>
            <div class="stat-value"><?php echo number_format($visitor_stats['total_pageviews']); ?></div>
            <div class="stat-label">Page Views</div>
            <div class="stat-change"><i class="bi bi-arrow-up me-1"></i><?php echo number_format($visitor_stats['total_pageviews'] / max(1, $date_diff), 1); ?> avg/day</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card-modern warning">
            <div class="stat-icon warning"><i class="bi bi-file-text-fill"></i></div>
            <div class="stat-value"><?php echo number_format($article_stats['total_articles']); ?></div>
            <div class="stat-label">Total Articles</div>
            <div class="stat-change"><i class="bi bi-info-circle me-1"></i><?php echo number_format($article_stats['total_views']); ?> views</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card-modern info">
            <div class="stat-icon info"><i class="bi bi-chat-dots-fill"></i></div>
            <div class="stat-value"><?php echo number_format($engagement_stats['total_comments']); ?></div>
            <div class="stat-label">Comments</div>
            <div class="stat-change"><i class="bi bi-people me-1"></i><?php echo number_format($engagement_stats['active_users']); ?> users</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Visitor Trend</h5>
                <span class="badge bg-primary">Last <?php echo $date_diff; ?> days</span>
            </div>
            <div class="card-body">
                <div class="chart-container"><canvas id="visitorChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-heart-fill me-2"></i>User Engagement</h5></div>
            <div class="card-body">
                <div class="engagement-metric likes">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="bi bi-hand-thumbs-up-fill me-2"></i>Likes</span>
                        <strong class="fs-4"><?php echo number_format($total_likes); ?></strong>
                    </div>
                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: <?php echo $total_reactions > 0 ? ($total_likes / $total_reactions * 100) : 0; ?>%"></div></div>
                    <small class="text-muted mt-1 d-block"><?php echo $total_reactions > 0 ? number_format(($total_likes / $total_reactions * 100), 1) : 0; ?>% of reactions</small>
                </div>
                <div class="engagement-metric dislikes">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="bi bi-hand-thumbs-down-fill me-2"></i>Dislikes</span>
                        <strong class="fs-4"><?php echo number_format($total_dislikes); ?></strong>
                    </div>
                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-danger" style="width: <?php echo $total_reactions > 0 ? ($total_dislikes / $total_reactions * 100) : 0; ?>%"></div></div>
                    <small class="text-muted mt-1 d-block"><?php echo $total_reactions > 0 ? number_format(($total_dislikes / $total_reactions * 100), 1) : 0; ?>% of reactions</small>
                </div>
                <div class="engagement-metric comments">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-chat-dots-fill me-2"></i>Avg. Comments/Day</span>
                        <strong class="fs-4"><?php echo number_format($engagement_stats['total_comments'] / max(1, $date_diff), 1); ?></strong>
                    </div>
                </div>
                <div class="engagement-metric users">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people-fill me-2"></i>Active Users</span>
                        <strong class="fs-4"><?php echo number_format($engagement_stats['active_users']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-trophy-fill me-2"></i>Top 10 Articles</h5>
                <span class="badge bg-warning text-dark">Most Viewed</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr><th width="60" class="text-center">Rank</th><th>Article</th><th width="120" class="text-center">Views</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_articles)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4"><i class="bi bi-inbox display-4 d-block mb-2 opacity-50"></i>No articles data</td></tr>
                            <?php else: ?>
                            <?php $rank = 1; foreach ($top_articles as $article): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($rank == 1): ?><span class="rank-badge gold"><?php echo $rank; ?></span>
                                    <?php elseif ($rank == 2): ?><span class="rank-badge silver"><?php echo $rank; ?></span>
                                    <?php elseif ($rank == 3): ?><span class="rank-badge bronze"><?php echo $rank; ?></span>
                                    <?php else: ?><span class="text-muted fw-bold"><?php echo $rank; ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-truncate mb-1" style="max-width: 400px;" title="<?php echo htmlspecialchars($article['title']); ?>">
                                        <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <small class="text-muted"><i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?></small>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?></small>
                                        <small class="text-muted"><i class="bi bi-calendar me-1"></i><?php echo date('d M Y', strtotime($article['publication_date'])); ?></small>
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge bg-primary fs-6"><?php echo number_format($article['view_count']); ?></span></td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-tags-fill me-2"></i>Category Performance</h5></div>
            <div class="card-body">
                <?php if (empty($category_stats)): ?>
                <div class="text-center text-muted py-4"><i class="bi bi-tags display-4 d-block mb-2 opacity-50"></i>No category data</div>
                <?php else: ?>
                <?php foreach ($category_stats as $category): ?>
                <div class="mb-3 pb-3 border-bottom border-secondary">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold"><i class="bi bi-tag-fill me-2"></i><?php echo htmlspecialchars($category['name']); ?></span>
                        <span class="badge bg-info"><?php echo number_format($category['total_views']); ?> views</span>
                    </div>
                    <div class="category-bar"><div class="category-bar-fill" style="width: <?php echo $max_category_views > 0 ? ($category['total_views'] / $max_category_views * 100) : 0; ?>%"></div></div>
                    <small class="text-muted"><i class="bi bi-file-text me-1"></i><?php echo $category['article_count']; ?> artikel • <i class="bi bi-bar-chart me-1"></i><?php echo $category['article_count'] > 0 ? number_format($category['total_views'] / $category['article_count'], 1) : 0; ?> avg/article</small>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-person-badge-fill me-2"></i>Top Authors</h5></div>
            <div class="card-body">
                <?php if (empty($author_stats)): ?>
                <div class="text-center text-muted py-4"><i class="bi bi-people display-4 d-block mb-2 opacity-50"></i>No author data</div>
                <?php else: ?>
                <?php foreach ($author_stats as $author): ?>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary">
                    <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($author['full_name']); ?></div>
                        <small class="text-muted">@<?php echo htmlspecialchars($author['username']); ?></small>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-primary mb-1"><?php echo number_format($author['total_views']); ?> views</div>
                        <div><small class="text-muted"><?php echo $author['article_count']; ?> artikel</small></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Popular Pages</h5></div>
            <div class="card-body">
                <?php if (empty($popular_pages)): ?>
                <div class="text-center text-muted py-4"><i class="bi bi-graph-down display-4 d-block mb-2 opacity-50"></i>No page data</div>
                <?php else: ?>
                <?php foreach ($popular_pages as $page): ?>
                <div class="mb-3 pb-3 border-bottom border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-truncate flex-grow-1" style="max-width: 300px;"><small class="text-muted"><i class="bi bi-link-45deg me-1"></i><?php echo htmlspecialchars($page['page_visited']); ?></small></div>
                        <span class="badge bg-success ms-2"><?php echo number_format($page['visit_count']); ?> visits</span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('visitorChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($d){return date('d M',strtotime($d));},array_column($daily_stats,'stat_date'))); ?>,
        datasets: [{
            label: 'Unique Visitors',
            data: <?php echo json_encode(array_column($daily_stats, 'unique_visitors')); ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 3,
            pointRadius: 4,
            pointBackgroundColor: '#667eea',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }, {
            label: 'Total Visits',
            data: <?php echo json_encode(array_column($daily_stats, 'total_visits')); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 3,
            pointRadius: 4,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }, {
            label: 'Page Views',
            data: <?php echo json_encode(array_column($daily_stats, 'page_views')); ?>,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            tension: 0.4,
            fill: true,
            borderWidth: 3,
            pointRadius: 4,
            pointBackgroundColor: '#f59e0b',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: { color: '#ffffff', padding: 15, font: { size: 12, weight: '600' }, usePointStyle: true }
            },
            tooltip: {
                backgroundColor: 'rgba(26, 26, 26, 0.95)',
                titleColor: '#ffffff',
                bodyColor: '#b3b3b3',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 8,
                callbacks: { label: function(c) { return c.dataset.label + ': ' + new Intl.NumberFormat().format(c.parsed.y); } }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#b3b3b3', font: { size: 11 }, callback: function(v) { return new Intl.NumberFormat().format(v); } },
                grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false }
            },
            x: {
                ticks: { color: '#b3b3b3', font: { size: 11 }, maxRotation: 45, minRotation: 45 },
                grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false }
            }
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.stats-card-modern');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });
    
    setTimeout(() => {
        const categoryBars = document.querySelectorAll('.category-bar-fill');
        categoryBars.forEach((bar, index) => {
            setTimeout(() => {
                bar.style.transition = 'width 1s ease';
            }, index * 100);
        });
    }, 500);
});
</script>