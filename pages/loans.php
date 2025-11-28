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

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'member';
$group_id = $_GET['group_id'] ?? null;

// Handle POST actions: apply, approve, reject, mark_paid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'apply_loan') {
            // Member applies for a loan
            $group_id_post = $_POST['group_id'] ?? null;
            $amount = $_POST['amount'] ?? null;
            $purpose = trim($_POST['purpose'] ?? '');

            if (!$group_id_post || !$amount) {
                throw new Exception('Missing required fields');
            }

            // Verify membership
            $stmt = $db->prepare("SELECT id FROM `group_members` WHERE group_id = ? AND user_id = ? AND status = 'active'");
            $stmt->execute([$group_id_post, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception('You are not a member of this group');
            }

            $amount = floatval($amount);
            if ($amount <= 0) throw new Exception('Loan amount must be greater than 0');

            // Determine group interest rate
            $stmt = $db->prepare("SELECT interest_rate FROM `groups` WHERE id = ? AND status = 'active'");
            $stmt->execute([$group_id_post]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$group) throw new Exception('Group not found or not active');

            $interest_rate = floatval($group['interest_rate']);
            $total_amount = calculateLoanTotal($amount, $interest_rate);

            $query = "INSERT INTO loans (group_id, user_id, amount, interest_rate, total_amount, purpose, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id_post, $user_id, $amount, $interest_rate, $total_amount, $purpose]);

            $_SESSION['success'] = 'Loan application submitted successfully';
            header('Location: loans.php?group_id=' . intval($group_id_post));
            exit();
        }

        if ($action === 'approve_loan' || $action === 'reject_loan' || $action === 'mark_paid') {
            // Treasurer actions
            $loan_id = $_POST['loan_id'] ?? null;
            if (!$loan_id) throw new Exception('Missing loan id');

            // fetch loan and group
            $stmt = $db->prepare("SELECT l.*, g.treasurer_id FROM `loans` l JOIN `groups` g ON l.group_id = g.id WHERE l.id = ?");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$loan) throw new Exception('Loan not found');

            if ($loan['treasurer_id'] != $user_id) throw new Exception('Unauthorized action');

            if ($action === 'approve_loan') {
                $stmt = $db->prepare("UPDATE loans SET status = 'approved', approved_date = NOW(), due_date = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
                // use group's loan_repayment_days if available
                $stmt2 = $db->prepare("SELECT loan_repayment_days FROM `groups` WHERE id = ?");
                $stmt2->execute([$loan['group_id']]);
                $grp = $stmt2->fetch(PDO::FETCH_ASSOC);
                $days = $grp ? intval($grp['loan_repayment_days']) : 20;
                $stmt->execute([$days, $loan_id]);
                $_SESSION['success'] = 'Loan approved';
            } elseif ($action === 'reject_loan') {
                $stmt = $db->prepare("UPDATE loans SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$loan_id]);
                $_SESSION['success'] = 'Loan rejected';
            } else {
                // mark_paid
                $stmt = $db->prepare("UPDATE loans SET status = 'paid', paid_date = NOW() WHERE id = ?");
                $stmt->execute([$loan_id]);
                $_SESSION['success'] = 'Loan marked as paid';
            }

            header('Location: loans.php?group_id=' . intval($loan['group_id']));
            exit();
        }
    } catch (Exception $e) {
        error_log('Loans action error: ' . $e->getMessage());
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
        // redirect back
        $back = $_POST['group_id'] ?? $_GET['group_id'] ?? '';
        header('Location: loans.php' . ($back ? '?group_id=' . intval($back) : ''));
        exit();
    }
}

