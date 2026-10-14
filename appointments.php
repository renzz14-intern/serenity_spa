<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's appointments
$appointments = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            id,
            booking_reference,
            service_name,
            booking_date,
            booking_time,
            room_type,
            total_price,
            created_at,
            health_info,
            status
        FROM bookings 
        WHERE user_id = ? 
        ORDER BY booking_date DESC, booking_time DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function getRoomDisplayName($roomType) {
    $roomNames = [
        'single' => 'Single Room',
        'couples' => 'Couples Room',
        'group' => 'Group Room',
        'vip' => 'VIP Suite'
    ];
    return $roomNames[$roomType] ?? 'Standard Room';
}

function getStatusBadge($status) {
    $statusClasses = [
        'confirmed' => 'status-confirmed',
        'pending' => 'status-pending',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];
    $statusClass = $statusClasses[$status] ?? 'status-pending';
    return "<span class='status-badge {$statusClass}'>" . ucfirst($status) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Serenity Spa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/original-logo.jpg">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .back-btn {
            background: linear-gradient(135deg, #dbfed7 0%, #92a98f 0%);
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 20px;
            display: inline-block;
        }

        .back-btn:hover {
            background: linear-gradient(135deg, #dbfed7 0%, #92a98f 100%);
        }

        .page-title {
            font-size: 28px;
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .appointments-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .appointments-header {
            background: linear-gradient(135deg, #dbfed7 0%, #92a98f 0%);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
        }

        .appointment-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .service-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .appointment-details {
            color: #666;
            margin-bottom: 5px;
        }

        .price {
            font-weight: bold;
            color: #d91111ff;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            float: right;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .no-appointments {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .no-appointments h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .booking-reference {
            font-family: monospace;
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="back-btn" onclick="window.location.href='index.html'">
            <i class="fas fa-arrow-left"></i> Back to Home
        </button>

        <h1 class="page-title">My Appointments</h1>
        
        <?php if (empty($appointments)): ?>
            <div class="no-appointments">
                <h3>No Appointments Found</h3>
                <p>You haven't made any appointments yet.</p>
            </div>
        <?php else: ?>
            <div class="appointments-container">
                <div class="appointments-header">
                    <i class="fas fa-calendar-check"></i> History
                </div>
                
                <?php foreach ($appointments as $appointment): ?>
                    <div class="appointment-item">
                        <?php echo getStatusBadge($appointment['status'] ?? 'pending'); ?>
                        
                        <div class="service-name"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                        
                        <div class="appointment-details">
                            <strong>Date:</strong> <?php echo formatDate($appointment['booking_date']); ?> at <?php echo formatTime($appointment['booking_time']); ?>
                        </div>
                        
                        <div class="appointment-details">
                            <strong>Room:</strong> <?php echo getRoomDisplayName($appointment['room_type']); ?>
                        </div>
                        
                        <div class="appointment-details">
                            <strong>Reference:</strong> <span class="booking-reference"><?php echo htmlspecialchars($appointment['booking_reference']); ?></span>
                        </div>
                        
                        <div class="appointment-details">
                            <strong>Booked on:</strong> <?php echo formatDate($appointment['created_at']); ?>
                        </div>
                        
                        <div class="appointment-details price">
                            Total: ₱<?php echo number_format($appointment['total_price'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="script.js"></script>
</body>
</html>