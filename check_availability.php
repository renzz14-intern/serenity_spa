<?php
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in response

try {
    // Include database config
    require_once 'config.php';
    
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    // Get and validate input
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    // Validate required field
    if (!isset($data['date']) || empty($data['date'])) {
        throw new Exception('Date is required');
    }

    $selectedDate = mysqli_real_escape_string($conn, trim($data['date']));

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        throw new Exception('Invalid date format');
    }

    // Create/check bookings table with therapist_id_2 column
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('confirmed', 'cancelled', 'completed') DEFAULT 'confirmed',
            
            INDEX idx_therapist_id (therapist_id),
            INDEX idx_therapist_id_2 (therapist_id_2)
        )
    ";
    
    $conn->query($createTable); // Don't throw error if this fails

    // Check if therapist_id_2 column exists, add if not
    $checkColumn = "SHOW COLUMNS FROM bookings LIKE 'therapist_id_2'";
    $result = $conn->query($checkColumn);
    if ($result && $result->num_rows == 0) {
        $addColumn = "ALTER TABLE bookings ADD COLUMN therapist_id_2 VARCHAR(50) NULL AFTER therapist_id";
        $conn->query($addColumn); // Don't throw error if this fails
    }

    // If checking specific therapist(s) availability (NEW for couples room support)
    if (isset($data['therapists']) && is_array($data['therapists'])) {
        $therapists = $data['therapists'];
        $selectedTime = isset($data['time']) ? trim($data['time']) : null;
        
        if (!$selectedTime) {
            throw new Exception('Time is required when checking therapist availability');
        }

        // Convert time to 24-hour format
        $time24Hour = convertTo24Hour($selectedTime);
        
        $unavailableTherapists = [];
        
        foreach ($therapists as $therapistId) {
            // Skip if no-preference
            if ($therapistId === 'no-preference') {
                continue;
            }

            $therapistIdEscaped = mysqli_real_escape_string($conn, $therapistId);
            
            // Check if this specific therapist is booked at this time (in either therapist_id or therapist_id_2)
            // Exclude cancelled AND completed bookings
            $sql = "SELECT id FROM bookings 
                    WHERE booking_date = ? 
                    AND booking_time = ? 
                    AND (therapist_id = ? OR therapist_id_2 = ?)
                    AND status NOT IN ('cancelled', 'completed')
                    LIMIT 1";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }

            $stmt->bind_param("ssss", $selectedDate, $time24Hour, $therapistIdEscaped, $therapistIdEscaped);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }

            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $unavailableTherapists[] = $therapistId;
            }

            $stmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'booked' => count($unavailableTherapists) > 0,
            'unavailableTherapists' => $unavailableTherapists
        ]);

    } else if (isset($data['time']) && isset($data['therapist'])) {
        // Legacy: Check specific time and single therapist
        $selectedTime = trim($data['time']);
        $selectedTherapist = trim($data['therapist']);

        $time24Hour = convertTo24Hour($selectedTime);
        
        // Check for specific therapist availability
        if ($selectedTherapist !== 'no-preference') {
            $sql = "SELECT COUNT(*) as count 
                    FROM bookings 
                    WHERE booking_date = ? 
                    AND booking_time = ?
                    AND (therapist_id = ? OR therapist_id_2 = ?)
                    AND status NOT IN ('cancelled', 'completed')";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }

            $stmt->bind_param("ssss", $selectedDate, $time24Hour, $selectedTherapist, $selectedTherapist);
        } else {
            // For "no-preference", check if there are slots available
            $sql = "SELECT COUNT(*) as count 
                    FROM bookings 
                    WHERE booking_date = ? 
                    AND booking_time = ?
                    AND status NOT IN ('cancelled', 'completed')";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }

            $stmt->bind_param("ss", $selectedDate, $time24Hour);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute query: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($selectedTherapist === 'no-preference') {
            $maxTherapists = 5;
            $isBooked = ($row['count'] >= $maxTherapists);
        } else {
            $isBooked = ($row['count'] > 0);
        }

        $stmt->close();

        echo json_encode([
            'date' => $selectedDate,
            'time' => $selectedTime,
            'therapist' => $selectedTherapist,
            'available' => !$isBooked,
            'booked' => $isBooked
        ]);

    } else {
        // Return general availability for all time slots on this date
        $sql = "SELECT booking_time, service_name, full_name, therapist_id, therapist_id_2
                FROM bookings 
                WHERE booking_date = ? 
                AND status NOT IN ('cancelled', 'completed')
                ORDER BY booking_time";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }

        $stmt->bind_param("s", $selectedDate);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute query: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $bookedSlots = [];
        $timeSlotAvailability = [];

        while ($row = $result->fetch_assoc()) {
            $time = $row['booking_time'];
            
            $bookedSlots[] = [
                'booking_time' => $time,
                'service_name' => $row['service_name'],
                'client_name' => $row['full_name'],
                'therapist_id' => $row['therapist_id'],
                'therapist_id_2' => $row['therapist_id_2']
            ];

            // Build availability map
            if (!isset($timeSlotAvailability[$time])) {
                $timeSlotAvailability[$time] = [];
            }
            
            // Add both therapists to the booked list
            if ($row['therapist_id'] && $row['therapist_id'] !== 'no-preference') {
                $timeSlotAvailability[$time][] = $row['therapist_id'];
            }
            if (!empty($row['therapist_id_2'])) {
                $timeSlotAvailability[$time][] = $row['therapist_id_2'];
            }
        }

        $stmt->close();

        // Return the availability info
        echo json_encode([
            'success' => true,
            'date' => $selectedDate,
            'bookedSlots' => $bookedSlots,
            'timeSlotAvailability' => $timeSlotAvailability
        ]);
    }

} catch (Exception $e) {
    // Log error
    error_log("Availability check error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

function convertTo24Hour($time12) {
    // Convert "9:00 AM" or "2:30 PM" to "09:00:00" or "14:30:00"
    $time = trim($time12);
    
    // Handle different formats
    if (preg_match('/^(\d{1,2}):?(\d{0,2})\s*(AM|PM)$/i', $time, $matches)) {
        $hour = intval($matches[1]);
        $minute = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : 0;
        $period = strtoupper($matches[3]);
        
        // Convert to 24-hour format
        if ($period === 'AM' && $hour === 12) {
            $hour = 0;
        } elseif ($period === 'PM' && $hour !== 12) {
            $hour += 12;
        }
        
        return sprintf('%02d:%02d:00', $hour, $minute);
    }
    
    // If already in 24-hour format, return as is
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    
    // Default fallback
    return '00:00:00';
}
?>