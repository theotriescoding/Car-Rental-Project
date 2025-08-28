<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        switch ($action) {
            case 'get_bookings':
                getBookings();
                break;
            case 'get_user_bookings':
                getUserBookings();
                break;
            case 'get_booking':
                getBooking($_GET['id'] ?? 0);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ενέργεια']);
        }
    } else {
        getUserBookings();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_booking':
            createBooking();
            break;
        case 'cancel_booking':
            cancelBooking();
            break;
        case 'update_booking_status':
            updateBookingStatus();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ενέργεια']);
    }
}

function createBooking() {
    global $db;
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Πρέπει να συνδεθείς για να κάνεις κράτηση']);
        return;
    }
    
    $car_id = intval($_POST['car_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    if ($car_id <= 0 || empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Συμπλήρωσε όλα τα απαραίτητα πεδία']);
        return;
    }
    
    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $today = new DateTime();
    
    if ($start < $today) {
        echo json_encode(['success' => false, 'message' => 'Η ημερομηνία έναρξης δεν μπορεί να είναι στο παρελθόν']);
        return;
    }
    
    if ($start >= $end) {
        echo json_encode(['success' => false, 'message' => 'Η ημερομηνία λήξης πρέπει να είναι μετά την ημερομηνία έναρξης']);
        return;
    }
    
    try {
        // Check car availability
        $avail_query = "SELECT COUNT(*) as booking_count FROM bookings 
                        WHERE car_id = :car_id 
                        AND status NOT IN ('cancelled') 
                        AND (
                            (start_date <= :start_date AND end_date >= :start_date) OR
                            (start_date <= :end_date AND end_date >= :end_date) OR
                            (start_date >= :start_date AND end_date <= :end_date)
                        )";
        
        $avail_stmt = $db->prepare($avail_query);
        $avail_stmt->bindParam(':car_id', $car_id);
        $avail_stmt->bindParam(':start_date', $start_date);
        $avail_stmt->bindParam(':end_date', $end_date);
        $avail_stmt->execute();
        
        $availability = $avail_stmt->fetch(PDO::FETCH_ASSOC);
        if ($availability['booking_count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Το αυτοκίνητο δεν είναι διαθέσιμο για τις επιλεγμένες ημερομηνίες']);
            return;
        }
        
        // Get car price
        $price_query = "SELECT price_per_day FROM cars WHERE id = :car_id AND available = 1";
        $price_stmt = $db->prepare($price_query);
        $price_stmt->bindParam(':car_id', $car_id);
        $price_stmt->execute();
        
        if ($price_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Το αυτοκίνητο δεν είναι διαθέσιμο']);
            return;
        }
        
        $car_data = $price_stmt->fetch(PDO::FETCH_ASSOC);
        $price_per_day = $car_data['price_per_day'];
        
        // Calculate total price
        $days = $start->diff($end)->days;
        $total_price = $days * $price_per_day;
        
        // Create booking
        $booking_query = "INSERT INTO bookings (user_id, car_id, start_date, end_date, total_price, status) 
                          VALUES (:user_id, :car_id, :start_date, :end_date, :total_price, 'pending')";
        $booking_stmt = $db->prepare($booking_query);
        $booking_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $booking_stmt->bindParam(':car_id', $car_id);
        $booking_stmt->bindParam(':start_date', $start_date);
        $booking_stmt->bindParam(':end_date', $end_date);
        $booking_stmt->bindParam(':total_price', $total_price);
        
        if ($booking_stmt->execute()) {
            $booking_id = $db->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => 'Η κράτηση δημιουργήθηκε επιτυχώς',
                'booking_id' => $booking_id,
                'total_price' => $total_price
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά τη δημιουργία κράτησης']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function cancelBooking() {
    global $db;
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Πρέπει να συνδεθείς']);
        return;
    }
    
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    if ($booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Μη έγκυρο ID κράτησης']);
        return;
    }
    
    try {
        // Check if booking belongs to user (unless admin)
        $where_clause = "WHERE id = :booking_id";
        if (!isAdmin()) {
            $where_clause .= " AND user_id = :user_id";
        }
        
        $check_query = "SELECT status FROM bookings $where_clause";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':booking_id', $booking_id);
        if (!isAdmin()) {
            $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Δεν βρέθηκε η κράτηση']);
            return;
        }
        
        $booking = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if ($booking['status'] === 'cancelled') {
            echo json_encode(['success' => false, 'message' => 'Η κράτηση είναι ήδη ακυρωμένη']);
            return;
        }
        
        // Cancel booking
        $cancel_query = "UPDATE bookings SET status = 'cancelled' WHERE id = :booking_id";
        $cancel_stmt = $db->prepare($cancel_query);
        $cancel_stmt->bindParam(':booking_id', $booking_id);
        
        if ($cancel_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Η κράτηση ακυρώθηκε επιτυχώς']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ακύρωση']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function getUserBookings() {
    global $db;
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Πρέπει να συνδεθείς']);
        return;
    }
    
    try {
        $query = "SELECT b.*, c.brand, c.model, c.category 
                  FROM bookings b 
                  JOIN cars c ON b.car_id = c.id 
                  WHERE b.user_id = :user_id 
                  ORDER BY b.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $bookings]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function getBookings() {
    global $db;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Δεν έχεις δικαίωμα πρόσβασης']);
        return;
    }
    
    try {
        $query = "SELECT b.*, c.brand, c.model, c.category, u.name as customer_name, u.email as customer_email
                  FROM bookings b 
                  JOIN cars c ON b.car_id = c.id 
                  JOIN users u ON b.user_id = u.id 
                  ORDER BY b.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $bookings]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function getBooking($id) {
    global $db;
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Πρέπει να συνδεθείς']);
        return;
    }
    
    try {
        $where_clause = "WHERE b.id = :id";
        if (!isAdmin()) {
            $where_clause .= " AND b.user_id = :user_id";
        }
        
        $query = "SELECT b.*, c.brand, c.model, c.category, c.price_per_day
                  FROM bookings b 
                  JOIN cars c ON b.car_id = c.id 
                  $where_clause";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        if (!isAdmin()) {
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
        }
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $booking]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Δεν βρέθηκε η κράτηση']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function updateBookingStatus() {
    global $db;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Δεν έχεις δικαίωμα πρόσβασης']);
        return;
    }
    
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $payment_status = $_POST['payment_status'] ?? '';
    
    $valid_statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
    $valid_payment_statuses = ['pending', 'paid', 'failed', 'refunded'];
    
    if ($booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Μη έγκυρο ID κράτησης']);
        return;
    }
    
    if (!empty($status) && !in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Μη έγκυρη κατάσταση κράτησης']);
        return;
    }
    
    if (!empty($payment_status) && !in_array($payment_status, $valid_payment_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Μη έγκυρη κατάσταση πληρωμής']);
        return;
    }
    
    try {
        $updates = [];
        $params = [':id' => $booking_id];
        
        if (!empty($status)) {
            $updates[] = "status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($payment_status)) {
            $updates[] = "payment_status = :payment_status";
            $params[':payment_status'] = $payment_status;
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'Δεν υπάρχουν αλλαγές για ενημέρωση']);
            return;
        }
        
        $query = "UPDATE bookings SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Η κράτηση ενημερώθηκε επιτυχώς']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ενημέρωση']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}
?>