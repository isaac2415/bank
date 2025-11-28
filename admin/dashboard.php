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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
        }
        
        .admin-nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .admin-nav-links a:hover,
        .admin-nav-links a.active {
            background: rgba(255,255,255,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .activity-item {
            padding: 1rem;
            border-left: 4px solid #667eea;
            background: white;
            margin-bottom: 0.5rem;
            border-radius: 0 5px 5px 0;
        }
        
        .activity-type {
            font-weight: bold;
            color: #667eea;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <nav class="admin-nav">
                <div class="logo" style="color: white;">BankingKhonde Admin</div>
                <ul class="admin-nav-links">
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="treasurers.php">Treasurers</a></li>
                    <li><a href="groups.php">Groups</a></li>
                    <li><a href="settings.php">Settings</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h1>Admin Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_treasurers']; ?></span>
                <span class="stat-label">Treasurers</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['pending_verifications']; ?></span>
                <span class="stat-label">Pending Verifications</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_groups']; ?></span>
                <span class="stat-label">Active Groups</span>
            </div>

        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- Quick Actions -->
            <div class="card">
                <h3>Quick Actions</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <a href="treasurers.php?filter=pending" class="btn btn-warning">
                        ⏳ Review Pending Treasurers
                    </a>
                    <a href="groups.php" class="btn btn-primary">
                        👥 Manage Groups
                    </a>
                    <a href="treasurers.php" class="btn btn-success">
                        👑 Manage Treasurers
                    </a>
                    <a href="settings.php" class="btn btn-secondary">
                        ⚙️ System Settings
                    </a>
                </div>
            </div>

            <!-- System Status -->
            <div class="card">
                <h3>System Status</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Database:</span>
                        <span class="status-badge status-paid">Connected</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Users Online:</span>
                        <span><?php echo $stats['total_users']; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Last Login:</span>
                        <span><?php echo date('M j, g:i A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card" style="margin-top: 2rem;">
            <h3>Recent System Activities</h3>
            <?php if (empty($recent_activities)): ?>
                <p>No recent activities to display.</p>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-type">
                                <?php echo str_replace('_', ' ', $activity['type']); ?>
                            </div>
                            <div><?php echo $activity['description']; ?></div>
                            <small style="color: #666;">
                                <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>