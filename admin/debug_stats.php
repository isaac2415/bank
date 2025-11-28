<?php
session_start();
require_once '../config/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$database = new Database();
$db = $database->getConnection();

echo "<h1>Database Debug Information</h1>";

try {
    // Test database connection
    echo "<h2>1. Database Connection Test</h2>";
    $test_query = "SELECT 1 as test_result";
    $test_stmt = $db->prepare($test_query);
    $test_stmt->execute();
    $test_result = $test_stmt->fetch(PDO::FETCH_ASSOC);
    echo "Database connection: <span style='color: green;'>✓ SUCCESS</span><br>";
    echo "Test query result: " . $test_result['test_result'] . "<br><br>";

    // Check table structures
    echo "<h2>2. Table Structure Check</h2>";
    $tables = ['users', 'groups', 'loans', 'payments'];
    
    foreach ($tables as $table) {
        echo "<h3>Table: $table</h3>";
        try {
            $stmt = $db->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "<br>";
        }
    }

    // Check actual data counts
    echo "<h2>3. Actual Data Counts</h2>";
    
    $queries = [
        'Total Users (non-admin)' => "SELECT COUNT(*) as count FROM users WHERE role != 'admin'",
        'Total Treasurers' => "SELECT COUNT(*) as count FROM users WHERE role = 'treasurer'",
        'Pending Treasurer Verifications' => "SELECT COUNT(*) as count FROM users WHERE role = 'treasurer' AND verified = 'no'",
        'Total Groups' => "SELECT COUNT(*) as count FROM groups",
        'Total Loans' => "SELECT COUNT(*) as count FROM loans",
        'Total Payments' => "SELECT COUNT(*) as count FROM payments"
    ];

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Query Description</th><th>SQL Query</th><th>Result</th></tr>";
    
    foreach ($queries as $description => $query) {
        try {
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<tr>";
            echo "<td>$description</td>";
            echo "<td><code>$query</code></td>";
            echo "<td>" . ($result['count'] ?? 'NULL') . "</td>";
            echo "</tr>";
        } catch (Exception $e) {
            echo "<tr>";
            echo "<td>$description</td>";
            echo "<td><code>$query</code></td>";
            echo "<td style='color: red;'>Error: " . $e->getMessage() . "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

    // Check sample data
    echo "<h2>4. Sample Data from Each Table</h2>";
    
    $sample_queries = [
        'users' => "SELECT id, username, role, verified, created_at FROM users LIMIT 5",
        'groups' => "SELECT id, name, code, treasurer_id, status FROM groups LIMIT 5",
        'loans' => "SELECT id, amount, status, applied_date FROM loans LIMIT 5",
        'payments' => "SELECT id, amount, status, payment_date FROM payments LIMIT 5"
    ];

    foreach ($sample_queries as $table => $query) {
        echo "<h3>Sample from $table</h3>";
        try {
            $stmt = $db->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($results)) {
                echo "No data found<br>";
            } else {
                echo "<table border='1' cellpadding='5'>";
                // Header
                echo "<tr>";
                foreach (array_keys($results[0]) as $column) {
                    echo "<th>$column</th>";
                }
                echo "</tr>";
                // Data
                foreach ($results as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "<br>";
        }
    }

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Critical Error</h2>";
    echo "Error: " . $e->getMessage();
}
?>