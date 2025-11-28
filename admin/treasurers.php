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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-inactive {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }
        
        textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-family: inherit;
            resize: vertical;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 10px;
            min-width: 400px;
            max-width: 500px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .stat-card.pending {
            border-left-color: #ffc107;
        }
        
        .stat-card.verified {
            border-left-color: #28a745;
        }
        
        .stat-card.rejected {
            border-left-color: #dc3545;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .verification-info {
            font-size: 0.75rem;
            color: #666;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .filter-tabs {
                flex-direction: column;
            }
            
            .modal-content {
                min-width: 90%;
                margin: 1rem;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Manage Treasurers</h2>
            
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
                <a href="treasurers.php?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
                    All Treasurers (<?php echo $stats['total']; ?>)
                </a>
                <a href="treasurers.php?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Pending Verification (<?php echo $stats['pending']; ?>)
                </a>
                <a href="treasurers.php?filter=verified" class="btn <?php echo $filter === 'verified' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Verified (<?php echo $stats['verified']; ?>)
                </a>
                <a href="treasurers.php?filter=rejected" class="btn <?php echo $filter === 'rejected' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Rejected (<?php echo $stats['rejected']; ?>)
                </a>
                <a href="treasurers.php?filter=inactive" class="btn <?php echo $filter === 'inactive' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Inactive
                </a>
            </div>

            <?php if (empty($treasurers)): ?>
                <div class="no-data">
                    <h3>No Treasurers Found</h3>
                    <p>No treasurers match the current filter criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>User Info</th>
                                <th>Contact</th>
                                <th>Groups & Loans</th>
                                <th>Verification Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treasurers as $treasurer): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($treasurer['full_name']); ?></strong>
                                    <div style="font-size: 0.875rem; color: #666;">
                                        @<?php echo htmlspecialchars($treasurer['username']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #999;">
                                        ID: <?php echo $treasurer['id']; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($treasurer['email']); ?>
                                    <?php if ($treasurer['phone']): ?>
                                        <div style="font-size: 0.875rem; color: #666;">
                                            📞 <?php echo htmlspecialchars($treasurer['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><strong><?php echo $treasurer['group_count']; ?></strong> groups</div>
                                    <div style="font-size: 0.875rem; color: #666;">
                                        <strong><?php echo $treasurer['loan_count']; ?></strong> loans
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($treasurer['verified']) {
                                        case 'yes':
                                            $status_class = 'status-verified';
                                            $status_text = 'Verified';
                                            break;
                                        case NULL:
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            break;
                                        case 'rejected':
                                            $status_class = 'status-rejected';
                                            $status_text = 'Rejected';
                                            break;
                                        default:
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                    
                                    <?php if ($treasurer['verified'] === 'yes' && $treasurer['verified_at']): ?>
                                        <div class="verification-info">
                                            Verified on: <?php echo date('M j, Y', strtotime($treasurer['verified_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!$treasurer['is_active']): ?>
                                        <span class="status-badge status-inactive" style="margin-top: 0.25rem;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($treasurer['created_at'])); ?>
                                    <?php if ($treasurer['last_login']): ?>
                                        <div style="font-size: 0.75rem; color: #666;">
                                            Last login: <?php echo date('M j, Y', strtotime($treasurer['last_login'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 0.75rem; color: #999;">
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
                                                    ✅ Verify User
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    onclick="showRejectModal(<?php echo $treasurer['id']; ?>, '<?php echo addslashes($treasurer['full_name']); ?>')">
                                                ❌ Reject
                                            </button>
                                        <?php elseif ($treasurer['verified'] === 'yes'): ?>
                                            <span class="status-badge status-verified" style="font-size: 0.7rem; display: block;">
                                                ✅ Verified
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- ACTIVATE/DEACTIVATE BUTTONS - Separate from verification -->
                                        <?php if ($treasurer['is_active']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="deactivate_user">
                                                <input type="hidden" name="user_id" value="<?php echo $treasurer['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" 
                                                        onclick="return confirm('Deactivate <?php echo addslashes($treasurer['full_name']); ?>? They will not be able to login.')">
                                                    ⚠️ Deactivate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="activate_user">
                                                <input type="hidden" name="user_id" value="<?php echo $treasurer['id']; ?>">
                                                <button type="submit" class="btn btn-info btn-sm" 
                                                        onclick="return confirm('Activate <?php echo addslashes($treasurer['full_name']); ?>? They will be able to login again.')">
                                                    🔓 Activate
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
            <h3>Reject Treasurer Application</h3>
            <p id="rejectUserName" style="margin-bottom: 1rem; font-weight: bold;"></p>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="action" value="reject_treasurer">
                <input type="hidden" name="user_id" id="rejectUserId">
                
                <div class="form-group">
                    <label for="rejectReason">Reason for Rejection:</label>
                    <textarea id="rejectReason" name="reason" rows="4" placeholder="Enter reason for rejection (optional)..." style="width: 100%;"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showRejectModal(userId, userName) {
        document.getElementById('rejectUserId').value = userId;
        document.getElementById('rejectUserName').textContent = 'Reject application for: ' + userName;
        document.getElementById('rejectModal').style.display = 'block';
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