<?php
// Simple header include for admin pages
?>
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
                <li>
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="treasurers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'treasurers.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-tie"></i>
                        Treasurers
                    </a>
                </li>
                <li>
                    <a href="groups.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'groups.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        Groups
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout (<?php echo htmlspecialchars($_SESSION['admin_username']); ?>)
                    </a>
                </li>
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            color: white;
        }
        
        .logo i {
            color: #667eea;
        }
        
        .admin-nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            margin: 0;
        }
        
        .admin-nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .admin-nav-links a:hover,
        .admin-nav-links a.active {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
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
                background: #1a2530;
                flex-direction: column;
                padding: 1rem;
                transform: translateY(-100%);
                opacity: 0;
                transition: all 0.3s ease;
                box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
                gap: 0;
            }
            
            .admin-nav-links.active {
                transform: translateY(0);
                opacity: 1;
            }
            
            .admin-nav-links li {
                width: 100%;
            }
            
            .admin-nav-links a {
                padding: 1rem;
                justify-content: center;
                border-radius: 5px;
                margin-bottom: 0.5rem;
            }
            
            .admin-nav-links a:hover {
                transform: none;
            }
        }
        
        @media (max-width: 576px) {
            .logo span {
                font-size: 1.2rem;
            }
            
            .logo i {
                font-size: 1.3rem;
            }
        }
    </style>
    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const navLinks = document.getElementById('navLinks');
            
            if (mobileMenuBtn && navLinks) {
                mobileMenuBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('active');
                });
                
                // Close mobile menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!navLinks.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                        navLinks.classList.remove('active');
                    }
                });
            }
        });
    </script>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
</header>