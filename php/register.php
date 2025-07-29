<?php
session_start();

// Include database connection
require_once 'database.php';

$username = $_POST['username'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role = 'player'; 

$stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
$stmt->execute([$username, $email, $password, $role]);
header("Location: ../index.html");
?>
