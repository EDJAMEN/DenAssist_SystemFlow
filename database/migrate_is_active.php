<?php
require_once __DIR__ . '/../api/config/database.php';

// Check if column exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($result->num_rows == 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE")) {
        echo "Column 'is_active' added to 'users' table successfully.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "Column 'is_active' already exists.\n";
}

$conn->close();
?>
