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
    
    if($_SESSION['error']){
        
        echo "<script>alert('".$_SESSION['error']."');</script>";
        unset($_SESSION['error']);
    }
    
    if ($action === 'register') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        
        $query = "INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `phone`, `role`) VALUES (?, ?, ?, ?, ?, ?)";
;
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$username, $email, $password, $full_name, $phone, $role])) {
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: index.php");
            exit();
        }
    } elseif ($action === 'login') {
        //$username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            
            if($user['role'] === 'treasurer') {
                if($user['verified'] !== 'yes') {
                    $_SESSION['error'] = "Your treasurer account is pending verification. Please wait for admin approval.";
                    header("Location: index.php");
                    exit();
                } elseif($user['verified'] === 'rejected') {
                    $_SESSION['error'] = "Your treasurer account verification was rejected. Please contact support.";
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
            $error = "Invalid username or password";
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
    <title>BankingKhonde - Welcome</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <i class="fas fa-piggy-bank"></i>
                BankingKhonde</div>
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#auth">Login</a></li>
                <li><a href="admin">Admin Portal</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">BankingKhonde</h1>
            <p class="hero-subtitle">Manage your group finances with ease. Track contributions, approve loans, and grow together.</p>
            
            <div class="action-buttons">
                <button class="btn-hero" onclick="showAuth('register')">Create a Group</button>
                <button class="btn-hero" onclick="showAuth('register')">Join a Group</button>
            </div>
            
            <p>Already have an account? <a href="#auth" style="color: white; text-decoration: underline;" onclick="showAuth('login')">Login here</a></p>
        </div>
    </section>

    <section id="features" class="features">
        <div class="feature-card">
            <div class="feature-icon">💰</div>
            <h3>Track Contributions</h3>
            <p>Monitor member payments and identify missed contributions automatically.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">📊</div>
            <h3>Loan Management</h3>
            <p>Apply for loans, track approvals, and monitor repayment schedules.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">👥</div>
            <h3>Group Collaboration</h3>
            <p>Chat with members, post announcements, and set group rules.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">📈</div>
            <h3>Smart Reports</h3>
            <p>Generate detailed reports and analytics for better decision making.</p>
        </div>
    </section>

    <section id="auth" class="auth-section">
        <div class="auth-container">
            <div class="form-tabs">
                <button class="form-tab active" onclick="showForm('login')">Login</button>
                <button class="form-tab" onclick="showForm('register')">Register</button>
            </div>

            <!-- Login Form -->
            <div id="loginForm" class="form-content active">
                <div class="form-container">
                    <h2>Login to BankingKhonde</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="message message-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="message message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="ajax-form">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="text" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                    </form>
                </div>
            </div>

            <!-- Register Form -->
            <div id="registerForm" class="form-content">
                <div class="form-container">
                    <h2>Join BankingKhonde</h2>
                    <form method="POST" class="ajax-form">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label for="reg_username">Username:</label>
                            <input type="text" id="reg_username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_email">Email:</label>
                            <input type="email" id="reg_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_full_name">Full Name:</label>
                            <input type="text" id="reg_full_name" name="full_name" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_phone">Phone:</label>
                            <input type="tel" id="reg_phone" name="phone">
                        </div>
                        <div class="form-group">
                            <label for="reg_role">I want to:</label>
                            <select id="reg_role" name="role" required onchange="updateRoleDescription()">
                                <option value="member">Join as Member</option>
                                <option value="treasurer">Create Groups as Treasurer</option>
                            </select>
                            <small id="roleDescription" style="display: block; margin-top: 0.5rem; color: #666;">
                                Join existing groups and participate in financial activities
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="reg_password">Password:</label>
                            <input type="password" id="reg_password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

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
                description.textContent = 'Create and manage groups, approve loans, and track contributions';
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
    </script>
</body>
</html>
