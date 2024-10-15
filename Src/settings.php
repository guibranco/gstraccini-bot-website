<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$userData = [
    'create_labels' => 1,
    'notify_issues' => 1,
    'auto_merge' => 1,
    'create_issue' => 1,
    'notify_pull_requests' => 1
];

if (isset($_SESSION['user_data'])) {
    $userData = $_SESSION['user_data'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $firstName = htmlspecialchars($_POST['firstName']);
    $lastName = htmlspecialchars($_POST['lastName']);
    $password = $_POST['password'];
    $passwordConfirm = $_POST['passwordConfirm'];

    if ($password !== '' && $password !== $passwordConfirm) {
        header("Location: settings.php?password_mismatch=true");
        exit();
    }

    $create_labels = isset($_POST['create_labels']) ? 1 : 0;
    $notify_issues = isset($_POST['notify_issues']) ? 1 : 0;
    $auto_merge = isset($_POST['auto_merge']) ? 1 : 0;
    $create_issue = isset($_POST['create_issue']) ? 1 : 0;
    $notify_pull_requests = isset($_POST['notify_pull_requests']) ? 1 : 0;

    $_SESSION['user_data'] = [
        'create_labels' => $create_labels,
        'notify_issues' => $notify_issues,
        'auto_merge' => $auto_merge,
        'create_issue' => $create_issue,
        'notify_pull_requests' => $notify_pull_requests
    ];

    $_SESSION['user']['first_name'] = $firstName;
    $_SESSION['user']['last_name'] = $lastName;


    header("Location: settings.php?settings_updated=true");
    exit();
}

$title = "Settings";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GStraccini-bot | <?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css">
    <style>
        .input-group-text {
            width: 40px;
            justify-content: center;
        }
    </style>
    <link rel="stylesheet" href="user.css">
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">Settings</h1>
        <p class="text-center">Manage your account and settings below.</p>

        <?php if (isset($_GET['settings_updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Your settings have been updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['password_mismatch'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Passwords do not match. Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8 offset-md-2">
                <form action="settings.php" method="POST" id="settingsForm" novalidate>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Account Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="githubUsername" class="form-label">GitHub Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-github"></i>
                                    </span>
                                    <input type="text" class="form-control" id="githubUsername"
                                        value="<?php echo htmlspecialchars($user['login']); ?>" disabled>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control" id="firstName" name="firstName"
                                        value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    <div class="invalid-feedback">First name is required.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control" id="lastName" name="lastName"
                                        value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    <div class="invalid-feedback">Last name is required.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <div class="invalid-feedback">Please provide a valid email address.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password"
                                        minlength="6">
                                    <br />
                                    <small class="form-text text-muted">Leave blank if you don't want to change your
                                        password.</small>
                                    <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="passwordConfirm" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="passwordConfirm"
                                        name="passwordConfirm">
                                    <br />
                                    <small class="form-text text-muted">Leave blank if you don't want to change your
                                        password.</small>
                                    <div class="invalid-feedback">Passwords must match.</div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Repository Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="create_labels" class="form-label">Create labels on new repository</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="create_labels"
                                        name="create_labels" <?php if ($userData['create_labels']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="create_labels">Automatically create labels on
                                        new repositories</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notify_issues" class="form-label">Issue Notification</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notify_issues"
                                        name="notify_issues" <?php if ($userData['notify_issues']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="notify_issues">Notify me when new issues are
                                        created</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Pull Requests Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="auto_merge" class="form-label">Enable Auto-Merge</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="auto_merge" name="auto_merge"
                                        <?php if ($userData['auto_merge']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="auto_merge">Automatically merge pull requests
                                        when all checks pass</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="create_issue" class="form-label">Create issues for: todo, fixme, bug
                                    comments</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="create_issue"
                                        name="create_issue" <?php if ($userData['create_issue']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="create_issue">Automatically create issues for
                                        specific keywords found in pull request content</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notify_pull_requests" class="form-label">Pull Requests Notification</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notify_pull_requests"
                                        name="notify_pull_requests" <?php if ($userData['notify_pull_requests']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="notify_pull_requests">Notify me when new pull
                                        requests are created</label>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('settingsForm');

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const password = document.getElementById('password').value;
                const passwordConfirm = document.getElementById('passwordConfirm').value;

                if (password !== passwordConfirm) {
                    document.getElementById('passwordConfirm').setCustomValidity("Passwords don't match");
                } else {
                    document.getElementById('passwordConfirm').setCustomValidity("");
                }
                if (form.checkValidity() === false) {
                    form.classList.add('was-validated');
                } else {
                    form.classList.remove('was-validated');
                    form.submit();
                }
            });
        });
    </script>
</body>

</html>