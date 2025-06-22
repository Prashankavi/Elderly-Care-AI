<?php
$host = 'localhost';
$db = 'elderly_care'; // Your actual database name
$user = 'root'; // Default XAMPP user
$pass = '';     // Empty password by default

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
