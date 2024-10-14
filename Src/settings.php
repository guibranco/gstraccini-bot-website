<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $repo_visibility = $_POST['repo_visibility'];
    $auto_merge = isset($_POST['auto_merge']) ? 1 : 0;
    $notify_issues = isset($_POST['notify_issues']) ? 1 : 0;

    $_SESSION['user_data'] = [
        'username' => $username,
        'email' => $email,
        'auto_merge' => $auto_merge,
        'notify_issues' => $notify_issues
    ];

    header("Location: dashboard.php?settings_updated=true");
    exit();
}

$title = "Activity Dashboard";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GStraccini-bot | <?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="user.css">
</head>

<body>
    <?php require_once 'includes/header.php'; ?>
    
    <div class="container mt-5">
        <h1 class="text-center">Settings</h1>
        <p class="text-center">Manage your account and settings below.</p>

        <div class="row">
            <div class="col-md-8 offset-md-2">
                <form action="settings.php" method="POST">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Account Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="username" class="form-label">GitHub Username</label>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Change Password</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <small class="form-text text-muted">Leave blank if you don't want to change your
                                    password.</small>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>GitHub Bot Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="auto_merge" class="form-label">Enable Auto-Merge</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="auto_merge" name="auto_merge"
                                        <?php if ($user['auto_merge'])
                                            echo 'checked'; ?>>
                                    <label class="form-check-label" for="auto_merge">Automatically merge pull requests
                                        when all checks pass</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notify_issues" class="form-label">Issue Notification</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notify_issues"
                                        name="notify_issues" <?php if ($user['notify_issues'])
                                            echo 'checked'; ?>>
                                    <label class="form-check-label" for="notify_issues">Notify me when new issues are
                                        created</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>

</html>