<?php
require_once 'config.php';
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userData = null;

if ($isLoggedIn) {
    // Fetch user data from database
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
}

// Initialize empty arrays in case queries fail
$therapists = [];
$services = [];

try {
    // Fetch therapists
    $therapistsQuery = "SELECT * FROM therapists"; // removed status filter
    $therapistsResult = $conn->query($therapistsQuery);
    if ($therapistsResult) {
        $therapists = $therapistsResult->fetch_all(MYSQLI_ASSOC);
    }

    // Fetch services
    $servicesQuery = "SELECT * FROM services"; // removed status filter
    $servicesResult = $conn->query($servicesQuery);
    if ($servicesResult) {
        $services = $servicesResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // Log error and continue with empty arrays
    error_log("Database error: " . $e->getMessage());
}

// Check if we have services and therapists, if not, provide default data
if (empty($services)) {
    $services = [
        [
            'id' => 1,
            'name' => 'Swedish Massage',
            'description' => 'Gentle, flowing strokes that release tension and improve circulation.',
            'price' => 600.00,
            'duration' => 60
        ],
        [
            'id' => 2,
            'name' => 'Deep Tissue Massage',
            'description' => 'Targeted therapy for deeper muscle layers and connective tissue.',
            'price' => 800.00,
            'duration' => 75
        ],
        [
            'id' => 3,
            'name' => 'Hot Stone Massage',
            'description' => 'Heated stones to melt away stress and tension.',
            'price' => 1000.00,
            'duration' => 90
        ],
        [
            'id' => 4,
            'name' => 'Aromatherapy Massage',
            'description' => 'Essential oils combined with therapeutic touch.',
            'price' => 1000.00,
            'duration' => 75
        ],
        [
            'id' => 5,
            'name' => 'Thai Massage',
            'description' => 'Yoga stretches and acupressure for total body wellness.',
            'price' => 1000.00,
            'duration' => 75
        ],
        [
            'id' => 6,
            'name' => 'Reflexology',
            'description' => 'Healing through targeted pressure points on your feet.',
            'price' => 600.00,
            'duration' => 60
        ]
    ];
}

if (empty($therapists)) {
    $therapists = [
        [
            'id' => 1,
            'name' => 'Sarah Johnson',
            'specialty' => 'Swedish & Aromatherapy Massage'
        ],
        [
            'id' => 2,
            'name' => 'Michael Chen',
            'specialty' => 'Deep Tissue & Thai Massage'
        ],
        [
            'id' => 3,
            'name' => 'Emma Wilson',
            'specialty' => 'Hot Stone & Reflexology'
        ],
        [
            'id' => 4,
            'name' => 'David Rodriguez',
            'specialty' => 'Sports & Deep Tissue Massage'
        ],
        [
            'id' => 5,
            'name' => 'Lisa Patel',
            'specialty' => 'Aromatherapy & Swedish Massage'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Spa Experience - Serenity Spa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="images/original-logo.jpg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .back-btn {
            background: linear-gradient(135deg, #dbfed7 0%, #92a98f 50%);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 165, 116, 0.3);
        }

        .header h1 {
            color: #333;
            font-size: 2rem;
            font-weight: 600;
        }

        .booking-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }

        .progress-tabs {
            display: flex;
            background: #92a98f;
            position: relative;
        }

        .tab {
            flex: 1;
            padding: 1.5rem 1rem;
            text-align: center;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            border: none;
            background: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .tab.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }

        .tab.completed {
            color: white;
        }

        .tab.completed::after {
            content: '✓';
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            background: #4CAF50;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .tab-content {
            padding: 3rem;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 1rem;
            text-align: center;
        }

        .step-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 2.5rem;
            font-size: 1rem;
        }

        /* Health Check Styles */
        .health-check-container {
            background: linear-gradient(135deg, #f9f9f9ff 0%, #ffffffff 100%);
            border: 2px solid #d4a574;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .health-check-title {
            color: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .health-check-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        .health-question {
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .health-question h4 {
            color: #333;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .health-options {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .health-option {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.5rem;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }

        .health-option:hover {
            background-color: #f8f9fa;
        }

        .health-option input[type="checkbox"],
        .health-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #d4a574;
        }

        .health-option label {
            flex: 1;
            cursor: pointer;
            color: #333;
        }

        .health-text-input {
            margin-left: 2rem;
            margin-top: 0.5rem;
        }

        .health-text-input input {
            width: 100%;
            max-width: 200px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .pregnancy-weeks {
            display: none;
            margin-left: 2rem;
            margin-top: 0.5rem;
        }

        .pregnancy-weeks input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }

        .allergies-other {
            display: none;
        }

        .health-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            display: none;
        }

        .health-warning.show {
            display: block;
        }

        .health-warning i {
            color: #856404;
            margin-right: 0.5rem;
        }

        .health-warning p {
            color: #856404;
            margin: 0;
        }

        .services-grid, .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .service-item {
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }

        .service-item:hover {
            border-color: #d4a574;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 165, 116, 0.2);
        }

        .service-item.selected {
            border-color: #d4a574;
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.1) 0%, rgba(193, 150, 96, 0.1) 100%);
        }

        .service-item input[type="radio"] {
            display: none;
        }

        .service-item h3 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .service-item p {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .service-price {
            font-weight: 600;
            color: #d4a574;
            font-size: 1.1rem;
        }

        .service-duration {
            color: #888;
            font-size: 0.9rem;
            margin-left: 1rem;
        }

        .datetime-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .date-picker, .time-picker {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
        }

        .date-picker h4, .time-picker h4 {
            margin-bottom: 1rem;
            color: #333;
        }

        .date-picker input {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 0.5rem;
        }

        .time-slot {
            padding: 0.6rem 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            background: white;
        }

        .time-slot:hover:not(.unavailable) {
            border-color: #d4a574;
        }

        .time-slot.selected {
            background: linear-gradient(135deg, #d4a574 0%, #c19660 100%);
            color: white;
            border-color: #d4a574;
        }

        .time-slot.unavailable {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
            border-color: #ddd;
        }

        .therapist-section {
            margin-top: 2rem;
        }

        .summary-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
            color: #d4a574;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .payment-item {
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: white;
        }

        .payment-item:hover {
            border-color: #d4a574;
        }

        .payment-item.selected {
            border-color: #d4a574;
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.1) 0%, rgba(193, 150, 96, 0.1) 100%);
        }

        .payment-item input[type="radio"] {
            display: none;
        }

        .payment-item i {
            font-size: 1.5rem;
            color: #d4a574;
            margin-bottom: 0.5rem;
        }

        .personal-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #d4a574;
        }

        .form-group textarea {
            resize: vertical;
            height: 100px;
        }

        .confirmation-card {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .confirmation-card i {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }

        .confirmation-card h3 {
            color: #155724;
            margin-bottom: 1rem;
        }

        .reference-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: #d4a574;
            margin: 1rem 0;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
        }

        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dbfed7 0%, #92a98f 50%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 165, 116, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loading-spinner {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #d4a574;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .tab-content {
                padding: 2rem 1rem;
            }
            
            .datetime-section, .personal-info {
                grid-template-columns: 1fr;
            }
            
            .services-grid, .room-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-tabs .tab {
                padding: 1rem 0.5rem;
                font-size: 0.8rem;
            }

            .health-check-container {
                padding: 1.5rem;
            }

            .health-options {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <button class="back-btn" onclick="window.location.href='index.html'">
            <i class="fas fa-arrow-left"></i> Back to Main
        </button>
        <h1>Book Your Spa Experience</h1>
    </div>

    <div class="booking-container">
        <div class="progress-tabs">
            <button class="tab active" data-step="1">
                <div>Step 1</div>
                <div>Health Check & Services</div>
            </button>
            <button class="tab" data-step="2">
                <div>Step 2</div>
                <div>Date & Therapist</div>
            </button>
            <button class="tab" data-step="3">
                <div>Step 3</div>
                <div>Review & Payment</div>
            </button>
            <button class="tab" data-step="4">
                <div>Step 4</div>
                <div>Confirmation</div>
            </button>
        </div>

        <div class="tab-content">
            <!-- Step 1: Health Check & Services Selection -->
            <div class="form-step active" id="step-1">
                <!-- Health Check Section -->
                <div class="health-check-container">
                    <div class="health-check-title">
                        <i class="fas fa-heartbeat"></i>
                        Health Check
                    </div>
                    <p class="health-check-subtitle">Before confirming your booking, please answer the following:</p>

                    <!-- Question 1: Medical Conditions -->
                    <div class="health-question">
                        <h4>1. Do you have any of the following medical conditions?</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="checkbox" id="heart-condition" name="medicalConditions" value="heart">
                                <label for="heart-condition">Heart Condition / High Blood Pressure</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="diabetes" name="medicalConditions" value="diabetes">
                                <label for="diabetes">Diabetes</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="surgery-injury" name="medicalConditions" value="surgery">
                                <label for="surgery-injury">Recent Surgery / Injury</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="skin-infection" name="medicalConditions" value="skin">
                                <label for="skin-infection">Skin Infection / Open Wounds</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="no-conditions" name="medicalConditions" value="none">
                                <label for="no-conditions">None</label>
                            </div>
                        </div>
                    </div>

                    <!-- Question 2: Pregnancy -->
                    <div class="health-question">
                        <h4>2. Are you pregnant?</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="radio" id="pregnant-yes" name="pregnancy" value="yes">
                                <label for="pregnant-yes">Yes</label>
                            </div>
                            <div class="pregnancy-weeks">
                                <input type="number" id="pregnancy-weeks" placeholder="weeks" min="1" max="42">
                                <label for="pregnancy-weeks"> weeks</label>
                            </div>
                            <div class="health-option">
                                <input type="radio" id="pregnant-no" name="pregnancy" value="no">
                                <label for="pregnant-no">No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Question 3: Allergies -->
                    <div class="health-question">
                        <h4>3. Do you have any allergies?</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="checkbox" id="oil-allergy" name="allergies" value="oils">
                                <label for="oil-allergy">Massage Oils / Lotions</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="essential-allergy" name="allergies" value="essential">
                                <label for="essential-allergy">Essential Oils</label>
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="other-allergy" name="allergies" value="other">
                                <label for="other-allergy">Other:</label>
                            </div>
                            <div class="allergies-other health-text-input">
                                <input type="text" id="other-allergy-text" placeholder="Please specify...">
                            </div>
                            <div class="health-option">
                                <input type="checkbox" id="no-allergies" name="allergies" value="none">
                                <label for="no-allergies">None</label>
                            </div>
                        </div>
                    </div>

                    <!-- Question 4: Current Illness -->
                    <div class="health-question">
                        <h4>4. Are you currently sick (fever, flu, contagious condition)?</h4>
                        <div class="health-options">
                            <div class="health-option">
                                <input type="radio" id="sick-yes" name="currentlyIll" value="yes">
                                <label for="sick-yes">Yes</label>
                            </div>
                            <div class="health-option">
                                <input type="radio" id="sick-no" name="currentlyIll" value="no">
                                <label for="sick-no">No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Health Warning -->
                    <div class="health-warning" id="health-warning">
                        <p><i class="fas fa-exclamation-triangle"></i>
                        <strong>Please note:</strong> Based on your health information, we may need to modify certain treatments or recommend consulting with our wellness coordinator before your appointment. We'll contact you to discuss the best options for your safety and comfort.</p>
                    </div>
                </div>

                <!-- Services Section -->
                <h2 class="step-title">Select Your Service</h2>
                <p class="step-subtitle">Choose the perfect treatment for your relaxation needs</p>
                
                <div class="services-grid">
                    <?php foreach ($services as $service): ?>
                        <div class="service-item" 
                             data-price="<?php echo $service['price']; ?>" 
                             data-duration="<?php echo $service['duration']; ?>"
                             data-type="<?php echo strtolower(str_replace(' ', '_', $service['name'])); ?>">
                            <input type="radio" 
                                   id="service-<?php echo $service['id']; ?>" 
                                   name="service" 
                                   value="<?php echo strtolower(str_replace(' ', '_', $service['name'])); ?>">
                            <label for="service-<?php echo $service['id']; ?>">
                                <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                <p><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="service-price">₱<?php echo number_format($service['price'], 2); ?> 
                                    <span class="service-duration"><?php echo $service['duration']; ?> mins</span>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; color: #333;">Choose Room Type</h3>
                <div class="room-grid">
                    <div class="service-item">
                        <input type="radio" id="single-room" name="roomType" value="single">
                        <label for="single-room">
                            <h3>Single Treatment Room</h3>
                            <p>Private room for individual treatments</p>
                            <div class="service-price">₱600</div>
                        </label>
                    </div>
                    
                    <div class="service-item">
                        <input type="radio" id="couples-room" name="roomType" value="couples">
                        <label for="couples-room">
                            <h3>Couples Room</h3>
                            <p>Side-by-side treatments for two people</p>
                            <div class="service-price">₱1,000</div>
                        </label>
                    </div>
                    
                    <div class="service-item">
                        <input type="radio" id="group-room" name="roomType" value="group">
                        <label for="group-room">
                            <h3>Group Room</h3>
                            <p>Perfect for small groups (3-4 people)</p>
                            <div class="service-price">₱1,750</div>
                        </label>
                    </div>
                    
                    <div class="service-item">
                        <input type="radio" id="vip-suite" name="roomType" value="vip">
                        <label for="vip-suite">
                            <h3>VIP Suite</h3>
                            <p>Luxury suite with premium amenities</p>
                            <div class="service-price">₱2,000</div>
                        </label>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <div></div>
                    <button class="btn btn-primary next-step" data-next="2">Next: Date & Time</button>
                </div>
            </div>

            <!-- Step 2: Date & Time Selection -->
            <div class="form-step" id="step-2">
                <h2 class="step-title">Pick Your Date & Time</h2>
                <p class="step-subtitle">Select your preferred appointment slot</p>
                
                <div class="datetime-section">
                    <div class="date-picker">
                        <h4><i class="fas fa-calendar"></i> Select Date</h4>
                        <input type="date" id="booking-date" required>
                    </div>
                    
                    <div class="time-picker">
                        <h4><i class="fas fa-clock"></i> Available Times</h4>
                        <div class="time-slots" id="time-slots">
                            <!-- Time slots will be generated by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="therapist-section">
                    <h3 style="margin-bottom: 1rem; color: #333;">Select Therapist</h3>
                    <div class="services-grid" id="therapist-grid">
                        <div class="service-item">
                            <input type="radio" id="no-preference" name="therapist" value="no-preference" checked>
                            <label for="no-preference">
                                <h3>No Preference</h3>
                                <p>Let us assign the best available therapist for you</p>
                            </label>
                        </div>
                        <?php foreach ($therapists as $therapist): ?>
                            <div class="service-item">
                                <input type="radio" id="therapist-<?php echo $therapist['id']; ?>" 
                                       name="therapist" value="<?php echo $therapist['id']; ?>">
                                <label for="therapist-<?php echo $therapist['id']; ?>">
                                    <h3><?php echo htmlspecialchars($therapist['name']); ?></h3>
                                    <p>Specialist in <?php echo htmlspecialchars($therapist['specialty']); ?></p>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <button class="btn btn-secondary prev-step" data-prev="1">Previous</button>
                    <button class="btn btn-primary next-step" data-next="3">Next: Review Booking</button>
                </div>
            </div>

            <!-- Step 3: Personal Info & Payment -->
            <div class="form-step" id="step-3">
                <h2 class="step-title">Personal Information & Payment</h2>
                <p class="step-subtitle">Complete your booking details</p>
                
                <div class="summary-card">
                    <h3 style="margin-bottom: 1rem; color: #333;">Booking Summary</h3>
                    <div class="summary-item">
                        <span>Service:</span>
                        <span id="summary-service">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Room Type:</span>
                        <span id="summary-room">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Date & Time:</span>
                        <span id="summary-datetime">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Therapist:</span>
                        <span id="summary-therapist">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Total Price:</span>
                        <span id="summary-price">₱0</span>
                    </div>
                </div>

                <!-- Health Information Summary -->
                <div class="summary-card" style="background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%); border: 1px solid #ffc9c9;">
                    <h3 style="margin-bottom: 1rem; color: #d4a574; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-heartbeat"></i>
                        Health Information Summary
                    </h3>
                    
                    <div class="health-summary-section">
                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Medical Conditions:</h4>
                        <div id="summary-medical-conditions" style="margin-bottom: 1rem; color: #333; font-size: 0.9rem;">-</div>
                        
                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Pregnancy Status:</h4>
                        <div id="summary-pregnancy" style="margin-bottom: 1rem; color: #333; font-size: 0.9rem;">-</div>
                        
                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Allergies:</h4>
                        <div id="summary-allergies" style="margin-bottom: 1rem; color: #333; font-size: 0.9rem;">-</div>
                        
                        <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.95rem;">Current Health Status:</h4>
                        <div id="summary-illness" style="margin-bottom: 0.5rem; color: #333; font-size: 0.9rem;">-</div>
                    </div>
                    
                    <div id="health-alert" style="display: none; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 0.8rem; margin-top: 1rem;">
                        <div style="color: #856404; font-weight: 500; font-size: 0.9rem;">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                            Health Alert: Special considerations may be needed for this client's treatment.
                        </div>
                    </div>
                </div>

                <form id="booking-form">
                    <div class="personal-info">
                        <?php if ($isLoggedIn): ?>
                            <input type="hidden" id="full-name" value="<?php echo htmlspecialchars($userData['name']); ?>">
                            <input type="hidden" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>">
                            <input type="hidden" id="phone" value="<?php echo htmlspecialchars($userData['phone']); ?>">
                            <div class="form-group full-width">
                                <p><strong>Booking as:</strong> <?php echo htmlspecialchars($userData['name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($userData['phone']); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="full-name">Full Name *</label>
                                <input type="text" id="full-name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" required>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin: 2rem 0 1rem 0; color: #333;">Payment Method</h3>
                    <div class="payment-methods">    
                        <div class="payment-item">
                            <input type="radio" id="pay-spa" name="paymentMethod" value="spa">
                            <label for="pay-spa">
                                <i class="fas fa-store"></i>
                                <h4>Pay at Spa</h4>
                                <p>Cash or Card payment on arrival</p>
                            </label>
                        </div>
                    </div>
                </form>

                <div class="navigation-buttons">
                    <button class="btn btn-secondary prev-step" data-prev="2">Previous</button>
                    <button class="btn btn-primary" id="confirm-booking-btn">Confirm Booking</button>
                </div>
            </div>

            <!-- Step 4: Confirmation -->
            <div class="form-step" id="step-4">
                <div class="confirmation-card">
                    <i class="fas fa-check-circle"></i>
                    <h3>Booking Confirmed!</h3>
                    <p>Thank you for choosing Serenity Spa. Your appointment has been successfully booked.</p>
                    <div class="reference-number">
                        Booking Reference: <span id="booking-reference">SPA001234</span>
                    </div>
                </div>

                <div class="summary-card">
                    <h3 style="margin-bottom: 1rem; color: #333;">Your Appointment Details</h3>
                    <div class="summary-item">
                        <span>Service:</span>
                        <span id="final-service">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Room Type:</span>
                        <span id="final-room">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Date & Time:</span>
                        <span id="final-datetime">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Therapist:</span>
                        <span id="final-therapist">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Total Price:</span>
                        <span id="final-price">₱0</span>
                    </div>
                </div>

                <!-- Final Health Information Summary -->
                <div class="summary-card" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd;">
                    <h3 style="margin-bottom: 1rem; color: #0369a1; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-notes-medical"></i>
                        Health Information on File
                    </h3>
                    
                    <div class="health-summary-section">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Medical Conditions:</h4>
                                <div id="final-medical-conditions" style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">-</div>
                            </div>
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Allergies:</h4>
                                <div id="final-allergies" style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">-</div>
                            </div>
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Pregnancy:</h4>
                                <div id="final-pregnancy" style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">-</div>
                            </div>
                            <div>
                                <h4 style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">Health Status:</h4>
                                <div id="final-illness" style="margin-bottom: 1rem; color: #333; font-size: 0.85rem;">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin: 2rem 0;">
                    <p><strong>Confirmation Details:</strong></p>
                    <p>A confirmation email has been sent to: <span id="confirmation-email">-</span></p>
                    <p style="margin-top: 1rem; color: #666;">
                        Please arrive 15 minutes before your appointment time.<br>
                        For any changes or cancellations, please call us at (555) 123-4567.
                    </p>
                </div>

                <div class="navigation-buttons" style="justify-content: center;">
                    <button class="btn btn-primary" onclick="window.location.href='index.html'">
                        Return to Main Page
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Processing your booking...</p>
        </div>
    </div>

    <!-- Right before closing body tag -->
    <script>
        // Spa Booking System JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.booking-container')) {
                console.log('Initializing booking system...');
                window.spaBooking = new SpaBookingSystem();
            } else {
                console.log('Booking container not found, skipping initialization');
            }
        });
        
        class SpaBookingSystem {
            constructor() {
                this.currentStep = 1;
                this.totalSteps = 4;
                this.bookingData = {
                    healthInfo: {
                        medicalConditions: [],
                        pregnancy: '',
                        pregnancyWeeks: '',
                        allergies: [],
                        otherAllergy: '',
                        currentlyIll: ''
                    },
                    service: {},
                    roomType: '',
                    dateTime: {},
                    therapist: {},
                    personalInfo: {},
                    paymentMethod: '',
                    totalPrice: 0
                };
                
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.setupDatePicker();
                this.generateTimeSlots();
                this.updateProgressTabs();
                this.setupHealthCheckLogic();
            }

            setupHealthCheckLogic() {
                // Handle "None" checkbox exclusivity for medical conditions
                const noneCondition = document.getElementById('no-conditions');
                const medicalCheckboxes = document.querySelectorAll('input[name="medicalConditions"]:not(#no-conditions)');
                
                noneCondition.addEventListener('change', () => {
                    if (noneCondition.checked) {
                        medicalCheckboxes.forEach(cb => cb.checked = false);
                    }
                });

                medicalCheckboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        if (cb.checked) {
                            noneCondition.checked = false;
                        }
                        this.checkHealthWarnings();
                    });
                });

                // Handle "None" checkbox exclusivity for allergies
                const noneAllergy = document.getElementById('no-allergies');
                const allergyCheckboxes = document.querySelectorAll('input[name="allergies"]:not(#no-allergies)');
                
                noneAllergy.addEventListener('change', () => {
                    if (noneAllergy.checked) {
                        allergyCheckboxes.forEach(cb => cb.checked = false);
                        document.querySelector('.allergies-other').style.display = 'none';
                    }
                });

                allergyCheckboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        if (cb.checked) {
                            noneAllergy.checked = false;
                        }
                    });
                });

                // Handle "Other" allergy text input
                const otherAllergyCheckbox = document.getElementById('other-allergy');
                const otherAllergyText = document.querySelector('.allergies-other');
                
                otherAllergyCheckbox.addEventListener('change', () => {
                    otherAllergyText.style.display = otherAllergyCheckbox.checked ? 'block' : 'none';
                });

                // Handle pregnancy weeks input
                const pregnantYes = document.getElementById('pregnant-yes');
                const pregnancyWeeks = document.querySelector('.pregnancy-weeks');
                
                pregnantYes.addEventListener('change', () => {
                    pregnancyWeeks.style.display = pregnantYes.checked ? 'block' : 'none';
                    this.checkHealthWarnings();
                });

                document.getElementById('pregnant-no').addEventListener('change', () => {
                    pregnancyWeeks.style.display = 'none';
                });

                // Handle illness check
                document.getElementById('sick-yes').addEventListener('change', () => {
                    this.checkHealthWarnings();
                });

                document.getElementById('sick-no').addEventListener('change', () => {
                    this.checkHealthWarnings();
                });
            }

            checkHealthWarnings() {
                const warning = document.getElementById('health-warning');
                let showWarning = false;

                // Check for conditions that require warning
                const heartCondition = document.getElementById('heart-condition').checked;
                const diabetes = document.getElementById('diabetes').checked;
                const surgery = document.getElementById('surgery-injury').checked;
                const skinInfection = document.getElementById('skin-infection').checked;
                const pregnant = document.getElementById('pregnant-yes').checked;
                const sick = document.getElementById('sick-yes').checked;

                if (heartCondition || diabetes || surgery || skinInfection || pregnant || sick) {
                    showWarning = true;
                }

                warning.classList.toggle('show', showWarning);
            }

            setupEventListeners() {
                // Navigation buttons
                document.querySelectorAll('.next-step').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const nextStep = parseInt(e.target.dataset.next);
                        if (this.validateCurrentStep()) {
                            this.goToStep(nextStep);
                        }
                    });
                });

                document.querySelectorAll('.prev-step').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const prevStep = parseInt(e.target.dataset.prev);
                        this.goToStep(prevStep);
                    });
                });

                // Confirm booking button
                const confirmBookingBtn = document.getElementById('confirm-booking-btn');
                if (confirmBookingBtn) {
                    confirmBookingBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (this.validateCurrentStep()) {
                            this.confirmBooking();
                        }
                    });
                }

                // Tab navigation
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        const stepNumber = parseInt(tab.dataset.step);
                        if (stepNumber <= this.currentStep || stepNumber === this.currentStep + 1) {
                            if (stepNumber === this.currentStep + 1) {
                                if (this.validateCurrentStep()) {
                                    this.goToStep(stepNumber);
                                }
                            } else {
                                this.goToStep(stepNumber);
                            }
                        }
                    });
                });

                // Service selection
                document.querySelectorAll('.service-item input[name="service"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handleServiceSelection(radio);
                        this.updateBookingSummary();
                    });
                });

                // Room type selection
                document.querySelectorAll('.service-item input[name="roomType"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handleRoomSelection(radio);
                        this.updateBookingSummary();
                    });
                });

                // Therapist selection
                document.querySelectorAll('input[name="therapist"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handleTherapistSelection(radio);
                    });
                });

                // Payment method selection
                document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handlePaymentMethodSelection(radio);
                    });
                });

                // Date selection
                document.getElementById('booking-date').addEventListener('change', (e) => {
                    this.handleDateChange(e.target.value);
                });

                this.setupFormValidation();
            }

            setupFormValidation() {
                const inputs = document.querySelectorAll('input[required]');
                inputs.forEach(input => {
                    input.addEventListener('blur', () => this.validateInput(input));
                    input.addEventListener('input', () => this.clearValidationError(input));
                });
            }

            validateInput(input) {
                const value = input.value.trim();
                let isValid = true;
                let errorMessage = '';

                this.clearValidationError(input);

                if (!value && input.required) {
                    isValid = false;
                    errorMessage = 'This field is required';
                } else if (input.type === 'email' && value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid email address';
                    }
                } else if (input.type === 'tel' && value) {
                    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
                    if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
                        isValid = false;
                        errorMessage = 'Please enter a valid phone number';
                    }
                }

                if (!isValid) {
                    this.showValidationError(input, errorMessage);
                }

                return isValid;
            }

            showValidationError(input, message) {
                input.style.borderColor = '#dc3545';
                input.style.backgroundColor = '#fff5f5';
                
                const existingError = input.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }

                const errorElement = document.createElement('div');
                errorElement.className = 'error-message';
                errorElement.style.color = '#dc3545';
                errorElement.style.fontSize = '0.875rem';
                errorElement.style.marginTop = '0.25rem';
                errorElement.textContent = message;
                input.parentNode.appendChild(errorElement);
            }

            clearValidationError(input) {
                input.style.borderColor = '#e1e5e9';
                input.style.backgroundColor = '';
                
                const errorMessage = input.parentNode.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.remove();
                }
            }

            validateCurrentStep() {
                let isValid = true;

                switch (this.currentStep) {
                    case 1:
                        // Validate health check
                        this.collectHealthInfo();
                        
                        // Validate service and room selection
                        const selectedService = document.querySelector('input[name="service"]:checked');
                        const selectedRoom = document.querySelector('input[name="roomType"]:checked');
                        
                        if (!selectedService) {
                            this.showAlert('Please select a service', 'error');
                            isValid = false;
                        }
                        if (!selectedRoom) {
                            this.showAlert('Please select a room type', 'error');
                            isValid = false;
                        }
                        break;

                    case 2:
                        // Validate date, time and therapist
                        const selectedDate = document.getElementById('booking-date').value;
                        const selectedTime = document.querySelector('.time-slot.selected');
                        const selectedTherapist = document.querySelector('input[name="therapist"]:checked');

                        if (!selectedDate) {
                            this.showAlert('Please select a date', 'error');
                            isValid = false;
                        }
                        if (!selectedTime) {
                            this.showAlert('Please select a time slot', 'error');
                            isValid = false;
                        }
                        if (!selectedTherapist) {
                            this.showAlert('Please select a therapist preference', 'error');
                            isValid = false;
                        }

                        if (isValid) {
                            this.bookingData.dateTime = {
                                date: selectedDate,
                                time: selectedTime.textContent,
                                formattedDate: this.formatDate(selectedDate)
                            };
                        }
                        break;

                    case 3:
                        // Validate personal info and payment method
                        if (!document.querySelector('.payment-item input[name="paymentMethod"]:checked')) {
                            this.showAlert('Please select a payment method', 'error');
                            isValid = false;
                        }

                        // Check if user is logged in, if not validate personal info fields
                        if (!<?php echo $isLoggedIn ? 'true' : 'false'; ?>) {
                            const requiredFields = ['full-name', 'email', 'phone'];
                            requiredFields.forEach(fieldId => {
                                const input = document.getElementById(fieldId);
                                if (input && !input.value.trim()) {
                                    this.showValidationError(input, 'This field is required');
                                    isValid = false;
                                }
                            });
                        }

                        if (isValid) {
                            // Get personal info from hidden fields if logged in, otherwise from form
                            this.bookingData.personalInfo = {
                                fullName: document.getElementById('full-name').value.trim(),
                                email: document.getElementById('email').value.trim(),
                                phone: document.getElementById('phone').value.trim()
                            };
                            this.bookingData.paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
                        }
                        break;
                }

                return isValid;
            }

            collectHealthInfo() {
                // Collect medical conditions
                const medicalConditions = [];
                document.querySelectorAll('input[name="medicalConditions"]:checked').forEach(cb => {
                    medicalConditions.push(cb.value);
                });

                // Collect pregnancy info
                const pregnancyRadio = document.querySelector('input[name="pregnancy"]:checked');
                const pregnancyWeeks = document.getElementById('pregnancy-weeks').value;

                // Collect allergies
                const allergies = [];
                document.querySelectorAll('input[name="allergies"]:checked').forEach(cb => {
                    allergies.push(cb.value);
                });
                const otherAllergy = document.getElementById('other-allergy-text').value;

                // Collect current illness status
                const currentlyIllRadio = document.querySelector('input[name="currentlyIll"]:checked');

                this.bookingData.healthInfo = {
                    medicalConditions: medicalConditions,
                    pregnancy: pregnancyRadio ? pregnancyRadio.value : '',
                    pregnancyWeeks: pregnancyWeeks,
                    allergies: allergies,
                    otherAllergy: otherAllergy,
                    currentlyIll: currentlyIllRadio ? currentlyIllRadio.value : ''
                };
            }

            goToStep(stepNumber) {
                if (stepNumber < 1 || stepNumber > this.totalSteps) return;

                // Hide current step
                const currentStepElement = document.getElementById(`step-${this.currentStep}`);
                if (currentStepElement) {
                    currentStepElement.classList.remove('active');
                }

                // Show new step
                const newStepElement = document.getElementById(`step-${stepNumber}`);
                if (newStepElement) {
                    newStepElement.classList.add('active');
                }

                // Update current step
                this.currentStep = stepNumber;

                // Update progress tabs
                this.updateProgressTabs();

                // Update final summary if on step 4
                if (stepNumber === 4) {
                    this.updateFinalSummary();
                }

                // Scroll to top of form
                document.querySelector('.booking-container').scrollIntoView({ behavior: 'smooth' });
            }

            updateProgressTabs() {
                document.querySelectorAll('.tab').forEach(tab => {
                    const stepNumber = parseInt(tab.dataset.step);
                    tab.classList.remove('active', 'completed');

                    if (stepNumber < this.currentStep) {
                        tab.classList.add('completed');
                    } else if (stepNumber === this.currentStep) {
                        tab.classList.add('active');
                    }
                });
            }

            handleServiceSelection(radio) {
                // Remove selection styling from all service items
                document.querySelectorAll('input[name="service"]').forEach(r => {
                    r.closest('.service-item').classList.remove('selected');
                });

                // Add selection styling to the selected item
                const serviceItem = radio.closest('.service-item');
                if (serviceItem) {
                    serviceItem.classList.add('selected');
                    
                    const serviceName = serviceItem.querySelector('h3').textContent;
                    const servicePrice = parseFloat(serviceItem.dataset.price);
                    const serviceDuration = parseInt(serviceItem.dataset.duration);

                    this.bookingData.service = {
                        type: serviceName.toLowerCase().replace(/\s+/g, '_'),
                        name: serviceName,
                        price: servicePrice,
                        duration: serviceDuration
                    };
                }
            }

            handleRoomSelection(radio) {
                // Remove selection styling from all room items
                document.querySelectorAll('input[name="roomType"]').forEach(r => {
                    r.closest('.service-item').classList.remove('selected');
                });

                // Add selection styling to the selected item
                const roomItem = radio.closest('.service-item');
                if (roomItem) {
                    roomItem.classList.add('selected');
                }
                
                this.bookingData.roomType = radio.value;
            }

            handleTherapistSelection(radio) {
                const therapistItem = radio.closest('.service-item');
                document.querySelectorAll('input[name="therapist"]').forEach(item => {
                    item.closest('.service-item').classList.remove('selected');
                });
                therapistItem.classList.add('selected');
                
                this.bookingData.therapist = {
                    id: radio.value,
                    name: radio.value === 'no-preference' ? 'No Preference' : therapistItem.querySelector('h3').textContent
                };
                this.updateBookingSummary();
            }

            handlePaymentMethodSelection(radio) {
                const paymentItem = radio.closest('.payment-item');
                document.querySelectorAll('input[name="paymentMethod"]').forEach(item => {
                    item.closest('.payment-item').classList.remove('selected');
                });
                paymentItem.classList.add('selected');
                this.bookingData.paymentMethod = radio.value;
            }

            setupDatePicker() {
                const dateInput = document.getElementById('booking-date');
                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);

                dateInput.min = tomorrow.toISOString().split('T')[0];

                const maxDate = new Date(today);
                maxDate.setMonth(maxDate.getMonth() + 3);
                dateInput.max = maxDate.toISOString().split('T')[0];
            }

            generateTimeSlots() {
                const timeSlotsContainer = document.getElementById('time-slots');
                if (!timeSlotsContainer) return;
                
                const timeSlots = [
                    '9:00 AM', '9:30 AM', '10:00 AM', '10:30 AM',
                    '11:00 AM', '11:30 AM', '1:00 PM', '1:30 PM',
                    '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM',
                    '4:00 PM', '4:30 PM', '5:00 PM', '5:30 PM',
                    '6:00 PM', '6:30 PM', '7:00 PM', '7:30 PM'
                ];

                timeSlotsContainer.innerHTML = '';

                timeSlots.forEach(time => {
                    const timeSlot = document.createElement('div');
                    timeSlot.className = 'time-slot';
                    timeSlot.textContent = time;
                    timeSlot.addEventListener('click', () => this.selectTimeSlot(timeSlot));
                    timeSlotsContainer.appendChild(timeSlot);
                });
            }

            selectTimeSlot(timeSlot) {
                if (timeSlot.classList.contains('unavailable')) return;

                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.classList.remove('selected');
                });

                timeSlot.classList.add('selected');
            }

            convertTo12Hour(time24) {
    // Convert 24-hour time (e.g., "14:30:00") to 12-hour format (e.g., "2:30 PM")
    if (!time24) return '';
    
    const [hours, minutes] = time24.split(':');
    const hour12 = parseInt(hours) % 12 || 12;
    const period = parseInt(hours) >= 12 ? 'PM' : 'AM';
    const minuteStr = minutes === '00' ? '' : `:${minutes}`;
    return `${hour12}${minuteStr} ${period}`;
}

            async handleDateChange(selectedDate) {
                if (!selectedDate) return;
                
                // Reset time slot selection
                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.classList.remove('selected', 'unavailable');
                });
                
                // Fetch booked time slots for the selected date
                await this.updateTimeSlotAvailability(selectedDate);
                this.updateBookingSummary();
            }

            async updateTimeSlotAvailability(date) {
    try {
        // Fetch booked appointments for the selected date
        const response = await fetch('check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ date: date })
        });
        
        if (!response.ok) {
            console.error('Server returned error:', response.status, response.statusText);
            // Don't mark any slots as unavailable if server error
            return;
        }
        
        const responseText = await response.text();
        console.log('Server response:', responseText);
        
        let bookedSlots;
        try {
            bookedSlots = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse server response:', parseError);
            console.error('Response was:', responseText);
            // Don't mark any slots as unavailable if JSON parse fails
            return;
        }
        
        // Ensure bookedSlots is an array
        if (!Array.isArray(bookedSlots)) {
            console.error('Expected array but received:', typeof bookedSlots, bookedSlots);
            // If server returned an error object instead of array
            if (bookedSlots && bookedSlots.error) {
                console.error('Server error:', bookedSlots.message);
                this.showAlert('Unable to check availability: ' + bookedSlots.message, 'error');
            }
            // Don't mark any slots as unavailable
            return;
        }
        
        // Mark booked time slots as unavailable
        const timeSlots = document.querySelectorAll('.time-slot');
        timeSlots.forEach(slot => {
            const slotTime = slot.textContent.trim();
            
            // Check if this time slot is booked
            const isBooked = bookedSlots.some(booking => {
                if (!booking || !booking.booking_time) {
                    console.warn('Invalid booking object:', booking);
                    return false;
                }
                return this.convertTo12Hour(booking.booking_time) === slotTime;
            });
            
            if (isBooked) {
                slot.classList.add('unavailable');
                slot.title = 'This time slot is not available';
            } else {
                slot.classList.remove('unavailable');
                slot.title = '';
            }
        });
        
    } catch (error) {
        console.error('Error fetching availability:', error);
        this.showAlert('Unable to check time slot availability. Please try selecting a different date.', 'error');
        // If there's a network error, don't mark any slots as unavailable
    }
}

