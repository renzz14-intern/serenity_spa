<?php
session_start();
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/mail_settings.php';
require_once 'vendor/autoload.php';

// Require a user session with the administrator role.
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Handle status updates
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $bookingId = intval($_POST['booking_id']);
    $newStatus = $_POST['status'];
    
    $allowedStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
    if (in_array($newStatus, $allowedStatuses)) {
        // Get the booking details before updating
        $getBookingSql = "SELECT b.*, 
                          t1.name as therapist_name,
                          t2.name as therapist_name_2
                          FROM bookings b 
                          LEFT JOIN therapists t1 ON b.therapist_id = t1.id
                          LEFT JOIN therapists t2 ON b.therapist_id_2 = t2.id
                          WHERE b.id = ?";
        $getBookingStmt = $conn->prepare($getBookingSql);
        $getBookingStmt->bind_param('i', $bookingId);
        $getBookingStmt->execute();
        $bookingResult = $getBookingStmt->get_result();
        $bookingData = $bookingResult->fetch_assoc();
        
        if ($bookingData) {
            $updateSql = "UPDATE bookings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param('si', $newStatus, $bookingId);
            
            if ($updateStmt->execute()) {
                $message = "Booking status updated successfully!";
                $messageType = "success";
                
                // Send emails based on status
                $emailSent = false;
                
                if ($newStatus === 'confirmed') {
                    // Send confirmation email when booking is confirmed
                    $emailSent = sendBookingConfirmationEmail($bookingData);
                    if ($emailSent) {
                        // Update email_sent flag
                        $updateEmailSql = "UPDATE bookings SET email_sent = TRUE WHERE id = ?";
                        $updateEmailStmt = $conn->prepare($updateEmailSql);
                        $updateEmailStmt->bind_param('i', $bookingId);
                        $updateEmailStmt->execute();
                        
                        $message .= " Confirmation email sent to customer.";
                    } else {
                        $message .= " Note: Confirmation email could not be sent.";
                    }
                } elseif ($newStatus === 'cancelled') {
                    $emailSent = sendCancellationEmail($bookingData);
                    if ($emailSent) {
                        $message .= " Cancellation email sent to customer.";
                    } else {
                        $message .= " Note: Cancellation email could not be sent.";
                    }
                } elseif ($newStatus === 'completed') {
                    $emailSent = sendCompletionEmail($bookingData);
                    if ($emailSent) {
                        $message .= " Completion email with feedback request sent to customer.";
                    } else {
                        $message .= " Note: Completion email could not be sent.";
                    }
                }
                
            } else {
                $message = "Error updating booking status.";
                $messageType = "error";
            }
        } else {
            $message = "Booking not found.";
            $messageType = "error";
        }
    }
}

// Fetch bookings with filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT b.*, 
        t1.name as therapist_name, 
        t1.specialty as therapist_specialty,
        t2.name as therapist_name_2,
        t2.specialty as therapist_specialty_2
        FROM bookings b 
        LEFT JOIN therapists t1 ON b.therapist_id = t1.id
        LEFT JOIN therapists t2 ON b.therapist_id_2 = t2.id
        WHERE 1=1";
$params = [];
$types = '';

if ($filter !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $filter;
    $types .= 's';
}

