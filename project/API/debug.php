<?php
// debug_ban.php - File untuk debug sistem ban
session_start();
require_once '../config/config.php';

echo "<h2>🔍 DEBUG SISTEM BAN</h2>";

// 1. Cek database structure
echo "<h3>1. Database Structure Check:</h3>";
$tables_to_check = ['users', 'user_warnings'];

foreach ($tables_to_check as $table) {
    echo "<h4>Table: $table</h4>";
    $desc_query = "DESCRIBE $table";
    $result = mysqli_query($koneksi, $desc_query);
    
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    } else {
        echo "❌ Error: " . mysqli_error($koneksi) . "<br>";
    }
}

// 2. Cek user yang dibanned
echo "<h3>2. Banned Users Check:</h3>";
$banned_query = "SELECT id, username, email, is_banned, ban_until, ban_reason, banned_at FROM users WHERE is_banned = 1";
$banned_result = mysqli_query($koneksi, $banned_query);

if ($banned_result && mysqli_num_rows($banned_result) > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Banned</th><th>Ban Until</th><th>Reason</th><th>Banned At</th></tr>";
    while ($row = mysqli_fetch_assoc($banned_result)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['username']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "<td>" . ($row['is_banned'] ? 'YES' : 'NO') . "</td>";
        echo "<td>{$row['ban_until']}</td>";
        echo "<td>{$row['ban_reason']}</td>";
        echo "<td>{$row['banned_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No banned users found!<br>";
}

// 3. Test ban_check.php functionality
echo "<h3>3. Test ban.php:</h3>";
if (file_exists('ban.php')) {
    echo "✅ ban.php exists<br>";
    
    // Simulate checking a banned user
    if ($banned_result && mysqli_num_rows($banned_result) > 0) {
        mysqli_data_seek($banned_result, 0); // Reset pointer
        $banned_user = mysqli_fetch_assoc($banned_result);
        
        echo "<h4>Testing with user ID: {$banned_user['id']} ({$banned_user['username']})</h4>";
        
        // Set session to simulate the banned user
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $banned_user['id'];
        
        // Include and test ban_check.php
        ob_start();
        include 'ban.php';
        $ban_check_output = ob_get_clean();
        
        echo "<strong>ban.php output:</strong><br>";
        echo "<pre>$ban_check_output</pre>";
    }
} else {
    echo "❌ ban.php NOT FOUND!<br>";
}

// 4. Check JavaScript files
echo "<h3>4. JavaScript Files Check:</h3>";
$js_files = ['notif-ban.js', 'notif-ban.js'];
foreach ($js_files as $js_file) {
    if (file_exists($js_file)) {
        echo "✅ $js_file exists<br>";
    } else {
        echo "❌ $js_file NOT FOUND!<br>";
    }
}

// 5. Check PHP files for JavaScript inclusion
echo "<h3>5. PHP Files Check for JS Inclusion:</h3>";
$php_files = ['header.php', 'index.php', 'berita.php', 'artikel.php'];
foreach ($php_files as $php_file) {
    if (file_exists($php_file)) {
        $content = file_get_contents($php_file);
        if (strpos($content, 'js/notif-ban.js') !== false) {
            echo "✅ $php_file includes js/notif-ban.js<br>";
        } elseif (strpos($content, 'js/notif-ban.js') !== false) {
            echo "⚠️ $php_file includes OLD js/notif-ban.js<br>";
        } else {
            echo "❌ $php_file does NOT include js/notif-ban.js<br>";
        }
    } else {
        echo "❌ $php_file NOT FOUND!<br>";
    }
}

// 6. Test current session
echo "<h3>6. Current Session Check:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
}

table {
    border-collapse: collapse;
    margin: 10px 0;
}

th,
td {
    padding: 8px;
    text-align: left;
}

th {
    background-color: #f2f2f2;
}

h2 {
    color: #333;
}

h3 {
    color: #666;
    border-bottom: 1px solid #ddd;
}

h4 {
    color: #888;
}
</style>