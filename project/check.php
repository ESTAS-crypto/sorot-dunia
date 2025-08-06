<?php

session_start();
include 'config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Check database connection
if (!$koneksi) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Initialize response
$response = ['available' => true, 'message' => ''];

try {
    // Check username availability
    if (isset($_POST['username'])) {
        $username = sanitize_input($_POST['username']);
        
        // Validate username format
        if (strlen($username) < 3 || strlen($username) > 20) {
            $response['available'] = false;
            $response['message'] = 'Username harus 3-20 karakter';
        } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $username)) {
            $response['available'] = false;
            $response['message'] = 'Username harus dimulai dengan huruf dan hanya boleh mengandung huruf, angka, dan underscore';
        } else {
            // Check if username exists in database
            $query = "SELECT id FROM users WHERE username = ? LIMIT 1";
            $stmt = mysqli_prepare($koneksi, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $response['available'] = false;
                    $response['message'] = 'Username sudah digunakan';
                } else {
                    $response['available'] = true;
                    $response['message'] = 'Username tersedia';
                }
                
                mysqli_stmt_close($stmt);
            } else {
                throw new Exception('Database query failed');
            }
        }
    }
    
    // Check email availability
    elseif (isset($_POST['email'])) {
        $email = sanitize_input($_POST['email']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['available'] = false;
            $response['message'] = 'Format email tidak valid';
        } elseif (strlen($email) > 100) {
            $response['available'] = false;
            $response['message'] = 'Email terlalu panjang (maksimal 100 karakter)';
        } else {
            // Check if email exists in database
            $query = "SELECT id FROM users WHERE email = ? LIMIT 1";
            $stmt = mysqli_prepare($koneksi, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $response['available'] = false;
                    $response['message'] = 'Email sudah digunakan';
                } else {
                    $response['available'] = true;
                    $response['message'] = 'Email tersedia';
                }
                
                mysqli_stmt_close($stmt);
            } else {
                throw new Exception('Database query failed');
            }
        }
    }
    
    else {
        $response['available'] = false;
        $response['message'] = 'Invalid request';
    }

} catch (Exception $e) {
    error_log("Availability check error: " . $e->getMessage());
    http_response_code(500);
    $response = [
        'available' => false,
        'message' => 'Server error occurred',
        'error' => 'Internal server error'
    ];
}

// Return JSON response
echo json_encode($response);
exit();
?>