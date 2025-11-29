<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$group_id = $_GET['group_id'] ?? null;
$selected_meeting_id = $_GET['meeting_id'] ?? null;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_meeting':
            createMeeting($db);
            break;
        case 'record_payment':
            recordPayment($db);
            break;
        case 'record_all_payments':
            recordAllPayments($db);
            break;
        case 'cancel_meeting':
            cancelMeeting($db);
            break;
        case 'complete_meeting':
            completeMeeting($db);
            break;
    }
}

// Function to cancel a meeting
function cancelMeeting($db) {
    global $user_id;

    try {
        $db->beginTransaction();
    
        $meeting_id = $_POST['meeting_id'];
    
    // Get meeting and verify treasurer permission
    $query = "SELECT m.*, g.treasurer_id 
         FROM `meetings` m
         JOIN `groups` g ON m.group_id = g.id 
         WHERE m.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$meeting_id]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$meeting) {
            throw new Exception("Meeting not found");
        }
    
        if ($meeting['treasurer_id'] != $user_id) {
            throw new Exception("Only treasurers can cancel meetings");
        }
    
        if ($meeting['status'] === 'completed') {
            throw new Exception("Cannot cancel a completed meeting");
        }
    
    // Update meeting status
    $query = "UPDATE `meetings` SET status = 'cancelled' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$meeting_id]);
    
        $db->commit();
        $_SESSION['success'] = "Meeting cancelled successfully";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

// Function to complete a meeting
function completeMeeting($db) {
    global $user_id;

    try {
        $db->beginTransaction();
    
        $meeting_id = $_POST['meeting_id'];
    
    // Get meeting and verify treasurer permission
    $query = "SELECT m.*, g.treasurer_id 
         FROM `meetings` m
         JOIN `groups` g ON m.group_id = g.id 
         WHERE m.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$meeting_id]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$meeting) {
            throw new Exception("Meeting not found");
        }
    
        if ($meeting['treasurer_id'] != $user_id) {
            throw new Exception("Only treasurers can complete meetings");
        }
    
        // Check if all members have a status (paid or missed)
    $query = "SELECT COUNT(*) as pending_count
         FROM `payments` 
         WHERE meeting_id = ? AND status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->execute([$meeting_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($result['pending_count'] > 0) {
            throw new Exception("Cannot complete meeting: some members still have pending payments");
        }
    
    // Update meeting status
    $query = "UPDATE `meetings` SET status = 'completed' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$meeting_id]);
    
        $db->commit();
        $_SESSION['success'] = "Meeting completed successfully";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

// Function to record payments for all members in a meeting
function recordAllPayments($db) {
    global $user_id;
    
    try {
        $db->beginTransaction();
        
        $group_id = $_POST['group_id'];
        $meeting_id = $_POST['meeting_id'];
        $member_statuses = $_POST['member_status'] ?? [];
        
            // Verify user is treasurer
            $query = "SELECT id FROM `groups` WHERE id = ? AND treasurer_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id, $user_id]);        if (!$stmt->fetch()) {
            throw new Exception("Only treasurers can record group payments");
        }
        
        // Get group contribution amount
        $query = "SELECT contribution_amount FROM groups WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        $contribution_amount = $stmt->fetch(PDO::FETCH_COLUMN);
        
        // Valid status values
        $valid_statuses = ['pending', 'paid', 'missed'];
        
        foreach ($member_statuses as $member_id => $status) {
            // Validate status
            if (!in_array($status, $valid_statuses)) {
                throw new Exception("Invalid payment status: " . htmlspecialchars($status));
            }
            
            $amount = $status === 'paid' ? $contribution_amount : 0;
            $payment_date = $status === 'paid' ? date('Y-m-d H:i:s') : null;
            
            $query = "UPDATE payments 
                     SET status = ?, amount = ?, payment_date = ? 
                     WHERE group_id = ? AND user_id = ? AND meeting_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $status,
                $amount,
                $payment_date,
                $group_id,
                $member_id,
                $meeting_id
            ]);
        }
        
        $db->commit();
        $_SESSION['success'] = "All payments recorded successfully";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

function recordPayment($db) {
    global $user_id;
    
    try {
        $db->beginTransaction();
        
        $payment_id = $_POST['payment_id'];
        $member_id = $_POST['member_id'];
        $amount = $_POST['amount'];
        $status = $_POST['status'] ?? 'paid';
        
        // Get payment details and verify treasurer permission
        $query = "SELECT p.*, g.treasurer_id, g.id as group_id
                 FROM `payments` p
                 JOIN `groups` g ON p.group_id = g.id
                 WHERE p.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception("Payment record not found");
        }
        
        if ($payment['treasurer_id'] != $user_id) {
            throw new Exception("Only treasurers can record payments");
        }
        
        // Update payment record
        $payment_date = $status === 'paid' ? date('Y-m-d H:i:s') : null;
        $query = "UPDATE payments 
                 SET status = ?, amount = ?, payment_date = ? 
                 WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$status, $amount, $payment_date, $payment_id]);
        
        $db->commit();
        $_SESSION['success'] = "Payment recorded successfully";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

