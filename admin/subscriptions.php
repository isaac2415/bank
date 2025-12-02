<?php
require_once 'includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'view';
$subscription_id = $_GET['id'] ?? null;
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Process actions if needed (for future functionality)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    
    // Handle different actions here if needed
    // Example: delete subscription, update status, etc.
    
    // Redirect to prevent form resubmission
    header("Location: subscriptions.php?filter=" . urlencode($filter));
    exit;
}

// Build query based on filter and search
$where_conditions = ["1=1"]; // Always true for base condition
$params = [];

// Apply search filter
if (!empty($search)) {
    $where_conditions[] = "(charge_id LIKE ? OR ref_id LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR mobile LIKE ?)";
    $searchParam = "%$search%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchParam;
    }
}

// Apply status filter
switch ($filter) {
    case 'success':
        $where_conditions[] = "status = 'success'";
        break;
    case 'failed':
        $where_conditions[] = "status = 'failed'";
        break;
    case 'pending':
        $where_conditions[] = "status = 'pending'";
        break;
    default:
        // 'all' - no additional conditions
        break;
}

// Date range filter (if implemented in the future)
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    if ($start_date && $end_date) {
        $where_conditions[] = "created_at BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date . ' 23:59:59';
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM subscription WHERE $where_sql";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalPages = ceil($totalRecords / $perPage);

// Get subscription data
$query = "SELECT * FROM subscription 
          WHERE $where_sql
          ORDER BY created_at DESC
          LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics for display
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(amount) as total_amount
    FROM subscription";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get recent subscriptions for chart data (last 30 days)
$recentStatsQuery = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count,
    SUM(amount) as amount
    FROM subscription 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date";
$recentStmt = $db->prepare($recentStatsQuery);
$recentStmt->execute();
$recentStats = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscriptions - BankingKhonde Admin</title>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --info: #17a2b8;
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

        /* Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
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
            color: var(--dark);
        }

        .main-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--dark);
        }

        .main-content {
            padding: 1.5rem;
        }

        /* Container */
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Card Styles */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h2 i {
            color: var(--primary);
        }

        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        }

        .stat-card.success {
            border-top-color: var(--success);
        }

        .stat-card.failed {
            border-top-color: var(--danger);
        }

        .stat-card.pending {
            border-top-color: var(--warning);
        }

        .stat-card.amount {
            border-top-color: var(--info);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .stat-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--info);
            margin-top: 0.5rem;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid transparent;
        }

        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .filter-tab:not(.active) {
            background: white;
            color: var(--gray);
            border-color: #e9ecef;
        }

        .filter-tab:not(.active):hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Search and Filter Bar */
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        /* Date Filter */
        .date-filter {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #ced4da;
        }

        .date-filter input {
            padding: 0.25rem 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.875rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: #212529;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .message-error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 1000px;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-failed {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        /* Chart Container */
        .chart-container {
            margin-bottom: 2rem;
            height: 300px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            border: 1px solid #ced4da;
            background: white;
            color: var(--dark);
            font-weight: 500;
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Action Buttons */
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .btn-group form {
            margin: 0;
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        /* Export Button */
        .export-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--success);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .export-btn:hover {
            background: #218838;
        }

        /* Detail View Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal h3 {
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-group {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .detail-value {
            color: var(--gray);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
                width: 100%;
            }

            .mobile-menu-btn {
                display: block;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .card {
                padding: 1rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
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
                <h1>Manage Subscriptions</h1>
            </header>
            
            <div class="main-content">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="message message-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="message message-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h2><i class="fas fa-credit-card"></i> Subscription Analytics</h2>
                    
                    <!-- Statistics -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Total Subscriptions</div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-number"><?php echo $stats['success']; ?></div>
                            <div class="stat-label">Successful</div>
                        </div>
                        <div class="stat-card failed">
                            <div class="stat-number"><?php echo $stats['failed']; ?></div>
                            <div class="stat-label">Failed</div>
                        </div>
                        <div class="stat-card pending">
                            <div class="stat-number"><?php echo $stats['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-card amount">
                            <div class="stat-number"><?php echo number_format($stats['total_amount']); ?></div>
                            <div class="stat-label">Total Amount (MWK)</div>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div class="chart-container">
                        <canvas id="subscriptionsChart"></canvas>
                    </div>
                    
                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <form method="GET" action="" style="display: inline-block; width: 100%;">
                                <input type="text" name="search" placeholder="Search by Charge ID, Reference, Email, Name..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            </form>
                        </div>
                        
                        <form method="GET" action="" class="date-filter">
                            <input type="date" name="start_date" placeholder="Start Date" 
                                   value="<?php echo $_GET['start_date'] ?? ''; ?>">
                            <span>to</span>
                            <input type="date" name="end_date" placeholder="End Date" 
                                   value="<?php echo $_GET['end_date'] ?? ''; ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <?php if (isset($_GET['start_date']) || isset($_GET['end_date'])): ?>
                                <a href="subscriptions.php?filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="filter-tabs">
                        <a href="subscriptions.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            All (<?php echo $stats['total']; ?>)
                        </a>
                        <a href="subscriptions.php?filter=success" class="filter-tab <?php echo $filter === 'success' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i>
                            Successful (<?php echo $stats['success']; ?>)
                        </a>
                        <a href="subscriptions.php?filter=failed" class="filter-tab <?php echo $filter === 'failed' ? 'active' : ''; ?>">
                            <i class="fas fa-times-circle"></i>
                            Failed (<?php echo $stats['failed']; ?>)
                        </a>
                        <a href="subscriptions.php?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i>
                            Pending (<?php echo $stats['pending']; ?>)
                        </a>
                        
                        <!-- Export Button -->
                        <form method="GET" action="export_subscriptions.php" target="_blank" style="margin-left: auto;">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="export-btn">
                                <i class="fas fa-file-export"></i>
                                Export to CSV
                            </button>
                        </form>
                    </div>

                    <?php if (empty($subscriptions)): ?>
                        <div class="no-data">
                            <i class="fas fa-credit-card"></i>
                            <h3>No Subscriptions Found</h3>
                            <p>No subscriptions match the current filter criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Charge Details</th>
                                        <th>Customer Info</th>
                                        <th>Payment Details</th>
                                        <th>Mobile Money</th>
                                        <th>Status & Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: bold; color: var(--dark);">
                                                <i class="fas fa-hashtag"></i>
                                                <?php echo htmlspecialchars($subscription['charge_id']); ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--gray); margin-top: 4px;">
                                                Ref: <?php echo htmlspecialchars($subscription['ref_id']); ?>
                                            </div>
                                            <?php if ($subscription['trace_id']): ?>
                                                <div style="font-size: 0.75rem; color: #999; margin-top: 2px;">
                                                    Trace: <?php echo substr($subscription['trace_id'], 0, 10) . '...'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: bold; color: var(--dark);">
                                                <?php echo htmlspecialchars($subscription['first_name'] . ' ' . $subscription['last_name']); ?>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 5px; font-size: 0.875rem; color: var(--gray);">
                                                <i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($subscription['email']); ?>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--gray);">
                                                <i class="fas fa-phone"></i>
                                                <?php echo htmlspecialchars($subscription['mobile']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: bold; color: var(--dark); font-size: 1.125rem;">
                                                <?php echo number_format($subscription['amount'], 2); ?> <?php echo htmlspecialchars($subscription['currency']); ?>
                                            </div>
                                            <?php if (isset($subscription['transaction_charges']) && $subscription['transaction_charges']): ?>
                                                <div style="font-size: 0.875rem; color: var(--gray); margin-top: 5px;">
                                                    Fees: <?php echo number_format($subscription['transaction_charges'], 2); ?> <?php echo htmlspecialchars($subscription['currency']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--dark);">
                                                <?php echo htmlspecialchars($subscription['mobile_money_name'] ?? 'Airtel Money'); ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--gray); margin-top: 5px;">
                                                Country: <?php echo htmlspecialchars($subscription['country'] ?? 'Malawi'); ?>
                                            </div>
                                            <?php if ($subscription['mobile_money_trans_id']): ?>
                                                <div style="font-size: 0.75rem; color: #999; margin-top: 2px;">
                                                    Trans ID: <?php echo substr($subscription['mobile_money_trans_id'], 0, 12) . '...'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            $status_icon = '';
                                            switch (strtolower($subscription['status'])) {
                                                case 'success':
                                                    $status_class = 'status-success';
                                                    $status_text = 'Success';
                                                    $status_icon = 'fa-check-circle';
                                                    break;
                                                case 'failed':
                                                    $status_class = 'status-failed';
                                                    $status_text = 'Failed';
                                                    $status_icon = 'fa-times-circle';
                                                    break;
                                                case 'pending':
                                                    $status_class = 'status-pending';
                                                    $status_text = 'Pending';
                                                    $status_icon = 'fa-clock';
                                                    break;
                                                default:
                                                    $status_class = 'status-pending';
                                                    $status_text = ucfirst($subscription['status']);
                                                    $status_icon = 'fa-clock';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <i class="fas <?php echo $status_icon; ?>"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                            
                                            <div style="margin-top: 8px; font-size: 0.875rem;">
                                                <i class="fas fa-calendar-plus"></i>
                                                <?php echo date('M j, Y', strtotime($subscription['created_at'])); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--gray);">
                                                <?php echo date('H:i:s', strtotime($subscription['created_at'])); ?>
                                            </div>
                                            <?php if ($subscription['completed_at'] && $subscription['completed_at'] !== '0000-00-00 00:00:00'): ?>
                                                <div style="font-size: 0.75rem; color: var(--success); margin-top: 4px;">
                                                    <i class="fas fa-calendar-check"></i>
                                                    Completed: <?php echo date('M j, H:i', strtotime($subscription['completed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-info btn-sm" 
                                                        onclick="showSubscriptionDetails(<?php echo htmlspecialchars(json_encode($subscription)); ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                
                                                <?php if ($subscription['status'] === 'success'): ?>
                                                    <a href="#" class="btn btn-success btn-sm">
                                                        <i class="fas fa-receipt"></i> Receipt
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- Add more actions as needed -->
                                                <!-- Example: Resend notification, mark as paid, etc. -->
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="subscriptions.php?filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="active"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="subscriptions.php?filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="subscriptions.php?filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Subscription Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-credit-card"></i> Subscription Details</h3>
            <div id="modalContent">
                <!-- Details will be loaded here -->
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; gap: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
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
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
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

        // Load sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }

        // Close mobile menu on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
            }
        });

        // Chart for subscription analytics
        const recentStats = <?php echo json_encode($recentStats); ?>;
        
        if (recentStats.length > 0) {
            const dates = recentStats.map(stat => stat.date);
            const counts = recentStats.map(stat => stat.count);
            const amounts = recentStats.map(stat => stat.amount);

            const ctx = document.getElementById('subscriptionsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Subscription Count',
                            data: counts,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Total Amount (MWK)',
                            data: amounts,
                            borderColor: '#764ba2',
                            backgroundColor: 'rgba(118, 75, 162, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Amount (MWK)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Total Amount (MWK)') {
                                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' MWK';
                                    }
                                    return context.dataset.label + ': ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    function showSubscriptionDetails(subscription) {
        const modalContent = document.getElementById('modalContent');
        
        let detailsHtml = `
            <div class="detail-group">
                <div class="detail-label">Charge ID</div>
                <div class="detail-value">${subscription.charge_id}</div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Customer Information</div>
                <div class="detail-value">
                    <strong>Name:</strong> ${subscription.first_name} ${subscription.last_name}<br>
                    <strong>Email:</strong> ${subscription.email}<br>
                    <strong>Phone:</strong> ${subscription.mobile}
                </div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Payment Information</div>
                <div class="detail-value">
                    <strong>Amount:</strong> ${parseFloat(subscription.amount).toLocaleString()} ${subscription.currency}<br>
                    <strong>Reference ID:</strong> ${subscription.ref_id}<br>
                    ${subscription.trace_id ? `<strong>Trace ID:</strong> ${subscription.trace_id}<br>` : ''}
                </div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Mobile Money Details</div>
                <div class="detail-value">
                    <strong>Operator:</strong> ${subscription.mobile_money_name || 'Airtel Money'}<br>
                    <strong>Country:</strong> ${subscription.country || 'Malawi'}<br>
                    ${subscription.mobile_money_trans_id ? `<strong>Transaction ID:</strong> ${subscription.mobile_money_trans_id}<br>` : ''}
                </div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">Status Information</div>
                <div class="detail-value">
                    <strong>Status:</strong> <span class="status-badge ${getStatusClass(subscription.status)}">
                        <i class="fas ${getStatusIcon(subscription.status)}"></i>
                        ${subscription.status}
                    </span><br>
                    <strong>Created:</strong> ${formatDateTime(subscription.created_at)}<br>
                    ${subscription.completed_at && subscription.completed_at !== '0000-00-00 00:00:00' ? 
                        `<strong>Completed:</strong> ${formatDateTime(subscription.completed_at)}<br>` : ''}
                </div>
            </div>
            
            ${subscription.transaction_charges ? `
            <div class="detail-group">
                <div class="detail-label">Transaction Charges</div>
                <div class="detail-value">
                    ${parseFloat(subscription.transaction_charges).toLocaleString()} ${subscription.currency}
                </div>
            </div>` : ''}
        `;
        
        modalContent.innerHTML = detailsHtml;
        document.getElementById('detailsModal').style.display = 'flex';
    }
    
    function closeDetailsModal() {
        document.getElementById('detailsModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
    
    function getStatusClass(status) {
        switch(status.toLowerCase()) {
            case 'success': return 'status-success';
            case 'failed': return 'status-failed';
            case 'pending': return 'status-pending';
            default: return 'status-pending';
        }
    }
    
    function getStatusIcon(status) {
        switch(status.toLowerCase()) {
            case 'success': return 'fa-check-circle';
            case 'failed': return 'fa-times-circle';
            case 'pending': return 'fa-clock';
            default: return 'fa-clock';
        }
    }
    
    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    </script>
</body>
</html>