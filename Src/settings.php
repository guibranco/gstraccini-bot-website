<?php
$cookie_lifetime = 604800;
session_set_cookie_params([
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'domain' => 'bot.straccini.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
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

    header("Location: settings.php?updated=true");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user.css">
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">Settings</h1>
        <p class="text-center">Manage your account below.</p>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Your settings have been updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8 offset-md-2">
                <form action="settings.php" method="POST" id="settingsForm" novalidate>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><i class="fas fa-folder-open"></i> Repository Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="create_labels" class="form-label"><i class="fas fa-tags"></i> Create labels on new repository</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="create_labels"
                                        name="create_labels" <?php if ($userData['create_labels']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="create_labels">Automatically create labels on new repositories</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><i class="fas fa-exclamation-circle"></i> Issues Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="reminder_issues" class="form-label"><i class="fas fa-calendar-alt"></i> Issues Reminder</label>
                                <div class="form-check form-switch">
                                  <input class="form-check-input" type="checkbox" id="reminder_issues">
                                  <label class="form-check-label" for="reminder_issues">
                                      Remind the assigned user when the issue has been inactive (no pull request and no comments) for at least <input type="number" class="form-control d-inline-block text-center" id="daysInput" min="1" max="99" style="width: 60px;" disabled> days
                                  </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="notify_issues" class="form-label"><i class="fas fa-bell"></i> Issues Notification</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="notify_issues"
                                        name="notify_issues" <?php if ($userData['notify_issues']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="notify_issues">Notify me when new issues are created</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><i class="fas fa-code-branch"></i> Pull Requests Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="auto_merge" class="form-label"><i class="fas fa-code-merge"></i> Enable Auto-Merge</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="auto_merge" name="auto_merge"
                                        <?php if ($userData['auto_merge']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="auto_merge">Automatically merge pull requests when all checks pass</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="create_issue" class="form-label">
                                    <i class="fas fa-tasks"></i>
                                    Create issues for pending tasks in code comments
                                    <span style="background-color: #f0f0f0; border: 1px solid #555; border-radius: 5px; padding: 5px; display: inline-block;">
                                      <i class="fas fa-wrench"></i> Fixme
                                    </span>
                                    <span style="background-color: #f0f0f0; border: 1px solid #555; border-radius: 5px; padding: 5px; display: inline-block;">
                                      <i class="fas fa-tasks"></i> Todo
                                    </span>
                                    <span style="background-color: #f0f0f0; border: 1px solid #555; border-radius: 5px; padding: 5px; display: inline-block;">
                                      <i class="fas fa-bug"></i> Bug
                                    </span>
                                </label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="create_issue"
                                        name="create_issue" <?php if ($userData['create_issue']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="create_issue">Automatically create issues for specific keywords found in pull request content</label>                                    
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notify_pull_requests" class="form-label"><i class="fas fa-bell"></i> Pull Requests Notification</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="notify_pull_requests"
                                        name="notify_pull_requests" <?php if ($userData['notify_pull_requests']) {
                                            echo 'checked';
                                        } ?>>
                                    <label class="form-check-label" for="notify_pull_requests">Notify me when new pull requests are created</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require_once "includes/footer.php"; ?>  
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('settingsForm');

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                event.stopPropagation();
                
                if (form.checkValidity() === false) {
                    form.classList.add('was-validated');
                } else {
                    form.classList.remove('was-validated');
                    form.submit();
                }
            });

            const reminder_issues = document.getElementById('reminder_issues');
            const daysInput = document.getElementById('daysInput');

            reminder_issues.addEventListener('change', function() {
              daysInput.disabled = !this.checked;
              if (!this.checked) {
                daysInput.value = ''; // Clear the input when the switch is off
              }
            });
        });
    </script>
</body>

</html>
