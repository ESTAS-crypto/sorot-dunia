<?php
// visitor_tracker.php
// File untuk mengelola tracking visitor

// Include this file di dashboard.php dan index.php
// require_once 'visitor_tracker.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function untuk mencatat kunjungan visitor
function trackVisitor($koneksi) {
    // Ambil informasi visitor
    $user_ip = getUserIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $current_date = date('Y-m-d');
    $current_time = date('Y-m-d H:i:s');
    
    // Cek apakah ini unique visitor untuk hari ini
    $visitor_key = 'visitor_' . $current_date . '_' . md5($user_ip . $user_agent);
    
    // Cek apakah visitor sudah tercatat hari ini
    $is_unique = !isset($_SESSION[$visitor_key]);
    
    // Jika unique visitor, tandai di session
    if ($is_unique) {
        $_SESSION[$visitor_key] = true;
    }
    
    // Insert atau update statistik harian
    $query = "INSERT INTO visitor_stats (stat_date, unique_visitors, total_visits, page_views) 
              VALUES (?, ?, 1, 1) 
              ON DUPLICATE KEY UPDATE 
              unique_visitors = unique_visitors + ?,
              total_visits = total_visits + 1,
              page_views = page_views + 1,
              updated_at = CURRENT_TIMESTAMP";
    
    $stmt = mysqli_prepare($koneksi, $query);
    if ($stmt) {
        $unique_increment = $is_unique ? 1 : 0;
        mysqli_stmt_bind_param($stmt, "sii", $current_date, $unique_increment, $unique_increment);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Log untuk debugging (optional)
    error_log("Visitor tracked: IP={$user_ip}, Unique={$is_unique}, Date={$current_date}");
}

// Function untuk mendapatkan IP address visitor
function getUserIP() {
    $ip_fields = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                  'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 
                  'REMOTE_ADDR'];
    
    foreach ($ip_fields as $field) {
        if (!empty($_SERVER[$field])) {
            $ip = $_SERVER[$field];
            // Jika ada multiple IP, ambil yang pertama
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validasi IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Function untuk mendapatkan statistik visitor hari ini
function getTodayVisitorStats($koneksi) {
    $today = date('Y-m-d');
    $query = "SELECT unique_visitors, total_visits, page_views 
              FROM visitor_stats 
              WHERE stat_date = ? 
              LIMIT 1";
    
    $stmt = mysqli_prepare($koneksi, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    return [
        'unique_visitors' => 0,
        'total_visits' => 0,
        'page_views' => 0
    ];
}

// Function untuk mendapatkan statistik visitor minggu ini
function getWeeklyVisitorStats($koneksi) {
    $start_week = date('Y-m-d', strtotime('monday this week'));
    $end_week = date('Y-m-d', strtotime('sunday this week'));
    
    $query = "SELECT 
                SUM(unique_visitors) as weekly_unique_visitors,
                SUM(total_visits) as weekly_total_visits,
                SUM(page_views) as weekly_page_views
              FROM visitor_stats 
              WHERE stat_date BETWEEN ? AND ?";
    
    $stmt = mysqli_prepare($koneksi, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $start_week, $end_week);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return [
                'weekly_unique_visitors' => $row['weekly_unique_visitors'] ?? 0,
                'weekly_total_visits' => $row['weekly_total_visits'] ?? 0,
                'weekly_page_views' => $row['weekly_page_views'] ?? 0
            ];
        }
        mysqli_stmt_close($stmt);
    }
    
    return [
        'weekly_unique_visitors' => 0,
        'weekly_total_visits' => 0,
        'weekly_page_views' => 0
    ];
}

// Function untuk mendapatkan statistik visitor bulan ini
function getMonthlyVisitorStats($koneksi) {
    $start_month = date('Y-m-01');
    $end_month = date('Y-m-t');
    
    $query = "SELECT 
                SUM(unique_visitors) as monthly_unique_visitors,
                SUM(total_visits) as monthly_total_visits,
                SUM(page_views) as monthly_page_views
              FROM visitor_stats 
              WHERE stat_date BETWEEN ? AND ?";
    
    $stmt = mysqli_prepare($koneksi, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $start_month, $end_month);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return [
                'monthly_unique_visitors' => $row['monthly_unique_visitors'] ?? 0,
                'monthly_total_visits' => $row['monthly_total_visits'] ?? 0,
                'monthly_page_views' => $row['monthly_page_views'] ?? 0
            ];
        }
        mysqli_stmt_close($stmt);
    }
    
    return [
        'monthly_unique_visitors' => 0,
        'monthly_total_visits' => 0,
        'monthly_page_views' => 0
    ];
}

