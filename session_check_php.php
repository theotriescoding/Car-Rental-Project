<?php
require_once 'config.php';

// Function to check if user is logged in and session is valid
function checkSession() {
    global $db;
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    // Check if session has expired
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        destroySession();
        return false;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Verify session token in database
    try {
        $query = "SELECT user_id FROM user_sessions 
                  WHERE user_id = :user_id AND session_token = :token AND expires_at > NOW()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':token', $_SESSION['session_token']);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            destroySession();
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to destroy session
function destroySession() {
    global $db;
    
    if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
        try {
            $query = "DELETE FROM user_sessions WHERE user_id = :user_id AND session_token = :token";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':token', $_SESSION['session_token']);
            $stmt->execute();
        } catch (PDOException $e) {
            // Log error but continue with session destruction
        }
    }
    
    session_unset();
    session_destroy();
}

// Function to require login
function requireLogin() {
    if (!checkSession()) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Πρέπει να συνδεθείς', 'redirect' => 'login.html']);
        exit();
    }
}

// Function to require admin access
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Δεν έχεις δικαίωμα πρόσβασης']);
        exit();
    }
}

// Clean expired sessions (call periodically)
function cleanExpiredSessions() {
    global $db;
    
    try {
        $query = "DELETE FROM user_sessions WHERE expires_at < NOW()";
        $stmt = $db->prepare($query);
        $stmt->execute();
    } catch (PDOException $e) {
        // Log error
    }
}

// Auto-clean expired sessions (1% chance per request)
if (rand(1, 100) === 1) {
    cleanExpiredSessions();
}

// Get current user info
function getCurrentUser() {
    if (!checkSession()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['role'] ?? 'customer'
    ];
}

// Function to extend session
function extendSession() {
    global $db;
    
    if (!checkSession()) {
        return false;
    }
    
    try {
        $new_expires = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        $query = "UPDATE user_sessions SET expires_at = :expires_at 
                  WHERE user_id = :user_id AND session_token = :token";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':expires_at', $new_expires);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':token', $_SESSION['session_token']);
        $stmt->execute();
        
        $_SESSION['last_activity'] = time();
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
?>