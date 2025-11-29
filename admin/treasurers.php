<?php
require_once 'includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'view';
$user_id = $_GET['id'] ?? null;
$filter = $_GET['filter'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    
    switch ($action) {
        case 'verify_treasurer':
            verifyTreasurer($db);
            break;
        case 'reject_treasurer':
            rejectTreasurer($db);
            break;
        case 'deactivate_user':
            deactivateUser($db);
            break;
        case 'activate_user':
            activateUser($db);
            break;
        case 'bulk_verify':
            bulkVerifyTreasurers($db);
            break;
    }
    
    // Redirect to prevent form resubmission
    header("Location: treasurers.php?filter=" . urlencode($filter));
    exit;
}

function verifyTreasurer($db) {
    $user_id = $_POST['user_id'];
    
    try {
        $query = "UPDATE `users` SET `verified` = 'yes', `verified_at` = NOW() WHERE `id` = ? AND `role` = 'treasurer'";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = "Treasurer verified successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error verifying treasurer: " . $e->getMessage();
    }
}

function bulkVerifyTreasurers($db) {
    if (!empty($_POST['treasurer_ids'])) {
        $treasurer_ids = $_POST['treasurer_ids'];
        $placeholders = str_repeat('?,', count($treasurer_ids) - 1) . '?';
        
        try {
            $query = "UPDATE `users` SET `verified` = 'yes', `verified_at` = NOW() 
                     WHERE `id` IN ($placeholders) AND `role` = 'treasurer' AND `verified` = 'no'";
            $stmt = $db->prepare($query);
            $stmt->execute($treasurer_ids);
            
            $count = $stmt->rowCount();
            $_SESSION['success'] = "Successfully verified $count treasurer(s)!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error verifying treasurers: " . $e->getMessage();
        }
    }
}

function rejectTreasurer($db) {
    $user_id = $_POST['user_id'];
    $reason = $_POST['reason'] ?? 'Not meeting requirements';
    
    try {
        $query = "UPDATE `users` SET `verified` = 'rejected', `is_active` = 0 WHERE `id` = ? AND `role` = 'treasurer'";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = "Treasurer application rejected!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error rejecting treasurer: " . $e->getMessage();
    }
}

function deactivateUser($db) {
    $user_id = $_POST['user_id'];
    
    try {
        $query = "UPDATE `users` SET `is_active` = 0 WHERE `id` = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = "User deactivated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deactivating user: " . $e->getMessage();
    }
}

function activateUser($db) {
    $user_id = $_POST['user_id'];
    
    try {
        $query = "UPDATE `users` SET `is_active` = 1 WHERE `id` = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = "User activated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error activating user: " . $e->getMessage();
    }
}

// Build query based on filter
$where_conditions = ["u.`role` = 'treasurer'"];
$params = [];

switch ($filter) {
    case 'pending':
        $where_conditions[] = "u.`verified` = NULL";
        break;
    case 'verified':
        $where_conditions[] = "u.`verified` = 'yes'";
        break;
    case 'rejected':
        $where_conditions[] = "u.`verified` = 'rejected'";
        break;
    case 'inactive':
        $where_conditions[] = "u.`is_active` = 0";
        break;
    default:
        // 'all' - no additional conditions
        break;
}

