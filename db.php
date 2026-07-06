<?php
$host = "localhost";
$user = "GW06";
$password = "DEKAN";
$database = "gw06";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");


?>