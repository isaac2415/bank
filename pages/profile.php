<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'view';

// Get current user data
$query = "SELECT * FROM `users` WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "User not found";
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    
    switch ($action) {
        case 'update_profile':
            updateProfile($db, $user_id);
            break;
        case 'change_password':
            changePassword($db, $user_id);
            break;
        case 'update_preferences':
            updatePreferences($db, $user_id);
            break;
    }
}

function updateProfile($db, $user_id) {
    try {
        $required_fields = ['full_name', 'email', 'phone'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        // Check if email already exists (excluding current user)
    $query = "SELECT id FROM `users` WHERE email = ? AND id != ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->fetch()) {
            throw new Exception("Email already exists. Please use a different email.");
        }
        
    $query = "UPDATE `users` SET full_name = ?, email = ?, phone = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$full_name, $email, $phone, $user_id])) {
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            $_SESSION['success'] = "Profile updated successfully!";
        } else {
            throw new Exception("Failed to update profile");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: profile.php");
    exit();
}

function changePassword($db, $user_id) {
    try {
        $required_fields = ['current_password', 'new_password', 'confirm_password'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("All password fields are required");
            }
        }
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
    $query = "SELECT password FROM `users` WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_data || !password_verify($current_password, $user_data['password'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Validate new password
        if (strlen($new_password) < 6) {
            throw new Exception("New password must be at least 6 characters long");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match");
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $query = "UPDATE `users` SET password = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            $_SESSION['success'] = "Password changed successfully!";
        } else {
            throw new Exception("Failed to change password");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: profile.php");
    exit();
}

function updatePreferences($db, $user_id) {
    try {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $loan_alerts = isset($_POST['loan_alerts']) ? 1 : 0;
        $payment_reminders = isset($_POST['payment_reminders']) ? 1 : 0;
        
        // In a real application, you'd have a user_preferences table
        // For now, we'll store in session or extend users table
        $_SESSION['preferences'] = [
            'email_notifications' => $email_notifications,
            'sms_notifications' => $sms_notifications,
            'loan_alerts' => $loan_alerts,
            'payment_reminders' => $payment_reminders
        ];
        
        $_SESSION['success'] = "Preferences updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: profile.php");
    exit();
}

// Get user statistics
$query = "SELECT
          COUNT(DISTINCT gm.group_id) as total_groups,
          COUNT(DISTINCT p.id) as total_payments,
          COALESCE(SUM(p.amount), 0) as total_contributions,
          COUNT(DISTINCT l.id) as total_loans,
          COUNT(DISTINCT CASE WHEN l.status = 'approved' THEN l.id END) as approved_loans
          FROM `users` u
          LEFT JOIN `group_members` gm ON u.id = gm.user_id AND gm.status = 'active'
          LEFT JOIN `payments` p ON u.id = p.user_id AND p.status = 'paid'
          LEFT JOIN `loans` l ON u.id = l.user_id
          WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's groups
$query = "SELECT g.*, gm.joined_at 
          FROM `groups` g
          JOIN `group_members` gm ON g.id = gm.group_id
          WHERE gm.user_id = ? AND gm.status = 'active'
          ORDER BY gm.joined_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$query = "(
    SELECT 'payment' as type, p.amount, p.payment_date as date, g.name as group_name, NULL as status
    FROM `payments` p
    JOIN `groups` g ON p.group_id = g.id
    WHERE p.user_id = ? AND p.status = 'paid'
    ORDER BY p.payment_date DESC
    LIMIT 5
) UNION ALL (
    SELECT 'loan' as type, l.amount, l.applied_date as date, g.name as group_name, l.status
    FROM `loans` l
    JOIN `groups` g ON l.group_id = g.id
    WHERE l.user_id = ?
    ORDER BY l.applied_date DESC
    LIMIT 5
) ORDER BY date DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$user_id, $user_id]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - BankingKhonde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #4895ef;
            --secondary: #7209b7;
            --success: #4cc9a7;
            --warning: #f9c74f;
            --danger: #f94144;
            --dark: #2b2d42;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border: #dee2e6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Header Styles */
        header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .logo i {
            font-size: 1.75rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            border-radius: var(--radius);
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
            background: var(--primary-light);
            color: white;
        }

        .nav-link.active {
            background: var(--primary);
            color: white;
        }

        .nav-link i {
            font-size: 1.1rem;
        }

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
        }

        .profile-info {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .profile-info h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .profile-meta {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            opacity: 0.9;
        }

        .profile-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
            border-top: 4px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            display: block;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            background: white;
            border-radius: var(--radius);
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .tab-button {
            flex: 1;
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray);
            border-radius: var(--radius);
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-button:hover {
            background: var(--gray-light);
            color: var(--dark);
        }

        .tab-button.active {
            background: var(--primary);
            color: white;
        }

        .tab-button i {
            font-size: 1.1rem;
        }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card h3 i {
            color: var(--primary);
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
            background-color: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-group input:disabled {
            background-color: var(--gray-light);
            color: var(--gray);
            cursor: not-allowed;
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.5rem;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #3da58a;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin: 1rem 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        table th {
            background-color: var(--background-alt);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            border-bottom: 1px solid var(--border);
        }

        table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover {
            background-color: var(--light);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-treasurer {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-member {
            background: #f3e8ff;
            color: #7c3aed;
        }

        /* Password Strength */
        .password-strength {
            height: 6px;
            background: var(--gray-light);
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }

        .strength-weak {
            background: var(--danger);
            width: 33%;
        }

        .strength-medium {
            background: var(--warning);
            width: 66%;
        }

        .strength-strong {
            background: var(--success);
            width: 100%;
        }

        /* Activity Items */
        .activity-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }

        .activity-item:hover {
            background: var(--light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .activity-payment {
            background: var(--success);
        }

        .activity-loan {
            background: var(--primary);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Checkbox Styles */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .checkbox-group:hover {
            background: var(--light);
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .checkbox-label {
            flex: 1;
        }

        .checkbox-label strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .checkbox-help {
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .message-success {
            background: #d1fae5;
            color: #065f46;
            border-left-color: var(--success);
        }

        .message-error {
            background: #fee2e2;
            color: #dc2626;
            border-left-color: var(--danger);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .tab-navigation {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .profile-info h1 {
                font-size: 2rem;
            }
            
            .profile-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
<?php include '../includes/header.php'; ?>
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <div class="profile-meta">
                    <span><i class="fas fa-user-circle"></i> @<?php echo htmlspecialchars($user['username']); ?></span>
                    <span><i class="fas fa-shield-alt"></i> <?php echo ucfirst($user['role']); ?></span>
                    <span><i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>

    <main class="container">
        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <span class="stat-number"><?php echo $user_stats['total_groups'] ?? 0; ?></span>
                <span class="stat-label">Active Groups</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <span class="stat-number"><?php echo $user_stats['total_payments'] ?? 0; ?></span>
                <span class="stat-label">Payments Made</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span class="stat-number">K <?php echo number_format($user_stats['total_contributions'] ?? 0, 0); ?></span>
                <span class="stat-label">Total Contributed</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <span class="stat-number"><?php echo $user_stats['approved_loans'] ?? 0; ?></span>
                <span class="stat-label">Approved Loans</span>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-button active" onclick="showTab('profile')">
                <i class="fas fa-user-circle"></i>
                Profile Information
            </button>
            <button class="tab-button" onclick="showTab('security')">
                <i class="fas fa-shield-alt"></i>
                Security
            </button>
            <button class="tab-button" onclick="showTab('preferences')">
                <i class="fas fa-cog"></i>
                Preferences
            </button>
            <button class="tab-button" onclick="showTab('activity')">
                <i class="fas fa-history"></i>
                Recent Activity
            </button>
        </div>

        <!-- Profile Information Tab -->
        <div id="profile-tab" class="tab-content active">
            <div class="card">
                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <div class="form-help">Username cannot be changed</div>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="role">Account Type</label>
                            <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled>
                            <div class="form-help">
                                <?php if ($user['role'] === 'treasurer'): ?>
                                    You can create and manage groups as a Treasurer
                                <?php else: ?>
                                    You can join groups and participate in financial activities as a Member
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Member Since</label>
                            <input type="text" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- My Groups Section -->
            <div class="card">
                <h3><i class="fas fa-users"></i> My Groups</h3>
                <?php if (empty($user_groups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Groups Yet</h3>
                        <p>You haven't joined any groups yet. Start by joining a group or creating one.</p>
                        <div style="margin-top: 1.5rem;">
                            <a href="groups.php?action=join" class="btn btn-primary">
                                <i class="fas fa-link"></i>
                                Join a Group
                            </a>
                            <?php if ($user['role'] === 'treasurer'): ?>
                                <a href="groups.php?action=create" class="btn btn-success" style="margin-left: 1rem;">
                                    <i class="fas fa-plus"></i>
                                    Create a Group
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Group Name</th>
                                    <th>Role</th>
                                    <th>Contribution</th>
                                    <th>Joined Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_groups as $group): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($group['name']); ?></strong></td>
                                    <td>
                                        <?php if ($group['treasurer_id'] == $user_id): ?>
                                            <span class="status-badge status-treasurer">Treasurer</span>
                                        <?php else: ?>
                                            <span class="status-badge status-member">Member</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>K <?php echo number_format($group['contribution_amount'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($group['joined_at'])); ?></td>
                                    <td>
                                        <a href="groups.php?action=view&id=<?php echo $group['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required 
                                   onkeyup="checkPasswordStrength(this.value)">
                            <div class="password-strength">
                                <div class="password-strength-bar" id="password-strength-bar"></div>
                            </div>
                            <div class="form-help">Password must be at least 6 characters long</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        Change Password
                    </button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-shield-alt"></i> Security Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Last Login</label>
                        <input type="text" value="<?php echo date('F j, Y g:i A'); ?>" disabled>
                        <div class="form-help">Current session</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Created</label>
                        <input type="text" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preferences Tab -->
        <div id="preferences-tab" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_preferences">
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="email_notifications" id="email_notifications"
                               <?php echo isset($_SESSION['preferences']['email_notifications']) && $_SESSION['preferences']['email_notifications'] ? 'checked' : 'checked'; ?>>
                        <div class="checkbox-label">
                            <strong>Email Notifications</strong>
                            <div class="checkbox-help">Receive important updates via email</div>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="sms_notifications" id="sms_notifications"
                               <?php echo isset($_SESSION['preferences']['sms_notifications']) && $_SESSION['preferences']['sms_notifications'] ? 'checked' : 'checked'; ?>>
                        <div class="checkbox-label">
                            <strong>SMS Notifications</strong>
                            <div class="checkbox-help">Receive payment reminders via SMS</div>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="loan_alerts" id="loan_alerts"
                               <?php echo isset($_SESSION['preferences']['loan_alerts']) && $_SESSION['preferences']['loan_alerts'] ? 'checked' : 'checked'; ?>>
                        <div class="checkbox-label">
                            <strong>Loan Application Alerts</strong>
                            <div class="checkbox-help">Get notified when loan applications are submitted</div>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="payment_reminders" id="payment_reminders"
                               <?php echo isset($_SESSION['preferences']['payment_reminders']) && $_SESSION['preferences']['payment_reminders'] ? 'checked' : 'checked'; ?>>
                        <div class="checkbox-label">
                            <strong>Payment Reminders</strong>
                            <div class="checkbox-help">Receive reminders for upcoming payments</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Preferences
                    </button>
                </form>
            </div>
        </div>

        <!-- Activity Tab -->
        <div id="activity-tab" class="tab-content">
            <div class="card">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <?php if (empty($recent_activity)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Recent Activity</h3>
                        <p>Your recent transactions and activities will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['type'] === 'payment' ? 'activity-payment' : 'activity-loan'; ?>">
                                    <i class="fas <?php echo $activity['type'] === 'payment' ? 'fa-money-bill-wave' : 'fa-hand-holding-usd'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php if ($activity['type'] === 'payment'): ?>
                                            Payment of K <?php echo number_format($activity['amount'], 2); ?>
                                        <?php else: ?>
                                            Loan Application - K <?php echo number_format($activity['amount'], 2); ?>
                                            <span class="status-badge status-<?php echo $activity['status']; ?>" style="margin-left: 0.5rem;">
                                                <?php echo ucfirst($activity['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-meta">
                                        <i class="fas fa-users"></i> <?php echo htmlspecialchars($activity['group_name']); ?> • 
                                        <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activate selected tab button
            event.currentTarget.classList.add('active');
        }
        
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength-bar');
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.querySelector('form[action="change_password"]');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>