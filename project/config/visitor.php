<?php
/**
 * Visitor Tracking System
 * File: config/visitor_tracker.php
 */

class VisitorTracker {
    private $koneksi;
    private $user_ip;
    private $user_agent;
    private $current_page;
    
    public function __construct($database_connection) {
        $this->koneksi = $database_connection;
        $this->user_ip = $this->getRealUserIp();
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $this->current_page = $_SERVER['REQUEST_URI'] ?? '/';
    }
    
    /**
     * Get real user IP address
     */
    private function getRealUserIp() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 
                   'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 
                   'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if visitor is a bot
     */
    private function isBot() {
        $bot_signatures = [
            'googlebot', 'bingbot', 'slurp', 'crawler', 'spider', 'robot', 'bot',
            'facebookexternalhit', 'twitterbot', 'linkedinbot', 'whatsapp',
            'telegrambot', 'applebot', 'baiduspider', 'yandexbot'
        ];
        
        $user_agent_lower = strtolower($this->user_agent);
        
        foreach ($bot_signatures as $bot) {
            if (strpos($user_agent_lower, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Record visitor visit
     */
    public function recordVisit() {
        // Skip bots
        if ($this->isBot()) {
            return false;
        }
        
        // Skip admin pages
        if (strpos($this->current_page, '/admin/') !== false) {
            return false;
        }
        
        try {
            // Insert visitor record
            $query = "INSERT INTO visitors (ip_address, user_agent, visit_time, page_visited) 
                     VALUES (?, ?, NOW(), ?)";
            $stmt = mysqli_prepare($this->koneksi, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sss", $this->user_ip, $this->user_agent, $this->current_page);
                $result = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                if ($result) {
                    // Update daily statistics
                    $this->updateDailyStats();
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Error recording visit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update daily statistics
     */
    private function updateDailyStats() {
        $today = date('Y-m-d');
        
        try {
            // Get today's stats
            $unique_visitors = $this->getUniqueVisitorsToday();
            $total_visits = $this->getTotalVisitsToday();
            $page_views = $this->getPageViewsToday();
            
            // Check if today's record exists
            $check_query = "SELECT id FROM visitor_stats WHERE stat_date = ?";
            $stmt = mysqli_prepare($this->koneksi, $check_query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $today);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    // Update existing record
                    $update_query = "UPDATE visitor_stats 
                                   SET unique_visitors = ?, total_visits = ?, page_views = ?, updated_at = NOW() 
                                   WHERE stat_date = ?";
                    $update_stmt = mysqli_prepare($this->koneksi, $update_query);
                    
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, "iiis", $unique_visitors, $total_visits, $page_views, $today);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }
                } else {
                    // Insert new record
                    $insert_query = "INSERT INTO visitor_stats (stat_date, unique_visitors, total_visits, page_views) 
                                   VALUES (?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($this->koneksi, $insert_query);
                    
                    if ($insert_stmt) {
                        mysqli_stmt_bind_param($insert_stmt, "siii", $today, $unique_visitors, $total_visits, $page_views);
                        mysqli_stmt_execute($insert_stmt);
                        mysqli_stmt_close($insert_stmt);
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
            
        } catch (Exception $e) {
            error_log("Error updating daily stats: " . $e->getMessage());
        }
    }
    
    /**
     * Get unique visitors today
     */
    private function getUniqueVisitorsToday() {
        $today = date('Y-m-d');
        
        $query = "SELECT COUNT(DISTINCT ip_address) as count 
                 FROM visitors 
                 WHERE DATE(visit_time) = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $today);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            return $row['count'] ?? 0;
        }
        
        return 0;
    }
    
    /**
     * Get total visits today
     */
    private function getTotalVisitsToday() {
        $today = date('Y-m-d');
        
        $query = "SELECT COUNT(*) as count 
                 FROM visitors 
                 WHERE DATE(visit_time) = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $today);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            return $row['count'] ?? 0;
        }
        
        return 0;
    }
    
    /**
     * Get page views today
     */
    private function getPageViewsToday() {
        // For now, same as total visits
        // You can modify this to track actual page views differently
        return $this->getTotalVisitsToday();
    }
    
    /**
     * Get visitor statistics
     */
    public function getStats($days = 7) {
        $stats = [
            'today' => [
                'unique_visitors' => 0,
                'total_visits' => 0,
                'page_views' => 0
            ],
            'yesterday' => [
                'unique_visitors' => 0,
                'total_visits' => 0,
                'page_views' => 0
            ],
            'last_7_days' => [
                'unique_visitors' => 0,
                'total_visits' => 0,
                'page_views' => 0
            ],
            'this_month' => [
                'unique_visitors' => 0,
                'total_visits' => 0,
                'page_views' => 0
            ]
        ];
        
        try {
            // Today's stats
            $today = date('Y-m-d');
            $stats['today'] = $this->getStatsByDate($today);
            
            // Yesterday's stats
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $stats['yesterday'] = $this->getStatsByDate($yesterday);
            
            // Last 7 days
            $stats['last_7_days'] = $this->getStatsByDateRange(7);
            
            // This month
            $stats['this_month'] = $this->getStatsByMonth();
            
        } catch (Exception $e) {
            error_log("Error getting visitor stats: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Get stats by specific date
     */
    private function getStatsByDate($date) {
        $query = "SELECT unique_visitors, total_visits, page_views 
                 FROM visitor_stats 
                 WHERE stat_date = ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $date);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if ($row) {
                return [
                    'unique_visitors' => (int)$row['unique_visitors'],
                    'total_visits' => (int)$row['total_visits'],
                    'page_views' => (int)$row['page_views']
                ];
            }
        }
        
        return ['unique_visitors' => 0, 'total_visits' => 0, 'page_views' => 0];
    }
    
    /**
     * Get stats by date range
     */
    private function getStatsByDateRange($days) {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $query = "SELECT SUM(unique_visitors) as unique_visitors, 
                        SUM(total_visits) as total_visits, 
                        SUM(page_views) as page_views 
                 FROM visitor_stats 
                 WHERE stat_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if ($row) {
                return [
                    'unique_visitors' => (int)$row['unique_visitors'],
                    'total_visits' => (int)$row['total_visits'],
                    'page_views' => (int)$row['page_views']
                ];
            }
        }
        
        return ['unique_visitors' => 0, 'total_visits' => 0, 'page_views' => 0];
    }
    
    /**
     * Get stats for current month
     */
    private function getStatsByMonth() {
        $start_of_month = date('Y-m-01');
        $end_of_month = date('Y-m-t');
        
        $query = "SELECT SUM(unique_visitors) as unique_visitors, 
                        SUM(total_visits) as total_visits, 
                        SUM(page_views) as page_views 
                 FROM visitor_stats 
                 WHERE stat_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $start_of_month, $end_of_month);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if ($row) {
                return [
                    'unique_visitors' => (int)$row['unique_visitors'],
                    'total_visits' => (int)$row['total_visits'],
                    'page_views' => (int)$row['page_views']
                ];
            }
        }
        
        return ['unique_visitors' => 0, 'total_visits' => 0, 'page_views' => 0];
    }
    
    /**
     * Get popular pages
     */
    public function getPopularPages($limit = 10) {
        $query = "SELECT page_visited, COUNT(*) as visits 
                 FROM visitors 
                 WHERE visit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY page_visited 
                 ORDER BY visits DESC 
                 LIMIT ?";
        $stmt = mysqli_prepare($this->koneksi, $query);
        
        $pages = [];
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $limit);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $pages[] = [
                    'page' => $row['page_visited'],
                    'visits' => (int)$row['visits']
                ];
            }
            
            mysqli_stmt_close($stmt);
        }
        
