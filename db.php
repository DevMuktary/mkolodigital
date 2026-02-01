<?php
// Database credentials - Replace with your cPanel MySQL details
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'mkolodig_good');
define('DB_PASSWORD', 'mkolodig_good');
define('DB_NAME', 'mkolodig_good');

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>