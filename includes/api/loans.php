<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'apply':
        applyForLoan($db);
        break;
    case 'approve':
        approveLoan($db);
        break;
    case 'get_loans':
        getLoans($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function applyForLoan($db) {
    requireLogin();
    
    $user_id = $_SESSION['user_id'];
    $group_id = $_POST['group_id'];
    $amount = $_POST['amount'];
    $purpose = $_POST['purpose'];
    
    // Get group interest rate
    $query = "SELECT interest_rate, loan_repayment_days FROM `groups` WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        return;
    }
    
    $interest_rate = $group['interest_rate'];
    $total_amount = $amount + ($amount * $interest_rate / 100);
    $due_date = date('Y-m-d', strtotime("+{$group['loan_repayment_days']} days"));
    
    $query = "INSERT INTO `loans` (group_id, user_id, amount, interest_rate, total_amount, purpose, due_date) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$group_id, $user_id, $amount, $interest_rate, $total_amount, $purpose, $due_date])) {
        echo json_encode(['success' => true, 'message' => 'Loan application submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit loan application']);
    }
}

function approveLoan($db) {
    requireTreasurer();
    
    $loan_id = $_POST['loan_id'];
    $action = $_POST['approval_action'];
    
    if ($action === 'approve') {
            $query = "UPDATE `loans` SET status = 'approved', approved_date = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$loan_id]);
        
        echo json_encode(['success' => true, 'message' => 'Loan approved successfully']);
    } else {
    $query = "UPDATE `loans` SET status = 'rejected' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$loan_id]);
        
        echo json_encode(['success' => true, 'message' => 'Loan rejected']);
    }
}

function getLoans($db) {
    requireLogin();
    
    $user_id = $_SESSION['user_id'];
    $group_id = $_GET['group_id'] ?? null;
    
    if ($_SESSION['role'] === 'treasurer') {
        if ($group_id) {
        $query = "SELECT l.*, u.full_name, u.username 
                  FROM `loans` l 
                  JOIN `users` u ON l.user_id = u.id 
                  WHERE l.group_id = ? 
                  ORDER BY l.applied_date DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id]);
        } else {
            $query = "SELECT l.*, u.full_name, u.username, g.name as group_name 
                      FROM `loans` l 
                      JOIN `users` u ON l.user_id = u.id 
                      JOIN `groups` g ON l.group_id = g.id 
                      WHERE g.treasurer_id = ? 
                      ORDER BY l.applied_date DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
        }
    } else {
    $query = "SELECT l.*, g.name as group_name 
          FROM `loans` l 
          JOIN `groups` g ON l.group_id = g.id 
          WHERE l.user_id = ? 
          ORDER BY l.applied_date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
    }
    
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'loans' => $loans]);
}
?>