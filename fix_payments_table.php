<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Drop and recreate the payments table to ensure correct ENUM
    $query = "DROP TABLE IF EXISTS payments";
    $db->exec($query);
    
    $query = "CREATE TABLE payments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        meeting_id INT NOT NULL,
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('pending', 'paid', 'missed') NOT NULL DEFAULT 'pending',
        payment_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (meeting_id) REFERENCES meetings(id),
        FOREIGN KEY (group_id) REFERENCES groups(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $db->exec($query);
    echo "Payments table recreated successfully with correct ENUM values";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>