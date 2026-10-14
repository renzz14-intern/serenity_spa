<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

ob_start();
header('Content-Type: application/json');

try {
    require_once 'config.php';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    // Create/update bookings table - CHANGED status default to 'pending'
    $createTable = "
        CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_reference VARCHAR(50) NOT NULL UNIQUE,
            user_id INT NULL,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            service_type VARCHAR(100) NOT NULL,
            service_name VARCHAR(255) NOT NULL,
            service_price DECIMAL(10,2) NOT NULL,
            service_duration INT NOT NULL,
            booking_date DATE NOT NULL,
            booking_time TIME NOT NULL,
            room_type VARCHAR(50) NOT NULL,
            room_price DECIMAL(10,2) DEFAULT 0.00,
            total_price DECIMAL(10,2) DEFAULT 0.00,
            therapist_id VARCHAR(50) NULL,
            therapist_id_2 VARCHAR(50) NULL,
            payment_method VARCHAR(50) NOT NULL,
            health_info TEXT NULL,
            email_sent BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
            
            INDEX idx_booking_date (booking_date),
            INDEX idx_booking_time (booking_time),
            INDEX idx_therapist_id (therapist_id),
            INDEX idx_therapist_id_2 (therapist_id_2),
            INDEX idx_status (status)
        )
    ";
    
    if (!$conn->query($createTable)) {
        throw new Exception('Failed to create table: ' . $conn->error);
    }

    // Check if therapist_id_2 column exists
    $checkColumn = "SHOW COLUMNS FROM bookings LIKE 'therapist_id_2'";
    $result = $conn->query($checkColumn);
    if ($result && $result->num_rows == 0) {
        $addColumn = "ALTER TABLE bookings ADD COLUMN therapist_id_2 VARCHAR(50) NULL AFTER therapist_id";
        $conn->query($addColumn);
    }

    // Check if updated_at column exists
    $checkUpdatedAt = "SHOW COLUMNS FROM bookings LIKE 'updated_at'";
    $result = $conn->query($checkUpdatedAt);
    if ($result && $result->num_rows == 0) {
        $addUpdatedAt = "ALTER TABLE bookings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
        $conn->query($addUpdatedAt);
    }

    // Validate required fields
    $required = ['fullName', 'email', 'phone', 'serviceType', 'serviceName', 
                'servicePrice', 'serviceDuration', 'bookingDate', 'bookingTime', 
                'roomType', 'therapistId', 'paymentMethod'];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Additional validation for couples room
    if ($data['roomType'] === 'couples') {
        if (!isset($data['therapistId2']) || empty(trim($data['therapistId2']))) {
            throw new Exception("Second therapist is required for couples room");
        }
        
        if ($data['therapistId'] === $data['therapistId2']) {
            throw new Exception("Please select different therapists for each person");
        }
    }

    // Sanitize input data
    $fullName = mysqli_real_escape_string($conn, trim($data['fullName']));
    $email = mysqli_real_escape_string($conn, trim($data['email']));
    $phone = mysqli_real_escape_string($conn, trim($data['phone']));
    $serviceType = mysqli_real_escape_string($conn, trim($data['serviceType']));
    $serviceName = mysqli_real_escape_string($conn, trim($data['serviceName']));
    $servicePrice = floatval($data['servicePrice']);
    $serviceDuration = intval($data['serviceDuration']);
    $bookingDate = mysqli_real_escape_string($conn, trim($data['bookingDate']));
    $bookingTime = mysqli_real_escape_string($conn, trim($data['bookingTime']));
    $roomType = mysqli_real_escape_string($conn, trim($data['roomType']));
    $therapistId = trim($data['therapistId']);
    $therapistId2 = isset($data['therapistId2']) && !empty(trim($data['therapistId2'])) ? 
                    trim($data['therapistId2']) : NULL;
    $paymentMethod = mysqli_real_escape_string($conn, trim($data['paymentMethod']));
    
    $healthInfo = NULL;
    if (isset($data['healthInfo'])) {
        $healthInfo = mysqli_real_escape_string($conn, json_encode($data['healthInfo']));
    }

    $timeFormatted = convertTimeFormat($bookingTime);
    
    // Enhanced conflict check for both therapists
    $therapistsToCheck = [$therapistId];
    if ($therapistId2) {
        $therapistsToCheck[] = $therapistId2;
    }
    
    $conflictingTherapists = [];
    
    foreach ($therapistsToCheck as $therapist) {
        if ($therapist === 'no-preference') continue;
        
        $therapistEscaped = mysqli_real_escape_string($conn, $therapist);
        $conflictCheck = "SELECT id, full_name, booking_reference FROM bookings 
                         WHERE booking_date = '$bookingDate' 
                         AND booking_time = '$timeFormatted' 
                         AND (therapist_id = '$therapistEscaped' OR therapist_id_2 = '$therapistEscaped')
                         AND status NOT IN ('cancelled')
                         LIMIT 1";
        
        $conflictResult = $conn->query($conflictCheck);
        if ($conflictResult && $conflictResult->num_rows > 0) {
            $therapistName = getTherapistName($conn, $therapist);
            $conflictingTherapists[] = $therapistName;
        }
    }
    
    if (!empty($conflictingTherapists)) {
        $therapistList = implode(' and ', $conflictingTherapists);
        throw new Exception("Sorry, $therapistList " . (count($conflictingTherapists) === 1 ? 'is' : 'are') . " already booked at this time slot. Please select different therapist(s) or time.");
    }
    
    // Calculate prices
    $roomPrices = ['single' => 600, 'couples' => 1000, 'vip' => 2000];
    $roomPrice = isset($roomPrices[$roomType]) ? $roomPrices[$roomType] : 0;
    $totalPrice = $servicePrice + $roomPrice;

    // Generate unique booking reference
    do {
        $bookingReference = 'SP' . date('Y') . rand(1000, 9999);
        $refCheckQuery = "SELECT id FROM bookings WHERE booking_reference = '$bookingReference'";
        $refCheckResult = $conn->query($refCheckQuery);
    } while ($refCheckResult && $refCheckResult->num_rows > 0);
    
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : NULL;

    // Get therapist names for response
    $therapistName = getTherapistName($conn, $therapistId);
    $therapistName2 = $therapistId2 ? getTherapistName($conn, $therapistId2) : NULL;

    // Insert booking with transaction - STATUS IS 'pending'
    $conn->begin_transaction();
    
    try {
        // Final conflict check within transaction
        foreach ($therapistsToCheck as $therapist) {
            if ($therapist === 'no-preference') continue;
            
            $therapistEscaped = mysqli_real_escape_string($conn, $therapist);
            $finalConflictCheck = "SELECT id FROM bookings 
                                  WHERE booking_date = '$bookingDate' 
                                  AND booking_time = '$timeFormatted' 
                                  AND (therapist_id = '$therapistEscaped' OR therapist_id_2 = '$therapistEscaped')
                                  AND status NOT IN ('cancelled')
                                  FOR UPDATE";
            
            $finalConflictResult = $conn->query($finalConflictCheck);
            if ($finalConflictResult && $finalConflictResult->num_rows > 0) {
                $therapistName = getTherapistName($conn, $therapist);
                throw new Exception("$therapistName became unavailable during booking process. Please select a different therapist or time slot.");
            }
        }

        $therapistIdForDB = ($therapistId === 'no-preference') ? 'no-preference' : $therapistId;
        $therapistIdEscaped = mysqli_real_escape_string($conn, $therapistIdForDB);
        $therapistId2Escaped = $therapistId2 ? "'" . mysqli_real_escape_string($conn, $therapistId2) . "'" : 'NULL';

        // IMPORTANT: Status is set to 'pending' instead of 'confirmed'
        $sql = "INSERT INTO bookings (
            booking_reference, user_id, full_name, email, phone,
            service_type, service_name, service_price, service_duration,
            booking_date, booking_time, room_type, room_price, total_price,
            therapist_id, therapist_id_2, payment_method, health_info, created_at, status
        ) VALUES (
            '$bookingReference', " . ($userId ? $userId : 'NULL') . ", '$fullName', '$email', '$phone',
            '$serviceType', '$serviceName', $servicePrice, $serviceDuration,
            '$bookingDate', '$timeFormatted', '$roomType', $roomPrice, $totalPrice,
            '$therapistIdEscaped', $therapistId2Escaped, '$paymentMethod', " . ($healthInfo ? "'$healthInfo'" : 'NULL') . ", NOW(), 'pending'
        )";

        if (!$conn->query($sql)) {
            throw new Exception('Database insert failed: ' . $conn->error);
        }

        $bookingId = $conn->insert_id;
        
        if (!$bookingId) {
            throw new Exception('Failed to get booking ID');
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    // NO EMAIL SENT HERE - Email will be sent when admin confirms

    ob_clean();

    // Return success response indicating pending status
    echo json_encode([
        'success' => true,
        'message' => 'Booking submitted successfully! Your booking is pending confirmation from our team. You will receive a confirmation email once approved.',
        'bookingReference' => $bookingReference,
        'bookingId' => $bookingId,
        'status' => 'pending',
        'emailSent' => false,
        'details' => [
            'service' => $serviceName,
            'date' => $bookingDate,
            'time' => $bookingTime,
            'therapist' => $therapistName,
            'therapist2' => $therapistName2,
            'total_price' => $totalPrice
        ]
    ]);

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

function getTherapistName($conn, $therapistId) {
    if ($therapistId === 'no-preference') {
        return 'Best Available Therapist';
    }
    
    $therapistIdEscaped = mysqli_real_escape_string($conn, $therapistId);
    $therapistQuery = "SELECT name FROM therapists WHERE id = '$therapistIdEscaped'";
    $therapistResult = $conn->query($therapistQuery);
    
    if ($therapistResult && $therapistResult->num_rows > 0) {
        $therapist = $therapistResult->fetch_assoc();
        return $therapist['name'];
    }
    
    $fallbackNames = [
        '1' => 'Sarah Johnson',
        '2' => 'Michael Chen', 
        '3' => 'Emma Wilson',
        '4' => 'David Rodriguez',
        '5' => 'Lisa Patel'
    ];
    
    return isset($fallbackNames[$therapistId]) ? $fallbackNames[$therapistId] : 'Selected Therapist';
}

function convertTimeFormat($time) {
    $time = trim($time);
    
    if (preg_match('/^(\d{1,2}):?(\d{0,2})\s*(AM|PM)$/i', $time, $matches)) {
        $hour = intval($matches[1]);
        $minute = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : 0;
        $period = strtoupper($matches[3]);
        
        if ($period === 'AM' && $hour === 12) {
            $hour = 0;
        } elseif ($period === 'PM' && $hour !== 12) {
            $hour += 12;
        }
        
        return sprintf('%02d:%02d:00', $hour, $minute);
    }
    
    return $time;
}
?>