function createMeeting($db) {
    global $user_id;
    
    try {
        $db->beginTransaction();
        
        $group_id = $_POST['group_id'];
        $meeting_date = $_POST['meeting_date'];
        
    // Verify user is treasurer
    $query = "SELECT id, contribution_amount FROM `groups` WHERE id = ? AND treasurer_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $user_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            throw new Exception("Only treasurers can create meetings");
        }
        
    // Create meeting
    $query = "INSERT INTO `meetings` (`group_id`, `meeting_date`, `status`, `created_by`) 
         VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $meeting_date, 'pending', $user_id]);
        $meeting_id = $db->lastInsertId();
        
    // Get all active members
    $query = "SELECT user_id FROM `group_members` 
         WHERE group_id = ? AND `status` = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
    // Prepare the payment insert statement
    $query = "INSERT INTO `payments` 
         (`group_id`, `user_id`, `meeting_id`, `status`, `amount`, `payment_date`) 
         VALUES (?, ?, ?, ?, ?, NULL)";
        $stmt = $db->prepare($query);
        
        foreach ($members as $member_id) {
            $stmt->execute([
                $group_id,
                $member_id,
                $meeting_id,
                'pending',
                $group['contribution_amount']
            ]);
        }
        
        $db->commit();
        $_SESSION['success'] = "Meeting created successfully";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Tracking - BankingKhonde</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .meeting-record {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .meeting-record h4 {
            margin: 0 0 0.5rem 0;
            color: #2d3748;
        }
        
        .amount-collected {
            float: right;
            font-weight: bold;
            color: #28a745;
        }
        
        .payment-record-form {
            margin-top: 1rem;
        }
        
        .payment-record-form select {
            padding: 0.25rem;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .payment-record-form select[disabled] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .status-pending { background-color: #ffeeba; color: #856404; }
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-missed { background-color: #f8d7da; color: #721c24; }
        
        .meeting-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <main class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Payments Management</h2>
            
            <?php
            // Check if user is a treasurer of any group
            $query = "SELECT g.id, g.name, g.contribution_amount, g.treasurer_id = ? as is_treasurer
                     FROM `groups` g 
                     LEFT JOIN `group_members` gm ON g.id = gm.group_id AND gm.user_id = ?
                     WHERE (gm.status = 'active' OR g.treasurer_id = ?) 
                     AND g.status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $user_id, $user_id]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if (empty($groups)): ?>
                <p>You are not a member of any active groups.</p>
            <?php else: ?>
                <div class="form-group">
                    <label for="groupSelect">Select Group:</label>
                    <select id="groupSelect" onchange="loadGroupPayments(this.value)">
                        <option value="">Select a group</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>" <?php echo ($group_id == $group['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['name']); ?> - K <?php echo number_format($group['contribution_amount'] ?? 0, 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($group_id):
            // Check if user is treasurer for this group
            $query = "SELECT treasurer_id FROM `groups` WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id]);
            $is_treasurer = ($stmt->fetch(PDO::FETCH_COLUMN) == $user_id);
            
            if ($is_treasurer):
        ?>
            <!-- Treasurer View -->
            <div class="card">
                <h3>Record Meeting Payments</h3>
                <form method="POST" class="form">
                    <input type="hidden" name="action" value="create_meeting">
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                    
                    <div class="form-group">
                        <label for="meeting_date">Meeting Date</label>
                        <input type="date" id="meeting_date" name="meeting_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create New Meeting</button>
                </form>
            </div>

            <!-- Meeting List -->
            <div class="card">
                <h3>Active Meetings</h3>
                <?php
                $query = "SELECT m.*, 
                         COUNT(DISTINCT p.id) as total_members,
                         SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) as paid_members,
                         SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as total_collected
                         FROM `meetings` m
                         LEFT JOIN `payments` p ON m.id = p.meeting_id
                         WHERE m.group_id = ? AND m.status = 'pending'
                         GROUP BY m.id
                         ORDER BY m.meeting_date DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id]);
                $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($meetings)): ?>
                    <p>No active meetings found.</p>
                <?php else:
                    foreach ($meetings as $meeting):
                ?>
                    <div class="meeting-record">
                        <h4>Meeting on <?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?></h4>
                        <p>
                            Members Paid: <?php echo $meeting['paid_members']; ?> / <?php echo $meeting['total_members']; ?>
                            <span class="amount-collected">Amount Collected: K <?php echo number_format($meeting['total_collected'] ?? 0, 2); ?></span>
                        </p>
                        
                        <?php if ($is_treasurer): ?>
                        <div class="meeting-actions">
                            <a href="payments.php?group_id=<?php echo $group_id; ?>&meeting_id=<?php echo $meeting['id']; ?>" 
                               class="btn btn-primary">Record Payments</a>
                        
                            <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to cancel this meeting?');">
                                <input type="hidden" name="action" value="cancel_meeting">
                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                <button type="submit" class="btn btn-danger">Cancel Meeting</button>
                            </form>
                        
                            <?php if ($meeting['paid_members'] == $meeting['total_members']): ?>
                            <form method="POST" style="display: inline-block;" onsubmit="return confirm('Complete this meeting? This will archive it.');">
                                <input type="hidden" name="action" value="complete_meeting">
                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                <button type="submit" class="btn btn-success">Complete Meeting</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php 
                    endforeach;
                endif;
                ?>
            </div>
            
                <?php if ($selected_meeting_id && $is_treasurer):
                    // Load payments for the selected meeting
                    $query = "SELECT m.* FROM `meetings` m WHERE m.id = ? AND m.group_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$selected_meeting_id, $group_id]);
                    $sel_meeting = $stmt->fetch(PDO::FETCH_ASSOC);
                
                    if ($sel_meeting):
                        // contribution amount
                        $query = "SELECT contribution_amount FROM `groups` WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$group_id]);
                        $contribution_amount = $stmt->fetch(PDO::FETCH_COLUMN);
                    
                        $query = "SELECT p.*, u.full_name, u.phone FROM `payments` p JOIN `users` u ON p.user_id = u.id WHERE p.meeting_id = ? ORDER BY u.full_name";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$selected_meeting_id]);
                        $meeting_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="card">
                    <h3>Recording Payments Meeting on <?php echo date('F j, Y', strtotime($sel_meeting['meeting_date'])); ?></h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
