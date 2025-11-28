<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

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
    }
}

function createGroup($db) {
    requireTreasurer();
    
    $name = $_POST['name'];
    $meeting_frequency = $_POST['meeting_frequency'];
    $meeting_days = $_POST['meeting_days'];
    $contribution_amount = $_POST['contribution_amount'];
    $interest_rate = $_POST['interest_rate'];
    $loan_repayment_days = $_POST['loan_repayment_days'];
    $rules = $_POST['rules'] ?? '';
    
    $code = generateGroupCode();
    $treasurer_id = $_SESSION['user_id'];
    
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
        $_SESSION['error'] = "Failed to create group";
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
    $query = "INSERT INTO `group_rules` (group_id, rule_text, created_by) VALUES (?, ?, ?)";
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
    
    $query = "UPDATE `groups` SET status = 'ended' WHERE id = ? AND treasurer_id = ?";
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
    
    $query = "UPDATE `groups` SET status = 'active' WHERE id = ? AND treasurer_id = ?";
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
    $query = "SELECT id FROM `groups` WHERE code = ? AND status = 'active'";
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
            $query = "INSERT INTO `group_members` (group_id, user_id) VALUES (?, ?)";
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
                <form method="POST" class="ajax-form">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="name">Group Name:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_frequency">Meeting Frequency:</label>
                        <select id="meeting_frequency" name="meeting_frequency" required>
                            <option value="weekly_once">Once a week</option>
                            <option value="weekly_twice">Twice a week</option>
                            <option value="monthly_thrice">Three times a month</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_days">Meeting Days (comma separated):</label>
                        <input type="text" id="meeting_days" name="meeting_days" placeholder="e.g., Monday, Wednesday, Friday" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contribution_amount">Contribution Amount per Meeting:</label>
                        <input type="number" id="contribution_amount" name="contribution_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="interest_rate">Loan Interest Rate (%):</label>
                        <input type="number" id="interest_rate" name="interest_rate" step="0.01" min="0" max="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loan_repayment_days">Loan Repayment Period (days):</label>
                        <input type="number" id="loan_repayment_days" name="loan_repayment_days" min="1" value="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="rules">Initial Group Rules:</label>
                        <textarea id="rules" name="rules" rows="4" placeholder="Enter initial rules for the group..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </form>
            </div>

        <?php break; case 'join': ?>
            <div class="card">
                <h2>Join a Group</h2>
                <form method="POST" class="ajax-form">
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
            
            <?php } else { ?>
                <div class="message message-info">Please select a group to view</div>
            <?php } ?>
            
        <?php break; endswitch; ?>
    </main>

    <script src="../assets/js/app.js"></script>
</body>
</html>