<?php
// test_token_debug.php - Debug Token System
// Letakkan di root project

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';

echo "<h1>🔧 Password Reset Token Debugger</h1>";
echo "<pre>";

// 1. Check Database Connection
echo "\n=== DATABASE CONNECTION ===\n";
if ($koneksi) {
    echo "✅ Database connected\n";
    echo "   Host: " . mysqli_get_host_info($koneksi) . "\n";
} else {
    die("❌ Database connection failed!\n");
}

// 2. Check Table Structure
echo "\n=== TABLE STRUCTURE ===\n";
$table_check = mysqli_query($koneksi, "DESCRIBE password_reset_tokens");
if ($table_check) {
    echo "✅ Table password_reset_tokens exists\n";
    echo "\nColumns:\n";
    while ($row = mysqli_fetch_assoc($table_check)) {
        echo "   - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "❌ Table password_reset_tokens NOT found!\n";
    echo "Creating table...\n";
    
    $create_table = "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `token_hash` VARCHAR(64) NOT NULL UNIQUE,
        `expires_at` DATETIME NOT NULL,
        `used_at` DATETIME NULL,
        `ip_address` VARCHAR(45),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token_hash (token_hash),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($koneksi, $create_table)) {
        echo "✅ Table created successfully!\n";
    } else {
        echo "❌ Failed to create table: " . mysqli_error($koneksi) . "\n";
    }
}

// 3. Check Existing Tokens
echo "\n=== EXISTING TOKENS ===\n";
$tokens_query = "SELECT 
    prt.id, 
    prt.user_id, 
    u.username, 
    u.email,
    LEFT(prt.token_hash, 20) as token_preview,
    prt.expires_at,
    prt.used_at,
    prt.created_at,
    CASE 
        WHEN prt.expires_at < NOW() THEN 'EXPIRED'
        WHEN prt.used_at IS NOT NULL THEN 'USED'
        ELSE 'VALID'
    END as status
FROM password_reset_tokens prt
JOIN users u ON prt.user_id = u.id
ORDER BY prt.created_at DESC
LIMIT 10";

$result = mysqli_query($koneksi, $tokens_query);

if ($result && mysqli_num_rows($result) > 0) {
    echo "Found " . mysqli_num_rows($result) . " tokens:\n\n";
    printf("%-5s %-10s %-20s %-25s %-22s %-10s\n", "ID", "User ID", "Username", "Email", "Token Preview", "Status");
    echo str_repeat("-", 100) . "\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        printf("%-5s %-10s %-20s %-25s %-22s %-10s\n", 
            $row['id'], 
            $row['user_id'], 
            substr($row['username'], 0, 20),
            substr($row['email'], 0, 25),
            $row['token_preview'] . "...",
            $row['status']
        );
    }
} else {
    echo "⚠️ No tokens found in database\n";
}

// 4. Test Token Generation
echo "\n=== TEST TOKEN GENERATION ===\n";
echo "Generating test token...\n";

$test_raw_token = bin2hex(random_bytes(32));
$test_token_hash = hash('sha256', $test_raw_token);

echo "Raw Token: $test_raw_token\n";
echo "Token Hash: $test_token_hash\n";
echo "Raw Token Length: " . strlen($test_raw_token) . " chars\n";
echo "Hash Length: " . strlen($test_token_hash) . " chars\n";

// 5. Test User Lookup
echo "\n=== TEST USER LOOKUP ===\n";
$test_email = 'eatharasya@gmail.com'; // Ganti dengan email yang ada
echo "Looking up user: $test_email\n";

$user_query = "SELECT id, username, email, full_name FROM users WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($koneksi, $user_query);
mysqli_stmt_bind_param($stmt, "s", $test_email);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($user_result)) {
    echo "✅ User found:\n";
    echo "   ID: " . $user['id'] . "\n";
    echo "   Username: " . $user['username'] . "\n";
    echo "   Email: " . $user['email'] . "\n";
    echo "   Full Name: " . $user['full_name'] . "\n";
} else {
    echo "❌ User not found\n";
}
mysqli_stmt_close($stmt);

// 6. Clean Old Tokens
echo "\n=== CLEANUP OLD TOKENS ===\n";
$cleanup_query = "DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL";
if (mysqli_query($koneksi, $cleanup_query)) {
    $deleted = mysqli_affected_rows($koneksi);
    echo "✅ Cleaned up $deleted old/used tokens\n";
} else {
    echo "❌ Cleanup failed: " . mysqli_error($koneksi) . "\n";
}

