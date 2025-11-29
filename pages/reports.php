<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/auth.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$group_id = $_GET['group_id'] ?? null;
$report_type = $_GET['type'] ?? 'overview';
$user_id = $_SESSION['user_id'];


// Only verify group access if a group_id is provided
if ($group_id) {
    // Verify user has access to this group
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
}

function getGroupOverview($db, $group_id) {
    $data = [];
    
    try {
        // Basic group stats
        $query = "SELECT
                  (SELECT COUNT(DISTINCT gm2.user_id) FROM `group_members` gm2 WHERE gm2.group_id = ?) as total_members,
                  (SELECT COUNT(*) FROM `meetings` m2 WHERE m2.group_id = ?) as total_meetings,
                  (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p WHERE p.group_id = ? AND p.status = 'paid') +
                  (SELECT COALESCE(SUM(l.total_amount), 0) FROM `loans` l WHERE l.group_id = ? AND l.status = 'paid') as total_contributions,
                  (SELECT COUNT(*) FROM `loans` l2 WHERE l2.group_id = ?) as total_loans,
                  (SELECT COALESCE(SUM(l2.amount), 0) FROM `loans` l2 WHERE l2.group_id = ? AND l2.status = 'approved') as active_loans
                  FROM `groups` g
                  WHERE g.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id, $group_id, $group_id, $group_id, $group_id, $group_id, $group_id]);
        $data['overview'] = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

        // Monthly contributions
        $query = "SELECT
                  DATE_FORMAT(m.meeting_date, '%Y-%m') as month,
                  SUM(p.amount) as total_contributions
                  FROM `payments` p
                  JOIN `meetings` m ON p.meeting_id = m.id
                  WHERE p.group_id = ? AND p.status = 'paid'
                  GROUP BY DATE_FORMAT(m.meeting_date, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT 12";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        $data['monthly_contributions'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

        // Member performance
        $query = "SELECT
                  u.full_name,
                  COUNT(p.id) as total_meetings,
                  SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) as meetings_paid,
                  SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as total_paid,
                  CASE
                    WHEN COUNT(p.id) > 0 THEN
                      (SUM(CASE WHEN p.status = 'paid' THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id))
                    ELSE 0
                  END as attendance_rate
                  FROM `group_members` gm
                  JOIN `users` u ON gm.user_id = u.id
                  LEFT JOIN `payments` p ON gm.user_id = p.user_id AND p.group_id = gm.group_id
                  WHERE gm.group_id = ? AND gm.status = 'active'
                  GROUP BY u.id, u.full_name
                  ORDER BY attendance_rate DESC, total_paid DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        $data['member_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

        // Loan statistics
        $query = "SELECT
                  status,
                  COUNT(*) as count,
                  COALESCE(SUM(amount), 0) as total_amount
                  FROM `loans`
                  WHERE group_id = ?
                  GROUP BY status";
        $stmt = $db->prepare($query);
        $stmt->execute([$group_id]);
        $data['loan_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    } catch (Exception $e) {
        error_log("Error in getGroupOverview: " . $e->getMessage());
        // Set default values
        $data = [
            'overview' => ['total_members' => 0, 'total_meetings' => 0, 'total_contributions' => 0, 'total_loans' => 0, 'active_loans' => 0],
            'monthly_contributions' => [],
            'member_performance' => [],
            'loan_stats' => []
        ];
    }
    
    return $data;
}

