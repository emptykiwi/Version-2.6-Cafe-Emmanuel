<?php
// Database configuration
$host = "localhost";
$user = "u763865560_Mancave";
$password = "ManCave2025";
$database = "u763865560_EmmanuelCafeDB";

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to ensure special characters display correctly
$conn->set_charset("utf8mb4");