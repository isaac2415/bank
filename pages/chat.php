<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$group_id = $_GET['group_id'] ?? null;

if (!$group_id) {
    header("Location: groups.php");
    exit();
}

// Verify user is member of the group
$user_id = $_SESSION['user_id'];
$query = "SELECT g.name, gm.id as member_id 
          FROM `groups` g 
          LEFT JOIN `group_members` gm ON g.id = gm.group_id AND gm.user_id = ?
          WHERE g.id = ? AND g.status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute([$user_id, $group_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group || !$group['member_id']) {
    header("Location: groups.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        sendMessage($db);
    } elseif ($action === 'create_announcement') {
        createAnnouncement($db);
        // Redirect to prevent form resubmission
        header("Location: chat.php?group_id=$group_id");
        exit();
    }
}

// Keep the functions for backward compatibility, but they won't be used for AJAX requests

function sendMessage($db) {
    global $group_id, $user_id;
    
    $message = $_POST['message'];
    
    if (empty(trim($message))) {
        return;
    }
    
    $query = "INSERT INTO `chat_messages` (group_id, user_id, message) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$group_id, $user_id, trim($message)]);
}

function createAnnouncement($db) {
    global $group_id, $user_id;

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required";
        return;
    }

    try {
        $query = "INSERT INTO `announcements` (`group_id`, `user_id`, `title`, `content`) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $user_id, $title, $content]);

        $_SESSION['success'] = "Announcement posted successfully";
    } catch (Exception $e) {
        error_log("Error creating announcement: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Chat - BankingKhonde</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .chat-container {
        display: grid;
        grid-template-rows: auto 1fr auto;
        height: 70vh;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .chat-header {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        background: #f8f9fa;
        border-radius: 10px 10px 0 0;
    }
    
    .chat-messages {
        padding: 1rem;
        overflow-y: auto;
        max-height: 50vh;
    }
    
    .chat-message {
        margin-bottom: 1rem;
        padding: 0.75rem;
        border-radius: 10px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
    }
    
    .chat-message.own {
        background: #667eea;
        color: white;
        margin-left: 2rem;
        border: none;
    }
    
    .message-sender {
        font-weight: bold;
        margin-bottom: 0.25rem;
    }
    
    .message-time {
        font-size: 0.75rem;
        opacity: 0.7;
        margin-top: 0.25rem;
    }
    
    .chat-input {
        display: flex;
        padding: 1rem;
        border-top: 1px solid #eee;
        background: #f8f9fa;
        border-radius: 0 0 10px 10px;
    }
    
    .chat-input input {
        flex: 1;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-right: 0.5rem;
    }
    
    .announcement-form {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 5px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    </style>
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
                <h2>Group Chat - <?php echo htmlspecialchars($group['name']); ?></h2>
                <a href="groups.php?action=view&id=<?php echo $group_id; ?>" class="btn btn-primary">Back to Group</a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
            <!-- Chat Interface -->
            <div class="chat-container">
                <div class="chat-header">
                    <h3 style="margin: 0;">Group Discussion</h3>
                    <small>Real-time chat with all group members</small>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will be loaded via JavaScript -->
                </div>
                
                <div class="chat-input">
                    <input type="text" id="messageInput" placeholder="Type your message here..." maxlength="500">
                    <button type="button" class="btn btn-primary" onclick="sendMessage()">Send</button>
                </div>
            </div>

            <!-- Announcements and Members -->
            <div>
                <!-- Create Announcement -->
                <div class="card">
                    <h3>Create Announcement</h3>
                    <form class="ajax-form" data-reset="true">
                        <input type="hidden" name="action" value="create_announcement">
                        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                        
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" id="title" name="title" required maxlength="255">
                        </div>
                        
                        <div class="form-group">
                            <label for="content">Content:</label>
                            <textarea id="content" name="content" rows="3" required maxlength="1000"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Post Announcement</button>
                    </form>
                </div>

                <!-- Online Members -->
                <div class="card" style="margin-top: 1.5rem;">
                    <h3>Group Members</h3>
                    <?php
                    $query = "SELECT u.full_name, u.username 
                             FROM group_members gm 
                             JOIN users u ON gm.user_id = u.id 
                             WHERE gm.group_id = ? AND gm.status = 'active' 
                             ORDER BY u.full_name";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$group_id]);
                    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($members as $member): ?>
                            <div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                                <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                <div style="font-size: 0.875rem; color: #666;">
                                    @<?php echo htmlspecialchars($member['username']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/app.js"></script>
    <script>
    let chatPolling;
    let lastMessageId = 0;
    let isAtBottom = true;

    function loadMessages() {
        const container = document.getElementById('chatMessages');
        const wasAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 10;

        fetch(`../api/chat.php?action=get_messages&group_id=<?php echo $group_id; ?>&last_id=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(message => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `chat-message ${message.is_own ? 'own' : ''}`;

                        messageDiv.innerHTML = `
                            <div class="message-sender">${message.user_name}</div>
                            <div class="message-text">${escapeHtml(message.message)}</div>
                            <div class="message-time">${formatTime(message.created_at)}</div>
                        `;

                        container.appendChild(messageDiv);
                        lastMessageId = Math.max(lastMessageId, message.id);
                    });

                    // Scroll to bottom only if user was already at bottom
                    if (wasAtBottom) {
                        container.scrollTop = container.scrollHeight;
                    }
                }
            });
    }
    
    function sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();

        if (!message) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('group_id', <?php echo $group_id; ?>);
        formData.append('message', message);

        fetch('../api/chat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageInput.value = '';
                // After sending, load new messages (which will append)
                loadMessages();
            }
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
    }
    
    // Handle Enter key in message input
    document.getElementById('messageInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    // Start polling for new messages
    chatPolling = setInterval(loadMessages, 2000);
    
    // Load messages initially
    loadMessages();
    
    // Clean up polling when leaving the page
    window.addEventListener('beforeunload', function() {
        clearInterval(chatPolling);
    });
    </script>
</body>
</html>