// 7. Test Insert Token
echo "\n=== TEST INSERT TOKEN ===\n";
if (isset($user) && $user) {
    $test_user_id = $user['id'];
    $test_expires = date('Y-m-d H:i:s', time() + 3600);
    $test_ip = '127.0.0.1';
    
    $insert_query = "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address, created_at) 
                     VALUES (?, ?, ?, ?, NOW())";
    $insert_stmt = mysqli_prepare($koneksi, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "isss", $test_user_id, $test_token_hash, $test_expires, $test_ip);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $insert_id = mysqli_stmt_insert_id($insert_stmt);
        echo "✅ Test token inserted successfully!\n";
        echo "   Insert ID: $insert_id\n";
        
        // Verify
        $verify_query = "SELECT * FROM password_reset_tokens WHERE id = ?";
        $verify_stmt = mysqli_prepare($koneksi, $verify_query);
        mysqli_stmt_bind_param($verify_stmt, "i", $insert_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        
        if ($verify_row = mysqli_fetch_assoc($verify_result)) {
            echo "✅ VERIFIED: Token found in database\n";
            echo "   Token Hash (first 20): " . substr($verify_row['token_hash'], 0, 20) . "...\n";
            echo "   Expires: " . $verify_row['expires_at'] . "\n";
        }
        mysqli_stmt_close($verify_stmt);
        
        // Delete test token
        mysqli_query($koneksi, "DELETE FROM password_reset_tokens WHERE id = $insert_id");
        echo "🗑️ Test token deleted\n";
    } else {
        echo "❌ Failed to insert test token: " . mysqli_stmt_error($insert_stmt) . "\n";
    }
    mysqli_stmt_close($insert_stmt);
}

// 8. Server Info
echo "\n=== SERVER INFO ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "MySQL Version: " . mysqli_get_server_info($koneksi) . "\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "Server Time: " . date('Y-m-d H:i:s') . "\n";

// 9. Check Email Config
echo "\n=== EMAIL CONFIGURATION ===\n";
if (file_exists('config/email.php')) {
    require_once 'config/email.php';
    echo "✅ Email config loaded\n";
    
    if (function_exists('sendResetEmailSMTP')) {
        echo "✅ sendResetEmailSMTP function exists\n";
    } else {
        echo "❌ sendResetEmailSMTP function NOT found\n";
    }
    
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "✅ PHPMailer class exists\n";
    } else {
        echo "❌ PHPMailer class NOT found\n";
    }
} else {
    echo "❌ Email config file not found\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
echo "</pre>";

// HTML Form untuk test manual
?>
<!DOCTYPE html>
<html>
<head>
    <title>Token Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-form { 
            background: #f0f8ff; 
            padding: 20px; 
            border-radius: 8px; 
            max-width: 600px;
            margin-top: 20px;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover { background: #45a049; }
        .result {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="test-form">
        <h3>🧪 Test Forgot Password Flow</h3>
        <form method="POST">
            <label>Enter email to test:</label>
            <input type="email" name="test_forgot_email" required placeholder="your@email.com">
            <button type="submit" name="test_forgot">Test Forgot Password</button>
        </form>
        
        <?php
        if (isset($_POST['test_forgot']) && isset($_POST['test_forgot_email'])) {
            $email = filter_var($_POST['test_forgot_email'], FILTER_VALIDATE_EMAIL);
            
            if ($email) {
                echo "<div class='result'>";
                echo "<h4>📧 Testing Forgot Password for: $email</h4>";
                
                // Simulate forgot password
                $user_check = mysqli_query($koneksi, "SELECT id, username FROM users WHERE email = '$email' LIMIT 1");
                
                if ($user_row = mysqli_fetch_assoc($user_check)) {
                    echo "<p>✅ User found: " . $user_row['username'] . "</p>";
                    
                    // Generate token
                    $raw_token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $raw_token);
                    $expires = date('Y-m-d H:i:s', time() + 3600);
                    
                    // Insert token
                    $insert = mysqli_query($koneksi, "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address) VALUES ({$user_row['id']}, '$token_hash', '$expires', '127.0.0.1')");
                    
                    if ($insert) {
                        $token_id = mysqli_insert_id($koneksi);
                        echo "<p>✅ Token generated and saved (ID: $token_id)</p>";
                        
                        // Generate link
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$raw_token";
                        
                        echo "<p><strong>Reset Link:</strong></p>";
                        echo "<textarea style='width:100%; height:60px; font-size:11px;'>$reset_link</textarea>";
                        echo "<p><a href='$reset_link' target='_blank'>🔗 Click to test reset page</a></p>";
                    } else {
                        echo "<p>❌ Failed to save token: " . mysqli_error($koneksi) . "</p>";
                    }
                } else {
                    echo "<p>❌ User not found with email: $email</p>";
                }
                
                echo "</div>";
            }
        }
        ?>
    </div>
</body>
</html>