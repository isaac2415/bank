<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$group_id = $_GET['group_id'] ?? null;
$action = $_GET['action'] ?? 'view';

if (!$group_id) {
    header("Location: groups.php");
    exit();
}

// Verify user has access to this group
$user_id = $_SESSION['user_id'];
$query = "SELECT g.*, gm.id as member_id 
          FROM `groups` g 
          LEFT JOIN `group_members` gm ON g.id = gm.group_id AND gm.user_id = ?
          WHERE g.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id, $group_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group || (!$group['member_id'] && $group['treasurer_id'] != $user_id)) {
    header("Location: groups.php");
    exit();
}

$is_treasurer = ($group['treasurer_id'] == $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    
    switch ($action) {
        case 'record_payment':
            recordPayment($db);
            break;
        case 'record_meeting':
            recordMeeting($db, $user_id);
            break;
        case 'remove_member':
            removeMember($db);
            break;
    }
}

function recordPayment($db) {
    global $group_id, $is_treasurer;
    
    if (!$is_treasurer) {
        $_SESSION['error'] = "Only treasurer can record payments";
        return;
    }
    
    $user_id = $_POST['user_id'];
    $meeting_id = $_POST['meeting_id'];
    $amount = $_POST['amount'];
    $status = $_POST['status'];
    
    $query = "INSERT INTO `payments` (group_id, user_id, meeting_id, amount, status) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$group_id, $user_id, $meeting_id, $amount, $status])) {
        $_SESSION['success'] = "Payment recorded successfully";
    } else {
        $_SESSION['error'] = "Failed to record payment";
    }
}

function recordMeeting($db, $user_id) {
    global $group_id, $is_treasurer, $group;

    if (!$is_treasurer) {
        $_SESSION['error'] = "Only treasurer can record meetings";
        return;
    }

    $meeting_date = $_POST['meeting_date'];

    $query = "INSERT INTO `meetings` (group_id, meeting_date, created_by) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt->execute([$group_id, $meeting_date, $user_id])) {
        $meeting_id = $db->lastInsertId();
        
        // Create payment records for all members
    $query = "SELECT user_id FROM `group_members` WHERE group_id = ? AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($members as $member) {
            $query = "INSERT INTO `payments` (group_id, user_id, meeting_id, amount, status) 
                      VALUES (?, ?, ?, ?, 'missed')";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id, $member['user_id'], $meeting_id, $group['contribution_amount']]);
        }
        
        $_SESSION['success'] = "Meeting recorded successfully";
    } else {
        $_SESSION['error'] = "Failed to record meeting";
    }
}