if ($search) {
    $sql .= " AND (b.full_name LIKE ? OR b.email LIKE ? OR b.booking_reference LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

$sql .= " ORDER BY b.booking_date DESC, b.booking_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Email functions
function sendBookingConfirmationEmail($bookingData) {
    $mail = new PHPMailer(true);
    
    try {
        $mailConfig = serenity_mail_config();
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailConfig['port'];

        $mail->setFrom($mailConfig['from_email'] ?: $mailConfig['username'], 'Serenity Spa (Foot & Body Massage)');
        $mail->addAddress($bookingData['email'], $bookingData['full_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmed - ' . $bookingData['booking_reference'];
        
        $therapistDisplay = $bookingData['therapist_name'] ?? 'Assigned Therapist';
        if (!empty($bookingData['therapist_name_2'])) {
            $therapistDisplay .= ' & ' . $bookingData['therapist_name_2'];
        }
        
        $bookingDetails = [
            'reference' => $bookingData['booking_reference'],
            'name' => $bookingData['full_name'],
            'email' => $bookingData['email'],
            'phone' => $bookingData['phone'],
            'service' => $bookingData['service_name'],
            'date' => date('l, F j, Y', strtotime($bookingData['booking_date'])),
            'time' => date('g:i A', strtotime($bookingData['booking_time'])),
            'room' => getRoomDisplayName($bookingData['room_type']),
            'therapist' => $therapistDisplay,
            'duration' => $bookingData['service_duration'],
            'total' => $bookingData['total_price'],
            'payment_method' => ucfirst($bookingData['payment_method'])
        ];
        
        $healthInfo = !empty($bookingData['health_info']) ? json_decode($bookingData['health_info'], true) : null;
        
        $mail->Body = generateConfirmationEmailHTML($bookingDetails, $healthInfo);
        $mail->AltBody = generateConfirmationEmailText($bookingDetails, $healthInfo);

        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Confirmation email sending failed: " . $e->getMessage());
        return false;
    }
}

function generateConfirmationEmailHTML($details, $healthInfo) {
    $healthSection = '';
    if ($healthInfo) {
        $healthSection = generateHealthInfoSection($healthInfo);
    }
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Booking Confirmed</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white;'>
            <div style='border-bottom: 2px solid #92a98f; padding-bottom: 20px; margin-bottom: 20px;'>
                <h1 style='color: #92a98f; margin: 0;'>Serenity Spa (Foot & Body Massage)</h1>
                <h2 style='color: #666; margin: 10px 0 0 0;'>Booking Confirmed!</h2>
            </div>
            
            <p>Dear {$details['name']},</p>

            <p>Great news! Your booking has been <strong>confirmed</strong> by our team. We look forward to welcoming you to Serenity Spa.</p>

            <h3 style='color: #92a98f; border-bottom: 1px solid #eee; padding-bottom: 5px;'>Your Appointment Details</h3>
            
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Booking Reference:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['reference']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Service:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['service']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Duration:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['duration']} minutes</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Date:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['date']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Time:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['time']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Room:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['room']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Therapist(s):</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['therapist']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Payment Method:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$details['payment_method']}</td></tr>
                <tr style='background-color: #f8f9fa;'><td style='padding: 10px 8px; font-weight: bold;'><strong>Total Amount:</strong></td><td style='padding: 10px 8px; font-weight: bold;'>₱" . number_format($details['total'], 2) . "</td></tr>
            </table>
            
            $healthSection
            
            <h3 style='color: #92a98f; border-bottom: 1px solid #eee; padding-bottom: 5px;'>Important Reminders</h3>
            <ul>
                <li>Please arrive 15 minutes before your appointment time</li>
                <li>Bring a valid ID for verification</li>
                <li>For cancellations or changes, please contact us at least 24 hours in advance</li>
                <li>Our cancellation policy applies to no-shows and same-day cancellations</li>
            </ul>
            
            <p><strong>Need to make changes?</strong><br>
            Email: serenityspamassage25@gmail.com</p>
            
            <p>We look forward to providing you with an exceptional spa experience!</p>
            
            <p>Best regards,<br>
            The Serenity Spa Team</p>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            <p style='font-size: 12px; color: #666; text-align: center;'>
                Serenity Spa (Foot & Body Massage)<br>
                Email: serenityspamassage25@gmail.com<br>
                <em>This is an automated confirmation email.</em>
            </p>
        </div>
    </body>
    </html>";
}

function generateConfirmationEmailText($details, $healthInfo) {
    return "SERENITY SPA (FOOT & BODY MASSAGE) - BOOKING CONFIRMED\n" .
           "==========================================\n\n" .
           "Dear {$details['name']},\n\n" .
           "Great news! Your booking has been CONFIRMED by our team.\n\n" .
           "YOUR APPOINTMENT DETAILS:\n" .
           "------------------------\n" .
           "Booking Reference: {$details['reference']}\n" .
           "Service: {$details['service']}\n" .
           "Duration: {$details['duration']} minutes\n" .
           "Date: {$details['date']}\n" .
           "Time: {$details['time']}\n" .
           "Room: {$details['room']}\n" .
           "Therapist(s): {$details['therapist']}\n" .
           "Payment Method: {$details['payment_method']}\n" .
           "Total Amount: ₱" . number_format($details['total'], 2) . "\n\n" .
           "IMPORTANT REMINDERS:\n" .
           "-------------------\n" .
           "• Please arrive 15 minutes before your appointment time\n" .
           "• Bring a valid ID for verification\n" .
           "• For cancellations or changes, contact us at least 24 hours in advance\n\n" .
           "CONTACT: serenityspamassage25@gmail.com\n\n" .
           "Best regards,\n" .
           "The Serenity Spa Team";
}

function generateHealthInfoSection($healthInfo) {
    if (!$healthInfo || empty($healthInfo)) {
        return '';
    }

    $section = "<h3 style='color: #92a98f; border-bottom: 1px solid #eee; padding-bottom: 5px;'>Health Information on File</h3>
        <p><small>We have the following health information on file for your safety:</small></p>
        <ul>";
    
    if (!empty($healthInfo['medicalConditions'])) {
        $conditions = array_filter($healthInfo['medicalConditions']);
        if (!empty($conditions) && !in_array('none', $conditions)) {
            $conditionNames = array_map(function($condition) {
                switch ($condition) {
                    case 'heart': return 'Heart Condition / High Blood Pressure';
                    case 'diabetes': return 'Diabetes';
                    case 'surgery': return 'Recent Surgery / Injury';
                    case 'skin': return 'Skin Infection / Open Wounds';
                    default: return ucfirst($condition);
                }
            }, $conditions);
            $section .= "<li><strong>Medical Conditions:</strong> " . implode(', ', $conditionNames) . "</li>";
        }
    }
    
    if (!empty($healthInfo['pregnancy']) && $healthInfo['pregnancy'] === 'yes') {
        $weeks = !empty($healthInfo['pregnancyWeeks']) ? " ({$healthInfo['pregnancyWeeks']} weeks)" : '';
        $section .= "<li><strong>Pregnancy Status:</strong> Pregnant$weeks</li>";
    }
    
    if (!empty($healthInfo['allergies'])) {
        $allergies = array_filter($healthInfo['allergies']);
        if (!empty($allergies) && !in_array('none', $allergies)) {
            $allergyNames = array_map(function($allergy) use ($healthInfo) {
                switch ($allergy) {
                    case 'oils': return 'Massage Oils / Lotions';
                    case 'essential': return 'Essential Oils';
                    case 'other': return !empty($healthInfo['otherAllergy']) ? 
                        "Other: {$healthInfo['otherAllergy']}" : 'Other (specified)';
                    default: return ucfirst($allergy);
                }
            }, $allergies);
            $section .= "<li><strong>Allergies:</strong> " . implode(', ', $allergyNames) . "</li>";
        }
    }
    
    if (!empty($healthInfo['currentlyIll']) && $healthInfo['currentlyIll'] === 'yes') {
        $section .= "<li><strong>Health Status:</strong> Currently experiencing illness symptoms</li>";
    }
    
    $section .= "</ul>
        <p><small><em>Our therapists will review this information before your session.</em></small></p>";
    
    return $section;
}

function sendCancellationEmail($bookingData) {
    $mail = new PHPMailer(true);
    
    try {
        $mailConfig = serenity_mail_config();
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailConfig['port'];

        $mail->setFrom($mailConfig['from_email'] ?: $mailConfig['username'], 'Serenity Spa (Foot & Body Massage)');
        $mail->addAddress($bookingData['email'], $bookingData['full_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Booking Cancellation - ' . $bookingData['booking_reference'];
        
        $mail->Body = generateCancellationEmailHTML($bookingData);
        $mail->AltBody = generateCancellationEmailText($bookingData);

        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Cancellation email sending failed: " . $e->getMessage());
        return false;
    }
}

function generateCancellationEmailHTML($bookingData) {
    $bookingDate = date('l, F j, Y', strtotime($bookingData['booking_date']));
    $bookingTime = date('g:i A', strtotime($bookingData['booking_time']));
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Booking Cancellation</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white;'>
            <div style='border-bottom: 2px solid #92a98f; padding-bottom: 20px; margin-bottom: 20px;'>
                <h1 style='color: #92a98f; margin: 0;'>Serenity Spa (Foot & Body Massage)</h1>
                <h2 style='color: #666; margin: 10px 0 0 0;'>Booking Cancellation</h2>
            </div>
            
            <p>Dear {$bookingData['full_name']},</p>
            
            <p>We regret to inform you that your spa appointment has been cancelled.</p>
            
            <h3 style='color: #92a98f; border-bottom: 1px solid #eee; padding-bottom: 5px;'>Cancelled Appointment Details</h3>
            
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Booking Reference:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$bookingData['booking_reference']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Service:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$bookingData['service_name']}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Date:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$bookingDate}</td></tr>
                <tr><td style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Time:</strong></td><td style='padding: 8px 0; border-bottom: 1px solid #eee;'>{$bookingTime}</td></tr>
            </table>
            
            <h3 style='color: #92a98f; border-bottom: 1px solid #eee; padding-bottom: 5px;'>What happens next?</h3>
            <ul>
                <li>If you paid in advance, a full refund will be processed within 3-5 business days</li>
                <li>You can book a new appointment at any time through our website</li>
                <li>If you have any questions, please don't hesitate to contact us</li>
            </ul>
            
            <p>We sincerely apologize for any inconvenience this may cause. We hope to serve you in the future.</p>
            
            <p><strong>Need assistance?</strong><br>
            Email: serenityspamassage25@gmail.com</p>
            
            <p>Best regards,<br>
            The Serenity Spa Team</p>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            <p style='font-size: 12px; color: #666; text-align: center;'>
                Serenity Spa (Foot & Body Massage)<br>
                Email: serenityspamassage25@gmail.com
            </p>
        </div>
    </body>
    </html>";
}

function generateCancellationEmailText($bookingData) {
    $bookingDate = date('l, F j, Y', strtotime($bookingData['booking_date']));
    $bookingTime = date('g:i A', strtotime($bookingData['booking_time']));
    
    return "SERENITY SPA (FOOT & BODY MASSAGE) - BOOKING CANCELLATION\n" .
           "============================================\n\n" .
           "Dear {$bookingData['full_name']},\n\n" .
           "We regret to inform you that your spa appointment has been cancelled.\n\n" .
           "CANCELLED APPOINTMENT DETAILS:\n" .
           "-----------------------------\n" .
           "Booking Reference: {$bookingData['booking_reference']}\n" .
           "Service: {$bookingData['service_name']}\n" .
           "Date: {$bookingDate}\n" .
           "Time: {$bookingTime}\n\n" .
           "WHAT HAPPENS NEXT:\n" .
           "-----------------\n" .
           "• If you paid in advance, a full refund will be processed within 3-5 business days\n" .
           "• You can book a new appointment at any time through our website\n" .
           "• If you have any questions, please contact us\n\n" .
           "We sincerely apologize for any inconvenience.\n\n" .
           "CONTACT: serenityspamassage25@gmail.com\n\n" .
           "Best regards,\n" .
           "The Serenity Spa Team";
}

function sendCompletionEmail($bookingData) {
    $mail = new PHPMailer(true);
    
    try {
        $mailConfig = serenity_mail_config();
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailConfig['port'];

        $mail->setFrom($mailConfig['from_email'] ?: $mailConfig['username'], 'Serenity Spa (Foot & Body Massage)');
        $mail->addAddress($bookingData['email'], $bookingData['full_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Thank You for Visiting Us - ' . $bookingData['booking_reference'];
        
        $mail->Body = generateCompletionEmailHTML($bookingData);
        $mail->AltBody = generateCompletionEmailText($bookingData);

        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Completion email sending failed: " . $e->getMessage());
        return false;
    }
}

function generateCompletionEmailHTML($bookingData) {
    $bookingDate = date('l, F j, Y', strtotime($bookingData['booking_date']));
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Thank You for Visiting Us</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white;'>
            <div style='border-bottom: 2px solid #92a98f; padding-bottom: 20px; margin-bottom: 20px;'>
                <h1 style='color: #92a98f; margin: 0;'>Serenity Spa (Foot & Body Massage)</h1>
                <h2 style='color: #666; margin: 10px 0 0 0;'>Thank You for Visiting Us</h2>
            </div>
            
            <p>Dear {$bookingData['full_name']},</p>
            
            <p>Thank you for choosing Serenity Spa (Foot & Body Massage) for your {$bookingData['service_name']} session on {$bookingDate}.</p>
            
            <p>We hope you enjoyed your relaxing experience with us and feel refreshed and rejuvenated.</p>
            
            <h3 style='color: #92a98f; border-bottom: 1px solid #eee; padding-bottom: 5px;'>We Value Your Feedback</h3>
            
            <p>Your opinion matters to us! Please take a few minutes to share your experience by emailing us at: <strong>serenityspamassage25@gmail.com</strong></p>
            
            <h3 style='color: #92a98f; border-bottom: 1px solid #eee; padding-bottom: 5px;'>Book Your Next Visit</h3>
            
            <p>We would love to welcome you back! Consider booking your next appointment to maintain your wellness routine.</p>
            
            <ul>
                <li>Regular spa treatments help maintain stress relief and wellness</li>
                <li>Book online through our website for convenience</li>
                <li>Ask about our loyalty program for returning customers</li>
            </ul>
            
            <p><strong>Questions or want to book again?</strong><br>
            Email: serenityspamassage25@gmail.com</p>
            
            <p>Thank you again for choosing us. We look forward to serving you again soon!</p>
            
            <p>Warm regards,<br>
            The Serenity Spa Team</p>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
            <p style='font-size: 12px; color: #666; text-align: center;'>
                Serenity Spa (Foot & Body Massage)<br>
                Email: serenityspamassage25@gmail.com
            </p>
        </div>
    </body>
    </html>";
}

function generateCompletionEmailText($bookingData) {
    $bookingDate = date('l, F j, Y', strtotime($bookingData['booking_date']));
    
    return "SERENITY SPA (FOOT & BODY MASSAGE) - THANK YOU\n" .
           "==================================\n\n" .
           "Dear {$bookingData['full_name']},\n\n" .
           "Thank you for choosing Serenity Spa for your {$bookingData['service_name']} session on {$bookingDate}.\n\n" .
           "We hope you enjoyed your experience!\n\n" .
           "WE VALUE YOUR FEEDBACK:\n" .
           "Email: serenityspamassage25@gmail.com\n\n" .
           "BOOK YOUR NEXT VISIT:\n" .
           "• Regular treatments help maintain wellness\n" .
           "• Book online for convenience\n\n" .
           "CONTACT: serenityspamassage25@gmail.com\n\n" .
           "Warm regards,\n" .
           "The Serenity Spa Team";
}

function getRoomDisplayName($roomType) {
    $roomNames = [
        'single' => 'Single Treatment Room',
        'couples' => 'Couples Room', 
        'vip' => 'VIP Suite'
    ];
    return $roomNames[$roomType] ?? 'Standard Room';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Serenity Spa</title>
    <link rel="icon" href="images/original-logo.jpg">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="header">
        <h1>Serenity Spa - Booking Management</h1>
        <a href="admin_booking.php" class="btn btn-primary">New Booking</a>
    </div>

    <div class="container">
        <?php if (isset($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" class="filter-group">
                <label for="filter">Status:</label>
                <select name="filter" id="filter">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Bookings</option>
                    <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>

                <label for="search">Search:</label>
                <input type="text" name="search" id="search" placeholder="Name, email, or reference..." value="<?php echo htmlspecialchars($search); ?>">
                
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="bookings-table">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <h3>No bookings found</h3>
                    <p>There are no bookings matching your criteria.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th>Therapist(s)</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong><br>
                                    <small><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['email']); ?></small><br>
                                    <small><?php echo htmlspecialchars($booking['phone']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong><br>
                                    <small><?php echo $booking['service_duration']; ?> mins • <?php echo ucfirst($booking['room_type']); ?> Room</small>
                                </td>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></strong><br>
                                    <small><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></small>
                                </td>
                                <td class="therapist-info">
                                    <?php if ($booking['therapist_name']): ?>
                                        <strong><?php echo htmlspecialchars($booking['therapist_name']); ?></strong>
                                        <?php if (!empty($booking['therapist_name_2'])): ?>
                                            <br><strong><?php echo htmlspecialchars($booking['therapist_name_2']); ?></strong>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small>Not assigned</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>₱<?php echo number_format($booking['total_price'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure? <?php echo $booking['status'] === 'pending' ? 'This will send a confirmation email to the customer.' : 'This will send an email to the customer.'; ?>')">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button type="submit" name="status" value="confirmed" class="btn btn-success">Confirm</button>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] !== 'cancelled' && $booking['status'] !== 'completed'): ?>
                                                <button type="submit" name="status" value="cancelled" class="btn btn-danger">Cancel</button>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'confirmed'): ?>
                                                <button type="submit" name="status" value="completed" class="btn btn-success">Complete</button>
                                            <?php endif; ?>
                                        </form>
                                        
                                        <button onclick="viewDetails(<?php echo htmlspecialchars(json_encode($booking)); ?>)" class="btn btn-primary">View</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div id="bookingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3 id="modalTitle">Booking Details</h3>
            <div id="modalContent"></div>
            <div style="text-align: right; margin-top: 1rem;">
                <button onclick="closeModal()" class="btn btn-secondary">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewDetails(booking) {
            const modal = document.getElementById('bookingModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            title.textContent = 'Booking Details - ' + booking.booking_reference;
            
            let therapistDisplay = booking.therapist_name || 'Not assigned';
            if (booking.therapist_name_2) {
                therapistDisplay += ' & ' + booking.therapist_name_2;
            }
            
            let healthInfo = '';
            if (booking.health_info) {
                try {
                    const health = JSON.parse(booking.health_info);
                    healthInfo = '<tr><td colspan="2"><strong>Health Information:</strong><br><small>' + JSON.stringify(health, null, 2) + '</small></td></tr>';
                } catch(e) {}
            }
            
            content.innerHTML = `
                <table style="width: 100%; margin: 1rem 0;">
                    <tr><td><strong>Customer:</strong></td><td>${booking.full_name}</td></tr>
                    <tr><td><strong>Email:</strong></td><td>${booking.email}</td></tr>
                    <tr><td><strong>Phone:</strong></td><td>${booking.phone}</td></tr>
                    <tr><td><strong>Service:</strong></td><td>${booking.service_name}</td></tr>
                    <tr><td><strong>Duration:</strong></td><td>${booking.service_duration} minutes</td></tr>
                    <tr><td><strong>Room Type:</strong></td><td>${booking.room_type.charAt(0).toUpperCase() + booking.room_type.slice(1)}</td></tr>
                    <tr><td><strong>Therapist(s):</strong></td><td>${therapistDisplay}</td></tr>
                    <tr><td><strong>Total Price:</strong></td><td><strong>₱${parseFloat(booking.total_price).toLocaleString(undefined, {minimumFractionDigits:2})}</strong></td></tr>
                    <tr><td><strong>Date:</strong></td><td>${new Date(booking.booking_date).toLocaleDateString()}</td></tr>
                    <tr><td><strong>Time:</strong></td><td>${new Date('2000-01-01 ' + booking.booking_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td></tr>
                    <tr><td><strong>Payment:</strong></td><td>${booking.payment_method}</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="status-badge status-${booking.status}">${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}</span></td></tr>
                    <tr><td><strong>Created:</strong></td><td>${new Date(booking.created_at).toLocaleString()}</td></tr>
                    ${healthInfo}
                </table>
            `;
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }
        
        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>