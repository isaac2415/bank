<?php
require_once 'includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = $_POST['settings'] ?? [];
        
        try {
            $db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $query = "INSERT INTO admin_settings (setting_key, setting_value) 
                         VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->execute([$key, $value, $value]);
            }
            
            $db->commit();
            $_SESSION['success'] = "Settings updated successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
        }
    }
}

// Get current settings
$query = "SELECT setting_key, setting_value, description FROM admin_settings";
$stmt = $db->prepare($query);
$stmt->execute();
$settings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settings = [];
foreach ($settings_data as $setting) {
    $settings[$setting['setting_key']] = [
        'value' => $setting['setting_value'],
        'description' => $setting['description']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - BankingKhonde Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            min-height: 100vh;
        }

        /* Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        /* .admin-sidebar {
            width: 250px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            transition: all 0.3s ease;
            position: fixed;
            height: 100vh;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .admin-sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
        }

        .logo i {
            color: #667eea;
            font-size: 1.5rem;
            min-width: 25px;
        }

        .sidebar-toggle {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .admin-sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        .admin-sidebar.collapsed .logo span {
            display: none;
        } */

        /* Sidebar Navigation */
        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
        }

        .sidebar-nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav-links li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-radius: 0;
            margin: 0 0.5rem;
            border-radius: 5px;
        }

        .sidebar-nav-links a:hover,
        .sidebar-nav-links a.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-nav-links i {
            width: 20px;
            text-align: center;
        }

        .admin-sidebar.collapsed .link-text {
            display: none;
        }

        /* Main Content Area */
        .admin-main {
            flex: 1;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            background-color: #f5f7fa;
            min-height: 100vh;
        }

        .admin-sidebar.collapsed ~ .admin-main {
            margin-left: 70px;
        }

        .main-header {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: #2c3e50;
        }

        .main-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .main-content {
            padding: 1.5rem;
        }

        /* Container */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Card Components */
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            border: 1px solid #e1e5e9;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f2f5;
            font-weight: 600;
        }

        .card h3 {
            color: #34495e;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        /* Message Styles */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border-left: 4px solid;
            animation: slideIn 0.3s ease-out;
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
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

        /* Form Styles */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group small {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Button Styles */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* Grid Layouts */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .grid-gap-2 {
            gap: 2rem;
        }

        .grid-gap-1 {
            gap: 1rem;
        }

        /* System Information */
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .system-info div {
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .system-info strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 0.25rem;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e5e9;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                width: 250px;
            }

            .admin-sidebar.mobile-open {
                transform: translateX(0);
            }

            .admin-sidebar.collapsed {
                transform: translateX(-100%);
            }

            .admin-main {
                margin-left: 0;
                width: 100%;
            }

            .admin-sidebar.collapsed ~ .admin-main {
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: block;
            }

            .sidebar-toggle {
                display: none;
            }

            .container {
                padding: 1rem;
            }
            
            .card {
                padding: 1.5rem;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .admin-sidebar {
                width: 100%;
            }

            .system-info {
                grid-template-columns: 1fr;
            }
        }

        /* Utility Classes */
        .mt-2 {
            margin-top: 2rem;
        }

        .text-center {
            text-align: center;
        }

        /* Loading State */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Custom Select Styling */
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 12px;
            padding-right: 2.5rem !important;
        }

        /* Number Input Styling */
        input[type="number"] {
            -moz-appearance: textfield;
        }

        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <!-- Main Content -->
        <main class="admin-main" id="adminMain">
            <header class="main-header">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>System Settings</h1>
            </header>
            
            <div class="main-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="message message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="message message-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="card">
                    <h2>System Settings</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div style="display: grid; gap: 2rem;">
                            <!-- Treasurer Settings -->
                            <div>
                                <h3>Treasurer Management</h3>
                                <div style="display: grid; gap: 1rem;">
                                    <div class="form-group">
                                        <label for="treasurer_auto_approve">Auto-approve Treasurers:</label>
                                        <select id="treasurer_auto_approve" name="settings[treasurer_auto_approve]" required>
                                            <option value="yes" <?php echo ($settings['treasurer_auto_approve']['value'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                            <option value="no" <?php echo ($settings['treasurer_auto_approve']['value'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                        </select>
                                        <small><?php echo $settings['treasurer_auto_approve']['description'] ?? 'Automatically approve new treasurer registrations'; ?></small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="max_groups_per_treasurer">Max Groups per Treasurer:</label>
                                        <input type="number" id="max_groups_per_treasurer" name="settings[max_groups_per_treasurer]" 
                                               value="<?php echo $settings['max_groups_per_treasurer']['value'] ?? 5; ?>" min="1" max="50" required>
                                        <small><?php echo $settings['max_groups_per_treasurer']['description'] ?? 'Maximum number of groups a treasurer can create'; ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- System Settings -->
                            <div>
                                <h3>System Configuration</h3>
                                <div style="display: grid; gap: 1rem;">
                                    <div class="form-group">
                                        <label for="system_maintenance">Maintenance Mode:</label>
                                        <select id="system_maintenance" name="settings[system_maintenance]" required>
                                            <option value="yes" <?php echo ($settings['system_maintenance']['value'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Enabled</option>
                                            <option value="no" <?php echo ($settings['system_maintenance']['value'] ?? 'no') === 'no' ? 'selected' : ''; ?>>Disabled</option>
                                        </select>
                                        <small><?php echo $settings['system_maintenance']['description'] ?? 'Put system in maintenance mode'; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                            <button type="reset" class="btn btn-secondary">Reset to Defaults</button>
                        </div>
                    </form>
                </div>

                <!-- System Information -->
                <div class="card" style="margin-top: 2rem;">
                    <h3>System Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                        </div>
                        <div>
                            <strong>Database:</strong> Connected
                        </div>
                        <div>
                            <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                        </div>
                        <div>
                            <strong>Admin User:</strong> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('adminSidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const adminMain = document.getElementById('adminMain');

            // Toggle sidebar collapse/expand
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    
                    // Save state to localStorage
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                });
            }

            // Toggle mobile menu
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                });
            }

            // Close mobile menu when clicking on main content
            if (adminMain) {
                adminMain.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('mobile-open');
                    }
                });
            }

            // Load sidebar state from localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
            }

            // Close mobile menu on window resize if it goes above mobile breakpoint
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('mobile-open');
                }
            });
        });
    </script>
</body>
</html>