        return $pages;
    }
    
    /**
     * Clean old visitor data (older than 30 days)
     */
    public function cleanOldData() {
        try {
            $query = "DELETE FROM visitors WHERE visit_time < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            mysqli_query($this->koneksi, $query);
            
            return true;
        } catch (Exception $e) {
            error_log("Error cleaning old visitor data: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Initialize visitor tracking
 * Call this function on every page you want to track
 */
function initVisitorTracking($koneksi) {
    try {
        $tracker = new VisitorTracker($koneksi);
        $tracker->recordVisit();
        
        // Clean old data occasionally (1% chance)
        if (rand(1, 100) === 1) {
            $tracker->cleanOldData();
        }
        
        return $tracker;
    } catch (Exception $e) {
        error_log("Error initializing visitor tracking: " . $e->getMessage());
        return null;
    }
}

/**
 * Get visitor statistics
 */
function getVisitorStats($koneksi) {
    try {
        $tracker = new VisitorTracker($koneksi);
        return $tracker->getStats();
    } catch (Exception $e) {
        error_log("Error getting visitor stats: " . $e->getMessage());
        return [
            'today' => ['unique_visitors' => 0, 'total_visits' => 0, 'page_views' => 0],
            'yesterday' => ['unique_visitors' => 0, 'total_visits' => 0, 'page_views' => 0],
            'last_7_days' => ['unique_visitors' => 0, 'total_visits' => 0, 'page_views' => 0],
            'this_month' => ['unique_visitors' => 0, 'total_visits' => 0, 'page_views' => 0]
        ];
    }
}

/**
 * Get popular pages
 */
function getPopularPages($koneksi, $limit = 10) {
    try {
        $tracker = new VisitorTracker($koneksi);
        return $tracker->getPopularPages($limit);
    } catch (Exception $e) {
        error_log("Error getting popular pages: " . $e->getMessage());
        return [];
    }
}
?>