<?php
// api/config/database.php
date_default_timezone_set('Asia/Manila');

$conn = new mysqli("127.0.0.1", "root", "", "dental_clinic_db");

if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}
?>
