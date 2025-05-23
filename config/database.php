<?php
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'job_log');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if(mysqli_query($conn, $sql)){
    if(!mysqli_select_db($conn, DB_NAME)){
        die("ERROR: Could not select database. " . mysqli_error($conn));
    }
} else{
    die("ERROR: Could not create database. " . mysqli_error($conn));
}

// Set charset to ensure proper encoding
mysqli_set_charset($conn, "utf8mb4");
?> 