<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        switch ($action) {
            case 'get_cars':
                getCars();
                break;
            case 'get_car':
                getCar($_GET['id'] ?? 0);
                break;
            case 'check_availability':
                checkAvailability();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ενέργεια']);
        }
    } else {
        getCars();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_car':
            addCar();
            break;
        case 'update_car':
            updateCar();
            break;
        case 'delete_car':
            deleteCar();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ενέργεια']);
    }
}

function getCars() {
    global $db;
    
    try {
        $query = "SELECT * FROM cars ORDER BY brand, model";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $cars]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function getCar($id) {
    global $db;
    
    try {
        $query = "SELECT * FROM cars WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $car = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $car]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Δεν βρέθηκε το αυτοκίνητο']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function checkAvailability() {
    global $db;
    
    $car_id = $_GET['car_id'] ?? 0;
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    if (empty($car_id) || empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Λείπουν απαραίτητα στοιχεία']);
        return;
    }
    
    try {
        $query = "SELECT COUNT(*) as booking_count FROM bookings 
                  WHERE car_id = :car_id 
                  AND status NOT IN ('cancelled') 
                  AND (
                      (start_date <= :start_date AND end_date >= :start_date) OR
                      (start_date <= :end_date AND end_date >= :end_date) OR
                      (start_date >= :start_date AND end_date <= :end_date)
                  )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':car_id', $car_id);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $available = $result['booking_count'] == 0;
        
        echo json_encode(['success' => true, 'available' => $available]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function addCar() {
    global $db;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Δεν έχεις δικαίωμα πρόσβασης']);
        return;
    }
    
    $model = sanitizeInput($_POST['model'] ?? '');
    $brand = sanitizeInput($_POST['brand'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $price_per_day = floatval($_POST['price_per_day'] ?? 0);
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if (empty($model) || empty($brand) || empty($category) || $price_per_day <= 0) {
        echo json_encode(['success' => false, 'message' => 'Συμπλήρωσε όλα τα απαραίτητα πεδία']);
        return;
    }
    
    try {
        $query = "INSERT INTO cars (model, brand, category, price_per_day, description) 
                  VALUES (:model, :brand, :category, :price_per_day, :description)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':model', $model);
        $stmt->bindParam(':brand', $brand);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':price_per_day', $price_per_day);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Το αυτοκίνητο προστέθηκε επιτυχώς']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την προσθήκη']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function updateCar() {
    global $db;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Δεν έχεις δικαίωμα πρόσβασης']);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    $model = sanitizeInput($_POST['model'] ?? '');
    $brand = sanitizeInput($_POST['brand'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $price_per_day = floatval($_POST['price_per_day'] ?? 0);
    $description = sanitizeInput($_POST['description'] ?? '');
    $available = isset($_POST['available']) ? 1 : 0;
    
    if ($id <= 0 || empty($model) || empty($brand) || empty($category) || $price_per_day <= 0) {
        echo json_encode(['success' => false, 'message' => 'Συμπλήρωσε όλα τα απαραίτητα πεδία']);
        return;
    }
    
    try {
        $query = "UPDATE cars SET model = :model, brand = :brand, category = :category, 
                  price_per_day = :price_per_day, description = :description, available = :available
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':model', $model);
        $stmt->bindParam(':brand', $brand);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':price_per_day', $price_per_day);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':available', $available);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Το αυτοκίνητο ενημερώθηκε επιτυχώς']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά την ενημέρωση']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}

function deleteCar() {
    global $db;
    
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Δεν έχεις δικαίωμα πρόσβασης']);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Μη έγκυρο ID αυτοκινήτου']);
        return;
    }
    
    try {
        // Check if car has active bookings
        $check_query = "SELECT COUNT(*) as booking_count FROM bookings 
                        WHERE car_id = :id AND status NOT IN ('cancelled', 'completed')";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['booking_count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Δεν μπορείς να διαγράψεις αυτοκίνητο με ενεργές κρατήσεις']);
            return;
        }
        
        $query = "DELETE FROM cars WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Το αυτοκίνητο διαγράφηκε επιτυχώς']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Σφάλμα κατά τη διαγραφή']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων']);
    }
}
?>