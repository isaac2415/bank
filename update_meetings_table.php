<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // First, check if the meetings table exists
    $query = "SHOW TABLES LIKE 'meetings'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        // Create meetings table if it doesn't exist, including status column
        $query = "CREATE TABLE 'meetings' (
            id INT PRIMARY KEY AUTO_INCREMENT,
            group_id INT NOT NULL,
            meeting_date DATE NOT NULL,
            status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )";
        $db->exec($query);
        echo "Created meetings table successfully\n";
    } else {
        // Ensure created_by column exists
        $query = "SHOW COLUMNS FROM meetings LIKE 'created_by'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            $query = "ALTER TABLE meetings 
                     ADD COLUMN created_by INT NOT NULL";
            $db->exec($query);
            // Add the FK separately to avoid issues if users table not yet configured with same engine
            try {
                $db->exec("ALTER TABLE meetings ADD FOREIGN KEY (created_by) REFERENCES users(id)");
            } catch (PDOException $e) {
                // ignore FK add error
            }
            echo "Added created_by column successfully\n";
        } else {
            echo "created_by column already exists\n";
        }

        // Ensure status column exists
        $query = "SHOW COLUMNS FROM meetings LIKE 'status'";
        $stmt = $db->prepare($query);
        $stmt->execute();

        if (!$stmt->fetch()) {
            $query = "ALTER TABLE meetings ADD COLUMN status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending' AFTER meeting_date";
            $db->exec($query);
            echo "Added status column to meetings table\n";
        } else {
            echo "status column already exists\n";
        }
    }

    // Check if payments table exists
    $query = "SHOW TABLES LIKE 'payments'";
    $stmt = $db->prepare($query);
    $stmt->execute();

    if (!$stmt->fetch()) {
        // Create payments table if it doesn't exist
        $query = "CREATE TABLE payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            meeting_id INT NOT NULL,
            group_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'paid', 'missed') DEFAULT 'pending',
            payment_date TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (meeting_id) REFERENCES meetings(id),
            FOREIGN KEY (group_id) REFERENCES groups(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $db->exec($query);
        echo "Created payments table successfully\n";
    } else {
        // Ensure status column has correct ENUM values
        $query = "SHOW COLUMNS FROM payments LIKE 'status'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $column = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($column) {
            // Check if ENUM includes 'pending'
            if (strpos($column['Type'], "'pending'") === false) {
                // Alter the status column to include 'pending'
                $query = "ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'paid', 'missed') DEFAULT 'pending'";
                $db->exec($query);
                echo "Updated payments status column to include 'pending'\n";
            } else {
                echo "Payments status column already correct\n";
            }
        }
    }

    echo "Database structure update completed successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>