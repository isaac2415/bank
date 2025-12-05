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

$action = $_GET['action'] ?? 'list';
$group_id = $_GET['group_id'] ?? null;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;

    switch ($action) {
        case 'remove':
            removeMember($db);
            break;
        case 'update':
            updateMember($db);
            break;
    }
}

function removeMember($db)
{
    try {
        $group_id = $_POST['group_id'];
        $user_id = $_POST['user_id'];
        $current_user_id = $_SESSION['user_id'];

        // Verify the user is treasurer of this group
        $query = "SELECT treasurer_id FROM `groups` WHERE id = ? AND treasurer_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $current_user_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Unauthorized action");
        }

        // Get user info before removal
        $query = "SELECT u.full_name FROM `group_members` gm
                  JOIN `users` u ON gm.user_id = u.id
                  WHERE gm.group_id = ? AND gm.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $user_id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            throw new Exception("Member not found");
        }

        // Cannot remove yourself
        if ($user_id == $current_user_id) {
            throw new Exception("Cannot remove yourself from the group");
        }

        // Check if user has active loans
        $query = "SELECT COUNT(*) as active_loans FROM `loans` 
                  WHERE group_id = ? AND user_id = ? AND status IN ('pending', 'active')";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['active_loans'] > 0) {
            $_SESSION['error'] = "Cannot remove member with active loans";
            header("Location: members1.php?group_id=$group_id");
            exit();
        }

        // Remove member
        $query = "DELETE FROM `group_members` WHERE group_id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$group_id, $user_id])) {
            $_SESSION['success'] = "Member {$member['full_name']} removed successfully";
        } else {
            throw new Exception("Failed to remove member");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: members1.php?group_id=$group_id");
    exit();
}

function updateMember($db)
{
    try {
        $group_id = $_POST['group_id'];
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];
        $current_user_id = $_SESSION['user_id'];

        // Verify the user is treasurer of this group
        $query = "SELECT treasurer_id FROM `groups` WHERE id = ? AND treasurer_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $current_user_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Unauthorized action");
        }

        // Update member status
        $query = "UPDATE `group_members` SET status = ? WHERE group_id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$status, $group_id, $user_id])) {
            $status_text = $status == 'active' ? 'activated' : 'suspended';
            
            // Get user info
            $query = "SELECT u.full_name FROM `users` u WHERE u.id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['success'] = "Member {$user['full_name']} {$status_text} successfully";
        } else {
            throw new Exception("Failed to update member");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: members1.php?group_id=$group_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Members - BankingKhonde</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <style>
        .members-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .members-table th,
        .members-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .members-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .members-table tr:hover {
            background: #f9f9f9;
        }
        
        .member-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .status-suspended {
            background: #dc3545;
            color: white;
        }
        
        .search-box {
            margin-bottom: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .search-box input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }
        
        .btn-sm {
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

        <?php
        if (!$group_id) {
            echo '<div class="message message-error">Group ID is required</div>';
            exit();
        }
        
        // Check if user is treasurer of this group
        $query = "SELECT g.name, g.treasurer_id FROM `groups` g WHERE g.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            echo '<div class="message message-error">Group not found</div>';
            exit();
        }
        
        // Verify user is treasurer
        if ($group['treasurer_id'] != $_SESSION['user_id']) {
            echo '<div class="message message-error">You are not authorized to manage members of this group</div>';
            exit();
        }
        
        // Get search term
        $search = $_GET['search'] ?? '';
        
        // Get members with search filter
        $query = "SELECT 
                    u.id,
                    u.full_name,
                    u.username,
                    u.phone,
                    gm.status,
                    gm.joined_at
                  FROM `group_members` gm
                  JOIN `users` u ON gm.user_id = u.id
                  WHERE gm.group_id = ?
                  AND (u.full_name LIKE ? OR u.username LIKE ? OR u.phone LIKE ?)
                  ORDER BY gm.joined_at DESC";
        
        $search_term = "%$search%";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $search_term, $search_term, $search_term]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Members of <?php echo htmlspecialchars($group['name']); ?></h2>
                <a href="groups.php?action=view&id=<?php echo $group_id; ?>" class="btn btn-secondary">Back to Group</a>
            </div>
            
            <!-- Search Box -->
            <form method="GET" class="search-box">
                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                <input type="text" name="search" placeholder="Search by name, username, or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?>
                    <a href="members1.php?group_id=<?php echo $group_id; ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
            
            <!-- Members Table -->
            <?php if (empty($members)): ?>
                <div class="message message-info">No members found.</div>
            <?php else: ?>
                <table class="members-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <td>@<?php echo htmlspecialchars($member['username']); ?></td>
                                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                <td>
                                    <span class="member-status status-<?php echo $member['status']; ?>">
                                        <?php echo ucfirst($member['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($member['joined_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Remove Button -->
                                        <?php if ($member['id'] != $group['treasurer_id']): ?>
                                            <form method="POST" onsubmit="return confirm('Remove this member?');" style="display: inline;">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Status Toggle -->
                                        <!-- <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $member['status'] == 'active' ? 'suspended' : 'active'; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <?php echo $member['status'] == 'active' ? 'Suspend' : 'Activate'; ?>
                                            </button>
                                        </form> -->
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 1rem; color: #666;">
                    Total: <?php echo count($members); ?> member(s)
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>