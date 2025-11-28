<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? 'view';
$group_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    
    switch ($action) {
        case 'create':
            createGroup($db);
            break;
        case 'update_rules':
            updateGroupRules($db);
            break;
        case 'end_group':
            endGroup($db);
            break;
        case 'restart_group':
            restartGroup($db);
            break;
        case 'join_group':
            joinGroup($db);
            break;
        case 'share_profits':
            shareProfits($db);
            break;
    }
}

function createGroup($db) {
    try {
        requireTreasurer();
        
        // Validate required fields
        $required_fields = ['name', 'meeting_frequency', 'meeting_days', 'contribution_amount', 'interest_rate', 'loan_repayment_days'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $name = trim($_POST['name']);
        $meeting_frequency = $_POST['meeting_frequency'];
        $meeting_days = trim($_POST['meeting_days']);
        $contribution_amount = floatval($_POST['contribution_amount']);
        $interest_rate = floatval($_POST['interest_rate']);
        $loan_repayment_days = intval($_POST['loan_repayment_days']);
        $rules = $_POST['rules'] ?? '';
        
        // Validate numeric values
        if ($contribution_amount <= 0) {
            throw new Exception("Contribution amount must be greater than 0");
        }
        
        if ($interest_rate < 0) {
            throw new Exception("Interest rate cannot be negative");
        }
        
        if ($loan_repayment_days <= 0) {
            throw new Exception("Loan repayment days must be greater than 0");
        }
        
        $code = generateGroupCode();
        $treasurer_id = $_SESSION['user_id'];
        
        // Check if group name already exists for this treasurer
    $check_query = "SELECT id FROM `groups` WHERE name = ? AND treasurer_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$name, $treasurer_id]);
        
        if ($check_stmt->fetch()) {
            throw new Exception("You already have a group with this name. Please choose a different name.");
        }
    
    $query = "INSERT INTO `groups` (name, code, treasurer_id, meeting_frequency, meeting_days, contribution_amount, interest_rate, loan_repayment_days) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$name, $code, $treasurer_id, $meeting_frequency, $meeting_days, $contribution_amount, $interest_rate, $loan_repayment_days])) {
            $group_id = $db->lastInsertId();
            
            // Add treasurer as group member
            $query = "INSERT INTO `group_members` (group_id, user_id) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id, $treasurer_id]);
            
            // Add group rules if provided
            if (!empty($rules)) {
                $query = "INSERT INTO `group_rules` (group_id, rule_text, created_by) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id, $rules, $treasurer_id]);
            }
            
            $_SESSION['success'] = "Group created successfully! Share this code with members: <strong>$code</strong>";
            header("Location: groups.php?action=view&id=$group_id");
            exit();
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to create group: " . ($errorInfo[2] ?? 'Unknown database error'));
        }
    } catch (Exception $e) {
        error_log("Error creating group: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while creating the group: " . $e->getMessage();
        header("Location: groups.php?action=create");
        exit();
    }
}

function updateGroupRules($db) {
    requireTreasurer();
    
    $group_id = $_POST['group_id'];
    $rule_text = $_POST['rule_text'];
    $user_id = $_SESSION['user_id'];
    
    // Verify user owns the group
    $query = "SELECT id FROM `groups` WHERE id = ? AND treasurer_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id, $user_id]);
    
    if ($stmt->fetch()) {
        $query = "INSERT INTO group_rules (group_id, rule_text, created_by) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$group_id, $rule_text, $user_id])) {
            $_SESSION['success'] = "Rule added successfully";
        } else {
            $_SESSION['error'] = "Failed to add rule";
        }
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
    
    header("Location: groups.php?action=view&id=$group_id");
    exit();
}

function endGroup($db) {
    requireTreasurer();
    
    $group_id = $_POST['group_id'];
    $user_id = $_SESSION['user_id'];
    
    $query = "UPDATE groups SET status = 'ended' WHERE id = ? AND treasurer_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$group_id, $user_id])) {
        $_SESSION['success'] = "Group ended successfully";
    } else {
        $_SESSION['error'] = "Failed to end group";
    }
    
    header("Location: groups.php?action=view&id=$group_id");
    exit();
}