$where_sql = implode(' AND ', $where_conditions);
$query = "SELECT u.*, 
          COUNT(g.`id`) as group_count,
          (SELECT COUNT(*) FROM `loans` WHERE `user_id` = u.`id`) as loan_count
          FROM `users` u
          LEFT JOIN `groups` g ON u.`id` = g.`treasurer_id`
          WHERE $where_sql
          GROUP BY u.`id`
          ORDER BY u.`created_at` DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$treasurers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count statistics for display
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN `verified` = NULL THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN `verified` = 'yes' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN `verified` = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM `users` 
    WHERE `role` = 'treasurer'";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Treasurers - BankingKhonde Admin</title>
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

        .stat-card.pending {
            border-top-color: var(--warning);
        }

        .stat-card.verified {
            border-top-color: var(--success);
        }

        .stat-card.rejected {
            border-top-color: var(--danger);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--dark);
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
            min-width: 800px;
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

        .status-verified {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .status-inactive {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
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

        .verification-info {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.25rem;
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

        /* Modal Styles */
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
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .modal h3 {
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal h3 i {
            color: var(--danger);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-family: inherit;
            resize: vertical;
            transition: var(--transition);
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .filter-tabs {
                flex-direction: column;
            }
            
            .filter-tab {
                justify-content: center;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
            
            .modal-actions {
                flex-direction: column;
            }
            
            .btn-group {
                flex-direction: row;
                flex-wrap: wrap;
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
    <?php include 'includes/header.php'; ?>

    <main class="container">
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
            <h2><i class="fas fa-user-tie"></i> Manage Treasurers</h2>
            
            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div>Total Treasurers</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div>Pending Verification</div>
                </div>
                <div class="stat-card verified">
                    <div class="stat-number"><?php echo $stats['verified']; ?></div>
                    <div>Verified</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                    <div>Rejected</div>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="treasurers.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    All Treasurers (<?php echo $stats['total']; ?>)
                </a>
                <a href="treasurers.php?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    Pending (<?php echo $stats['pending']; ?>)
                </a>
                <a href="treasurers.php?filter=verified" class="filter-tab <?php echo $filter === 'verified' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    Verified (<?php echo $stats['verified']; ?>)
                </a>
                <a href="treasurers.php?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i>
                    Rejected (<?php echo $stats['rejected']; ?>)
                </a>
                <a href="treasurers.php?filter=inactive" class="filter-tab <?php echo $filter === 'inactive' ? 'active' : ''; ?>">
                    <i class="fas fa-user-slash"></i>
                    Inactive
                </a>
            </div>

            <?php if (empty($treasurers)): ?>
                <div class="no-data">
                    <i class="fas fa-user-times"></i>
                    <h3>No Treasurers Found</h3>
                    <p>No treasurers match the current filter criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User Information</th>
                                <th>Contact Details</th>
                                <th>Groups & Loans</th>
                                <th>Status</th>
                                <th>Registration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treasurers as $treasurer): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($treasurer['full_name']); ?></strong>
                                    <div style="font-size: 0.875rem; color: var(--gray);">
                                        <i class="fas fa-user"></i>
                                        @<?php echo htmlspecialchars($treasurer['username']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #999;">
                                        ID: <?php echo $treasurer['id']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($treasurer['email']); ?>
                                    </div>
                                    <?php if ($treasurer['phone']): ?>
                                        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--gray);">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($treasurer['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                        <i class="fas fa-users"></i>
                                        <strong><?php echo $treasurer['group_count']; ?></strong> groups
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--gray);">
                                        <i class="fas fa-hand-holding-usd"></i>
                                        <strong><?php echo $treasurer['loan_count']; ?></strong> loans
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    $status_icon = '';
                                    switch ($treasurer['verified']) {
                                        case 'yes':
                                            $status_class = 'status-verified';
                                            $status_text = 'Verified';
                                            $status_icon = 'fa-check-circle';
                                            break;
                                        case NULL:
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            $status_icon = 'fa-clock';
                                            break;
                                        case 'rejected':
                                            $status_class = 'status-rejected';
                                            $status_text = 'Rejected';
                                            $status_icon = 'fa-times-circle';
                                            break;
                                        default:
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            $status_icon = 'fa-clock';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                    
                                    <?php if ($treasurer['verified'] === 'yes' && $treasurer['verified_at']): ?>
                                        <div class="verification-info">
                                            <i class="fas fa-calendar-check"></i>
                                            Verified: <?php echo date('M j, Y', strtotime($treasurer['verified_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$treasurer['is_active']): ?>
                                        <span class="status-badge status-inactive" style="margin-top: 0.25rem;">
                                            <i class="fas fa-user-slash"></i>
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                        <i class="fas fa-calendar-plus"></i>
                                        <?php echo date('M j, Y', strtotime($treasurer['created_at'])); ?>
                                    </div>
                                    <?php if ($treasurer['last_login']): ?>
                                        <div style="font-size: 0.75rem; color: var(--gray);">
                                            <i class="fas fa-sign-in-alt"></i>
                                            Last login: <?php echo date('M j, Y', strtotime($treasurer['last_login'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 0.75rem; color: #999;">
                                            <i class="fas fa-times"></i>
                                            Never logged in
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <!-- VERIFY BUTTON - Shows ONLY for users who are NOT verified (verified = 'no') -->
                                        <?php if ($treasurer['verified'] === NULL): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="verify_treasurer">
                                                <input type="hidden" name="user_id" value="<?php echo $treasurer['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Verify <?php echo addslashes($treasurer['full_name']); ?> as treasurer? This will grant them access to manage groups and loans.')">
                                                    <i class="fas fa-check"></i> Verify
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    onclick="showRejectModal(<?php echo $treasurer['id']; ?>, '<?php echo addslashes($treasurer['full_name']); ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php elseif ($treasurer['verified'] === 'yes'): ?>
                                            <span class="status-badge status-verified" style="font-size: 0.7rem;">
                                                <i class="fas fa-check"></i> Verified
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- ACTIVATE/DEACTIVATE BUTTONS - Separate from verification -->
                                        <?php if ($treasurer['is_active']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="deactivate_user">
                                                <input type="hidden" name="user_id" value="<?php echo $treasurer['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" 
                                                        onclick="return confirm('Deactivate <?php echo addslashes($treasurer['full_name']); ?>? They will not be able to login.')">
                                                    <i class="fas fa-user-slash"></i> Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="activate_user">
                                                <input type="hidden" name="user_id" value="<?php echo $treasurer['id']; ?>">
                                                <button type="submit" class="btn btn-info btn-sm" 
                                                        onclick="return confirm('Activate <?php echo addslashes($treasurer['full_name']); ?>? They will be able to login again.')">
                                                    <i class="fas fa-user-check"></i> Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-times-circle"></i> Reject Treasurer Application</h3>
            <p id="rejectUserName" style="margin-bottom: 1rem; font-weight: bold; color: var(--dark);"></p>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject_treasurer">
                <input type="hidden" name="user_id" id="rejectUserId">
                
                <div class="form-group">
                    <label for="rejectReason">Reason for Rejection:</label>
                    <textarea id="rejectReason" name="reason" rows="4" placeholder="Enter reason for rejection (optional)..." style="width: 100%;"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reject Application
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showRejectModal(userId, userName) {
        document.getElementById('rejectUserId').value = userId;
        document.getElementById('rejectUserName').textContent = 'Reject application for: ' + userName;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
        document.getElementById('rejectReason').value = '';
    }
    
    // Close modal when clicking outside
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
    </script>
</body>
</html>