<?php
session_start();

// Include database connection
require_once 'database.php';

$username = $_POST['username'];
$password = $_POST['password'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    
    // Redirect based on role
    if ($user['role'] === 'admin') {
        header("Location: https://codd.cs.gsu.edu/~wou1/wp/pw/test/admin_dashboard_v2.php");
    } else {
        header("Location: https://codd.cs.gsu.edu/~wou1/wp/pw/test/game.html");
    }
    exit();
} else {
    echo "Invalid credentials.";
}
?>
