<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_unpaid_meetings':
        getUnpaidMeetings($db);
        break;
    case 'record_payment':
        recordPayment($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getUnpaidMeetings($db) {
    $group_id = $_GET['group_id'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$group_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Group ID and User ID required']);
        return;
    }
    
    $query = "SELECT m.id, m.meeting_date, p.amount 
              FROM `meetings` m
              JOIN `payments` p ON m.id = p.meeting_id
              WHERE p.group_id = ? AND p.user_id = ? AND p.status = 'missed'
              ORDER BY m.meeting_date ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id, $user_id]);
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'meetings' => $meetings]);
}

function recordPayment($db) {
    $group_id = $_POST['group_id'] ?? null;
    $meeting_id = $_POST['meeting_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    
    if (!$group_id || !$meeting_id || !$amount) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    
    $query = "UPDATE `payments` 
              SET status = 'paid', amount = ?, payment_date = NOW() 
              WHERE group_id = ? AND user_id = ? AND meeting_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$amount, $group_id, $user_id, $meeting_id])) {
        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
    }
}
?>