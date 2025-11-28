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
        
        .groups-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .groups-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            color: #2d3748;
        }
        
        .groups-table td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
        }
        
        .groups-table tr:hover {
            background: #f8f9fa;
        }
        
        .group-info-main {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }
        
        .group-info-code {
            color: #667eea;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .group-info-details {
            color: #718096;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .treasurer-info-main {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }
        
        .treasurer-info-username {
            color: #718096;
            font-size: 0.875rem;
        }
        
        .status-select {
            padding: 0.5rem;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            background: white;
            font-size: 0.875rem;
            min-width: 120px;
        }
        
        .status-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .btn-view {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.875rem;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .btn-view:hover {
            background: #5a6fd8;
        }
        
        .btn-delete {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-delete:hover {
            background: #c53030;
        }
        
        .stats-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .status-paid {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .financial-amount {
            font-weight: 600;
            color: #2d3748;
        }
        
        .financial-details {
            color: #718096;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }
        
        .no-data h3 {
            margin-bottom: 0.5rem;
            color: #4a5568;
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .message-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .message-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #feb2b2;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <!-- <header class="admin-header">
        <div class="container">
            <nav class="admin-nav">
                <div style="font-size: 1.5rem; font-weight: bold; color: white;">BankingKhonde Admin</div>
                <ul class="admin-nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="treasurers.php">Treasurers</a></li>
                    <li><a href="groups.php" style="background: rgba(255,255,255,0.1);">Groups</a></li>
                    <li><a href="settings.php">Settings</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header> -->

    <main class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin: 0 0 1rem 0; color: #2d3748; font-size: 1.5rem;">Manage Groups</h2>
            
            <?php if (empty($groups)): ?>
                <div class="no-data">
                    <h3>No Groups Found</h3>
                    <p>There are no groups in the system yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="groups-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Group Info</th>
                                <th style="width: 18%;">Treasurer</th>
                                <th style="width: 12%;">Members & Loans</th>
                                <th style="width: 15%;">Financials</th>
                                <th style="width: 12%;">Status</th>
                                <th style="width: 10%;">Created</th>
                                <th style="width: 13%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                            <tr>
                                <td>
                                    <div class="group-info-main"><?php echo htmlspecialchars($group['name']); ?></div>
                                    <div class="group-info-code">Code: <?php echo $group['code']; ?></div>
                                    <div class="group-info-details">
                                        <?php 
                                        $frequency_map = [
                                            'weekly_once' => 'Weekly Once',
                                            'weekly_twice' => 'Weekly Twice', 
                                            'monthly_thrice' => 'Monthly Thrice'
                                        ];
                                        echo $frequency_map[$group['meeting_frequency']] ?? $group['meeting_frequency'];
                                        ?> • K<?php echo number_format($group['contribution_amount'], 2); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="treasurer-info-main"><?php echo htmlspecialchars($group['treasurer_name']); ?></div>
                                    <div class="treasurer-info-username">@<?php echo htmlspecialchars($group['treasurer_username']); ?></div>
                                    <span class="stats-badge status-<?php echo $group['treasurer_verified'] === 'yes' ? 'paid' : 'pending'; ?>">
                                        <?php echo $group['treasurer_verified'] === 'yes' ? 'Verified' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #2d3748;"><?php echo $group['member_count']; ?> members</div>
                                    <div style="color: #718096; font-size: 0.875rem; margin-top: 0.25rem;">
                                        <?php echo $group['loan_count']; ?> loans
                                    </div>
                                </td>
                                <td>
                                    <div class="financial-amount">K <?php echo number_format($group['total_contributions'], 2); ?></div>
                                    <div class="financial-details">
                                        <?php echo $group['interest_rate']; ?>% interest rate
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
                                    <div style="color: #2d3748; font-weight: 500;"><?php echo date('M j, Y', strtotime($group['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../pages/groups.php?action=view&id=<?php echo $group['id']; ?>" 
                                           class="btn-view" target="_blank">View Group</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                            <button type="submit" class="btn-delete" 
                                                    onclick="return confirm('WARNING: This will permanently delete this group and all its data. This action cannot be undone. Are you sure?')">
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