function removeMember($db) {
    global $group_id, $is_treasurer;
    
    if (!$is_treasurer) {
        $_SESSION['error'] = "Only treasurer can remove members";
        return;
    }
    
    $member_id = $_POST['member_id'];
    
    $query = "UPDATE `group_members` SET status = 'inactive' WHERE id = ? AND group_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$member_id, $group_id])) {
        $_SESSION['success'] = "Member removed successfully";
    } else {
        $_SESSION['error'] = "Failed to remove member";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management - BankingKhonde</title>
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

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Member Management - <?php echo htmlspecialchars($group['name']); ?></h2>
                <a href="groups.php?action=view&id=<?php echo $group_id; ?>" class="btn btn-primary">Back to Group</a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
            <!-- Member Payments Overview -->
            <div class="card">
                <h3>Payment Overview</h3>
                
                <?php
                // Get all members with their payment summary
                $query = "SELECT u.id, u.full_name, u.username,
                         COUNT(p.id) as total_meetings,
                         SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) as meetings_paid,
                         SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as total_paid
                         FROM `group_members` gm
                         JOIN `users` u ON gm.user_id = u.id
                         LEFT JOIN `payments` p ON gm.user_id = p.user_id AND p.group_id = gm.group_id
                         WHERE gm.group_id = ? AND gm.status = 'active'
                         GROUP BY u.id, u.full_name, u.username
                         ORDER BY u.full_name";
                $stmt = $db->prepare($query);
                $stmt->execute([$group_id]);
                $member_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Meetings</th>
                                <th>Paid</th>
                                <th>Missed</th>
                                <th>Total Paid</th>
                                <?php if ($is_treasurer): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($member_payments as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                    <div style="font-size: 0.875rem; color: #666;">@<?php echo htmlspecialchars($member['username']); ?></div>
                                </td>
                                <td><?php echo $member['total_meetings']; ?></td>
                                <td><?php echo $member['meetings_paid']; ?></td>
                                <td><?php echo $member['total_meetings'] - $member['meetings_paid']; ?></td>
                                <td>K <?php echo number_format($member['total_paid'], 2); ?></td>
                                <?php if ($is_treasurer): ?>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="showRecordPayment(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name']); ?>')">
                                        Record Payment
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Treasurer Actions -->
            <?php if ($is_treasurer): ?>
            <div>
                <!-- Record Meeting -->
                <div class="card">
                    <h3>Record New Meeting</h3>
                    <form method="POST" class="ajax-form">
                        <input type="hidden" name="action" value="record_meeting">
                        <div class="form-group">
                            <label for="meeting_date">Meeting Date:</label>
                            <input type="date" id="meeting_date" name="meeting_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Record Meeting</button>
                    </form>
                </div>

                <!-- Recent Meetings -->
                <div class="card" style="margin-top: 1.5rem;">
                    <h3>Recent Meetings</h3>
                    <?php
                    $query = "SELECT * FROM meetings 
                             WHERE group_id = ? 
                             ORDER BY meeting_date DESC 
                             LIMIT 5";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$group_id]);
                    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($meetings)): ?>
                        <p>No meetings recorded yet.</p>
                    <?php else: ?>
                        <div style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($meetings as $meeting): ?>
                                <div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                                    <strong><?php echo date('M j, Y', strtotime($meeting['meeting_date'])); ?></strong>
                                    <div style="font-size: 0.875rem; color: #666;">
                                        Recorded on <?php echo date('M j, Y', strtotime($meeting['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Detailed Payment History -->
        <div class="card" style="margin-top: 1.5rem;">
            <h3>Detailed Payment History</h3>
            
            <?php
            $query = "SELECT p.*, u.full_name, u.username, m.meeting_date 
                     FROM payments p
                     JOIN users u ON p.user_id = u.id
                     JOIN meetings m ON p.meeting_id = m.id
                     WHERE p.group_id = ?
                     ORDER BY m.meeting_date DESC, u.full_name";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Meeting Date</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($payment['meeting_date'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($payment['full_name']); ?>
                                <div style="font-size: 0.875rem; color: #666;">@<?php echo htmlspecialchars($payment['username']); ?></div>
                            </td>
                            <td>K <?php echo number_format($payment['amount'], 2); ?></td>
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
    </main>

    <!-- Record Payment Modal -->
    <?php if ($is_treasurer): ?>
    <div id="recordPaymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; min-width: 400px;">
            <h3>Record Payment for <span id="memberName"></span></h3>
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="user_id" id="paymentUserId">
                
                <div class="form-group">
                    <label for="meeting_id">Meeting Date:</label>
                    <select id="meeting_id" name="meeting_id" required>
                        <option value="">Select Meeting</option>
                        <?php
                        $query = "SELECT m.* FROM meetings m 
                                 LEFT JOIN payments p ON m.id = p.meeting_id AND p.user_id = ? AND p.status = 'paid'
                                 WHERE m.group_id = ? AND p.id IS NULL
                                 ORDER BY m.meeting_date DESC";
                        $stmt = $db->prepare($query);
                        // We'll populate this via JavaScript
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount:</label>
                    <input type="number" id="amount" name="amount" step="0.01" value="<?php echo $group['contribution_amount']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="paid">Paid</option>
                        <option value="missed">Missed</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                    <button type="button" class="btn btn-danger" onclick="closeRecordPayment()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="../assets/js/app.js"></script>
    <script>
    function showRecordPayment(userId, memberName) {
        document.getElementById('memberName').textContent = memberName;
        document.getElementById('paymentUserId').value = userId;
        document.getElementById('recordPaymentModal').style.display = 'block';
        
        // Load unpaid meetings for this member
        fetch(`../api/payments.php?action=get_unpaid_meetings&group_id=<?php echo $group_id; ?>&user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('meeting_id');
                select.innerHTML = '<option value="">Select Meeting</option>';
                
                if (data.success && data.meetings) {
                    data.meetings.forEach(meeting => {
                        const option = document.createElement('option');
                        option.value = meeting.id;
                        option.textContent = meeting.meeting_date + ' - K ' + meeting.amount;
                        select.appendChild(option);
                    });
                }
            });
    }
    
    function closeRecordPayment() {
        document.getElementById('recordPaymentModal').style.display = 'none';
    }
    </script>
</body>
</html>