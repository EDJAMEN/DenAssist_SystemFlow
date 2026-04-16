<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/database.php';

$queries = [
    "ALTER TABLE users ADD COLUMN professional_id VARCHAR(50) DEFAULT NULL AFTER phone",
    "ALTER TABLE users ADD COLUMN position VARCHAR(100) DEFAULT NULL AFTER professional_id"
];

$results = [];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        $results[] = "Success: " . substr($q, 0, 30) . "...";
    } else {
        $results[] = "Error/Skipped (likely exists): " . $conn->error;
    }
}

echo json_encode(["status" => "done", "results" => $results]);
$conn->close();
?>