<tbody>
    <?php foreach ($meeting_payments as $mp): ?>
    <tr>
        <td><?php echo htmlspecialchars($mp['full_name']); ?></td>
        <td><?php echo htmlspecialchars($mp['phone']); ?></td>
        <td><span class="status-badge status-<?php echo $mp['status']; ?>"><?php echo ucfirst($mp['status']); ?></span></td>
        <td><?php echo $mp['payment_date'] ? date('M j, Y g:i A', strtotime($mp['payment_date'])) : '-'; ?></td>
        <td>
            <?php if ($mp['status'] !== 'paid'): ?>
                <input type="number" 
                       id="amount_<?php echo $mp['id']; ?>" 
                       name="amount_<?php echo $mp['id']; ?>" 
                       placeholder="Amount" 
                       value="<?php echo $contribution_amount; ?>" 
                       min="0" 
                       step="0.01" 
                       style="width: 80px; padding: 0.25rem;"
                       onchange="updatePaymentAmount(<?php echo $mp['id']; ?>, this.value)">
            <?php else: ?>
                K <?php echo number_format($mp['amount'], 2); ?>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($mp['status'] !== 'paid'): ?>
                <button type="button" 
                        class="btn btn-primary btn-small" 
                        onclick="recordPayment(<?php echo $mp['id']; ?>, <?php echo $mp['user_id']; ?>, '<?php echo addslashes($mp['full_name']); ?>', document.getElementById('amount_<?php echo $mp['id']; ?>').value)">
                    Record Payment
                </button>
                <button type="button" 
                        class="btn btn-danger btn-small" 
                        onclick="markAsMissed(<?php echo $mp['id']; ?>, <?php echo $mp['user_id']; ?>)">
                    Mark as Missed
                </button>
            <?php else: ?>
                <span class="status-badge status-paid">Paid</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                        </table>
                    </div>
                </div>
                <script src="../assets/js/payments.js"></script>
                <?php
                    endif; // sel_meeting
                endif; // selected_meeting_id
                ?>
            
            <?php if ($is_treasurer): ?>
            <!-- Completed Meetings -->
            <div class="card">
                <h3>Completed Meetings</h3>
                <?php
                $query = "SELECT m.*, 
                         COUNT(DISTINCT p.id) as total_members,
                         SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) as paid_members,
                         SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as total_collected
                         FROM `meetings` m
                         LEFT JOIN `payments` p ON m.id = p.meeting_id
                         WHERE m.group_id = ? AND m.status = 'completed'
                         GROUP BY m.id
                         ORDER BY m.meeting_date DESC
                         LIMIT 5";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id]);
                $completed_meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
                if (empty($completed_meetings)): ?>
                    <p>No completed meetings found.</p>
                <?php else:
                    foreach ($completed_meetings as $meeting):
                ?>
                    <div class="meeting-record completed">
                        <h4>Meeting on <?php echo date('F j, Y', strtotime($meeting['meeting_date'])); ?></h4>
                        <p>
                            All Members Paid
                            <span class="amount-collected">Total Collected: K <?php echo number_format($meeting['total_collected'] ?? 0, 2); ?></span>
                        </p>
                    </div>
                <?php 
                    endforeach;
                endif;
                ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Member View -->
            <div class="card">
                <h3>My Payment History</h3>
                <?php
                $query = "SELECT p.*, m.meeting_date, g.name as group_name, g.contribution_amount
                         FROM `payments` p
                         JOIN `meetings` m ON p.meeting_id = m.id
                         JOIN `groups` g ON p.group_id = g.id
                         WHERE p.group_id = ? AND p.user_id = ?
                         ORDER BY m.meeting_date DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id, $user_id]);
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Meeting Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('F j, Y', strtotime($payment['meeting_date'])); ?></td>
                                <td>K <?php echo number_format($payment['contribution_amount'] ?? 0, 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $payment['payment_date'] ? date('M j, Y', strtotime($payment['payment_date'])) : '-'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <?php
        // Verify user is member of selected group
    $query = "SELECT g.name, g.contribution_amount 
         FROM `groups` g 
         JOIN `group_members` gm ON g.id = gm.group_id 
         WHERE g.id = ? AND gm.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $user_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            echo '<div class="message message-error">You are not a member of this group</div>';
        } else {
            // Get payment summary
            $query = "SELECT 
                     COUNT(*) as total_meetings,
                     SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) as meetings_paid,
                     SUM(CASE WHEN p.status = 'missed' THEN 1 ELSE 0 END) as meetings_missed,
                     SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as total_paid
                     FROM `payments` p 
                     JOIN `meetings` m ON p.meeting_id = m.id
                     WHERE p.group_id = ? AND p.user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id, $user_id]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get payment history
            $query = "SELECT p.*, m.meeting_date 
                     FROM `payments` p
                     JOIN `meetings` m ON p.meeting_id = m.id
                     WHERE p.group_id = ? AND p.user_id = ?
                     ORDER BY m.meeting_date DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id, $user_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <!-- Payment Summary -->
        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Meetings</h3>
                <p><?php echo $summary['total_meetings']; ?></p>
            </div>
            <div class="card">
                <h3>Meetings Paid</h3>
                <p style="color: #28a745;"><?php echo $summary['meetings_paid']; ?></p>
            </div>
            <div class="card">
                <h3>Meetings Missed</h3>
                <p style="color: #dc3545;"><?php echo $summary['meetings_missed']; ?></p>
            </div>
            <div class="card">
                <h3>Total Paid</h3>
                <p>K <?php echo number_format($summary['total_paid'] ?? 0, 2); ?></p>
            </div>
        </div>

        <!-- Unpaid Payments -->
        <div class="card">
            <h3>Outstanding Payments</h3>
            <?php
            $query = "SELECT p.*, m.meeting_date 
                     FROM `payments` p
                     JOIN `meetings` m ON p.meeting_id = m.id
                     WHERE p.group_id = ? AND p.user_id = ? AND p.status = 'missed'
                     ORDER BY m.meeting_date ASC";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id, $user_id]);
            $unpaid_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if (empty($unpaid_payments)): ?>
                <p>No outstanding payments. Great job!</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Meeting Date</th>
                                <th>Amount Due</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unpaid_payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($payment['meeting_date'])); ?></td>
                                <td>K <?php echo number_format($payment['amount'] ?? 0, 2); ?></td>
                                <td>
                                    <span class="status-badge status-missed">Missed</span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="showPaymentModal(<?php echo $payment['id']; ?>, '<?php echo date('M j, Y', strtotime($payment['meeting_date'])); ?>', <?php echo $payment['amount']; ?>)">
                                        Pay Now
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment History -->
        <div class="card">
            <h3>Payment History</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Meeting Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($payment['meeting_date'])); ?></td>
                            <td>K <?php echo number_format($payment['amount'] ?? 0, 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($payment['status'] === 'paid'): ?>
                                    <?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>
        <?php endif; ?>
    </main>

    <!-- Payment Modal -->
    <div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; min-width: 400px;">
            <h3>Record Payment</h3>
            <form method="POST" class="ajax-form" data-reset="true">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="group_id" id="paymentGroupId" value="<?php echo $group_id; ?>">
                <input type="hidden" name="meeting_id" id="paymentMeetingId">
                
                <div class="form-group">
                    <label>Meeting Date:</label>
                    <p id="meetingDateDisplay" style="padding: 0.5rem; background: #f8f9fa; border-radius: 5px;"></p>
                </div>
                
                <div class="form-group">
                    <label for="paymentAmount">Amount:</label>
                    <input type="number" id="paymentAmount" name="amount" step="0.01" required>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                    <button type="button" class="btn btn-danger" onclick="closePaymentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
    function loadGroupPayments(groupId) {
        window.location.href = `payments.php?group_id=${groupId}`;
    }
    
    function showPaymentModal(paymentId, meetingDate, amount) {
        document.getElementById('paymentMeetingId').value = paymentId;
        document.getElementById('meetingDateDisplay').textContent = meetingDate;
        document.getElementById('paymentAmount').value = amount;
        document.getElementById('paymentModal').style.display = 'block';
    }
    
    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }
    // Store payment amounts in a global object
