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
if (isset($user['first_name']) === false) {
    $user['first_name'] = '';
}
if (isset($user['last_name']) === false) {
    $user['last_name'] = '';
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

    header("Location: acount.php?updated=true");
    exit();
}

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
    <link rel="stylesheet" href="user.css">
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
                                <small class="form-text text-muted">Leave blank if you don't want to change your password.</small>
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
                                <small class="form-text text-muted">Leave blank if you don't want to change your password.</small>
                            </div>

                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Account Details</button>
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