// Also update the confirmBooking method to handle the same issue
async confirmBooking() {
    if (!this.validateCurrentStep()) {
        return;
    }

    this.showLoadingOverlay();

    try {
        // First, double-check availability before booking
        const availabilityCheck = await fetch('check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                date: this.bookingData.dateTime.date,
                time: this.bookingData.dateTime.time 
            })
        });
        
        if (availabilityCheck.ok) {
            const responseText = await availabilityCheck.text();
            
            let availabilityData;
            try {
                availabilityData = JSON.parse(responseText);
            } catch (parseError) {
                console.warn('Could not parse availability response, proceeding with booking');
                availabilityData = null;
            }
            
            // Only check availability if we got valid data
            if (availabilityData) {
                // If checking specific time availability
                if (availabilityData.hasOwnProperty('available')) {
                    if (!availabilityData.available || availabilityData.booked) {
                        throw new Error('Sorry, this time slot has just been booked by another customer. Please select a different time.');
                    }
                }
                // If checking all booked slots for the date
                else if (Array.isArray(availabilityData)) {
                    const isTimeSlotTaken = availabilityData.some(booking => {
                        if (!booking || !booking.booking_time) return false;
                        return this.convertTo12Hour(booking.booking_time) === this.bookingData.dateTime.time;
                    });
                    
                    if (isTimeSlotTaken) {
                        throw new Error('Sorry, this time slot has just been booked by another customer. Please select a different time.');
                    }
                }
            }
        } else {
            console.warn('Availability check failed, proceeding with booking attempt');
        }

        // Collect all final data including health information
        const bookingData = {
            fullName: this.bookingData.personalInfo.fullName,
            email: this.bookingData.personalInfo.email,
            phone: this.bookingData.personalInfo.phone,
            serviceType: this.bookingData.service.type,
            serviceName: this.bookingData.service.name,
            servicePrice: this.bookingData.service.price,
            serviceDuration: this.bookingData.service.duration,
            bookingDate: this.bookingData.dateTime.date,
            bookingTime: this.bookingData.dateTime.time,
            roomType: this.bookingData.roomType,
            therapistId: this.bookingData.therapist.id,
            paymentMethod: this.bookingData.paymentMethod,
            healthInfo: this.bookingData.healthInfo
        };

        console.log('Sending booking data:', bookingData);

        const response = await fetch('process_booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(bookingData)
        });

        const rawResponse = await response.text();
        console.log('Raw server response:', rawResponse);

        let result;
        try {
            result = JSON.parse(rawResponse);
        } catch (e) {
            console.error('Failed to parse server response:', rawResponse);
            throw new Error('Server returned invalid response. Please try again.');
        }

        if (!response.ok) {
            throw new Error(result.message || `Server returned error ${response.status}`);
        }
        
        if (result.success) {
            this.bookingData.reference = result.bookingReference;
            this.processBookingConfirmation(result.bookingReference);
            this.goToStep(4);
            this.showAlert('Booking confirmed successfully!', 'success');
        } else {
            throw new Error(result.message || 'Failed to process booking');
        }
        
    } catch (error) {
        console.error('Booking error:', error);
        this.showAlert(error.message || 'Error processing booking', 'error');
        
        // If there was a booking conflict, refresh the time slots
        if (error.message.includes('time slot has just been booked')) {
            await this.updateTimeSlotAvailability(this.bookingData.dateTime.date);
        }
    } finally {
        this.hideLoadingOverlay();
    }
}

            processBookingConfirmation(bookingReference) {
                const refElement = document.getElementById('booking-reference');
                const emailElement = document.getElementById('confirmation-email');
                
                if (refElement) {
                    refElement.textContent = bookingReference;
                }
                if (emailElement) {
                    emailElement.textContent = this.bookingData.personalInfo.email;
                }
            }

            updateFinalSummary() {
                document.getElementById('final-service').textContent = this.bookingData.service.name || '-';
                document.getElementById('final-room').textContent = this.getRoomDisplayName() || '-';
                document.getElementById('final-datetime').textContent = 
                    `${this.bookingData.dateTime.formattedDate} at ${this.bookingData.dateTime.time}` || '-';
                document.getElementById('final-therapist').textContent = this.bookingData.therapist.name || '-';
                document.getElementById('final-price').textContent = `${this.bookingData.totalPrice}`;
                
                // Update final health summary
                this.updateFinalHealthSummary();
            }

            updateFinalHealthSummary() {
                const healthInfo = this.bookingData.healthInfo;
                
                // Medical Conditions
                const finalMedicalElement = document.getElementById('final-medical-conditions');
                if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                    const conditions = healthInfo.medicalConditions.map(condition => {
                        switch (condition) {
                            case 'heart': return 'Heart Condition / High Blood Pressure';
                            case 'diabetes': return 'Diabetes';
                            case 'surgery': return 'Recent Surgery / Injury';
                            case 'skin': return 'Skin Infection / Open Wounds';
                            case 'none': return 'None reported';
                            default: return condition;
                        }
                    });
                    finalMedicalElement.textContent = conditions.join(', ');
                } else {
                    finalMedicalElement.textContent = 'None reported';
                }

                // Pregnancy Status
                const finalPregnancyElement = document.getElementById('final-pregnancy');
                if (healthInfo.pregnancy === 'yes') {
                    const weeks = healthInfo.pregnancyWeeks ? ` (${healthInfo.pregnancyWeeks} weeks)` : '';
                    finalPregnancyElement.textContent = `Yes${weeks}`;
                } else if (healthInfo.pregnancy === 'no') {
                    finalPregnancyElement.textContent = 'No';
                } else {
                    finalPregnancyElement.textContent = 'Not specified';
                }

                // Allergies
                const finalAllergiesElement = document.getElementById('final-allergies');
                if (healthInfo.allergies && healthInfo.allergies.length > 0) {
                    const allergies = healthInfo.allergies.map(allergy => {
                        switch (allergy) {
                            case 'oils': return 'Massage Oils / Lotions';
                            case 'essential': return 'Essential Oils';
                            case 'other': return healthInfo.otherAllergy ? `Other: ${healthInfo.otherAllergy}` : 'Other (not specified)';
                            case 'none': return 'None reported';
                            default: return allergy;
                        }
                    });
                    finalAllergiesElement.textContent = allergies.join(', ');
                } else {
                    finalAllergiesElement.textContent = 'None reported';
                }

                // Current Illness
                const finalIllnessElement = document.getElementById('final-illness');
                if (healthInfo.currentlyIll === 'yes') {
                    finalIllnessElement.textContent = 'Currently experiencing illness symptoms';
                    finalIllnessElement.style.color = '#dc3545';
                } else if (healthInfo.currentlyIll === 'no') {
                    finalIllnessElement.textContent = 'No current illness reported';
                    finalIllnessElement.style.color = '#28a745';
                } else {
                    finalIllnessElement.textContent = 'Not specified';
                }
            }

            formatDate(dateString) {
                const date = new Date(dateString);
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                return date.toLocaleDateString('en-US', options);
            }

            showLoadingOverlay() {
                const overlay = document.getElementById('loading-overlay');
                if (overlay) {
                    overlay.style.display = 'flex';
                }
            }

            hideLoadingOverlay() {
                const overlay = document.getElementById('loading-overlay');
                if (overlay) {
                    overlay.style.display = 'none';
                }
            }

            showAlert(message, type = 'info') {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 1rem 1.5rem;
                    border-radius: 8px;
                    color: white;
                    font-weight: 500;
                    z-index: 10000;
                    min-width: 300px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                `;

                switch (type) {
                    case 'error':
                        alert.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
                        break;
                    case 'success':
                        alert.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                        break;
                    default:
                        alert.style.background = 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)';
                }

                alert.textContent = message;
                document.body.appendChild(alert);

                setTimeout(() => {
                    alert.style.transform = 'translateX(0)';
                }, 100);

                setTimeout(() => {
                    alert.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 4000);
            }

            updateBookingSummary() {
                const selectedService = document.querySelector('input[name="service"]:checked');
                const selectedRoom = document.querySelector('input[name="roomType"]:checked');

                if (selectedService) {
                    document.getElementById('summary-service').textContent = this.bookingData.service.name;
                }

                if (selectedRoom) {
                    document.getElementById('summary-room').textContent = this.getRoomDisplayName();
                }

                if (this.bookingData.dateTime.date && this.bookingData.dateTime.time) {
                    document.getElementById('summary-datetime').textContent = 
                        `${this.bookingData.dateTime.formattedDate} at ${this.bookingData.dateTime.time}`;
                }

                if (this.bookingData.therapist.name) {
                    document.getElementById('summary-therapist').textContent = this.bookingData.therapist.name;
                }

                // Calculate total price
                this.calculateTotalPrice();
                document.getElementById('summary-price').textContent = `${this.bookingData.totalPrice}`;
                
                // Update health information summary
                this.updateHealthSummary();
            }

            updateHealthSummary() {
                // Ensure health info is collected
                if (!this.bookingData.healthInfo || Object.keys(this.bookingData.healthInfo).length === 0) {
                    this.collectHealthInfo();
                }

                const healthInfo = this.bookingData.healthInfo;
                
                // Medical Conditions
                const medicalElement = document.getElementById('summary-medical-conditions');
                if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                    const conditions = healthInfo.medicalConditions.map(condition => {
                        switch (condition) {
                            case 'heart': return 'Heart Condition / High Blood Pressure';
                            case 'diabetes': return 'Diabetes';
                            case 'surgery': return 'Recent Surgery / Injury';
                            case 'skin': return 'Skin Infection / Open Wounds';
                            case 'none': return 'None reported';
                            default: return condition;
                        }
                    });
                    medicalElement.textContent = conditions.join(', ');
                } else {
                    medicalElement.textContent = 'None reported';
                }

                // Pregnancy Status
                const pregnancyElement = document.getElementById('summary-pregnancy');
                if (healthInfo.pregnancy === 'yes') {
                    const weeks = healthInfo.pregnancyWeeks ? ` (${healthInfo.pregnancyWeeks} weeks)` : '';
                    pregnancyElement.textContent = `Yes${weeks}`;
                } else if (healthInfo.pregnancy === 'no') {
                    pregnancyElement.textContent = 'No';
                } else {
                    pregnancyElement.textContent = 'Not specified';
                }

                // Allergies
                const allergiesElement = document.getElementById('summary-allergies');
                if (healthInfo.allergies && healthInfo.allergies.length > 0) {
                    const allergies = healthInfo.allergies.map(allergy => {
                        switch (allergy) {
                            case 'oils': return 'Massage Oils / Lotions';
                            case 'essential': return 'Essential Oils';
                            case 'other': return healthInfo.otherAllergy ? `Other: ${healthInfo.otherAllergy}` : 'Other (not specified)';
                            case 'none': return 'None reported';
                            default: return allergy;
                        }
                    });
                    allergiesElement.textContent = allergies.join(', ');
                } else {
                    allergiesElement.textContent = 'None reported';
                }

                // Current Illness
                const illnessElement = document.getElementById('summary-illness');
                if (healthInfo.currentlyIll === 'yes') {
                    illnessElement.textContent = 'Currently experiencing illness symptoms';
                    illnessElement.style.color = '#dc3545';
                    illnessElement.style.fontWeight = '600';
                } else if (healthInfo.currentlyIll === 'no') {
                    illnessElement.textContent = 'No current illness reported';
                    illnessElement.style.color = '#28a745';
                } else {
                    illnessElement.textContent = 'Not specified';
                }

                // Show health alert if needed
                this.showHealthAlert();
            }

            showHealthAlert() {
                const healthAlert = document.getElementById('health-alert');
                const healthInfo = this.bookingData.healthInfo;
                let showAlert = false;

                // Check for conditions that require special attention
                if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                    const concerningConditions = ['heart', 'diabetes', 'surgery', 'skin'];
                    showAlert = healthInfo.medicalConditions.some(condition => concerningConditions.includes(condition));
                }

                if (healthInfo.pregnancy === 'yes') {
                    showAlert = true;
                }

                if (healthInfo.currentlyIll === 'yes') {
                    showAlert = true;
                }

                if (healthInfo.allergies && healthInfo.allergies.length > 0 && !healthInfo.allergies.includes('none')) {
                    showAlert = true;
                }

                healthAlert.style.display = showAlert ? 'block' : 'none';
            }

            getRoomDisplayName() {
                const roomNames = {
                    'single': 'Single Treatment Room',
                    'couples': 'Couples Room',
                    'group': 'Group Room',
                    'vip': 'VIP Suite'
                };
                return roomNames[this.bookingData.roomType] || '';
            }

            calculateTotalPrice() {
                let totalPrice = this.bookingData.service.price || 0;
                
                // Add room surcharge
                switch (this.bookingData.roomType) {
                    case 'single': totalPrice += 600; break;
                    case 'couples': totalPrice += 1000; break;
                    case 'group': totalPrice += 1750; break;
                    case 'vip': totalPrice += 2000; break;
                }
                
                this.bookingData.totalPrice = `₱${totalPrice.toLocaleString()}`;
            }
        }
    </script>
</body>
</html>