<?php
require_once __DIR__ . '/../api/config/database.php';

// Check if column exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'deleted_at'");
if ($result->num_rows == 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL")) {
        echo "Column 'deleted_at' added to 'users' table successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'deleted_at' already exists.\n";
}

$conn->close();
?>
