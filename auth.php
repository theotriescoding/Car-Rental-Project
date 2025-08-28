<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'login':
            handleLogin();
            break;
        case 'register':
            handleRegister();
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ενέργεια']);
    }
}

function handleLogin() {
    global $db;
    
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Συμπλήρωσε όλα τα πεδία']);
        return;
    }
    
    try {
        $query = "SELECT id, name, email, password, role FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (verifyPassword($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Create session token
                createSessionToken($user['id']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Επιτυχής σύνδεση',
                    'role' => $user['role']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Λάθος κωδικός']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Δεν βρέθηκε χρήστης με αυτό το email']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function handleRegister() {
    global $db;
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Όλα τα πεδία είναι υποχρεωτικά']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Μη έγκυρο email']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες']);
        return;
    }
    
    try {
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Το email υπάρχει ήδη']);
            return;
        }
        
        // Insert new user
        $hashed_password = hashPassword($password);
        $query = "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, 'customer')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Επιτυχής εγγραφή! Μπορείς να συνδεθείς τώρα.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την εγγραφή']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function handleLogout() {
    global $db;
    
    if (isset($_SESSION['user_id'])) {
        // Remove session token from database
        $query = "DELETE FROM user_sessions WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
    }
    
    // Destroy session
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Επιτυχής αποσύνδεση']);
}

function createSessionToken($user_id) {
    global $db;
    
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
    
    // Clean old sessions for this user
    $clean_query = "DELETE FROM user_sessions WHERE user_id = :user_id OR expires_at < NOW()";
    $clean_stmt = $db->prepare($clean_query);
    $clean_stmt->bindParam(':user_id', $user_id);
    $clean_stmt->execute();
    
    // Insert new session
    $query = "INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (:user_id, :token, :expires_at)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':token', $token);
    $stmt->bindParam(':expires_at', $expires_at);
    $stmt->execute();
    
    $_SESSION['session_token'] = $token;
}
?>