// Function untuk mendapatkan data visitor untuk chart (7 hari terakhir)
function getVisitorChartData($koneksi) {
    $query = "SELECT stat_date, unique_visitors, total_visits, page_views
              FROM visitor_stats 
              WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
              ORDER BY stat_date ASC";
    
    $result = mysqli_query($koneksi, $query);
    $chart_data = [];
    
    // Buat array untuk 7 hari terakhir
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $chart_data[$date] = [
            'date' => $date,
            'unique_visitors' => 0,
            'total_visits' => 0,
            'page_views' => 0
        ];
    }
    
    // Isi data dari database
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $chart_data[$row['stat_date']] = [
                'date' => $row['stat_date'],
                'unique_visitors' => (int)$row['unique_visitors'],
                'total_visits' => (int)$row['total_visits'],
                'page_views' => (int)$row['page_views']
            ];
        }
    }
    
    return array_values($chart_data);
}

// Function untuk membersihkan data visitor lama (optional, untuk optimasi)
function cleanupOldVisitorData($koneksi, $days_to_keep = 365) {
    $cutoff_date = date('Y-m-d', strtotime("-{$days_to_keep} days"));
    
    $query = "DELETE FROM visitor_stats WHERE stat_date < ?";
    $stmt = mysqli_prepare($koneksi, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $cutoff_date);
        mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        error_log("Cleaned up {$affected_rows} old visitor records before {$cutoff_date}");
        return $affected_rows;
    }
    
    return 0;
}

// Function untuk mendapatkan top pages (jika ingin tracking per halaman)
function getTopPages($koneksi, $limit = 10) {
    // Ini memerlukan tabel tambahan untuk tracking per halaman
    // Untuk sekarang, return array kosong atau bisa dikembangkan lebih lanjut
    return [];
}

// Function untuk export statistik visitor ke CSV
function exportVisitorStats($koneksi, $start_date, $end_date) {
    $query = "SELECT stat_date, unique_visitors, total_visits, page_views, created_at, updated_at
              FROM visitor_stats 
              WHERE stat_date BETWEEN ? AND ? 
              ORDER BY stat_date DESC";
    
    $stmt = mysqli_prepare($koneksi, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $csv_data = [];
        $csv_data[] = ['Date', 'Unique Visitors', 'Total Visits', 'Page Views', 'Created At', 'Updated At'];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $csv_data[] = [
                $row['stat_date'],
                $row['unique_visitors'],
                $row['total_visits'],
                $row['page_views'],
                $row['created_at'],
                $row['updated_at']
            ];
        }
        
        mysqli_stmt_close($stmt);
        return $csv_data;
    }
    
    return [];
}

// Auto-cleanup old data (jalankan sekali seminggu)
function autoCleanupVisitorData($koneksi) {
    $last_cleanup_key = 'last_visitor_cleanup';
    $last_cleanup = $_SESSION[$last_cleanup_key] ?? 0;
    $week_ago = strtotime('-1 week');
    
    if ($last_cleanup < $week_ago) {
        cleanupOldVisitorData($koneksi, 365); // Keep 1 year of data
        $_SESSION[$last_cleanup_key] = time();
    }
}
?>