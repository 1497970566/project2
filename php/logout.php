<?php
session_start();

// Destroy all session data
session_destroy();
 
// Redirect to login page
header("Location: https://codd.cs.gsu.edu/~wou1/wp/pw/02/index.html");
exit();
?> 