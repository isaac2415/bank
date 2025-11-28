<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = ? AND role = 'admin' AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Update last login
        $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$admin['id']]);
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid admin credentials";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BankingKhonde</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .admin-login-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .admin-header h1 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .admin-header p {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="admin-login-form">
            <div class="admin-header">
                <h1>BankingKhonde</h1>
                <p>Admin Portal</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="message message-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Admin Username:</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login to Admin</button>
            </form>
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="../index.php" style="color: #667eea;">← Back to Main Site</a>
            </div>
        </div>
    </div>
</body>
</html>