<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_messages':
        getMessages($db);
        break;
    case 'send_message':
        sendMessage($db);
        break;
    case 'create_announcement':
        createAnnouncement($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getMessages($db) {
    $group_id = $_GET['group_id'] ?? null;
    $last_id = $_GET['last_id'] ?? null;

    if (!$group_id) {
        echo json_encode(['success' => false, 'message' => 'Group ID required']);
        return;
    }

    if ($last_id) {
        // Fetch only new messages since last_id
        $query = "SELECT cm.*, u.full_name as user_name,
                CASE WHEN cm.user_id = ? THEN 1 ELSE 0 END as is_own
            FROM `chat_messages` cm
            JOIN `users` u ON cm.user_id = u.id
            WHERE cm.group_id = ? AND cm.id > ?
            ORDER BY cm.created_at ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $group_id, $last_id]);
    } else {
        // Initial load: fetch all messages
        $query = "SELECT cm.*, u.full_name as user_name,
                CASE WHEN cm.user_id = ? THEN 1 ELSE 0 END as is_own
            FROM `chat_messages` cm
            JOIN `users` u ON cm.user_id = u.id
            WHERE cm.group_id = ?
            ORDER BY cm.created_at ASC
            LIMIT 100";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $group_id]);
    }

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
}

function sendMessage($db) {
    $group_id = $_POST['group_id'] ?? null;
    $message = $_POST['message'] ?? '';

    if (!$group_id || empty(trim($message))) {
        echo json_encode(['success' => false, 'message' => 'Group ID and message required']);
        return;
    }

    $user_id = $_SESSION['user_id'];

    // Verify user is member of the group
    $query = "SELECT id FROM `group_members` WHERE group_id = ? AND user_id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id, $user_id]);

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
        return;
    }

    $query = "INSERT INTO `chat_messages` (group_id, user_id, message) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);

    if ($stmt->execute([$group_id, $user_id, trim($message)])) {
        echo json_encode(['success' => true, 'message' => 'Message sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

function createAnnouncement($db) {
    $group_id = $_POST['group_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$group_id) {
        echo json_encode(['success' => false, 'message' => 'Group ID required']);
        return;
    }

    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        return;
    }

    $user_id = $_SESSION['user_id'];

    // Verify user is member of the group
    $query = "SELECT id FROM `group_members` WHERE group_id = ? AND user_id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id, $user_id]);

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Not a member of this group']);
        return;
    }

    try {
        $query = "INSERT INTO `announcements` (group_id, user_id, title, content) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);

        if ($stmt->execute([$group_id, $user_id, $title, $content])) {
            echo json_encode(['success' => true, 'message' => 'Announcement posted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to post announcement']);
        }
    } catch (Exception $e) {
        error_log("Error creating announcement: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again']);
    }
}
?>