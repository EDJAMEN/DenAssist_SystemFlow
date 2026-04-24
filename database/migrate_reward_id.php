<?php
include_once __DIR__ . '/../api/config/database.php';

$query = "ALTER TABLE appointments ADD COLUMN reward_id INT NULL AFTER cancellation_reason";

if ($conn->query($query)) {
    echo "Successfully added reward_id column to appointments table.";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>
