<?php
require_once 'includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];
try {
    // Total users
    $query = "SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_COLUMN);

    // Total treasurers
    $query = "SELECT COUNT(*) as total_treasurers FROM users WHERE role = 'treasurer'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_treasurers'] = $stmt->fetch(PDO::FETCH_COLUMN);

    // Pending treasurer verifications
    $query = "SELECT COUNT(*) as pending_verifications FROM users WHERE role = 'treasurer' AND verified IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['pending_verifications'] = $stmt->fetch(PDO::FETCH_COLUMN);

    // Total groups
    $query = "SELECT COUNT(*) as total_groups FROM `groups`";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_groups'] = $stmt->fetch(PDO::FETCH_COLUMN);




} catch (Exception $e) {
    error_log("Error fetching admin stats: " . $e->getMessage());
    $stats = array_fill_keys(['total_users', 'total_treasurers', 'pending_verifications', 'total_groups', 'total_loans', 'total_payments'], 0);
}

// Get recent activities
$recent_activities = [];
try {
    $query = "(
        SELECT 'user_registered' as type, u.username, u.created_at as date, CONCAT('New ', u.role, ' registered: ', u.username) as description
        FROM users u
        WHERE u.role != 'admin'
        ORDER BY u.created_at DESC
        LIMIT 5
    ) UNION ALL (
        SELECT 'group_created' as type, g.name, g.created_at as date, CONCAT('New group created: ', g.name) as description
        FROM `groups` g
        ORDER BY g.created_at DESC
        LIMIT 5
    ) UNION ALL (
        SELECT 'loan_applied' as type, u.username, l.applied_date as date, CONCAT('Loan application: K', l.amount, ' by ', u.username) as description
        FROM loans l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.applied_date DESC
        LIMIT 5
    ) ORDER BY date DESC LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching recent activities: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BankingKhonde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd8;
            --secondary: #764ba2;
            --dark: #2c3e50;
            --darker: #1a2530;
            --light: #f8f9fa;
            --gray: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header Styles */
        .admin-header {
            background: linear-gradient(135deg, var(--dark) 0%, var(--darker) 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            color: var(--primary);
        }

        .admin-nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
        }

        .admin-nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .admin-nav-links a:hover,
        .admin-nav-links a.active {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Main Content */
        main {
            padding: 2rem 0;
        }

        .welcome-section {
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .welcome-section p {
            color: var(--gray);
            font-size: 1.1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: var(--transition);
            border-top: 4px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--dark);
            display: block;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            margin-bottom: 1.5rem;
            color: var(--dark);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h3 i {
            color: var(--primary);
        }

        /* Quick Actions */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            background: white;
            border: 1px solid #eaeaea;
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
            text-align: center;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        .action-btn i {
            font-size: 2rem;
            margin-bottom: 0.8rem;
        }

        .action-btn span {
            font-weight: 500;
        }

        /* System Status */
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-connected {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        /* Recent Activities */
        .activities-container {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .activity-item {
            padding: 1rem;
            border-left: 4px solid var(--primary);
            background: white;
            margin-bottom: 0.8rem;
            border-radius: 0 5px 5px 0;
            transition: var(--transition);
        }

        .activity-item:hover {
            transform: translateX(5px);
        }

        .activity-type {
            font-weight: bold;
            color: var(--primary);
            text-transform: capitalize;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 0.5rem;
        }

        .activity-description {
            margin-bottom: 0.5rem;
        }

        .activity-date {
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .admin-nav-links {
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .admin-nav-links {
                position: fixed;
                top: 70px;
                left: 0;
                width: 100%;
                background: var(--darker);
                flex-direction: column;
                padding: 1rem;
                transform: translateY(-100%);
                opacity: 0;
                transition: var(--transition);
                box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
            }

            .admin-nav-links.active {
                transform: translateY(0);
                opacity: 1;
            }

            .admin-nav-links a {
                padding: 1rem;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }
        }

        /* Scrollbar Styling */
        .activities-container::-webkit-scrollbar {
            width: 6px;
        }

        .activities-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .activities-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .activities-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <nav class="admin-nav">
                <div class="logo">
                    <i class="fas fa-university"></i>
                    <span>BankingKhonde Admin</span>
                </div>
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <ul class="admin-nav-links" id="navLinks">
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="treasurers.php"><i class="fas fa-user-tie"></i> Treasurers</a></li>
                    <li><a href="groups.php"><i class="fas fa-users"></i> Groups</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="welcome-section">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-tie stat-icon"></i>
                <span class="stat-number"><?php echo $stats['total_treasurers']; ?></span>
                <span class="stat-label">Treasurers</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock stat-icon"></i>
                <span class="stat-number"><?php echo $stats['pending_verifications']; ?></span>
                <span class="stat-label">Pending Verifications</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-layer-group stat-icon"></i>
                <span class="stat-number"><?php echo $stats['total_groups']; ?></span>
                <span class="stat-label">Active Groups</span>
            </div>
        </div>

        <div class="content-grid">
            <!-- Quick Actions -->
            <div class="card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="actions-grid">
                    <a href="treasurers.php?filter=pending" class="action-btn">
                        <i class="fas fa-clock"></i>
                        <span>Review Pending Treasurers</span>
                    </a>
                    <a href="groups.php" class="action-btn">
                        <i class="fas fa-users"></i>
                        <span>Manage Groups</span>
                    </a>
                    <a href="treasurers.php" class="action-btn">
                        <i class="fas fa-user-tie"></i>
                        <span>Manage Treasurers</span>
                    </a>
                    <a href="settings.php" class="action-btn">
                        <i class="fas fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </div>
            </div>

            <!-- System Status -->
            <div class="card">
                <h3><i class="fas fa-server"></i> System Status</h3>
                <div>
                    <div class="status-item">
                        <span>Database:</span>
                        <span class="status-badge status-connected">Connected</span>
                    </div>
                    <div class="status-item">
                        <span>Users Online:</span>
                        <span><?php echo $stats['total_users']; ?></span>
                    </div>
                    <div class="status-item">
                        <span>Last Login:</span>
                        <span><?php echo date('M j, g:i A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Recent System Activities</h3>
            <?php if (empty($recent_activities)): ?>
                <p>No recent activities to display.</p>
            <?php else: ?>
                <div class="activities-container">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-type">
                                <i class="fas fa-<?php 
                                    switch($activity['type']) {
                                        case 'user_registration': echo 'user-plus'; break;
                                        case 'group_creation': echo 'users'; break;
                                        case 'payment_processed': echo 'money-bill-wave'; break;
                                        case 'system_update': echo 'sync-alt'; break;
                                        default: echo 'bell';
                                    }
                                ?>"></i>
                                <?php echo str_replace('_', ' ', $activity['type']); ?>
                            </div>
                            <div class="activity-description"><?php echo $activity['description']; ?></div>
                            <small class="activity-date">
                                <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('active');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navLinks = document.getElementById('navLinks');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            
            if (!navLinks.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                navLinks.classList.remove('active');
            }
        });
    </script>
</body>
</html>