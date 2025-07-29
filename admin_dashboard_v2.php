<?php
session_start();


if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: https://codd.cs.gsu.edu/~wou1/wp/pw/test/index.html");
    exit();
}

require_once 'php/database.php';

$upload_success = "";
$upload_error = "";
$user_edit_success = "";
$user_edit_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit_user':
                $user_id = (int)$_POST['user_id'];
                $new_username = trim($_POST['new_username']);
                $new_email = trim($_POST['new_email']);
                $new_role = $_POST['new_role'];
                $new_password = trim($_POST['new_password']);
                
                if (empty($new_username) || empty($new_email)) {
                    $user_edit_error = "Username and email cannot be empty.";
                } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $user_edit_error = "Invalid email format.";
                } else {

                    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->bind_param("si", $new_username, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $user_edit_error = "Username '$new_username' already exists. Please choose a different username.";
                    } else {

                        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->bind_param("si", $new_email, $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $user_edit_error = "Email '$new_email' already exists. Please choose a different email.";
                        } else {
                            if (!empty($new_password)) {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password_hash = ? WHERE id = ?");
                                $stmt->bind_param("ssssi", $new_username, $new_email, $new_role, $hashed_password, $user_id);
                            } else {
                                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                                $stmt->bind_param("sssi", $new_username, $new_email, $new_role, $user_id);
                            }
                            
                            if ($stmt->execute()) {
                                $user_edit_success = "User details updated successfully!";
                            } else {
                                $user_edit_error = "Error updating user: " . $conn->error;
                            }
                        }
                    }
                }
                break;
                
            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                

                $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if ($user && $user['role'] === 'admin') {
                    $user_edit_error = "Cannot delete admin users.";
                } else {
                    $conn->begin_transaction();
                    
                    try {
                        $stmt = $conn->prepare("DELETE FROM game_stats WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        
                        $stmt = $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        
                        $conn->commit();
                        
                        $user_edit_success = "User and all related data deleted successfully!";
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $user_edit_error = "Error deleting user: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_background':
                $bg_id = (int)$_POST['bg_id'];
                $new_name = trim($_POST['new_name']);
                $new_status = (int)$_POST['new_status'];
                
                if (empty($new_name)) {
                    $upload_error = "Background name cannot be empty.";
                } else {
                    $stmt = $conn->prepare("UPDATE background_images SET image_name = ?, is_active = ? WHERE image_id = ?");
                    $stmt->bind_param("sii", $new_name, $new_status, $bg_id);
                    if ($stmt->execute()) {
                        $upload_success = "Background updated successfully!";
                    } else {
                        $upload_error = "Error updating background: " . $conn->error;
                    }
                }
                break;
                
            case 'toggle_background':
                $bg_id = (int)$_POST['bg_id'];
                $stmt = $conn->prepare("UPDATE background_images SET is_active = NOT is_active WHERE image_id = ?");
                $stmt->bind_param("i", $bg_id);
                if ($stmt->execute()) {
                    $upload_success = "Background status updated successfully!";
                } else {
                    $upload_error = "Error updating background status: " . $conn->error;
                }
                break;
                
            case 'delete_background':
                $bg_id = (int)$_POST['bg_id'];
                

                $stmt = $conn->prepare("SELECT image_url FROM background_images WHERE image_id = ?");
                $stmt->bind_param("i", $bg_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $image_path = $row['image_url'];
                    
                    if (file_exists($image_path)) {
                        if (unlink($image_path)) {
                            $upload_success = "Background image deleted successfully!";
                        } else {
                            $upload_error = "Error deleting file from server.";
                        }
                    } else {
                        $upload_error = "File not found on server: " . $image_path;
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM background_images WHERE image_id = ?");
                $stmt->bind_param("i", $bg_id);
                if ($stmt->execute()) {
                    if (empty($upload_error)) {
                        $upload_success = "Background image deleted successfully!";
                    }
                } else {
                    $upload_error = "Error deleting from database: " . $conn->error;
                }
                
                // Force redirect to refresh the page
                header("Location: ?tab=backgrounds");
                exit;
                break;
        }
    }
}

$activeTab = isset($_SESSION['active_tab']) ? $_SESSION['active_tab'] : 'dashboard';

if (isset($_GET['tab'])) {
    $activeTab = $_GET['tab'];
    $_SESSION['active_tab'] = $activeTab;
}


$stats = [];
$result = $conn->query("SELECT COUNT(*) as total_users FROM users");
$stats['total_users'] = $result->fetch_assoc()['total_users'];

$result = $conn->query("SELECT COUNT(*) as total_games FROM game_stats");
$stats['total_games'] = $result->fetch_assoc()['total_games'];

$result = $conn->query("SELECT COUNT(*) as wins FROM game_stats WHERE won = 1");
$stats['wins'] = $result->fetch_assoc()['wins'];

$result = $conn->query("SELECT AVG(time_seconds) as avg_time FROM game_stats WHERE won = 1");
$avg_time = $result->fetch_assoc()['avg_time'];
$stats['avg_time'] = $avg_time ? gmdate('i:s', $avg_time) : '00:00';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard v2 - Fifteen Puzzle</title>
    <link rel="stylesheet" href="admin_dashboard_v2.css">
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <h1>Fifteen Puzzle Admin <span class="admin-badge">ADMIN</span></h1>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="?tab=dashboard" class="nav-link <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?tab=users" class="nav-link <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?tab=games" class="nav-link <?php echo $activeTab === 'games' ? 'active' : ''; ?>">
                        Games
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?tab=backgrounds" class="nav-link <?php echo $activeTab === 'backgrounds' ? 'active' : ''; ?>">
                        Backgrounds
                    </a>
                </li>
            </ul>
            
            <div class="nav-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
                <a href="https://codd.cs.gsu.edu/~wou1/wp/pw/test/php/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">
            <div class="dashboard-grid">
                <div class="stat-card">
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['total_games']; ?></h3>
                    <p>Games Played</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['wins']; ?></h3>
                    <p>Games Won</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $stats['avg_time']; ?></h3>
                    <p>Average Time</p>
                </div>
            </div>

            <div class="content-card">
                <h2>Recent Activity</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                      <!--wait for adding-->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Users Section -->
        <div id="users" class="content-section <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
            <div class="content-card">
                <h2>User Management</h2>
                
                <?php if ($user_edit_success): ?>
                <div class="message success"><?php echo htmlspecialchars($user_edit_success); ?></div>
                <?php endif; ?>
                
                <?php if ($user_edit_error): ?>
                <div class="message error"><?php echo htmlspecialchars($user_edit_error); ?></div>
                <?php endif; ?>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10");
                        while ($user = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="btn <?php echo $user['role'] === 'admin' ? 'btn-danger' : 'btn-success'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="showEditUser(<?php echo $user['id']; ?>)">Edit</button>
                                <?php if ($user['role'] !== 'admin'): ?>
                                <form method="POST" style="display: inline;" action="?tab=users">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete user <?php echo htmlspecialchars($user['username']); ?>? This action cannot be undone.')">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <!-- Edit User Form -->
                        <tr id="edit-user-<?php echo $user['id']; ?>" class="edit-user-form" style="display: none;">
                            <td colspan="6">
                                <form method="POST" class="edit-user-form" action="?tab=users">
                                    <input type="hidden" name="action" value="edit_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="new_username_<?php echo $user['id']; ?>">Username:</label>
                                            <input type="text" id="new_username_<?php echo $user['id']; ?>" name="new_username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="new_email_<?php echo $user['id']; ?>">Email:</label>
                                            <input type="email" id="new_email_<?php echo $user['id']; ?>" name="new_email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="new_role_<?php echo $user['id']; ?>">Role:</label>
                                            <select id="new_role_<?php echo $user['id']; ?>" name="new_role">
                                                <option value="player" <?php echo $user['role'] === 'player' ? 'selected' : ''; ?>>Player</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="new_password_<?php echo $user['id']; ?>">New Password (leave empty to keep current):</label>
                                            <input type="password" id="new_password_<?php echo $user['id']; ?>" name="new_password">
                                        </div>
                                    </div>
                                    
                                    <div class="btn-group">
                                        <button type="submit" class="btn btn-primary">Update User</button>
                                        <button type="button" class="btn-secondary" onclick="hideEditUser(<?php echo $user['id']; ?>)">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Games Section -->
        <div id="games" class="content-section <?php echo $activeTab === 'games' ? 'active' : ''; ?>">
            <div class="content-card">
                <h2>Game Statistics</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Puzzle Size</th>
                            <th>Time</th>
                            <th>Moves</th>
                            <th>Result</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT 
                            u.username, gs.puzzle_size, gs.time_seconds, gs.moves, gs.won, gs.played_at
                            FROM game_stats gs
                            JOIN users u ON gs.user_id = u.id
                            ORDER BY gs.played_at DESC
                            LIMIT 15");
                        while ($game = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($game['username']); ?></td>
                            <td><?php echo $game['puzzle_size']; ?></td>
                            <td><?php echo gmdate('i:s', $game['time_seconds']); ?></td>
                            <td><?php echo $game['moves']; ?></td>
                            <td>
                                <span class="btn <?php echo $game['won'] ? 'btn-success' : 'btn-danger'; ?>">
                                    <?php echo $game['won'] ? 'Won' : 'Lost'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y H:i', strtotime($game['played_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Backgrounds Section -->
        <div id="backgrounds" class="content-section <?php echo $activeTab === 'backgrounds' ? 'active' : ''; ?>">
            <div class="content-card">
                <h2>Background Management</h2>

                <!-- Background Grid -->
                <div class="background-grid">
                    <?php
                    $result = $conn->query("SELECT * FROM background_images ORDER BY created_at DESC");
                    if ($result && $result->num_rows > 0):
                        while ($bg = $result->fetch_assoc()):
                    ?>
                    <div class="bg-card">
                        <div class="bg-preview">
                            <?php if (!empty($bg['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($bg['image_url']); ?>" alt="<?php echo htmlspecialchars($bg['image_name']); ?>">
                            <?php else: ?>
                                <div class="no-image">No Image</div>
                            <?php endif; ?>
                            <div class="bg-id">
                                ID: <?php echo $bg['image_id']; ?>
                            </div>
                        </div>
                        <h4 class="bg-name"><?php echo htmlspecialchars($bg['image_name']); ?></h4>
                        <p class="bg-url"><?php echo htmlspecialchars($bg['image_url']); ?></p>
                        <div class="bg-actions">
                            <button class="btn btn-primary" onclick="showEditBackground(<?php echo $bg['image_id']; ?>)">Edit</button>
                            <form method="POST" style="display: inline;" action="?tab=backgrounds">
                                <input type="hidden" name="action" value="toggle_background">
                                <input type="hidden" name="bg_id" value="<?php echo $bg['image_id']; ?>">
                                <button type="submit" class="btn <?php echo $bg['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                    <?php echo $bg['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" action="?tab=backgrounds">
                                <input type="hidden" name="action" value="delete_background">
                                <input type="hidden" name="bg_id" value="<?php echo $bg['image_id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this background?')">Delete</button>
                            </form>
                        </div>
                        
                        <!-- Edit Background Form (Hidden by default) -->
                        <div id="edit-bg-<?php echo $bg['image_id']; ?>" class="edit-bg-form" style="display: none;">
                            <form method="POST" action="?tab=backgrounds">
                                <input type="hidden" name="action" value="edit_background">
                                <input type="hidden" name="bg_id" value="<?php echo $bg['image_id']; ?>">
                                <div class="form-group">
                                    <label for="new_name_<?php echo $bg['image_id']; ?>">Background Name:</label>
                                    <input type="text" id="new_name_<?php echo $bg['image_id']; ?>" name="new_name" value="<?php echo htmlspecialchars($bg['image_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_status_<?php echo $bg['image_id']; ?>">Status:</label>
                                    <select id="new_status_<?php echo $bg['image_id']; ?>" name="new_status">
                                        <option value="1" <?php echo $bg['is_active'] ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?php echo !$bg['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-success">Save Changes</button>
                                    <button type="button" class="btn btn-secondary" onclick="hideEditBackground(<?php echo $bg['image_id']; ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">Image</div>
                        <h3>No Backgrounds Found</h3>
                        <p>Upload your first background image to get started!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            document.getElementById(sectionId).classList.add('active');
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
        }



        function showEditUser(userId) {
            const allForms = document.querySelectorAll('.edit-user-form');
            
            allForms.forEach(form => {
                form.style.display = 'none';
            });
            
            const editForm = document.getElementById('edit-user-' + userId);
            if (editForm) {
                editForm.style.display = 'table-row';
                const formElement = editForm.querySelector('form');
                if (formElement) {
                    formElement.style.display = 'block';
                }
            }
        }

        function hideEditUser(userId) {
            const editForm = document.getElementById('edit-user-' + userId);
            if (editForm) {
                editForm.style.display = 'none';
                const formElement = editForm.querySelector('form');
                if (formElement) {
                    formElement.style.display = 'none';
                }
            }
        }

        function showEditBackground(bgId) {
            const editForm = document.getElementById('edit-bg-' + bgId);
            if (editForm) {
                editForm.style.display = 'block';
            }
        }

        function hideEditBackground(bgId) {
            const editForm = document.getElementById('edit-bg-' + bgId);
            if (editForm) {
                editForm.style.display = 'none';
            }
        }

    </script>
</body>
</html> 