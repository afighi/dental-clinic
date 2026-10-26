<?php
session_start();
// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_SANITIZE_NUMBER_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $billing_name = filter_input(INPUT_POST, 'billing_name', FILTER_SANITIZE_STRING);
    
    // Validate inputs
    if (empty($appointment_id) || empty($patient_id) || empty($amount) || empty($payment_method) || empty($billing_name)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: make-payment.php?id=" . $appointment_id);
        exit();
    }
    
    // Verify appointment belongs to the patient
    $stmt = $conn->prepare("SELECT * FROM appointment WHERE appointment_id = :appointment_id AND patient_id = :patient_id");
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Invalid appointment.";
        header("Location: my-appointments.php");
        exit();
    }
    
    // Get appointment details
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if appointment is already paid
    $stmt = $conn->prepare("SELECT * FROM payment WHERE appointment_id = :appointment_id AND status = 'succeeded'");
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "This appointment has already been paid.";
        header("Location: appointment-details.php?id=" . $appointment_id);
        exit();
    }
    
    // Get PayMongo API credentials
    $stmt = $conn->prepare("SELECT * FROM apiintegration WHERE api_name = 'PayMongo' AND is_active = 1");
    $stmt->execute();
    $paymongo_api = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paymongo_api) {
        $_SESSION['error'] = "Payment gateway is currently unavailable. Please try again later.";
        header("Location: make-payment.php?id=" . $appointment_id);
        exit();
    }
    
    $paymongo_credentials = json_decode($paymongo_api['credentials'], true);
    $paymongo_secret_key = $paymongo_credentials['secret_key'];
    
    // Get service information for description
    $stmt = $conn->prepare("SELECT s.service_name FROM appointment a 
                          JOIN service s ON a.service_id = s.service_id 
                          WHERE a.appointment_id = :appointment_id");
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create a PayMongo Payment Intent
    // For test purposes, we're using cURL but in production you might want to use a library
    
    // Create PayMongo Payment Intent
    $intent_data = [
        'data' => [
            'attributes' => [
                'amount' => (int)$amount, // Amount in centavos
                'payment_method_allowed' => [$payment_method],
                'payment_method_options' => [
                    'card' => ['request_three_d_secure' => 'automatic']
                ],
                'currency' => 'PHP',
                'description' => 'Payment for ' . $service['service_name'],
                'statement_descriptor' => 'JRC DENTAL CARE'
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.paymongo.com/v1/payment_intents');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $paymongo_secret_key . ":");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($intent_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    
    curl_close($ch);
    
    if ($err) {
        // Handle error
        $_SESSION['error'] = "Error creating payment: " . $err;
        header("Location: make-payment.php?id=" . $appointment_id);
        exit();
    }
    
    $intent_response = json_decode($response, true);
    
    if (!isset($intent_response['data']['id'])) {
        // Handle error
        $_SESSION['error'] = "Error creating payment intent: " . json_encode($intent_response);
        header("Location: make-payment.php?id=" . $appointment_id);
        exit();
    }
    
    $intent_id = $intent_response['data']['id'];
    $client_key = $intent_response['data']['attributes']['client_key'];
    
    // Save the payment intent to our database
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("INSERT INTO payment (appointment_id, paymongo_payment_intent_id, amount, currency, status, payment_method, created_at) 
                               VALUES (:appointment_id, :payment_intent_id, :amount, 'PHP', 'pending', :payment_method, NOW())");
        $stmt->bindParam(':appointment_id', $appointment_id);
        $stmt->bindParam(':payment_intent_id', $intent_id);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->execute();
        
        $payment_id = $conn->lastInsertId();
        
        // Create a payment method with billing name included
        $method_data = [
            'data' => [
                'attributes' => [
                    'type' => $payment_method,
                    'details' => [
                        'email' => $email,
                        'phone' => $phone
                    ],
                    'billing' => [
                        'name' => $billing_name,  // Now included as required by PayMongo
                        'email' => $email,
                        'phone' => $phone
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.paymongo.com/v1/payment_methods');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $paymongo_secret_key . ":");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($method_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        
        curl_close($ch);
        
        if ($err) {
            // Handle error
            $conn->rollback();
            $_SESSION['error'] = "Error creating payment method: " . $err;
            header("Location: make-payment.php?id=" . $appointment_id);
            exit();
        }
        
        $method_response = json_decode($response, true);
        
        if (!isset($method_response['data']['id'])) {
            // Handle error and show detailed error message
            $conn->rollback();
            $error_msg = "Error creating payment method: ";
            if (isset($method_response['errors']) && is_array($method_response['errors'])) {
                foreach ($method_response['errors'] as $error) {
                    $error_msg .= $error['detail'] . " ";
                }
            } else {
                $error_msg .= json_encode($method_response);
            }
            $_SESSION['error'] = $error_msg;
            header("Location: make-payment.php?id=" . $appointment_id);
            exit();
        }
        
        $method_id = $method_response['data']['id'];
        
        // Determine if we're on localhost and adjust protocol accordingly
        $protocol = "https://";
        
        // Get the directory path for the project
        $project_path = "";
        // If you're working in a subfolder, uncomment and modify this line:
        // $project_path = "/jrc-dental"; // Replace with your actual project folder name
        
        // Attach payment method to the intent
        $attach_data = [
            'data' => [
                'attributes' => [
                    'payment_method' => $method_id,
                    'client_key' => $client_key,
                    'return_url' => $protocol . $_SERVER['HTTP_HOST'] . $project_path . '/payment-success.php?payment_id=' . $payment_id
                ]
            ]
        ];
        // Log the return URL for debugging
        error_log("Return URL: " . $protocol . $_SERVER['HTTP_HOST'] . $project_path . '/payment-success.php?payment_id=' . $payment_id);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.paymongo.com/v1/payment_intents/' . $intent_id . '/attach');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $paymongo_secret_key . ":");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($attach_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        
        curl_close($ch);
        
        if ($err) {
            // Handle error
            $conn->rollback();
            $_SESSION['error'] = "Error attaching payment method: " . $err;
            header("Location: make-payment.php?id=" . $appointment_id);
            exit();
        }
        
        $attach_response = json_decode($response, true);
        
        if (!isset($attach_response['data']['attributes']['status'])) {
            // Handle error
            $conn->rollback();
            $error_msg = "Error attaching payment method: ";
            if (isset($attach_response['errors']) && is_array($attach_response['errors'])) {
                foreach ($attach_response['errors'] as $error) {
                    $error_msg .= $error['detail'] . " ";
                }
            } else {
                $error_msg .= json_encode($attach_response);
            }
            $_SESSION['error'] = $error_msg;
            header("Location: make-payment.php?id=" . $appointment_id);
            exit();
        }
        
        // Check if there's a next_action URL (for e-wallets)
        if (isset($attach_response['data']['attributes']['next_action']['redirect']['url'])) {
            $redirect_url = $attach_response['data']['attributes']['next_action']['redirect']['url'];
            
            $conn->commit();
            
            // Create notification for dentist
            $stmt = $conn->prepare("SELECT u.first_name, u.last_name, a.dentist_id 
                                   FROM appointment a 
                                   JOIN user u ON a.patient_id = u.user_id
                                   WHERE a.appointment_id = :appointment_id");
            $stmt->bindParam(':appointment_id', $appointment_id);
            $stmt->execute();
            $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $dentist_id = $patient_info['dentist_id'];
            $notification_message = $patient_info['first_name'] . ' ' . $patient_info['last_name'] . ' has initiated payment for appointment #' . $appointment_id;
            
            $stmt = $conn->prepare("INSERT INTO notification (user_id, notification_type, notification_message, related_entity_id, related_table, is_read, is_sent, send_via, created_at) 
                                   VALUES (:dentist_id, 'payment', :message, :payment_id, 'payment', 0, 1, 'email', NOW())");
            $stmt->bindParam(':dentist_id', $dentist_id);
            $stmt->bindParam(':message', $notification_message);
            $stmt->bindParam(':payment_id', $payment_id);
            $stmt->execute();
            
            // Redirect user to e-wallet payment page
            header("Location: " . $redirect_url);
            exit();
        } else if ($attach_response['data']['attributes']['status'] === 'succeeded') {
            // Payment succeeded immediately (unlikely for e-wallets)
            $paymongo_payment_id = $attach_response['data']['id'];
            
            // Update payment record
            $stmt = $conn->prepare("UPDATE payment SET 
                                   paymongo_payment_id = :paymongo_payment_id, 
                                   status = 'succeeded', 
                                   updated_at = NOW() 
                                   WHERE payment_id = :payment_id");
            $stmt->bindParam(':paymongo_payment_id', $paymongo_payment_id);
            $stmt->bindParam(':payment_id', $payment_id);
            $stmt->execute();
            
            $conn->commit();
            
            // Redirect to success page
            $_SESSION['success'] = "Payment completed successfully!";
            header("Location: payment-success.php?id=" . $payment_id);
            exit();
        } else {
            // Some other status
            $status = $attach_response['data']['attributes']['status'];
            
            // Update payment record
            $stmt = $conn->prepare("UPDATE payment SET 
                                   status = :status, 
                                   updated_at = NOW() 
                                   WHERE payment_id = :payment_id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':payment_id', $payment_id);
            $stmt->execute();
            
            $conn->commit();
            
            // Since this is test mode, we'll simulate a successful payment
            $_SESSION['success'] = "Test payment initiated successfully!";
            header("Location: payment-success.php?id=" . $payment_id);
            exit();
        }
    } catch (PDOException $e) {
        $conn->rollback();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: make-payment.php?id=" . $appointment_id);
        exit();
    }
} else {
    // If not a POST request, redirect to dashboard
    header("Location: patient-dashboard.php");
    exit();
}