<?php
/**
 * AJAX Visitor Tracking Endpoint
 * File: track_visit.php
 */

// Allow CORS for AJAX requests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Include configuration
require_once 'config/config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Extract data
    $page = $input['page'] ?? '/';
    $referrer = $input['referrer'] ?? '';
    $timestamp = $input['timestamp'] ?? time();
    
    // Initialize visitor tracking
    if ($koneksi) {
        $tracker = new VisitorTracker($koneksi);
        $result = $tracker->recordVisit();
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Visit recorded successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to record visit'
            ]);
        }
    } else {
        throw new Exception('Database connection not available');
    }
    
} catch (Exception $e) {
    error_log("Error in track_visit.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
?>