function restartGroup($db) {
    requireTreasurer();
    
    $group_id = $_POST['group_id'];
    $user_id = $_SESSION['user_id'];
    
    $query = "UPDATE groups SET status = 'active' WHERE id = ? AND treasurer_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$group_id, $user_id])) {
        $_SESSION['success'] = "Group restarted successfully";
    } else {
        $_SESSION['error'] = "Failed to restart group";
    }
    
    header("Location: groups.php?action=view&id=$group_id");
    exit();
}

function joinGroup($db) {
    $code = $_POST['code'];
    $user_id = $_SESSION['user_id'];

    // Find group by code
    $query = "SELECT id FROM `groups` WHERE `code` = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$code]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($group) {
        $group_id = $group['id'];

        // Check if already a member
        $query = "SELECT id FROM `group_members` WHERE group_id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $user_id]);

        if ($stmt->fetch()) {
            $_SESSION['error'] = "You are already a member of this group";
        } else {
            $query = "INSERT INTO group_members (group_id, user_id) VALUES (?, ?)";
            $stmt = $db->prepare($query);

            if ($stmt->execute([$group_id, $user_id])) {
                $_SESSION['success'] = "Successfully joined the group";
                header("Location: groups.php?action=view&id=$group_id");
                exit();
            } else {
                $_SESSION['error'] = "Failed to join group";
            }
        }
    } else {
        $_SESSION['error'] = "Invalid group code or group is not active";
    }

    header("Location: groups.php?action=join");
    exit();
}

