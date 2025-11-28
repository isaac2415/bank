<?php
require_once 'includes/auth.php';
require_once '../config/database.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = $_POST['settings'] ?? [];
        
        try {
            $db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $query = "INSERT INTO admin_settings (setting_key, setting_value) 
                         VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
                $stmt = $db->prepare($query);
                $stmt->execute([$key, $value, $value]);
            }
            
            $db->commit();
            $_SESSION['success'] = "Settings updated successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
        }
    }
}

// Get current settings
$query = "SELECT setting_key, setting_value, description FROM admin_settings";
$stmt = $db->prepare($query);
$stmt->execute();
$settings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settings = [];
foreach ($settings_data as $setting) {
    $settings[$setting['setting_key']] = [
        'value' => $setting['setting_value'],
        'description' => $setting['description']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - BankingKhonde Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>System Settings</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <div style="display: grid; gap: 2rem;">
                    <!-- Treasurer Settings -->
                    <div>
                        <h3>Treasurer Management</h3>
                        <div style="display: grid; gap: 1rem;">
                            <div class="form-group">
                                <label for="treasurer_auto_approve">Auto-approve Treasurers:</label>
                                <select id="treasurer_auto_approve" name="settings[treasurer_auto_approve]" required>
                                    <option value="yes" <?php echo ($settings['treasurer_auto_approve']['value'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                    <option value="no" <?php echo ($settings['treasurer_auto_approve']['value'] ?? 'no') === 'no' ? 'selected' : ''; ?>>No</option>
                                </select>
                                <small><?php echo $settings['treasurer_auto_approve']['description'] ?? 'Automatically approve new treasurer registrations'; ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_groups_per_treasurer">Max Groups per Treasurer:</label>
                                <input type="number" id="max_groups_per_treasurer" name="settings[max_groups_per_treasurer]" 
                                       value="<?php echo $settings['max_groups_per_treasurer']['value'] ?? 5; ?>" min="1" max="50" required>
                                <small><?php echo $settings['max_groups_per_treasurer']['description'] ?? 'Maximum number of groups a treasurer can create'; ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- System Settings -->
                    <div>
                        <h3>System Configuration</h3>
                        <div style="display: grid; gap: 1rem;">
                            <div class="form-group">
                                <label for="system_maintenance">Maintenance Mode:</label>
                                <select id="system_maintenance" name="settings[system_maintenance]" required>
                                    <option value="yes" <?php echo ($settings['system_maintenance']['value'] ?? 'no') === 'yes' ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="no" <?php echo ($settings['system_maintenance']['value'] ?? 'no') === 'no' ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                                <small><?php echo $settings['system_maintenance']['description'] ?? 'Put system in maintenance mode'; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <button type="reset" class="btn btn-secondary">Reset to Defaults</button>
                </div>
            </form>
        </div>

        <!-- System Information -->
        <div class="card" style="margin-top: 2rem;">
            <h3>System Information</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                </div>
                <div>
                    <strong>Database:</strong> Connected
                </div>
                <div>
                    <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                </div>
                <div>
                    <strong>Admin User:</strong> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>