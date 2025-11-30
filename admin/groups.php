<?php
require_once 'includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? 'view';
$group_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    
    switch ($action) {
        case 'delete_group':
            deleteGroup($db);
            break;
        case 'update_group_status':
            updateGroupStatus($db);
            break;
    }
}

function deleteGroup($db) {
    $group_id = $_POST['group_id'];
    
    try {
        $db->beginTransaction();
        
        // Delete related records first
        $tables = ['announcements', 'chat_messages', 'group_rules', 'payments', 'loans', 'meetings', 'group_members'];
        foreach ($tables as $table) {
            $query = "DELETE FROM $table WHERE group_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id]);
        }
        
        // Delete the group
        $query = "DELETE FROM `groups` WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        
        $db->commit();
        $_SESSION['success'] = "Group deleted successfully";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error deleting group: " . $e->getMessage();
    }
}

function updateGroupStatus($db) {
    $group_id = $_POST['group_id'];
    $status = $_POST['status'];
    
    try {
        $query = "UPDATE `groups` SET status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$status, $group_id]);
        
        $_SESSION['success'] = "Group status updated successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating group status: " . $e->getMessage();
    }
}

// Get all groups with treasurer info
$query = "SELECT g.*, 
          u.full_name as treasurer_name, 
          u.username as treasurer_username,
          u.verified as treasurer_verified,
          COUNT(DISTINCT gm.user_id) as member_count,
          COUNT(DISTINCT l.id) as loan_count,
          COALESCE(SUM(p.amount), 0) as total_contributions
          FROM `groups` g
          JOIN users u ON g.treasurer_id = u.id
          LEFT JOIN group_members gm ON g.id = gm.group_id
          LEFT JOIN loans l ON g.id = l.group_id
          LEFT JOIN payments p ON g.id = p.group_id AND p.status = 'paid'
          GROUP BY g.id
          ORDER BY g.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Groups - BankingKhonde Admin</title>
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
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        /* Group Info */
        .group-info-main {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .group-info-code {
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 500;
            background: rgba(102, 126, 234, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
            margin-top: 0.25rem;
        }

        .group-info-details {
            color: var(--gray);
            font-size: 0.75rem;
            margin-top: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        /* Treasurer Info */
        .treasurer-info-main {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .treasurer-info-username {
            color: var(--gray);
            font-size: 0.875rem;
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

        /* Stats */
        .stats-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stats-main {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-details {
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Financials */
        .financial-amount {
            font-weight: 600;
            color: var(--success);
            font-size: 1.1rem;
        }

        .financial-details {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Status Select */
        .status-select {
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 5px;
            background: white;
            font-size: 0.875rem;
            min-width: 120px;
            transition: var(--transition);
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            text-align: center;
            justify-content: center;
        }

        .btn-view {
            background: var(--primary);
            color: white;
        }

        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-delete {
            background: var(--danger);
            color: white;
        }

        .btn-delete:hover {
            background: #c53030;
            transform: translateY(-2px);
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

        .no-data h3 {
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .card {
                padding: 1rem;
            }
            
            .table-container {
                border-radius: 5px;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
            
            .action-buttons {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .btn {
                flex: 1;
                min-width: 120px;
            }
        }

        @media (max-width: 576px) {
            .card h2 {
                font-size: 1.3rem;
            }
            
            .no-data {
                padding: 2rem 1rem;
            }
            
            .no-data i {
                font-size: 2.5rem;
            }
        }
    </style>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
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
            <h2><i class="fas fa-users"></i> Manage Groups</h2>
            
            <?php if (empty($groups)): ?>
                <div class="no-data">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Groups Found</h3>
                    <p>There are no groups in the system yet.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Group Information</th>
                                <th>Treasurer</th>
                                <th>Statistics</th>
                                <th>Financials</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                            <tr>
                                <td>
                                    <div class="group-info-main">
                                        <i class="fas fa-users"></i>
                                        <?php echo htmlspecialchars($group['name']); ?>
                                    </div>
                                    <div class="group-info-code">
                                        <i class="fas fa-hashtag"></i>
                                        <?php echo $group['code']; ?>
                                    </div>
                                    <div class="group-info-details">
                                        <div>
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php 
                                            $frequency_map = [
                                                'weekly_once' => 'Weekly Once',
                                                'weekly_twice' => 'Weekly Twice', 
                                                'monthly_thrice' => 'Monthly Thrice'
                                            ];
                                            echo $frequency_map[$group['meeting_frequency']] ?? $group['meeting_frequency'];
                                            ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-money-bill-wave"></i>
                                            K<?php echo number_format($group['contribution_amount'], 2); ?> per contribution
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="treasurer-info-main">
                                        <i class="fas fa-user-tie"></i>
                                        <?php echo htmlspecialchars($group['treasurer_name']); ?>
                                    </div>
                                    <div class="treasurer-info-username">
                                        @<?php echo htmlspecialchars($group['treasurer_username']); ?>
                                    </div>
                                    <span class="status-badge <?php echo $group['treasurer_verified'] === 'yes' ? 'status-verified' : 'status-pending'; ?>" style="margin-top: 0.5rem;">
                                        <i class="fas <?php echo $group['treasurer_verified'] === 'yes' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                        <?php echo $group['treasurer_verified'] === 'yes' ? 'Verified' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stats-item">
                                        <div class="stats-main">
                                            <i class="fas fa-users"></i>
                                            <?php echo $group['member_count']; ?> Members
                                        </div>
                                        <div class="stats-details">
                                            <i class="fas fa-hand-holding-usd"></i>
                                            <?php echo $group['loan_count']; ?> Active Loans
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="financial-amount">
                                        <i class="fas fa-wallet"></i>
                                        K <?php echo number_format($group['total_contributions'], 2); ?>
                                    </div>
                                    <div class="financial-details">
                                        <i class="fas fa-percentage"></i>
                                        <?php echo $group['interest_rate']; ?>% Interest Rate
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_group_status">
                                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="active" <?php echo $group['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="ended" <?php echo $group['status'] === 'ended' ? 'selected' : ''; ?>>Ended</option>
                                            <option value="restarted" <?php echo $group['status'] === 'restarted' ? 'selected' : ''; ?>>Restarted</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px; color: var(--dark); font-weight: 500;">
                                        <i class="fas fa-calendar-plus"></i>
                                        <?php echo date('M j, Y', strtotime($group['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- <a href="../pages/groups.php?action=view&id=<?php echo $group['id']; ?>" 
                                           class="btn btn-view" target="_blank">
                                            <i class="fas fa-eye"></i>
                                            View Group
                                        </a> -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <button type="submit" class="btn btn-delete" 
                                                    onclick="return confirm('WARNING: This will permanently delete this group and all its data. This action cannot be undone. Are you sure?')">
                                                <i class="fas fa-trash"></i>
                                                Delete Group
                                            </button>
                                        </form>
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
</body>
</html>