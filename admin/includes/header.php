<?php
// Simple header include for admin pages
?>
<header class="admin-header">
    <div class="container">
        <nav class="admin-nav">
            <div class="logo" style="color: white;">BankingKhonde Admin</div>
            <ul class="admin-nav-links">
                <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="treasurers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'treasurers.php' ? 'active' : ''; ?>">Treasurers</a></li>
                <li><a href="groups.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'groups.php' ? 'active' : ''; ?>">Groups</a></li>
                <li><a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">Settings</a></li>
                <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['admin_username']); ?>)</a></li>
            </ul>
        </nav>
    </div>
    <style>
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
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
        
    </style>
</header>