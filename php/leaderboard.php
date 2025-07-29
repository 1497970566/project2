<?php
// Include database connection
require_once 'database.php';

$stmt = $pdo->query("
    SELECT u.username, MIN(gs.time_seconds) AS best_time, gs.moves, gs.puzzle_size
    FROM game_stats gs
    JOIN users u ON gs.user_id = u.id
    WHERE gs.won = 1
    GROUP BY u.id, u.username, gs.puzzle_size
    ORDER BY best_time ASC
    LIMIT 10
");

$results = $stmt->fetchAll();
echo json_encode($results);
?>