let paymentAmounts = {};

function updatePaymentAmount(paymentId, amount) {
    paymentAmounts[paymentId] = amount;
}

function recordPayment(paymentId, memberId, memberName, amount) {
    // Use the amount from the input field or the stored amount
    const inputAmount = document.getElementById('amount_' + paymentId)?.value || amount;
    
    if (!inputAmount || inputAmount <= 0) {
        alert('Please enter a valid amount greater than 0.');
        return;
    }
    
    if (confirm(`Record payment of K ${inputAmount} for ${memberName}?`)) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'record_payment';
        form.appendChild(actionInput);
        
        const paymentIdInput = document.createElement('input');
        paymentIdInput.name = 'payment_id';
        paymentIdInput.value = paymentId;
        form.appendChild(paymentIdInput);
        
        const memberIdInput = document.createElement('input');
        memberIdInput.name = 'member_id';
        memberIdInput.value = memberId;
        form.appendChild(memberIdInput);
        
        const amountInput = document.createElement('input');
        amountInput.name = 'amount';
        amountInput.value = inputAmount;
        form.appendChild(amountInput);
        
        const statusInput = document.createElement('input');
        statusInput.name = 'status';
        statusInput.value = 'paid';
        form.appendChild(statusInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function markAsMissed(paymentId, memberId) {
    if (confirm('Mark this payment as missed? The amount will be set to 0.')) {
        // Create a hidden form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'record_payment';
        form.appendChild(actionInput);
        
        const paymentIdInput = document.createElement('input');
        paymentIdInput.name = 'payment_id';
        paymentIdInput.value = paymentId;
        form.appendChild(paymentIdInput);
        
        const memberIdInput = document.createElement('input');
        memberIdInput.name = 'member_id';
        memberIdInput.value = memberId;
        form.appendChild(memberIdInput);
        
        const amountInput = document.createElement('input');
        amountInput.name = 'amount';
        amountInput.value = '0';
        form.appendChild(amountInput);
        
        const statusInput = document.createElement('input');
        statusInput.name = 'status';
        statusInput.value = 'missed';
        form.appendChild(statusInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
    
    // Auto-load group payments if group is selected
    <?php if ($group_id): ?>
    // No auto-redirect here — the page already has group_id in the URL.
    // If you need a redirect after form POST, handle it server-side instead.
    <?php endif; ?>
    </script>
</body>
</html>