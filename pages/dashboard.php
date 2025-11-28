<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/auth.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$sql = "SELECT COUNT(amount) AS number_of_approved FROM loans WHERE status = 'approved'";
$stmt = $db->query($sql);

// Fetch the result
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Store result in variable
$numberofapproved = $row['number_of_approved'] ?? 0;

// SQL query to get sum of 'amount' where status is 'approved'
$sql = "SELECT SUM(amount) AS total_approved FROM loans WHERE status = 'approved'";
$stmt = $db->query($sql);

// Fetch the result
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Store result in variable
$totalApproved = $row['total_approved'] ?? 0;


$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

    // Get user's groups and statistics
    if ($role === 'treasurer') {
        $query = "SELECT g.*,
            COUNT(DISTINCT gm.user_id) as member_count,
            COUNT(DISTINCT l.id) as loan_count,
            (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p WHERE p.group_id = g.id AND p.status = 'paid') +
            (SELECT COALESCE(SUM(ln.total_amount), 0) FROM `loans` ln WHERE ln.group_id = g.id AND ln.status = 'paid') as total_contributions
        FROM `groups` g
        LEFT JOIN `group_members` gm ON g.id = gm.group_id
        LEFT JOIN `loans` l ON g.id = l.group_id
        WHERE g.treasurer_id = ?
        GROUP BY g.id
        ORDER BY g.created_at DESC
        LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $groups = $stmt->fetchAll();
    
    // Get overall statistics for treasurer
        $query = "SELECT
                            COUNT(DISTINCT g.id) as total_groups,
                            COUNT(DISTINCT gm.user_id) as total_members,
                            (SELECT COUNT(*) FROM `loans`
                             WHERE group_id IN (SELECT id FROM `groups` WHERE treasurer_id = ?)
                             AND status != 'paid') as total_loans,
                            (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p
                             JOIN `groups` g2 ON p.group_id = g2.id
                             WHERE g2.treasurer_id = ? AND p.status = 'paid') +
                            (SELECT COALESCE(SUM(l.total_amount), 0) FROM `loans` l
                             JOIN `groups` g3 ON l.group_id = g3.id
                             WHERE g3.treasurer_id = ? AND l.status = 'paid') as total_contributions
                            FROM `groups` g
                            LEFT JOIN `group_members` gm ON g.id = gm.group_id
                            WHERE g.treasurer_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} else {
    $query = "SELECT g.*, 
            COUNT(DISTINCT gm.user_id) as member_count,
            COUNT(DISTINCT l.id) as loan_count
        FROM `groups` g 
        JOIN `group_members` gm ON g.id = gm.group_id 
        LEFT JOIN `loans` l ON g.id = l.group_id 
        WHERE gm.user_id = ? 
        GROUP BY g.id
        ORDER BY g.created_at DESC
        LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $groups = $stmt->fetchAll();
    
    // Get overall statistics for member
    $query = "SELECT
              COUNT(DISTINCT gm.group_id) as total_groups,
              (SELECT COUNT(*) FROM `payments` WHERE user_id = ? AND status = 'paid') as total_payments,
              (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p WHERE p.user_id = ? AND p.status = 'paid') +
              (SELECT COALESCE(SUM(l.total_amount), 0) FROM `loans` l WHERE l.user_id = ? AND l.status = 'paid') as total_contributions,
              (SELECT COUNT(*) FROM `loans`
               WHERE user_id = ? AND status != 'paid') as total_loans
              FROM `group_members` gm
              WHERE gm.user_id = ? AND gm.status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get pending loans for notification
if ($role === 'treasurer') {
    $query = "SELECT COUNT(*) as pending_loans 
              FROM `loans` l 
              JOIN `groups` g ON l.group_id = g.id 
              WHERE g.treasurer_id = ? AND l.status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $pending_loans = $stmt->fetch(PDO::FETCH_ASSOC)['pending_loans'];
} else {
    $query = "SELECT COUNT(*) as pending_loans 
              FROM `loans` 
              WHERE user_id = ? AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $pending_loans = $stmt->fetch(PDO::FETCH_ASSOC)['pending_loans'];
}

// Get recent announcements
$query = "SELECT 
                        a.id, 
                        a.title, 
                        a.content, 
                        a.created_at, 
                        g.name as group_name, 
                        u.full_name 
                    FROM `announcements` a
                    JOIN `groups` g ON a.group_id = g.id
                    JOIN `users` u ON a.user_id = u.id
                    WHERE a.group_id IN (SELECT group_id FROM `group_members` WHERE user_id = ? AND status = 'active')
                    ORDER BY a.created_at DESC 
                    LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$announcements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BankingKhonde</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container">
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
        <p>Here's what's happening with your groups today.</p>
        
        <!-- Quick Stats -->
        <div class="dashboard-cards">
            <div class="card">
                <h3>My Groups</h3>
                <p id="total-groups"><?php echo $stats['total_groups'] ?? 0; ?></p>
            </div>
            
            <?php if ($role === 'treasurer'): ?>
            <div class="card">
                <h3>Total Members</h3>
                <p id="total-members"><?php echo $stats['total_members'] ?? 0; ?></p>
            </div>
            <div class="card">
                <h3>Active Loans</h3>
                <p id="total-loans" style="color: #dc3545;"><?php echo $numberofapproved ?? 0; ?></p>
            </div>
            <div class="card">
                <h3>Total Contributions</h3>
                <p id="total-balance" style="color: #28a745; font-weight: bold;">K <?php echo number_format($stats['total_contributions'] - $totalApproved, 2); ?></p>
            </div>
            <?php else: ?>
            <div class="card">
                <h3>My Payments</h3>
                <p id="total-payments"><?php echo $stats['total_payments'] ?? 0; ?></p>
            </div>
            <div class="card">
                <h3>My Contributions</h3>
                <p id="total-contributions">K <?php echo number_format($stats['total_contributions'] ?? 0, 2); ?></p>
            </div>
            <div class="card">
                <h3>My Loans</h3>
                <p id="my-loans"><?php echo $stats['total_loans'] ?? 0; ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="groups.php" class="action-card">
                <div class="action-icon">
                    <span class="material-icons">groups</span>
                </div>
                <h3>My Groups</h3>
                <p>View and manage your groups</p>
            </a>
            
            <?php if ($role === 'treasurer'): ?>
            <a href="groups.php?action=create" class="action-card">
                <div class="action-icon">
                    <span class="material-icons">add_circle</span>
                </div>
                <h3>Create Group</h3>
                <p>Start a new banking group</p>
            </a>
            <?php endif; ?>
            
            <a href="groups.php?action=join" class="action-card">
                <div class="action-icon">
                    <span class="material-icons">link</span>
                </div>
                <h3>Join Group</h3>
                <p>Join with a group code</p>
            </a>
            
            <a href="loans.php" class="action-card">
                <div class="action-icon">
                    <span class="material-icons">request_quote</span>
                </div>
                <h3>Loans
                    <?php if ($pending_loans > 0): ?>
                        <span class="notification-badge"><?php echo $pending_loans; ?></span>
                    <?php endif; ?>
                </h3>
                <p>Manage loan applications</p>
            </a>
            
            <a href="payments.php" class="action-card">
                <div class="action-icon">
                    <span class="material-icons">payments</span>
                </div>
                <h3>Payments</h3>
                <p>Track contributions</p>
            </a>
            
            <a href="reports.php" class="action-card">
                <div class="action-icon">
                    <span class="material-icons">analytics</span>
                </div>
                <h3>Reports</h3>
                <p>View analytics & insights</p>
            </a>
        </div>

        <div class="dashboard-grid">
            <!-- Recent Groups -->
            <div class="card">
                <h3>Recent Groups</h3>
                <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <span class="material-icons">groups</span>
                        </div>
                        <p>You haven't joined any groups yet.</p>
                        <div style="display: flex; gap: 1rem; margin-top: 1rem; justify-content: center;">
                            <?php if ($role === 'treasurer'): ?>
                                <a href="groups.php?action=create" class="btn btn-primary">
                                    <span class="material-icons">add_circle</span>
                                    Create Your First Group
                                </a>
                            <?php endif; ?>
                            <a href="groups.php?action=join" class="btn btn-primary" style="text-decoration: none; align-items: center; display: flex; gap: 0.5rem;">
                                <span class="material-icons">link</span>
                                Join a Group
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Group Name</th>
                                    <th>Members</th>
                                    <th>Contribution</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($group['name']); ?></td>
                                    <td><?php echo $group['member_count']; ?></td>
                                    <td>K <?php echo number_format($group['contribution_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $group['status']; ?>">
                                            <?php echo ucfirst($group['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="groups.php?action=view&id=<?php echo $group['id']; ?>" class="btn btn-primary btn-sm">
                                            <span class="material-icons">visibility</span>
                                    
                                        </a>
                                        <a href="chat.php?group_id=<?php echo $group['id']; ?>" class="btn btn-success btn-sm">
                                            <span class="material-icons">chat</span>
                                            
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="groups.php" class="btn btn-primary">
                            <span class="material-icons">list</span>
                            View All Groups
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div>
                <!-- Recent Announcements -->
                <div class="card">
                    <h3>Recent Announcements</h3>
                    <?php if (empty($announcements)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <span class="material-icons">campaign</span>
                            </div>
                            <p>No recent announcements.</p>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <h4 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                    <p class="announcement-content"><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 100))); ?>...</p>
                                    <small class="announcement-meta">
                                        In <?php echo htmlspecialchars($announcement['group_name']); ?> • 
                                        By <?php echo htmlspecialchars($announcement['full_name']); ?> • 
                                        <?php echo date('M j, g:i A', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Links -->
                <div class="card" style="margin-top: 1.5rem;">
                    <h3>Quick Links</h3>
                    <div class="quick-links">
                        <a href="profile.php" class="quick-link">
                            <span class="material-icons quick-link-icon">person</span>
                            My Profile
                        </a>
                        <?php if ($role === 'treasurer'): ?>
                        <a href="reports.php" class="quick-link">
                            <span class="material-icons quick-link-icon">analytics</span>
                            Group Analytics
                        </a>
                        <a href="members.php" class="quick-link">
                            <span class="material-icons quick-link-icon">group</span>
                            Manage Members
                        </a>
                        <?php endif; ?>
                        <a href="payments.php" class="quick-link">
                            <span class="material-icons quick-link-icon">payments</span>
                            My Payments
                        </a>
                        <a href="../logout.php" class="quick-link">
                            <span class="material-icons quick-link-icon">logout</span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/app.js"></script>
</body>
</html>