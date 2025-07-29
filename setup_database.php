<?php
$host = "localhost";
$user = "wou1";
$pass = "wou1";
$dbname = "wou1";
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Simple Database Setup</h1>";

//Users Table
echo "<h2>Creating Users Table...</h2>";
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'player') DEFAULT 'player',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Check if password_hash column exists, if not add it
echo "<h3>Checking for password_hash column...</h3>";
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
if ($result->num_rows == 0) {
    echo "Adding password_hash column to existing users table...<br>";
    $alter_sql = "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL DEFAULT ''";
    if ($conn->query($alter_sql) === TRUE) {
        echo "password_hash column added successfully<br>";
    } else {
        echo "Error adding password_hash column: " . $conn->error . "<br>";
    }
} else {
    echo "password_hash column already exists<br>";
}

//Game Stats Table
echo "<h2>Creating Game Stats Table...</h2>";
$sql = "CREATE TABLE IF NOT EXISTS game_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    puzzle_size VARCHAR(10) NOT NULL,
    time_seconds INT NOT NULL,
    moves INT NOT NULL,
    won BOOLEAN DEFAULT FALSE,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Game Stats table created<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

//User Preferences Table
echo "<h2>Creating User Preferences Table...</h2>";
$sql = "CREATE TABLE IF NOT EXISTS user_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    default_puzzle_size VARCHAR(10) DEFAULT '4x4',
    preferred_background_image_id INT,
    sound_enabled BOOLEAN DEFAULT TRUE,
    animations_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (preferred_background_image_id) REFERENCES background_images(image_id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "User Preferences table created<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

//Background Images Table
echo "<h2>Creating Background Images Table...</h2>";
$sql = "CREATE TABLE IF NOT EXISTS background_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    image_name VARCHAR(100) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    uploaded_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo " Background Images table created<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Insert Sample Data
echo "<h2>Inserting Sample Data...</h2>";

// Sample Users
$users = [
    ['admin', 'admin@game.com', 'admin', 'admin123'],
    ['player1', 'player1@game.com', 'player', 'player123'],
    ['player2', 'player2@game.com', 'player', 'player123'],
    ['player3', 'player3@game.com', 'player', 'player123']
];

foreach ($users as $user) {
    $hashedPassword = password_hash($user[3], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO users (username, email, role, password_hash) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user[0], $user[1], $user[2], $hashedPassword);
    if ($stmt->execute()) {
        echo " User '{$user[0]}' added (password: {$user[3]})<br>";
    }
}

// Update existing users without password_hash
echo "<h3>Updating existing users without passwords...</h3>";
$result = $conn->query("SELECT id, username, role FROM users WHERE password_hash = '' OR password_hash IS NULL");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $defaultPassword = ($row['role'] === 'admin') ? 'admin123' : 'player123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $row['id']);
        if ($stmt->execute()) {
            echo "Updated user '{$row['username']}' with default password: $defaultPassword<br>";
        }
    }
} else {
    echo "All users already have password hashes<br>";
}

// Sample Game Stats
$game_stats = [
    [2, '4x4', 180, 45, true],
    [2, '4x4', 220, 52, true],
    [2, '5x5', 450, 78, false],
    [3, '4x4', 195, 48, true],
    [3, '4x4', 280, 65, true],
    [4, '4x4', 320, 75, false],
    [4, '4x4', 210, 50, true]
];

foreach ($game_stats as $stat) {
    $stmt = $conn->prepare("INSERT IGNORE INTO game_stats (user_id, puzzle_size, time_seconds, moves, won) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiii", $stat[0], $stat[1], $stat[2], $stat[3], $stat[4]);
    if ($stmt->execute()) {
        echo "Game stat added for user {$stat[0]}<br>";
    }
}

//Background Images
$background_images = [
    ['Egg Theme', 'image/egg.jpg', 1],
    ['Cinnamon Roll Theme', 'image/cinnamoroll.jpg', 1],
    ['Twin Theme', 'image/twin.jpg', 1],
    ['Pudding dog Theme', 'image/dog.jpg', 1],
    ['Hello Kitty', 'image/hellokitty.jpg', 1]
];

foreach ($background_images as $bg) {
    $stmt = $conn->prepare("INSERT IGNORE INTO background_images (image_name, image_url, uploaded_by_user_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $bg[0], $bg[1], $bg[2]);
    if ($stmt->execute()) {
        echo "Background image '{$bg[0]}' added<br>";
    }
}

// Sample User Preferences
$user_preferences = [
    [1, '4x4', 1, true, true],   // admin
    [2, '4x4', 2, true, true],   // player1
    [3, '5x5', 3, false, true],  // player2
    [4, '4x4', 1, true, false]   // player3
];

foreach ($user_preferences as $pref) {
    $stmt = $conn->prepare("INSERT IGNORE INTO user_preferences (user_id, default_puzzle_size, preferred_background_image_id, sound_enabled, animations_enabled) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiii", $pref[0], $pref[1], $pref[2], $pref[3], $pref[4]);
    if ($stmt->execute()) {
        echo "User preferences added for user {$pref[0]}<br>";
    }
}

// Show Summary
echo "<h2>Database Summary</h2>";
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$user_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM game_stats");
$game_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM background_images");
$bg_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM user_preferences");
$pref_count = $result->fetch_assoc()['count'];

echo " Users: $user_count<br>";
echo "Games: $game_count<br>";
echo " Background Images: $bg_count<br>";
echo "User Preferences: $pref_count<br>";

$conn->close();
echo "<br><h2>Database setup complete!</h2>";

echo "<h3>Login Credentials:</h3>";
echo "<p><strong>Admin Login:</strong></p>";
echo "<ul>";
echo "<li>Username: admin</li>";
echo "<li>Password: admin123</li>";
echo "</ul>";

echo "<p><strong>Player Logins:</strong></p>";
echo "<ul>";
echo "<li>Username: player1, Password: player123</li>";
echo "<li>Username: player2, Password: player123</li>";
echo "<li>Username: player3, Password: player123</li>";
echo "</ul>";

echo "<p><a href='index.html'>Go to Login Page</a></p>";
echo "<p><a href='admin_dashboard.php'>Go to Admin Dashboard</a></p>";
?> 