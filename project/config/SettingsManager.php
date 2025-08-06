<?php

class SettingsManager {
    private $koneksi;
    private static $instance = null;
    private $settings = [];
    private $loaded = false;

    private function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->createSettingsTable();
    }

    public static function getInstance($koneksi = null) {
        if (self::$instance === null) {
            if ($koneksi === null) {
                throw new Exception("Database connection diperlukan untuk inisialisasi SettingsManager");
            }
            self::$instance = new self($koneksi);
        }
        return self::$instance;
    }

    // Buat tabel settings jika belum ada
    private function createSettingsTable() {
        if (!$this->koneksi) return false;
        
        $sql = "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        
        try {
            mysqli_query($this->koneksi, $sql);
            
            // Insert default settings jika tabel kosong
            $check = mysqli_query($this->koneksi, "SELECT COUNT(*) as total FROM settings");
            $row = mysqli_fetch_assoc($check);
            
            if ($row['total'] == 0) {
                $this->insertDefaultSettings();
            }
            
        } catch (Exception $e) {
            error_log("Error creating settings table: " . $e->getMessage());
        }
    }

    // Insert default settings
    private function insertDefaultSettings() {
        $defaults = $this->getDefaultSettings();
        
        foreach ($defaults as $key => $value) {
            $key_escaped = mysqli_real_escape_string($this->koneksi, $key);
            $value_escaped = mysqli_real_escape_string($this->koneksi, $value);
            
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key_escaped', '$value_escaped')";
            mysqli_query($this->koneksi, $sql);
        }
    }

    // Load semua settings dari database
    public function loadSettings() {
        if ($this->loaded || !$this->koneksi) {
            return $this->settings;
        }

        try {
            $query = "SELECT setting_key, setting_value FROM settings";
            $result = mysqli_query($this->koneksi, $query);
            
            $this->settings = [];
            if ($result && mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $this->settings[$row['setting_key']] = $row['setting_value'];
                }
            }
            
            // Merge dengan default settings untuk key yang hilang
            $defaults = $this->getDefaultSettings();
            foreach ($defaults as $key => $value) {
                if (!isset($this->settings[$key])) {
                    $this->settings[$key] = $value;
                }
            }
            
            $this->loaded = true;
            
        } catch (Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
            $this->settings = $this->getDefaultSettings();
        }
        
        return $this->settings;
    }

    // Get setting value dengan fallback
    public function get($key, $default = null) {
        if (!$this->loaded) {
            $this->loadSettings();
        }
        
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    // Set setting value
    public function set($key, $value) {
        if (!$this->koneksi) return false;
        
        try {
            $key_escaped = mysqli_real_escape_string($this->koneksi, $key);
            $value_escaped = mysqli_real_escape_string($this->koneksi, $value);
            
            // Cek apakah key sudah ada
            $check_query = "SELECT setting_key FROM settings WHERE setting_key = '$key_escaped'";
            $check_result = mysqli_query($this->koneksi, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing
                $update_query = "UPDATE settings SET setting_value = '$value_escaped' WHERE setting_key = '$key_escaped'";
                $result = mysqli_query($this->koneksi, $update_query);
            } else {
                // Insert new
                $insert_query = "INSERT INTO settings (setting_key, setting_value) VALUES ('$key_escaped', '$value_escaped')";
                $result = mysqli_query($this->koneksi, $insert_query);
            }
            
            if ($result) {
                $this->settings[$key] = $value;
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Error setting value: " . $e->getMessage());
        }
        
        return false;
    }

    // PERBAIKAN UTAMA: Method getAllSettings() yang aman
    public function getAllSettings() {
        if (!$this->loaded) {
            $this->loadSettings();
        }
        
        return $this->settings;
    }

    // Get default settings
    private function getDefaultSettings() {
        return [
            'site_name' => 'Sorot Dunia',
            'site_description' => 'Portal berita terpercaya Indonesia',
            'site_keywords' => 'berita, news, artikel, indonesia, terkini',
            'admin_email' => 'admin@sorotdunia.com',
            'articles_per_page' => '10',
            'show_author' => '1',
            'show_date' => '1',
            'show_category' => '1',
            'enable_comments' => '1',
            'maintenance_mode' => '0',
            'maintenance_message' => 'Website sedang dalam maintenance. Silakan kembali lagi nanti.',
            'theme' => 'dark',
            'timezone' => 'Asia/Jakarta'
        ];
    }

    // Check if maintenance mode is active
    public function isMaintenanceMode() {
        return $this->get('maintenance_mode') == '1';
    }

    // Check if user can bypass maintenance
    public function canBypassMaintenance() {
        // Check if user is admin
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                return true;
            }
        }
        
        // Check bypass token (optional)
        if (isset($_GET['bypass']) && $_GET['bypass'] === 'evan123') {
            return true;
        }
        
        return false;
    }
}
?>