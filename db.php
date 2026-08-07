<?php
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "online-voting"
);

if(!$conn){
    die("Database connection failed: " . mysqli_connect_error());
}
?>