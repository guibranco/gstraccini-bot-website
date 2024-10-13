<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Get user data from session
$user = $_SESSION['user'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($user['login']); ?>!</h1>
    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="GitHub Avatar" width="100">
    <p>GitHub Username: <?php echo htmlspecialchars($user['login']); ?></p>
    <p>GitHub Profile: <a href="<?php echo htmlspecialchars($user['html_url']); ?>" target="_blank"><?php echo htmlspecialchars($user['html_url']); ?></a></p>

    <a href="logout.php">Logout</a>
</body>
</html>
