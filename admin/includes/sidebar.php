<style>
        .admin-sidebar {
            width: 260px;
            background: white;
            color: white;
            transition: var(--transition);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }
         /* Sidebar Styles */


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
            color: black;
        }

        .logo i {
            color: blue;
            font-size: 1.5rem;
            min-width: 25px;
        }

        .sidebar-toggle {
            
            border: none;
            color: black;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .sidebar-toggle:hover {
            background: rgba(59, 108, 255, 0.66);
        }

        .admin-sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        .admin-sidebar.collapsed .logo span {
            display: none;
        }

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
            color: black;
            text-decoration: none;
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            border-radius: 0;
            margin: 0 0.5rem;
            border-radius: 5px;
        }

        .sidebar-nav-links a:hover,
        .sidebar-nav-links a.active {
            background: rgba(0,123, 255, 1);
            color: white;
        }

        .sidebar-nav-links i {
            width: 20px;
            text-align: center;
        }

        .admin-sidebar.collapsed .link-text {
            display: none;
        }

</style>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-university"></i>
            <span>BankingKhonde</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="sidebar-nav-links">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="link-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="treasurers.php">
                    <i class="fas fa-user-tie"></i>
                    <span class="link-text">Treasurers</span>
                </a>
            </li>
            <li>
                <a href="groups.php">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Groups</span>
                </a>
            </li>
            <li>
                <a href="subscriptions.php">
                    <i class="fas fa-coins"></i>
                    <span class="link-text">Subscriptions</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span class="link-text">Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="link-text">Logout (<?php echo htmlspecialchars($_SESSION['admin_username']); ?>)</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