// Page rendering
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans - BankingKhonde</title>
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

        <?php if ($group_id): ?>
            <?php
            // Get group and verify access
            $stmt = $db->prepare("SELECT g.*, u.full_name as treasurer_name FROM `groups` g JOIN `users` u ON g.treasurer_id = u.id WHERE g.id = ?");
            $stmt->execute([$group_id]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$group) {
                echo '<div class="message message-error">Group not found</div>';
            } else {
                // Check membership
                $is_treasurer = ($group['treasurer_id'] == $user_id);
                $stmt = $db->prepare("SELECT id FROM `group_members` WHERE group_id = ? AND user_id = ? AND status = 'active'");
                $stmt->execute([$group_id, $user_id]);
                $is_member = (bool)$stmt->fetch() || $is_treasurer;
                if (!$is_member) {
                    echo '<div class="message message-error">You are not a member of this group</div>';
                } else {
                    // Fetch loans for group
                    $stmt = $db->prepare("SELECT l.*, u.full_name, u.username FROM `loans` l JOIN `users` u ON l.user_id = u.id WHERE l.group_id = ? ORDER BY l.applied_date DESC");
                    $stmt->execute([$group_id]);
                    $loans = $stmt->fetchAll();
            ?>

            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <h2>Loans for <?php echo htmlspecialchars($group['name']); ?></h2>
                    <div><strong>Treasurer:</strong> <?php echo htmlspecialchars($group['treasurer_name']); ?></div>
                </div>
                <p>Group interest rate: <?php echo htmlspecialchars($group['interest_rate']); ?>%</p>
            </div>

            <?php if (!$is_treasurer): ?>
            <div class="card" style="margin-top:1rem;">
                <h3>Apply for Loan</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="apply_loan">
                    <input type="hidden" name="group_id" value="<?php echo intval($group_id); ?>">

                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="purpose">Purpose (optional)</label>
                        <textarea id="purpose" name="purpose" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="card" style="margin-top:1rem;">
                <h3>All Loan Applications</h3>
                <?php if (empty($loans)): ?>
                    <p>No loan applications yet.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Amount</th>
                                    <th>Interest</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Applied</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loan['full_name']) . ' (@' . htmlspecialchars($loan['username']) . ')'; ?></td>
                                    <td>K <?php echo number_format($loan['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
                                    <td>K <?php echo number_format($loan['total_amount'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $loan['status']; ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($loan['applied_date'])); ?></td>
                                    <td>
                                        <?php if ($is_treasurer && $loan['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="action" value="approve_loan">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button class="btn btn-success btn-sm" type="submit">Approve</button>
                                            </form>
                                            <form method="POST" style="display:inline-block; margin-left:6px;">
                                                <input type="hidden" name="action" value="reject_loan">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button class="btn btn-danger btn-sm" type="submit">Reject</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($is_treasurer && $loan['status'] === 'approved'): ?>
                                            <form method="POST" style="display:inline-block; margin-left:6px;">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button class="btn btn-primary btn-sm" type="submit">Mark Paid</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php
                } // end inner else (is_member)
            } // end else (group exists)
            ?>
        <?php else: ?>
            <?php
            // show list of groups user belongs to or treasurer created
            $groups = [];
            if ($role === 'treasurer') {
                $stmt = $db->prepare("SELECT g.*, COUNT(DISTINCT gm.user_id) as member_count FROM `groups` g LEFT JOIN `group_members` gm ON g.id = gm.group_id WHERE g.treasurer_id = ? GROUP BY g.id ORDER BY g.created_at DESC");
                $stmt->execute([$user_id]);
                $groups = $stmt->fetchAll();
            } else {
                $stmt = $db->prepare("SELECT g.*, COUNT(DISTINCT gm2.user_id) as member_count FROM `groups` g JOIN `group_members` gm ON g.id = gm.group_id LEFT JOIN `group_members` gm2 ON g.id = gm2.group_id WHERE gm.user_id = ? AND gm.status = 'active' GROUP BY g.id ORDER BY g.created_at DESC");
                $stmt->execute([$user_id]);
                $groups = $stmt->fetchAll();
            }
            ?>

        <div class="card">
            <h2>My Groups</h2>
            <?php if (empty($groups)): ?>
                <p>No groups found. Visit <a href="groups.php">Groups</a> to join or create one.</p>
            <?php else: ?>
                <div class="groups-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
                    <?php foreach ($groups as $g): ?>
                        <div class="card">
                            <h3><a href="?group_id=<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></a></h3>
                            <div><strong>Members:</strong> <?php echo $g['member_count']; ?></div>
                            <div style="margin-top:0.5rem;"><a href="?group_id=<?php echo $g['id']; ?>" class="btn btn-primary">View Loans</a></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </main>

    <script src="../assets/js/app.js"></script>
</body>
</html>