function getPersonalReports($db, $user_id) {
    $data = [];
    
    try {
        // Overall personal stats
        $query = "SELECT
                  (SELECT COUNT(DISTINCT gm2.group_id) FROM `group_members` gm2 WHERE gm2.user_id = ? AND gm2.status = 'active') as total_groups,
                  (SELECT COUNT(DISTINCT p.id) FROM `payments` p WHERE p.user_id = ? AND p.status = 'paid') as total_payments,
                  (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p WHERE p.user_id = ? AND p.status = 'paid') as total_contributed,
                  (SELECT COUNT(DISTINCT l.id) FROM `loans` l WHERE l.user_id = ?) as total_loans_applied,
                  (SELECT COALESCE(SUM(l.amount), 0) FROM `loans` l WHERE l.user_id = ? AND l.status = 'approved') as total_loans_approved
                  FROM `users` u
                  WHERE u.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
        $data['overview'] = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

        // Group-wise breakdown
        $query = "SELECT
                  g.name as group_name,
                  (SELECT COUNT(p.id) FROM `payments` p WHERE p.user_id = ? AND p.group_id = g.id AND p.status = 'paid') as payments_count,
                  (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p WHERE p.user_id = ? AND p.group_id = g.id AND p.status = 'paid') as total_contributed,
                  (SELECT COUNT(l.id) FROM `loans` l WHERE l.user_id = ? AND l.group_id = g.id) as loans_count,
                  (SELECT COUNT(*) FROM `payments` p2
                   JOIN `meetings` m ON p2.meeting_id = m.id
                   WHERE p2.user_id = ? AND p2.group_id = g.id AND p2.status = 'missed') as missed_payments
                  FROM `group_members` gm
                  JOIN `groups` g ON gm.group_id = g.id
                  WHERE gm.user_id = ? AND gm.status = 'active'
                  GROUP BY g.id, g.name";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
        $data['group_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    } catch (Exception $e) {
        error_log("Error in getPersonalReports: " . $e->getMessage());
        // Set default values
        $data = [
            'overview' => ['total_groups' => 0, 'total_payments' => 0, 'total_contributed' => 0, 'total_loans_applied' => 0, 'total_loans_approved' => 0],
            'group_breakdown' => []
        ];
    }
    
    return $data;
}

// Get user's groups for the dropdown
$user_groups = [];
try {
    $query = "SELECT g.id, g.name
             FROM `groups` g
             JOIN `group_members` gm ON g.id = gm.group_id
             WHERE gm.user_id = ? AND gm.status = 'active'
             ORDER BY g.name";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user groups: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - BankingKhonde</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .btn{
            text-decoration: none;
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-style: italic;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <main class="container">
        <div class="card">
            <h2>Reports & Analytics</h2>
            
            <!-- Report Type Selector -->
            <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                <?php if ($group_id): ?>
                    <a href="reports.php?group_id=<?php echo $group_id; ?>&type=overview" 
                       class="btn <?php echo $report_type === 'overview' ? 'btn-primary' : 'btn-secondary'; ?>">
                        Group Overview
                    </a>
                    <a href="reports.php?group_id=<?php echo $group_id; ?>&type=members" 
                       class="btn <?php echo $report_type === 'members' ? 'btn-primary' : 'btn-secondary'; ?>">
                        Member Performance
                    </a>
                    <a href="reports.php?group_id=<?php echo $group_id; ?>&type=loans" 
                       class="btn <?php echo $report_type === 'loans' ? 'btn-primary' : 'btn-secondary'; ?>">
                        Loan Analysis
                    </a>
                <?php endif; ?>
                <a href="reports.php?type=personal" 
                   class="btn <?php echo $report_type === 'personal' ? 'btn-primary' : 'btn-secondary'; ?>">
                    Personal Reports
                </a>
            </div>

            <!-- Group Selector -->
            <?php if ($report_type !== 'personal'): ?>
            <div class="form-group">
                <label for="groupSelect">Select Group:</label>
                <select id="groupSelect" onchange="loadGroupReports(this.value)">
                    <option value="">Select a group</option>
                    <?php foreach ($user_groups as $group_item): ?>
                        <option value="<?php echo $group_item['id']; ?>" <?php echo ($group_id == $group_item['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group_item['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($report_type === 'overview' && $group_id): ?>
        <?php
        $report_data = getGroupOverview($db, $group_id);
        $overview = $report_data['overview'] ?? [];
        $monthly_contributions = $report_data['monthly_contributions'] ?? [];
        $member_performance = $report_data['member_performance'] ?? [];
        $loan_stats = $report_data['loan_stats'] ?? [];
        ?>
        
        <!-- Overview Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Members</h3>
                <p><?php echo $overview['total_members'] ?? 0; ?></p>
            </div>
            <div class="card">
                <h3>Total Meetings</h3>
                <p><?php echo $overview['total_meetings'] ?? 0; ?></p>
            </div>
            <div class="card">
                <h3>Total Contributions</h3>
                <p>K <?php echo number_format($overview['total_contributions'] ?? 0, 2);?> <br> No Interest</p>
            </div>
            <div class="card">
                <h3>Active Loans</h3>
                <p>K <?php echo number_format($overview['active_loans'] ?? 0, 2); ?></p>
            </div>
        </div>

        <!-- Charts -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
            <!-- Monthly Contributions Chart -->
            <div class="card">
                <h3>Monthly Contributions</h3>
                <div class="chart-container">
                    <canvas id="contributionsChart"></canvas>
                </div>
                <?php if (empty($monthly_contributions)): ?>
                    <div class="no-data">No contribution data available</div>
                <?php endif; ?>
            </div>

            <!-- Loan Status Chart -->
            <div class="card">
                <h3>Loan Distribution</h3>
                <div class="chart-container">
                    <canvas id="loansChart"></canvas>
                </div>
                <?php if (empty($loan_stats)): ?>
                    <div class="no-data">No loan data available</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Member Performance -->
        <div class="card" style="margin-top: 1.5rem;">
            <h3>Member Performance</h3>
            <?php if (empty($member_performance)): ?>
                <div class="no-data">No member performance data available</div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Total Meetings</th>
                                <th>Paid</th>
                                <th>Attendance Rate</th>
                                <th>Total Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($member_performance as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                <td><?php echo $member['total_meetings']; ?></td>
                                <td><?php echo $member['meetings_paid']; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="flex: 1; background: #e9ecef; border-radius: 10px; height: 8px;">
                                            <div style="background: #667eea; height: 100%; border-radius: 10px; width: <?php echo min($member['attendance_rate'], 100); ?>%;"></div>
                                        </div>
                                        <?php echo number_format($member['attendance_rate'], 1); ?>%
                                    </div>
                                </td>
                                <td>K <?php echo number_format($member['total_paid'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Contributions Chart
            const contributionsCtx = document.getElementById('contributionsChart');
            if (contributionsCtx) {
                const contributionsData = {
                    labels: [<?php 
                        if (!empty($monthly_contributions)) {
                            echo implode(', ', array_map(function($item) { 
                                return "'" . date('M Y', strtotime($item['month'] . '-01')) . "'"; 
                            }, $monthly_contributions));
                        }
                    ?>],
                    datasets: [{
                        label: 'Contributions',
                        data: [<?php 
                            if (!empty($monthly_contributions)) {
                                echo implode(', ', array_column($monthly_contributions, 'total_contributions'));
                            }
                        ?>],
                        backgroundColor: '#667eea',
                        borderColor: '#5a6fd8',
                        borderWidth: 1
                    }]
                };

                new Chart(contributionsCtx, {
                    type: 'bar',
                    data: contributionsData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'K ' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Loans Chart
            const loansCtx = document.getElementById('loansChart');
            if (loansCtx) {
                const loansData = {
                    labels: [<?php 
                        if (!empty($loan_stats)) {
                            echo implode(', ', array_map(function($item) { 
                                return "'" . ucfirst($item['status']) . "'"; 
                            }, $loan_stats));
                        }
                    ?>],
                    datasets: [{
                        data: [<?php 
                            if (!empty($loan_stats)) {
                                echo implode(', ', array_column($loan_stats, 'count'));
                            }
                        ?>],
                        backgroundColor: ['#667eea', '#28a745', '#dc3545', '#ffc107', '#6c757d']
                    }]
                };

                new Chart(loansCtx, {
                    type: 'pie',
                    data: loansData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });
        </script>

        <?php elseif ($report_type === 'personal'): ?>
        <?php
        $personal_data = getPersonalReports($db, $user_id);
        $personal_overview = $personal_data['overview'] ?? [];
        $group_breakdown = $personal_data['group_breakdown'] ?? [];
        ?>
        
        <!-- Personal Overview -->
        <div class="dashboard-cards">
            <div class="card">
                <h3>Active Groups</h3>
                <p><?php echo $personal_overview['total_groups'] ?? 0; ?></p>
            </div>
            <div class="card">
                <h3>Total Payments</h3>
                <p><?php echo $personal_overview['total_payments'] ?? 0; ?></p>
            </div>
            <div class="card">
                <h3>Total Contributed</h3>
                <p>K <?php echo number_format($personal_overview['total_contributed'] ?? 0, 2); ?></p>
            </div>
            <div class="card">
                <h3>Approved Loans</h3>
                <p>K <?php echo number_format($personal_overview['total_loans_approved'] ?? 0, 2); ?></p>
            </div>
        </div>

        <!-- Group Breakdown -->
        <div class="card" style="margin-top: 1.5rem;">
            <h3>Group-wise Breakdown</h3>
            <?php if (empty($group_breakdown)): ?>
                <div class="no-data">No group data available</div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Group Name</th>
                                <th>Payments Made</th>
                                <th>Total Contributed</th>
                                <th>Loans Applied</th>
                                <th>Missed Payments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group_breakdown as $group_data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($group_data['group_name']); ?></td>
                                <td><?php echo $group_data['payments_count'] ?? 0; ?></td>
                                <td>K <?php echo number_format($group_data['total_contributed'] ?? 0, 2); ?></td>
                                <td><?php echo $group_data['loans_count'] ?? 0; ?></td>
                                <td>
                                    <span class="<?php echo ($group_data['missed_payments'] ?? 0) > 0 ? 'status-badge status-missed' : 'status-badge status-paid'; ?>">
                                        <?php echo $group_data['missed_payments'] ?? 0; ?> missed
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($report_type === 'members' && $group_id): ?>
        <?php
        $report_data = getGroupOverview($db, $group_id);
        $member_performance = $report_data['member_performance'] ?? [];
        ?>
        
        <div class="card">
            <h3>Detailed Member Performance</h3>
            <?php if (empty($member_performance)): ?>
                <div class="no-data">No member performance data available</div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Meetings Attended</th>
                                <th>Total Paid</th>
                                <th>Average Payment</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($member_performance as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo $member['meetings_paid']; ?> / <?php echo $member['total_meetings']; ?>
                                    (<?php echo number_format($member['attendance_rate'], 1); ?>%)
                                </td>
                                <td>K <?php echo number_format($member['total_paid'], 2); ?></td>
                                <td>
                                    <?php if ($member['meetings_paid'] > 0): ?>
                                        K <?php echo number_format($member['total_paid'] / $member['meetings_paid'], 2); ?>
                                    <?php else: ?>
                                        K 0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $performance = '';
                                    $color = '';
                                    $rate = $member['attendance_rate'];
                                    if ($rate >= 90) {
                                        $performance = 'Excellent';
                                        $color = 'status-paid';
                                    } elseif ($rate >= 75) {
                                        $performance = 'Good';
                                        $color = 'status-approved';
                                    } elseif ($rate >= 50) {
                                        $performance = 'Fair';
                                        $color = 'status-pending';
                                    } else {
                                        $performance = 'Poor';
                                        $color = 'status-missed';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $color; ?>"><?php echo $performance; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($report_type === 'loans' && $group_id): ?>
        <?php
        try {
            $query = "SELECT l.*, u.full_name, u.username
                     FROM `loans` l
                     JOIN `users` u ON l.user_id = u.id
                     WHERE l.group_id = ?
                     ORDER BY l.applied_date DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([$group_id]);
            $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching loans: " . $e->getMessage());
            $loans = [];
        }
        ?>
        
        <div class="card">
            <h3>Loan Analysis</h3>
            <?php if (empty($loans)): ?>
                <div class="no-data">No loan data available</div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Amount</th>
                                <th>Interest</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($loan['full_name']); ?></strong>
                                    <div style="font-size: 0.875rem; color: #666;">@<?php echo htmlspecialchars($loan['username']); ?></div>
                                </td>
                                <td>K <?php echo number_format($loan['amount'], 2); ?></td>
                                <td><?php echo $loan['interest_rate']; ?>%</td>
                                <td>K <?php echo number_format($loan['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $loan['status']; ?>">
                                        <?php echo ucfirst($loan['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($loan['applied_date'])); ?></td>
                                <td>
                                    <?php if ($loan['due_date']): ?>
                                        <?php echo date('M j, Y', strtotime($loan['due_date'])); ?>
                                        <?php if ($loan['status'] === 'approved' && strtotime($loan['due_date']) < time()): ?>
                                            <span class="status-badge status-missed" style="margin-left: 0.5rem;">Overdue</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($report_type !== 'personal' && !$group_id): ?>
        <div class="card">
            <div class="no-data">
                <h3>Select a Group</h3>
                <p>Please select a group from the dropdown above to view reports.</p>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        
    function loadGroupReports(groupId) {
        if (groupId) {
            window.location.href = `reports.php?group_id=${groupId}&type=overview`;
        }
    }

    // Handle chart errors gracefully
    window.addEventListener('error', function(e) {
        console.error('Chart error:', e.error);
        const chartContainers = document.querySelectorAll('.chart-container');
        chartContainers.forEach(container => {
            if (!container.querySelector('.no-data')) {
                container.innerHTML = '<div class="no-data">Chart could not be loaded</div>';
            }
        });
    });
    </script>
</body>
</html>


