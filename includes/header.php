<?php
// This file is included in all pages after login
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ====== MODERN RESPONSIVE HEADER ====== */
        :root {
            --primary: #4361ee;
            --primary-dark: #354ad8;
            --primary-light: #5888ff;
            --bg-glass: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.35);
            --dark: #1e1f26;
            --gray: #70798c;
            --radius: 12px;
            --transition: all 0.25s ease;
            --shadow-soft: 0px 10px 25px rgba(0,0,0,0.08);
            --danger: #ff4d6d;
            --success: #00c853;
        }

        /* Header with glass effect */
        header {
            backdrop-filter: blur(10px);
            background: var(--bg-glass);
            border-bottom: 1px solid var(--glass-border);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-soft);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* LOGO */
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 0.5px;
            text-shadow: 0px 1px 2px rgba(0,0,0,0.05);
        }

        .logo i {
            font-size: 1.9rem;
        }

        /* NAV LINKS */
        .nav-links1 {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 0.5rem;
            padding: 0;
            margin: 0;
        }

        .nav-link1 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            border-radius: var(--radius);
            transition: var(--transition);
            font-size: 0.95rem;
            white-space: nowrap;
        }

        .nav-link1:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-soft);
        }

        .nav-link1.active {
            background: var(--primary-dark);
            color: white;
            box-shadow: var(--shadow-soft);
        }

        .nav-link1 i {
            font-size: 1.15rem;
        }

        /* DROPDOWN */
        .nav-dropdown {
            position: relative;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1rem;
            background: white;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--glass-border);
        }

        .user-menu:hover {
            background: var(--primary-light);
            color: white;
        }

        /* Avatar */
        .user-avatar {
            width: 34px;
            height: 34px;
            background: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--gray);
        }

        /* Dropdown content */
        .dropdown-content {
            display: none;
            position: absolute;
            top: 110%;
            right: 0;
            background: white;
            min-width: 230px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            animation: fadeIn 0.25s ease;
            z-index: 1000;
        }

        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.85rem 1rem;
            text-decoration: none;
            color: var(--dark);
            font-size: 0.95rem;
            border-bottom: 1px solid var(--glass-border);
            transition: var(--transition);
        }

        .dropdown-content a:hover {
            background: var(--primary);
            color: white;
        }

        .dropdown-content a:last-child {
            border-bottom: none;
            color: var(--danger);
        }

        .dropdown-content a:last-child:hover {
            background: var(--danger);
            color: white;
        }

        .nav-dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-icon {
            transition: var(--transition);
            font-size: 0.8rem;
        }

        .nav-dropdown:hover .dropdown-icon {
            transform: rotate(180deg);
        }

        /* Notification Badge */
        .notification-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            position: absolute;
            top: 5px;
            right: 5px;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-7px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* MOBILE MENU TOGGLE */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .mobile-toggle:hover {
            background: var(--primary-light);
            color: white;
        }

        /* ====== RESPONSIVE DESIGN ====== */
        
        /* Tablet */
        @media (max-width: 1024px) {
            nav {
                padding: 1rem 1.5rem;
            }
            
            .nav-links1 {
                gap: 0.3rem;
            }
            
            .nav-link1 {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .nav-link1 span {
                display: none;
            }
            
            .user-info {
                display: none;
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }
            
            .nav-links1 {
                position: fixed;
                top: 80px;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 1.5rem;
                box-shadow: var(--shadow-soft);
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: var(--transition);
                z-index: 999;
                gap: 0.5rem;
            }
            
            .nav-links1.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }
            
            .nav-link1 {
                width: 100%;
                justify-content: flex-start;
                padding: 1rem 1.5rem;
            }
            
            .nav-link1 span {
                display: inline;
            }
            
            .user-info {
                display: flex;
            }
            
            .nav-dropdown .dropdown-content {
                position: static;
                box-shadow: none;
                background: var(--bg-glass);
                margin-top: 0.5rem;
                display: none;
                width: 100%;
            }
            
            .nav-dropdown.active .dropdown-content {
                display: block;
            }
        }

        /* Small Mobile */
        @media (max-width: 480px) {
            nav {
                padding: 0.75rem 1rem;
            }
            
            .logo {
                font-size: 1.4rem;
            }
            
            .logo i {
                font-size: 1.6rem;
            }
            
            .nav-links1 {
                top: 70px;
                padding: 1rem;
            }
            
            .nav-link1 {
                padding: 0.875rem 1.25rem;
                font-size: 0.9rem;
            }
        }

        /* Extra small devices */
        @media (max-width: 360px) {
            .logo span {
                font-size: 1.2rem;
            }
            
            .logo i {
                font-size: 1.4rem;
            }
            
            .user-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <i class="fas fa-piggy-bank"></i>
                <span>BankingKhonde</span>
            </div>
            
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <ul class="nav-links1" id="navLinks">
                <li>
                    <a href="dashboard.php" class="nav-link1 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="groups.php" class="nav-link1 <?php echo basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>My Groups</span>
                    </a>
                </li>
                <li>
                    <a href="loans.php" class="nav-link1 <?php echo basename($_SERVER['PHP_SELF']) == 'loans.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Loans</span>
                        <?php if (isset($pending_loans) && $pending_loans > 0): ?>
                            <span class="notification-badge"><?php echo $pending_loans; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="payments.php" class="nav-link1 <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="nav-link1 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-dropdown" id="userDropdown">
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                        </div>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </div>
                    <div class="dropdown-content">
                        <a href="profile.php">
                            <i class="fas fa-user"></i>
                            Profile
                        </a>
                        <?php if ($_SESSION['role'] === 'treasurer'): ?>
                        <a href="groups.php?action=create">
                            <i class="fas fa-plus-circle"></i>
                            Create Group
                        </a>
                        <?php endif; ?>
                        <a href="groups.php?action=join">
                            <i class="fas fa-link"></i>
                            Join Group
                        </a>
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
    </header>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('active');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navLinks = document.getElementById('navLinks');
            const mobileToggle = document.getElementById('mobileToggle');
            const userDropdown = document.getElementById('userDropdown');
            
            if (!navLinks.contains(event.target) && !mobileToggle.contains(event.target)) {
                navLinks.classList.remove('active');
            }
            
            // Close user dropdown if clicking outside on mobile
            if (window.innerWidth <= 768 && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('active');
            }
        });

        // User dropdown for mobile
        document.getElementById('userDropdown').addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                event.preventDefault();
                this.classList.toggle('active');
            }
        });

        // Close dropdowns when clicking on links
        document.querySelectorAll('.dropdown-content a').forEach(link => {
            link.addEventListener('click', function() {
                document.getElementById('navLinks').classList.remove('active');
                document.getElementById('userDropdown').classList.remove('active');
            });
        });

        // Close menu when window is resized above mobile breakpoint
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('navLinks').classList.remove('active');
                document.getElementById('userDropdown').classList.remove('active');
            }
        });
    </script>
</body>
</html>