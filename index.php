<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isLoggedIn()) {
    header("Location: pages/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    // check if system is in maintenance mode
    $maintenanceQuery = "SELECT setting_value FROM admin_settings WHERE setting_key = 'maintenance_mode'";
    $stmt = $db->prepare($maintenanceQuery);
    $stmt->execute();
    $maintenanceMode = $stmt->fetchColumn();
    
    if ($maintenanceMode === 'on') {
        $_SESSION['error'] = "The system is currently under maintenance. Please try again later.";
        header("Location: index.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    // if($_SESSION['error']){
    //     echo "<script>alert('".$_SESSION['error']."');</script>";
    //     unset($_SESSION['error']);
    // }
    
    if ($action === 'register') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        
        $query = "INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `phone`, `role`) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$username, $email, $password, $full_name, $phone, $role])) {
            $_SESSION['success'] = "Registration successful! Please login.";
            if($role === 'treasurer') {
                header("Location: subscription.php");
                $_SESSION['subcription_email'] = $email;
                $_SESSION['subcription_name'] = $full_name;
                
            }else{
                header("Location: index.php");
            }
        }
    } elseif ($action === 'login') {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            
            if($user['role'] === 'treasurer') {
                //check in subscription table if the email has paid the subscription fee
                $sub_check_query = "SELECT * FROM subscription WHERE email = ?";
                $sub_stmt = $db->prepare($sub_check_query);
                $sub_stmt->execute([$email]);
                $treasurerpaid = $sub_stmt->fetch(PDO::FETCH_ASSOC);
                
                //check if user is active
                $active_query = 'SELECT is_active FROM users WHERE email = ?';
                $active_stmt = $db->prepare($active_query);
                $active_stmt->execute([$email]);
                $is_active = $active_stmt->fetchColumn();
                
                
                if(!$treasurerpaid && $is_active) {
                    $_SESSION['error'] = "Your treasurer account subscription is not active. Please complete the subscription.";
                    header("Location: index.php");
                    exit();
                }else{
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    header("Location: pages/dashboard.php");
                    exit();
                }
            }
            if($user['role'] === 'member') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                header("Location: pages/dashboard.php");
                exit();
            } 
            
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BankingKhonde - Group Financial Management Made Simple</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --accent: #f093fb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --darker: #111827;
            --light: #f8fafc;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background: var(--light);
            overflow-x: hidden;
        }

        /* Header & Navigation */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: var(--transition);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1200px;
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
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            transition: var(--transition);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-button {
            
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .nav-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 8rem 2rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            line-height: 1.8;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn-hero {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-hero:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-hero.primary {
            background: white;
            color: var(--primary);
            border-color: white;
        }

        .btn-hero.primary:hover {
            background: var(--light);
            transform: translateY(-3px);
        }

        /* Features Section */
        .features {
            padding: 5rem 2rem;
            background: var(--light);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: rgb(0, 123, 255);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .feature-card p {
            color: var(--gray);
            line-height: 1.7;
        }

        /* Auth Section */
        .auth-section {
            padding: 5rem 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .auth-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .form-tabs {
            display: flex;
            background: var(--light);
            border-bottom: 1px solid var(--gray-light);
        }

        .form-tab {
            flex: 1;
            padding: 1.25rem 2rem;
            background: none;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .form-tab.active {
            color: var(--primary);
        }

        .form-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .form-tab:hover:not(.active) {
            background: rgba(102, 126, 234, 0.05);
            color: var(--primary);
        }

        .form-content {
            display: none;
            padding: 2.5rem;
        }

        .form-content.active {
            display: block;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-container h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--dark);
            font-size: 1.8rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--gray);
            font-size: 0.8rem;
        }

        /* Button Styles */
        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        /* Message Styles */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out;
        }

        .message-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .message-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Footer */
        footer {
            background: var(--darker);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: var(--gray-light);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
        }

        .copyright {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-hero {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .form-content {
                padding: 2rem;
            }
        }

        @media (max-width: 480px) {
            nav {
                padding: 1rem;
            }

            .hero-section {
                padding: 6rem 1rem 3rem;
            }

            .hero-title {
                font-size: 2rem;
            }

            .features,
            .auth-section {
                padding: 3rem 1rem;
            }

            .form-tab {
                padding: 1rem;
                font-size: 1rem;
            }
        }

        /* Additional Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* Loading States */
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-loading {
            position: relative;
            color: transparent;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <nav>
            <div class="logo">
                <i class="fas fa-university"></i>
                BankingKhonde
            </div>
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#auth">Get Started</a></li>
                <li><a href="admin" class="nav-button">Admin Portal</a></li>
            </ul>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Group Financial Management Made Simple</h1>
            <p class="hero-subtitle">
                Streamline your group finances with BankingKhonde. Track contributions, manage loans, 
                and foster financial growth within your community through our intuitive platform.
            </p>
            
            <div class="action-buttons">
                <button class="btn-hero primary" onclick="showAuth('register')">
                    <i class="fas fa-rocket"></i>
                    Start Your Journey
                </button>
                <button class="btn-hero" onclick="showAuth('login')">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In to Account
                </button>
            </div>
            
            <p style="opacity: 0.9; margin-top: 1rem;">
                Join thousands of users managing their finances together
            </p>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="features-grid">
            <div class="feature-card floating">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Smart Financial Tracking</h3>
                <p>Monitor contributions, expenses, and savings with real-time analytics and automated reporting.</p>
            </div>
            
            <div class="feature-card floating" style="animation-delay: 0.2s;">
                <div class="feature-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h3>Loan Management</h3>
                <p>Streamline loan applications, approvals, and repayment tracking with automated reminders.</p>
            </div>
            
            <div class="feature-card floating" style="animation-delay: 0.4s;">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Collaborative Tools</h3>
                <p>Connect with members, share updates, and make collective financial decisions seamlessly.</p>
            </div>
            
            <div class="feature-card floating" style="animation-delay: 0.6s;">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Bank-Grade Security</h3>
                <p>Your financial data is protected with enterprise-level security and encryption protocols.</p>
            </div>
        </div>
    </section>

    <!-- Auth Section -->
    <section id="auth" class="auth-section">
        <div class="auth-container">
            <div class="form-tabs">
                <button class="form-tab active" onclick="showForm('login')">Sign In</button>
                <button class="form-tab" onclick="showForm('register')">Create Account</button>
            </div>

            <!-- Login Form -->
            <div id="loginForm" class="form-content active">
                <div class="form-container">
                    <h2>Welcome Back</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="message message-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="message message-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="ajax-form" id="loginFormElement">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required placeholder="Enter your email">
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required placeholder="Enter your password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-sign-in-alt"></i>
                            Sign In to Dashboard
                        </button>
                    </form>
                </div>
            </div>

            <!-- Register Form -->
            <div id="registerForm" class="form-content">
                <div class="form-container">
                    <h2>Join BankingKhonde</h2>
                    <form method="POST" class="ajax-form" id="registerFormElement">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label for="reg_username">Username</label>
                            <input type="text" id="reg_username" name="username" required placeholder="Choose a username">
                        </div>
                        <div class="form-group">
                            <label for="reg_email">Email Address</label>
                            <input type="email" id="reg_email" name="email" required placeholder="Enter your email">
                        </div>
                        <div class="form-group">
                            <label for="reg_full_name">Full Name</label>
                            <input type="text" id="reg_full_name" name="full_name" required placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="reg_phone">Phone Number</label>
                            <input type="tel" id="reg_phone" name="phone" placeholder="Optional phone number">
                        </div>
                        <div class="form-group">
                            <label for="reg_role">Account Type</label>
                            <select id="reg_role" name="role" required onchange="updateRoleDescription()">
                                <option value="member">Group Member</option>
                                <option value="treasurer">Group Treasurer</option>
                            </select>
                            <small id="roleDescription">
                                Join existing groups and participate in financial activities
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="reg_password">Password</label>
                            <input type="password" id="reg_password" name="password" required placeholder="Create a secure password">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-user-plus"></i>
                            Create Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="logo" style="justify-content: center; margin-bottom: 1rem;">
                <i class="fas fa-university"></i>
                BankingKhonde
            </div>
            <div class="footer-links">
                <a href="#features">Features</a>
                <a href="#auth">Get Started</a>
                <a href="admin">Admin Portal</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
            <div class="copyright">
                &copy; 2024 BankingKhonde. All rights reserved. Empowering communities through financial technology.
            </div>
        </div>
    </footer>

    <script>
        function showAuth(formType) {
            document.querySelectorAll('.form-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.form-content').forEach(content => content.classList.remove('active'));
            
            if (formType === 'login') {
                document.querySelector('.form-tab:first-child').classList.add('active');
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.querySelector('.form-tab:last-child').classList.add('active');
                document.getElementById('registerForm').classList.add('active');
            }
            
            document.getElementById('auth').scrollIntoView({ behavior: 'smooth' });
        }
        
        function showForm(formType) {
            document.querySelectorAll('.form-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.form-content').forEach(content => content.classList.remove('active'));
            
            if (formType === 'login') {
                document.querySelector('.form-tab:first-child').classList.add('active');
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.querySelector('.form-tab:last-child').classList.add('active');
                document.getElementById('registerForm').classList.add('active');
            }
        }
        
        function updateRoleDescription() {
            const role = document.getElementById('reg_role').value;
            const description = document.getElementById('roleDescription');
            
            if (role === 'member') {
                description.textContent = 'Join existing groups and participate in financial activities';
            } else {
                description.textContent = 'Create and manage groups, approve loans, and track contributions (requires admin verification)';
            }
        }
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = 'none';
            }
        });

        // Form submission handling
        document.getElementById('loginFormElement')?.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.classList.add('btn-loading');
            button.innerHTML = '';
        });

        document.getElementById('registerFormElement')?.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.classList.add('btn-loading');
            button.innerHTML = '';
        });
    </script>
</body>
</html>