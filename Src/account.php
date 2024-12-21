<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];
if (isset($user['first_name']) === false) {
    $user['first_name'] = '';
}
if (isset($user['last_name']) === false) {
    $user['last_name'] = '';
}
if (isset($user['email']) === false) {
    $user['email'] = '';
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

    $_SESSION['user']['first_name'] = $firstName;
    $_SESSION['user']['last_name'] = $lastName;

    header("Location: account.php?updated=true");
    exit();
}

$installations = $_SESSION['installations']['installations'] ?? [];
$organizations = $_SESSION['organizations'] ?? [];

$installationMap = [];
foreach ($installations as $installation) {
    $installationMap[$installation['account']['id']] = $installation;
}

$entities = [];
foreach ($organizations as $organization) {
    $organizationId = $organization['id'];
    $hasInstallation = isset($installationMap[$organizationId]);

    $entities[$organizationId] = [
        'id' => $organizationId,
        'name' => $organization['login'],
        'image' => $organization['avatar_url'],
        'html_url' => 'https://github.com/'.$organization['login'],
        'installation' => $hasInstallation ? $installationMap[$organizationId] : null,
    ];
}

foreach ($installations as $installation) {
    if (!isset($entities[$installation['account']['id']])) {
        $entities[$installation['account']['id']] = [
            'id' => $installation['id'],
            'name' => $installation['account']['login'],
            'image' => $installation['account']['avatar_url'],
            'html_url' => $installation['account']['html_url'],
            'installation' => $installation,
        ];
    }
}

ksort($entities);

$title = "Account Details";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GStraccini-bot | <?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/static/user.css">
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5">
        <h1 class="text-center">Account</h1>
        <p class="text-center">Manage your account details below.</p>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Your account details have been updated successfully.
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
                <form action="account.php" method="POST" id="settingsForm" novalidate>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>Account Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="githubUserId" class="form-label">GitHub User ID</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fab fa-github"></i>
                                    </span>
                                    <input type="text" class="form-control" id="githubUserId"
                                        value="<?php echo htmlspecialchars($user['id']); ?>" disabled>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="githubUsername" class="form-label">GitHub Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fab fa-github"></i>
                                    </span>
                                    <input type="text" class="form-control" id="githubUsername"
                                        value="<?php echo htmlspecialchars($user['login']); ?>" disabled>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
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
                                        <i class="fas fa-user"></i>
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
                                        <i class="fas fa-envelope"></i>
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
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password"
                                        minlength="6">
                                    <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                                </div>
                                <small class="form-text text-muted">Leave blank if you don't want to change your
                                    password.</small>
                            </div>

                            <div class="mb-3">
                                <label for="passwordConfirm" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="passwordConfirm"
                                        name="passwordConfirm">
                                    <div class="invalid-feedback">Passwords must match.</div>
                                </div>
                                <small class="form-text text-muted">Leave blank if you don't want to change your
                                    password.</small>
                            </div>

                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Account
                            Details</button>
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h3>Entities <span
                                class="badge text-bg-warning rounded-pill"><?php echo count($entities); ?></span></h3>
                    </div>
                    <div class="card-body entities">
                        <table>
                            <thead>
                                <tr>
                                    <th>&nbsp;</th>
                                    <th>Login</th>
                                    <th>Installation Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entities as $entity): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($entity['html_url']); ?>" target="_blank">
                                                <img src="<?php echo htmlspecialchars($entity['image']); ?>"
                                                    alt="Entity Avatar">
                                            </a>
                                        </td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($entity['html_url']); ?>" target="_blank">
                                                <strong><?php echo htmlspecialchars($entity['name']); ?></strong>
                                            </a>
                                        </td>
                                        <td>
                                            <?php
                                            if ($entity['installation']) {
                                                echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($entity['installation']['created_at'])));
                                            } else {
                                                echo "<span style='text-align:center;font-weight:bold;'>-</span>";
                                            }
                                            ?>

                                        </td>
                                        <td>
                                            <?php
                                            if ($entity['installation'] && $entity['installation']['suspended_at']) {
                                                echo '<span class="status-suspended">Suspended</span>';
                                            } else if ($entity['installation']) {
                                                echo '<span class="status-installed">Installed</span>';
                                            } else {
                                                echo '<span class="status-uninstalled">Not Installed</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!$entity['installation']): ?>
                                                <a class="btn btn-success btn-sm" target="_blank" rel="noopener noreferrer"
                                                    href="https://github.com/apps/gstraccini/installations/new/permissions?target_id=<?= $entity['id'] ?>">Install</a>
                                            <?php else: ?>
                                                <a class="btn btn-primary btn-sm"
                                                    href="repositories.php?organization=<?= htmlspecialchars(urlencode($entity['installation']['account']['login'])) ?>">
                                                    View Repositories
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="add-installation-note mt-3">
                            <p>
                                <strong>Didn't find the organization or entity you're looking for?</strong><br>
                                No worries! If the desired organization or entity is missing from the list, you can
                                manually add it to the installations by clicking the button below:
                            </p>
                            <div class="add-installation-container mt-2">
                                <a class="add-installation-button btn btn-success" target="_blank"
                                    rel="noopener noreferrer"
                                    href="https://github.com/apps/gstraccini/installations/select_target">Add New
                                    Installation</a>
                            </div>
                        </div>
                    </div>
                </div>
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
