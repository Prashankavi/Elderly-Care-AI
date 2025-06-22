<?php
// db.php - Centralized database connection file

$servername = "localhost";  // The database server (usually localhost)
$username = "root";         // The default MySQL username for XAMPP
$password = "";             // The default MySQL password for XAMPP (blank)
$dbname = "elderly_care";   // The name of your database

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
