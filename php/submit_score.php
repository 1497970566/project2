<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];
$moves = $_POST['moves'];
$time = $_POST['time'];

$stmt = $pdo->prepare("INSERT INTO scores (user_id, moves, time_seconds) VALUES (?, ?, ?)");
$stmt->execute([$user_id, $moves, $time]);
?>
