<?php
require 'db.php';

$stmt = $pdo->query("
    SELECT u.username, MIN(s.time_seconds) AS best_time, s.moves
    FROM scores s
    JOIN users u ON s.user_id = u.id
    GROUP BY u.id
    ORDER BY best_time ASC
    LIMIT 10
");

$results = $stmt->fetchAll();
echo json_encode($results);
?>
