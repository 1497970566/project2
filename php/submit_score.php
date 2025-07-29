<?php
session_start();

// Include database connection
require_once 'database.php';

if (!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];
$moves = $_POST['moves'];
$time = $_POST['time'];
$puzzle_size = isset($_POST['puzzle_size']) ? $_POST['puzzle_size'] : '4x4';
$won = isset($_POST['won']) ? $_POST['won'] : 1;

$stmt = $pdo->prepare("INSERT INTO game_stats (user_id, puzzle_size, time_seconds, moves, won) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $puzzle_size, $time, $moves, $won]);
?>