function shareProfits($db) {
    requireTreasurer();

    $group_id = $_POST['group_id'];
    $user_id = $_SESSION['user_id'];

    // Verify user owns the group
    $query = "SELECT id, name FROM `groups` WHERE id = ? AND treasurer_id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id, $user_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        $_SESSION['error'] = "Group not found or you don't have permission to share profits";
        header("Location: groups.php?action=view&id=$group_id");
        exit();
    }

    try {
        $db->beginTransaction();

        // Get total contributions in the system (payments + paid loans)
        $query = "SELECT
                 (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p WHERE p.group_id = ? AND p.status = 'paid') +
                 (SELECT COALESCE(SUM(l.total_amount), 0) FROM `loans` l WHERE l.group_id = ? AND l.status = 'paid') as total_money";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $group_id]);
        $total_money = $stmt->fetch(PDO::FETCH_ASSOC)['total_money'];

        if ($total_money <= 0) {
            $_SESSION['error'] = "No contributions found to share";
            $db->rollBack();
            header("Location: groups.php?action=view&id=$group_id");
            exit();
        }

        // Get all members and their contributions (payments + paid loans)
        $query = "SELECT u.id, u.full_name, u.username,
                         (COALESCE(SUM(p.amount), 0) + COALESCE(SUM(l.total_amount), 0)) as total_contributed,
                         ((COALESCE(SUM(p.amount), 0) + COALESCE(SUM(l.total_amount), 0)) / ?) * 100 as contribution_percentage
                 FROM `group_members` gm
                 JOIN `users` u ON gm.user_id = u.id
                 LEFT JOIN `payments` p ON gm.user_id = p.user_id AND p.group_id = gm.group_id AND p.status = 'paid'
                 LEFT JOIN `loans` l ON gm.user_id = l.user_id AND l.group_id = gm.group_id AND l.status = 'paid'
                 WHERE gm.group_id = ? AND gm.status = 'active'
                 GROUP BY u.id, u.full_name, u.username
                 ORDER BY total_contributed DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$total_money, $group_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate share for each member
        $profit_sharing_report = [];
        foreach ($members as $member) {
            $share_amount = ($member['contribution_percentage'] / 100) * $total_money;
            $profit_sharing_report[] = [
                'member_id' => $member['id'],
                'full_name' => $member['full_name'],
                'username' => $member['username'],
                'total_contributed' => $member['total_contributed'],
                'contribution_percentage' => $member['contribution_percentage'],
                'share_amount' => $share_amount
            ];
        }

        // Store the profit sharing report (you might want to create a table for this)
        // For now, we'll store it in session for display
        $_SESSION['profit_sharing_report'] = [
            'group_id' => $group_id,
            'group_name' => $group['name'],
            'total_money' => $total_money,
            'members' => $profit_sharing_report,
            'shared_at' => date('Y-m-d H:i:s')
        ];

        // Reset the group contributions (set all payments and paid loans to 'shared')
        // Mark payments as shared
        $query = "UPDATE `payments` SET status = 'shared' WHERE group_id = ? AND status = 'paid'";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);

        // Mark paid loans as shared
        $query = "UPDATE `loans` SET status = 'shared' WHERE group_id = ? AND status = 'paid'";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);

        $db->commit();

        $_SESSION['success'] = "Profit sharing completed successfully! View the report below.";
        header("Location: groups.php?action=view&id=$group_id&show_report=1");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error sharing profits: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while sharing profits: " . $e->getMessage();
        header("Location: groups.php?action=view&id=$group_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Management - BankingKhonde</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php switch ($action): case 'create': ?>
            <?php if (!isTreasurer()): ?>
                <div class="message message-error">Only treasurers can create groups</div>
                <?php break; ?>
            <?php endif; ?>

            <div class="card">
                <h2>Create New Group</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="name">Group Name:</label>
                        <input type="text" id="name" name="name" pattern="[A-Za-z0-9\s-]+" maxlength="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_frequency">Meeting Frequency:</label>
                        <select id="meeting_frequency" name="meeting_frequency" required>
                            <option value="" disabled selected>Select Frequency</option>
                            <option value="weekly_once">Once a week</option>
                            <option value="weekly_twice">Twice a week</option>
                            <option value="monthly_thrice">Three times a month</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_days">Meeting Days (comma separated):</label>
                        <input type="text" id="meeting_days" name="meeting_days" 
                               pattern="^[A-Za-z]+(,[A-Za-z]+)*$"
                               placeholder="e.g., Monday,Wednesday,Friday" required>
                        <small>Enter days separated by commas without spaces</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="contribution_amount">Contribution Amount per Meeting:</label>
                        <input type="number" id="contribution_amount" name="contribution_amount" 
                               step="0.01" min="1" max="1000000" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="interest_rate">Loan Interest Rate (%):</label>
                        <input type="number" id="interest_rate" name="interest_rate" 
                               step="0.01" min="0" max="100" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loan_repayment_days">Loan Repayment Period (days):</label>
                        <input type="number" id="loan_repayment_days" name="loan_repayment_days" 
                               min="1" max="365" value="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="rules">Initial Group Rules:</label>
                        <textarea id="rules" name="rules" rows="4" placeholder="Enter initial rules for the group..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Group</button>
                    <a href="groups.php" class="btn btn-danger">Cancel</a>
                </form>
            </div>

        <?php break; case 'join': ?>
            <div class="card">
                <h2>Join a Group</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="join_group">
                    
                    <div class="form-group">
                        <label for="code">Group Code:</label>
                        <input type="text" id="code" name="code" placeholder="Enter 6-digit group code" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Join Group</button>
                </form>
            </div>

        <?php break; case 'view': default: ?>
            <?php
            if ($group_id) {
                // Get group details
                $query = "SELECT g.*, u.username as treasurer_name 
                         FROM `groups` g 
                         JOIN `users` u ON g.treasurer_id = u.id 
                         WHERE g.id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id]);
                $group = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$group) {
                    echo '<div class="message message-error">Group not found</div>';
                    break;
                }
                
                // Check if user is member or treasurer
                $is_member = false;
                $is_treasurer = ($group['treasurer_id'] == $_SESSION['user_id']);
                
                if (!$is_treasurer) {
                    $query = "SELECT id FROM `group_members` WHERE group_id = ? AND user_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$group_id, $_SESSION['user_id']]);
                    $is_member = (bool)$stmt->fetch();
                } else {
                    $is_member = true;
                }
                
                if (!$is_member && !$is_treasurer) {
                    echo '<div class="message message-error">You are not a member of this group</div>';
                    break;
                }
                
                // Get group rules
                $query = "SELECT gr.*, u.full_name 
                         FROM `group_rules` gr 
                         JOIN `users` u ON gr.created_by = u.id 
                         WHERE gr.group_id = ? 
                         ORDER BY gr.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id]);
                $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get group members
                $query = "SELECT u.id, u.full_name, u.username, u.phone, gm.joined_at 
                         FROM `group_members` gm 
                         JOIN `users` u ON gm.user_id = u.id 
                         WHERE gm.group_id = ? AND gm.status = 'active'";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id]);
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get recent announcements
                $query = "SELECT a.*, u.full_name 
                         FROM `announcements` a 
                         JOIN `users` u ON a.user_id = u.id 
                         WHERE a.group_id = ? 
                         ORDER BY a.created_at DESC 
                         LIMIT 5";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id]);
                $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><?php echo htmlspecialchars($group['name']); ?></h2>
                    <span class="status-badge status-<?php echo $group['status']; ?>">
                        <?php echo ucfirst($group['status']); ?>
                    </span>
                </div>
                
                <div class="group-info" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0;">
                    <div>
                        <strong>Group Code:</strong> <?php echo $group['code']; ?>
                    </div>
                    <div>
                        <strong>Treasurer:</strong> <?php echo htmlspecialchars($group['treasurer_name']); ?>
                    </div>
                    <div>
                        <strong>Meeting Frequency:</strong> <?php echo getMeetingFrequencyText($group['meeting_frequency']); ?>
                    </div>
                    <div>
                        <strong>Contribution:</strong> K <?php echo number_format($group['contribution_amount'], 2); ?> per meeting
                    </div>
                    <div>
                        <strong>Interest Rate:</strong> <?php echo $group['interest_rate']; ?>%
                    </div>
                    <div>
                        <strong>Loan Repayment:</strong> <?php echo $group['loan_repayment_days']; ?> days
                    </div>
                </div>
                
                <?php if ($is_treasurer): ?>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <?php if ($group['status'] === 'active'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="end_group">
                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to end this group?')">End Group</button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="share_profits">
                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to share profits? This will distribute all current contributions and reset the group funds to zero.')">Share Profits</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="restart_group">
                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                            <button type="submit" class="btn btn-success">Restart Group</button>
                        </form>
                    <?php endif; ?>
                    <a href="reports.php?group_id=<?php echo $group_id; ?>" class="btn btn-primary">View Reports</a>
                    <a href="loans.php?group_id=<?php echo $group_id; ?>" class="btn btn-success">Manage Loans</a>
                </div>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                <div>
                    <!-- Rules Section -->
                    <div class="card">
                        <h3>Group Rules</h3>
                        <?php if (empty($rules)): ?>
                            <p>No rules defined yet.</p>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($rules as $rule): ?>
                                    <div style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                                        <p><?php echo nl2br(htmlspecialchars($rule['rule_text'])); ?></p>
                                        <small>Added by <?php echo htmlspecialchars($rule['full_name']); ?> on <?php echo date('M j, Y', strtotime($rule['created_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_treasurer): ?>
                        <form method="POST" style="margin-top: 1rem;">
                            <input type="hidden" name="action" value="update_rules">
                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                            <div class="form-group">
                                <label for="rule_text">Add New Rule:</label>
                                <textarea id="rule_text" name="rule_text" rows="3" placeholder="Enter new rule..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Rule</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <!-- Announcements -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3>Recent Announcements</h3>
                        <?php if (empty($announcements)): ?>
                            <p>No announcements yet.</p>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                                    <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                    <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                    <small>By <?php echo htmlspecialchars($announcement['full_name']); ?> on <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div style="margin-top: 1rem;">
                            <a href="chat.php?group_id=<?php echo $group_id; ?>" class="btn btn-primary">Go to Group Chat</a>
                        </div>
                    </div>
                </div>

                <div>
                    <!-- Members List -->
                    <div class="card">
                        <h3>Group Members (<?php echo count($members); ?>)</h3>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($members as $member): ?>
                                <div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                    <div style="font-size: 0.875rem; color: #666;">
                                        @<?php echo htmlspecialchars($member['username']); ?>
                                        <?php if ($member['phone']): ?>
                                            • <?php echo htmlspecialchars($member['phone']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <small>Joined <?php echo date('M j, Y', strtotime($member['joined_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profit Sharing Report -->
            <?php if (isset($_GET['show_report']) && isset($_SESSION['profit_sharing_report']) && $_SESSION['profit_sharing_report']['group_id'] == $group_id): ?>
            <div class="card" style="margin-top: 1.5rem; border: 2px solid #28a745;">
                <h3 style="color: #28a745;">🎉 Profit Sharing Report - <?php echo htmlspecialchars($_SESSION['profit_sharing_report']['group_name']); ?></h3>
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div><strong>Total Money Shared:</strong> K <?php echo number_format($_SESSION['profit_sharing_report']['total_money'], 2); ?></div>
                        <div><strong>Members:</strong> <?php echo count($_SESSION['profit_sharing_report']['members']); ?></div>
                        <div><strong>Shared At:</strong> <?php echo date('M j, Y g:i A', strtotime($_SESSION['profit_sharing_report']['shared_at'])); ?></div>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Total Contributed</th>
                                <th>Contribution %</th>
                                <th>Share Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['profit_sharing_report']['members'] as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                    <div style="font-size: 0.875rem; color: #666;">@<?php echo htmlspecialchars($member['username']); ?></div>
                                </td>
                                <td>K <?php echo number_format($member['total_contributed'], 2); ?></td>
                                <td><?php echo number_format($member['contribution_percentage'], 2); ?>%</td>
                                <td style="font-weight: bold; color: #28a745;">K <?php echo number_format($member['share_amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 1rem; padding: 1rem; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                    <strong>Note:</strong> All contributions have been marked as shared and the group funds have been reset to zero. The group can now continue with new contributions.
                </div>

                <?php unset($_SESSION['profit_sharing_report']); // Clear the report after displaying ?>
            </div>
            <?php endif; ?>

            <?php } else {
                // Get user's groups
                if ($_SESSION['role'] === 'treasurer') {
                    $query = "SELECT g.*,
                                    COUNT(DISTINCT gm.user_id) as member_count,
                                    COUNT(DISTINCT l.id) as loan_count,
                                    COALESCE(SUM(p.amount), 0) as total_contributions
                                FROM `groups` g
                                LEFT JOIN `group_members` gm ON g.id = gm.group_id
                                LEFT JOIN `loans` l ON g.id = l.group_id
                                LEFT JOIN `payments` p ON g.id = p.group_id AND p.status = 'paid'
                                WHERE g.treasurer_id = ?
                                GROUP BY g.id
                                ORDER BY g.created_at DESC";
                } else {
                    $query = "SELECT g.*, 
                                COUNT(DISTINCT gm2.user_id) as member_count,
                                COUNT(DISTINCT l.id) as loan_count
                            FROM `groups` g 
                            JOIN `group_members` gm ON g.id = gm.group_id 
                            LEFT JOIN `group_members` gm2 ON g.id = gm2.group_id
                            LEFT JOIN `loans` l ON g.id = l.group_id 
                            WHERE gm.user_id = ? AND gm.status = 'active'
                            GROUP BY g.id
                            ORDER BY g.created_at DESC";
                }
                
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
                $groups = $stmt->fetchAll();
                
                if (empty($groups)) { ?>
                    <div class="message message-info">
                        <?php if ($_SESSION['role'] === 'treasurer'): ?>
                            You haven't created any groups yet. <a href="?action=create">Create a new group</a>
                        <?php else: ?>
                            You're not a member of any groups yet. <a href="?action=join">Join a group</a>
                        <?php endif; ?>
                    </div>
                <?php } else { ?>
                    <div class="groups-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($groups as $group): ?>
                            <div class="card" style="position: relative;">
                                <div class="status-badge status-<?php echo $group['status']; ?>" style="position: absolute; top: 1rem; right: 1rem;">
                                    <?php echo ucfirst($group['status']); ?>
                                </div>
                                <h3><a href="?action=view&id=<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></a></h3>
                                <div style="margin: 1rem 0;">
                                    <div><strong>Members:</strong> <?php echo $group['member_count']; ?></div>
                                    <div><strong>Active Loans:</strong> <?php echo $group['loan_count']; ?></div>
                                    <?php if (isset($group['total_contributions'])): ?>
                                        <div><strong>Total Contributions:</strong> K <?php echo number_format($group['total_contributions'], 2); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.875rem; color: #666;">
                                    Created <?php echo date('M j, Y', strtotime($group['created_at'])); ?>
                                </div>
                                <div style="margin-top: 1rem;">
                                    <a href="?action=view&id=<?php echo $group['id']; ?>" class="btn btn-primary">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php }
            } ?>
            
        <?php break; endswitch; ?>
    </main>

    <script src="../assets/js/app.js"></script>
</body>
</html>