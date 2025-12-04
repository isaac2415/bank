<?php
session_start();
require_once('vendor/autoload.php');
require_once('config/database.php');

// Generate random charge ID
function generateChargeId($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$error = '';
$success = '';
$chargeId = generateChargeId();
$responseData = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client = new \GuzzleHttp\Client();
        
        // Generate new charge ID for each submission
        $chargeId = generateChargeId();
        
        // Prepare request data from form with validation
        $requestData = [
            'mobile_money_operator_ref_id' => trim($_POST['operator_id']),
            'mobile' => trim($_POST['mobile']),
            'amount' => trim($_POST['amount']),
            'charge_id' => $chargeId,
            'email' => trim($_POST['email']),
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name'])
        ];
        
        // Debug: Show what we're sending
        error_log("Sending to API: " . json_encode($requestData));
        
        $response = $client->request('POST', 'https://api.paychangu.com/mobile-money/payments/initialize', [
            'body' => json_encode($requestData),
            'headers' => [
                'Authorization' => 'Bearer sec-test-fFkqKmNetfyCxAG6ImJ4vQt2KD0Rh4KN',
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);
        
        $responseBody = $response->getBody();
        $responseData = json_decode($responseBody, true);
        
        // Debug: Show what we received
        error_log("Received from API: " . json_encode($responseData));
        
        // Store data in database if API call was successful
        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            
            // Get database connection
            $database = new Database();
            $db = $database->getConnection();
            
            // Prepare SQL statement
            $sql = "INSERT INTO subscription (charge_id, ref_id, first_name, last_name, email, status, amount, currency, mobile, created_at) 
                    VALUES (:charge_id, :ref_id, :first_name, :last_name, :email, :status, :amount, :currency, :mobile, NOW())";
            
            $stmt = $db->prepare($sql);
            
            // Bind parameters
            $stmt->bindParam(':charge_id', $chargeId);
            $stmt->bindParam(':ref_id', $responseData['data']['ref_id']);
            $stmt->bindParam(':first_name', $responseData['data']['first_name']);
            $stmt->bindParam(':last_name', $responseData['data']['last_name']);
            $stmt->bindParam(':email', $responseData['data']['email']);
            $stmt->bindParam(':status', $responseData['data']['status']);
            $stmt->bindParam(':amount', $responseData['data']['amount']);
            $stmt->bindParam(':currency', $responseData['data']['currency']);
            $stmt->bindParam(':mobile', $responseData['data']['mobile']);
            
            // Execute the query
            if ($stmt->execute()) {
                $success = 'Payment initiated successfully and subscription recorded in database!';
            } else {
                $success = 'Payment initiated successfully, but there was an error saving to database.';
            }
        } else {
            // Show API error message if available
            if (isset($responseData['message'])) {
                if (is_array($responseData['message'])) {
                    // If message is an array (like validation errors)
                    $error = 'API Error: ' . implode(', ', array_map(function($key, $value) {
                        return "$key: " . (is_array($value) ? implode(', ', $value) : $value);
                    }, array_keys($responseData['message']), $responseData['message']));
                } else {
                    // If message is a string
                    $error = 'API Error: ' . $responseData['message'];
                }
            } else {
                $error = 'Payment failed. Please check your details and try again.';
            }
        }
        
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        // Handle 400 Bad Request specifically
        $response = $e->getResponse();
        $responseBody = $response->getBody()->getContents();
        $responseData = json_decode($responseBody, true);
        
        if (isset($responseData['message'])) {
            if (is_array($responseData['message'])) {
                $error = 'Validation Error: ';
                foreach ($responseData['message'] as $key => $messages) {
                    $error .= ucfirst(str_replace('_', ' ', $key)) . ': ' . 
                              (is_array($messages) ? implode(', ', $messages) : $messages) . '. ';
                }
            } else {
                $error = 'API Error: ' . $responseData['message'];
            }
        } else {
            $error = 'Error: Bad Request - Please check all required fields are correct.';
        }
        
    } catch (Exception $e) {
        $error = 'System Error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Money Payment</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .form-container {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #004085;
        }
        
        .charge-id-display {
            background: #f0f4ff;
            border: 2px dashed #667eea;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .charge-id-display span {
            font-family: monospace;
            font-weight: bold;
            color: #667eea;
        }
        
        .success-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #e1e5e9;
        }
        
        .success-details h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            text-align: center;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        @media (max-width: 600px) {
            .container {
                max-width: 100%;
            }
            
            .form-container {
                padding: 30px 20px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mobile Money Payment</h1>
            <p>Pay 2000 per Year</p>
        </div>
        
        <div class="form-container">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                
                <?php if (isset($responseData['data'])): ?>
                <div class="success-details">
                    <h3>Payment Details</h3>
                    <div class="detail-item">
                        <span class="detail-label">Charge ID:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($responseData['data']['charge_id']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reference ID:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($responseData['data']['ref_id']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Amount:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($responseData['data']['amount']); ?> <?php echo htmlspecialchars($responseData['data']['currency']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($responseData['data']['first_name'] . ' ' . $responseData['data']['last_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value" style="color: #28a745; font-weight: bold;">
                            <?php echo htmlspecialchars(ucfirst($responseData['data']['status'])); ?>
                        </span>
                    </div>
                    <!-- go to dashboard index button -->
                    <a href="index.php">Go to Dashboard</a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="">
                <!-- Display generated charge ID -->
                <div class="charge-id-display">
                    Charge ID: <span id="chargeIdDisplay"><?php echo htmlspecialchars($chargeId); ?></span>
                    <small style="color: #666; display: block; margin-top: 5px;">This ID is automatically generated</small>
                </div>
                
                <div class="info-box">
                    <strong>Note:</strong> You need a valid Mobile Money Operator Reference ID from Paychangu dashboard.
                </div>
                
                <div class="form-group">
                    <label for="operator_id">Mobile Money Operator Reference ID *</label>
                    <input type="text" 
                           id="operator_id" 
                           name="operator_id" 
                           class="form-control" 
                           value="20be6c20-adeb-4b5b-a7ba-0769820df4fb" 
                           required 
                           placeholder="Enter valid operator reference ID"
                           pattern="[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}"
                           title="Enter a valid UUID format">
                    <small style="color: #666; font-size: 12px;">Format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</small>
                </div>
                
                <div class="form-group">
                    <label for="mobile">Mobile Number *</label>
                    <input type="tel" 
                           id="mobile" 
                           name="mobile" 
                           class="form-control" 
                           value="" 
                           required 
                           placeholder="Enter mobile number (e.g., 0990088193)">
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount (MWK) *</label>
                    <input type="number" 
                           id="amount" 
                           name="amount" 
                           class="form-control" 
                           value="2000" 
                           required 
                           placeholder="Enter amount"
                           min="100"
                           step="100"
                           readonly>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-control" 
                           value="<?php echo isset($_SESSION['subcription_email']) ? htmlspecialchars($_SESSION['subcription_email']) : ''; ?>" 
                           required 
                           placeholder="Enter email address">
                </div>
                
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           class="form-control" 
                           value="<?php echo isset($_SESSION['subcription_name']) ? htmlspecialchars(explode(' ', $_SESSION['subcription_name'])[0]) : ''; ?>" 
                           required 
                           placeholder="Enter first name">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           class="form-control" 
                           value="<?php echo isset($_SESSION['subcription_name']) ? htmlspecialchars(explode(' ', $_SESSION['subcription_name'])[1] ?? '') : ''; ?>" 
                           required 
                           placeholder="Enter last name">
                </div>
                
                <button type="submit" class="btn-submit">
                    Process Payment
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chargeIdDisplay = document.getElementById('chargeIdDisplay');
            if (chargeIdDisplay && !chargeIdDisplay.textContent.trim()) {
                const chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                let result = '';
                for (let i = 0; i < 12; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                chargeIdDisplay.textContent = result;
            }
        });
    </script>
</body>
</html>