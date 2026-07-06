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
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['passwordConfirm'] ?? '';

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

if (!isset($entities[$user["id"]])) {
    $entities[$user["id"]] = [
        'id' => $user['id'],
        'name' => $user['login'],
        'image' => $user['avatar_url'],
        'html_url' => $user['html_url'],
        'installation' => null,
    ];
}

ksort($entities);

$displayName = trim($user['first_name'] . ' ' . $user['last_name']);
if ($displayName === '') {
    $displayName = $user['login'];
}

$title = "Account Details";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GStraccini Bot | <?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/static/user.css">
    <style>
        .profile-avatar {
            width: 88px;
            height: 88px;
            object-fit: cover;
        }

        .account-nav .list-group-item {
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 3px solid transparent;
        }

        .account-nav .list-group-item.active {
            background-color: #eef4ff;
            color: #0d6efd;
            border-left-color: #0d6efd;
            font-weight: 600;
        }

        .security-item:not(:last-child) {
            border-bottom: 1px solid rgba(0, 0, 0, 0.075);
        }

        .digit-inputs {
            display: flex;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .digit-inputs input {
            width: 40px;
            text-align: center;
        }

        .qr-placeholder {
            width: 120px;
            height: 120px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f3f5;
            border-radius: 8px;
            font-size: 2.5rem;
            color: #adb5bd;
        }

        .recovery-codes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            font-family: 'Courier New', Courier, monospace;
            text-align: center;
        }

        .recovery-codes-grid span {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 6px;
        }
    </style>
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="d-flex flex-column flex-sm-row align-items-center gap-3 mb-4">
            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>"
                alt="<?php echo htmlspecialchars($user['login']); ?> avatar" class="rounded-circle profile-avatar">
            <div class="text-center text-sm-start">
                <h1 class="h3 mb-1"><?php echo htmlspecialchars($displayName); ?></h1>
                <p class="text-muted mb-0">
                    <i class="fab fa-github"></i>
                    <a href="<?php echo htmlspecialchars($user['html_url']); ?>" target="_blank" rel="noopener noreferrer">
                        @<?php echo htmlspecialchars($user['login']); ?>
                    </a>
                </p>
            </div>
        </div>

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
            <div class="col-md-3 mb-4">
                <div class="list-group account-nav" id="accountTabs" role="tablist">
                    <a class="list-group-item list-group-item-action active" data-bs-toggle="list" href="#tab-profile"
                        role="tab"><i class="fas fa-user"></i> Profile</a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#tab-security"
                        role="tab"><i class="fas fa-shield-alt"></i> Security</a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#tab-preferences"
                        role="tab"><i class="fas fa-sliders-h"></i> Preferences</a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#tab-installations"
                        role="tab"><i class="fas fa-plug"></i> Installations
                        <span class="badge text-bg-warning rounded-pill"><?php echo count($entities); ?></span></a>
                </div>
            </div>

            <div class="col-md-9">
                <form action="account.php" method="POST" id="settingsForm" novalidate>
                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="tab-profile" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0"><i class="fas fa-user"></i> Profile</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="githubUserId" class="form-label">GitHub User ID</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-github"></i></span>
                                            <input type="text" class="form-control" id="githubUserId"
                                                value="<?php echo htmlspecialchars($user['id']); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="githubUsername" class="form-label">GitHub Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-github"></i></span>
                                            <input type="text" class="form-control" id="githubUsername"
                                                value="<?php echo htmlspecialchars($user['login']); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="firstName" class="form-label">First Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="firstName" name="firstName"
                                                value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                            <div class="invalid-feedback">First name is required.</div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="lastName" class="form-label">Last Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="lastName" name="lastName"
                                                value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                            <div class="invalid-feedback">Last name is required.</div>
                                        </div>
                                    </div>

                                    <div class="mb-0">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            <div class="invalid-feedback">Please provide a valid email address.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-security" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0"><i class="fas fa-shield-alt"></i> Security</h3>
                                </div>
                                <div class="card-body p-0">

                                    <div class="security-item p-3">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                            <div class="d-flex align-items-start gap-3">
                                                <i class="fas fa-lock fa-lg text-primary mt-1"></i>
                                                <div>
                                                    <h5 class="mb-1">Password</h5>
                                                    <p class="mb-0 text-muted">Change the password used to sign in
                                                        with your email.</p>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="collapse" data-bs-target="#passwordFields">
                                                Change password
                                            </button>
                                        </div>
                                        <div class="collapse mt-3" id="passwordFields">
                                            <div class="mb-3">
                                                <label for="password" class="form-label">New Password</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <input type="password" class="form-control" id="password"
                                                        name="password" minlength="6">
                                                    <div class="invalid-feedback">Password must be at least 6
                                                        characters long.</div>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label for="passwordConfirm" class="form-label">Confirm New
                                                    Password</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <input type="password" class="form-control" id="passwordConfirm"
                                                        name="passwordConfirm">
                                                    <div class="invalid-feedback">Passwords must match.</div>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Your new password is applied when you
                                                save changes below.</small>
                                        </div>
                                    </div>

                                    <div class="security-item p-3">
                                        <div
                                            class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                            <div class="d-flex align-items-start gap-3">
                                                <i class="fas fa-mobile-alt fa-lg text-primary mt-1"></i>
                                                <div>
                                                    <h5 class="mb-1">Two-Factor Authentication</h5>
                                                    <p class="mb-0 text-muted">Require a 6-digit code from an
                                                        authenticator app when signing in.</p>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span id="twoFaStatus"
                                                    class="badge text-bg-secondary d-block mb-2">Disabled</span>
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                    id="twoFaActionBtn" data-bs-toggle="modal"
                                                    data-bs-target="#twoFaModal">Enable</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="security-item p-3">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                            <div class="d-flex align-items-start gap-3">
                                                <i class="fas fa-fingerprint fa-lg text-primary mt-1"></i>
                                                <div>
                                                    <h5 class="mb-1">Security Keys (FIDO)</h5>
                                                    <p class="mb-1 text-muted">Sign in with a hardware key,
                                                        fingerprint, or face recognition.</p>
                                                    <ul class="list-unstyled mb-0" id="fidoKeyList"></ul>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                id="addFidoKeyBtn"><i class="fas fa-plus"></i> Add a security
                                                key</button>
                                        </div>
                                    </div>

                                    <div class="security-item p-3">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                            <div class="d-flex align-items-start gap-3">
                                                <i class="fas fa-life-ring fa-lg text-primary mt-1"></i>
                                                <div>
                                                    <h5 class="mb-1">Recovery Codes</h5>
                                                    <p class="mb-0 text-muted" id="recoveryStatus">Generate one-time
                                                        codes to use if you lose access to your other sign-in
                                                        methods.</p>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                id="viewRecoveryBtn" data-bs-toggle="modal"
                                                data-bs-target="#recoveryModal">View codes</button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-preferences" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0"><i class="fas fa-sliders-h"></i> Preferences</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="theme" class="form-label">Select Theme:</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-adjust"></i></span>
                                            <select id="theme" name="theme" onchange="changeTheme()">
                                                <option value="light">☀️ Light</option>
                                                <option value="dark">🌙 Dark</option>
                                                <option value="system">🖥️ Use System Theme</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label d-block">Repository &amp; List Grouping</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="groupByOrgToggle">
                                            <label class="form-check-label" for="groupByOrgToggle">
                                                Group repositories, issues, and pull requests by
                                                organization/account
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">When disabled, repositories, issues,
                                            and pull requests are listed together instead of grouped by
                                            organization/account.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-installations" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">Installations <span
                                            class="badge text-bg-warning rounded-pill"><?php echo count($entities); ?></span>
                                    </h3>
                                </div>
                                <div class="card-body installations">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>&nbsp;</th>
                                                <th>Login</th>
                                                <th>Status</th>
                                                <th>Installation Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($entities as $entity): ?>
                                                <tr>
                                                    <td>
                                                        <a href="<?php echo htmlspecialchars($entity['html_url']); ?>"
                                                            target="_blank">
                                                            <img src="<?php echo htmlspecialchars($entity['image']); ?>"
                                                                alt="Entity Avatar">
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo htmlspecialchars($entity['html_url']); ?>"
                                                            target="_blank">
                                                            <strong><?php echo htmlspecialchars($entity['name']); ?></strong>
                                                        </a>
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
                                                        <?php
                                                        if ($entity['installation']) {
                                                            echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($entity['installation']['created_at'])));
                                                        } else {
                                                            echo "<span style='font-weight:bold;text-align:center;display:block;color:red;'><i class='fas fa-times'></i></span>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!$entity['installation']): ?>
                                                            <a class="btn btn-success btn-sm" target="_blank"
                                                                rel="noopener noreferrer"
                                                                href="https://github.com/apps/gstraccini/installations/new/permissions?target_id=<?= $entity['id'] ?>">
                                                                <i class="fas fa-plus"></i> Install
                                                            </a>
                                                        <?php else: ?>
                                                            <a class="btn btn-primary btn-sm"
                                                                href="repositories.php?organization=<?= htmlspecialchars(urlencode($entity['installation']['account']['login'])) ?>">
                                                                <i class="fas fa-list"></i> View Repositories
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="add-installation-note mt-3">
                                        <p>
                                            <strong>Didn't find the organization you're looking for?</strong><br>
                                            No worries! If the desired organization is missing from the list, you can
                                            manually add it to the installations by clicking the button below:
                                        </p>
                                        <div class="add-installation-container mt-2">
                                            <a class="add-installation-button btn btn-success" target="_blank"
                                                rel="noopener noreferrer"
                                                href="https://github.com/apps/gstraccini/installations/select_target"><i
                                                    class="fas fa-plus"></i> Add New Installation</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="twoFaModal" tabindex="-1" aria-labelledby="twoFaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="twoFaModalLabel">Set Up Two-Factor Authentication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Scan the code below with your authenticator app, or enter the setup key manually:</p>
                    <div class="text-center mb-3">
                        <div class="qr-placeholder mb-2"><i class="fas fa-qrcode"></i></div>
                        <code id="twoFaSecret"></code>
                    </div>
                    <p>Then enter the 6-digit code it generates:</p>
                    <div class="digit-inputs mb-2" id="twoFaSetupInputs"></div>
                    <div id="twoFaSetupError" class="text-danger mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmTwoFaBtn">Verify &amp; Enable</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="recoveryModal" tabindex="-1" aria-labelledby="recoveryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="recoveryModalLabel">Recovery Codes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Store these codes somewhere safe. Each code can only be used once to sign in if you lose
                        access to your other sign-in methods.</p>
                    <div class="recovery-codes-grid" id="recoveryCodesGrid"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="regenerateRecoveryBtn">
                        <i class="fas fa-sync"></i> Regenerate codes
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
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

        function changeTheme() {
            const theme = document.getElementById('theme').value;
            localStorage.setItem('theme', theme);
            applyTheme(theme);
        }

        // --- Digit input helpers, shared by the 2FA setup and recovery codes UI ---
        function buildDigitInputs(container, length) {
            container.innerHTML = '';
            for (let i = 0; i < length; i++) {
                const input = document.createElement('input');
                input.type = 'text';
                input.maxLength = 1;
                input.inputMode = 'numeric';
                input.className = 'form-control';
                container.appendChild(input);
            }
        }

        function digitCode(container) {
            return Array.from(container.querySelectorAll('input')).map((i) => i.value).join('');
        }

        function randomBase32(length) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            let out = '';
            for (let i = 0; i < length; i++) {
                out += chars[Math.floor(Math.random() * chars.length)];
            }
            return out;
        }

        document.addEventListener('input', function (event) {
            if (!event.target.matches('.digit-inputs input')) {
                return;
            }
            const input = event.target;
            if (input.value.length === 1 && input.nextElementSibling) {
                input.nextElementSibling.focus();
            } else if (input.value.length === 0 && input.previousElementSibling) {
                input.previousElementSibling.focus();
            }
        });

        // --- Two-Factor Authentication (client-side demo, no backend yet) ---
        const twoFaStatus = document.getElementById('twoFaStatus');
        const twoFaActionBtn = document.getElementById('twoFaActionBtn');
        const twoFaModalEl = document.getElementById('twoFaModal');
        const twoFaSetupInputs = document.getElementById('twoFaSetupInputs');

        function refreshTwoFaUi() {
            const enabled = localStorage.getItem('demo_2fa_enabled') === 'true';
            twoFaStatus.textContent = enabled ? 'Enabled' : 'Disabled';
            twoFaStatus.className = 'badge d-block mb-2 ' + (enabled ? 'text-bg-success' : 'text-bg-secondary');
            twoFaActionBtn.textContent = enabled ? 'Disable' : 'Enable';
            if (enabled) {
                twoFaActionBtn.removeAttribute('data-bs-toggle');
                twoFaActionBtn.removeAttribute('data-bs-target');
            } else {
                twoFaActionBtn.setAttribute('data-bs-toggle', 'modal');
                twoFaActionBtn.setAttribute('data-bs-target', '#twoFaModal');
            }
        }

        twoFaModalEl.addEventListener('show.bs.modal', function () {
            document.getElementById('twoFaSecret').textContent = randomBase32(16).match(/.{1,4}/g).join(' ');
            buildDigitInputs(twoFaSetupInputs, 6);
            document.getElementById('twoFaSetupError').textContent = '';
        });

        document.getElementById('confirmTwoFaBtn').addEventListener('click', function () {
            const code = digitCode(twoFaSetupInputs);
            if (code.length !== 6) {
                document.getElementById('twoFaSetupError').textContent = 'Please enter the 6-digit code.';
                return;
            }
            localStorage.setItem('demo_2fa_enabled', 'true');
            refreshTwoFaUi();
            bootstrap.Modal.getInstance(twoFaModalEl).hide();
        });

        twoFaActionBtn.addEventListener('click', function () {
            const enabled = localStorage.getItem('demo_2fa_enabled') === 'true';
            if (enabled && confirm('Disable Two-Factor Authentication?')) {
                localStorage.removeItem('demo_2fa_enabled');
                refreshTwoFaUi();
            }
        });

        refreshTwoFaUi();

        // --- Security keys / FIDO (client-side demo, no backend yet) ---
        const fidoKeyList = document.getElementById('fidoKeyList');

        function loadFidoKeys() {
            return JSON.parse(localStorage.getItem('demo_fido_keys') || '[]');
        }

        function renderFidoKeys() {
            const keys = loadFidoKeys();
            fidoKeyList.innerHTML = '';

            if (keys.length === 0) {
                fidoKeyList.innerHTML = '<li class="text-muted">No security keys registered yet.</li>';
                return;
            }

            keys.forEach((name, index) => {
                const li = document.createElement('li');
                li.className = 'd-flex align-items-center gap-2 mt-1';

                const label = document.createElement('span');
                label.innerHTML = '<i class="fas fa-key text-muted"></i> ';
                label.append(name);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-link btn-sm text-danger p-0 ms-2';
                removeBtn.textContent = 'Remove';
                removeBtn.addEventListener('click', function () {
                    const remaining = loadFidoKeys().filter((_, i) => i !== index);
                    localStorage.setItem('demo_fido_keys', JSON.stringify(remaining));
                    renderFidoKeys();
                });

                li.appendChild(label);
                li.appendChild(removeBtn);
                fidoKeyList.appendChild(li);
            });
        }

        document.getElementById('addFidoKeyBtn').addEventListener('click', function () {
            const name = prompt('Name this security key (e.g. "YubiKey 5"):');
            if (!name) {
                return;
            }
            const keys = loadFidoKeys();
            keys.push(name);
            localStorage.setItem('demo_fido_keys', JSON.stringify(keys));
            renderFidoKeys();
        });

        renderFidoKeys();

        // --- Recovery codes (client-side demo, no backend yet) ---
        const recoveryCodesGrid = document.getElementById('recoveryCodesGrid');
        const recoveryStatus = document.getElementById('recoveryStatus');

        function loadRecoveryCodes() {
            const stored = localStorage.getItem('demo_recovery_codes');
            return stored ? JSON.parse(stored) : null;
        }

        function generateRecoveryCodes() {
            const codes = [];
            for (let i = 0; i < 10; i++) {
                codes.push(randomBase32(10).match(/.{1,5}/g).join('-'));
            }
            localStorage.setItem('demo_recovery_codes', JSON.stringify(codes));
            return codes;
        }

        function renderRecoveryCodes(codes) {
            recoveryCodesGrid.innerHTML = codes.map((code) => `<span>${code}</span>`).join('');
            recoveryStatus.textContent = codes.length + ' recovery codes generated.';
        }

        document.getElementById('recoveryModal').addEventListener('show.bs.modal', function () {
            renderRecoveryCodes(loadRecoveryCodes() || generateRecoveryCodes());
        });

        document.getElementById('regenerateRecoveryBtn').addEventListener('click', function () {
            if (confirm('Regenerating will invalidate your existing recovery codes. Continue?')) {
                renderRecoveryCodes(generateRecoveryCodes());
            }
        });

        const existingRecoveryCodes = loadRecoveryCodes();
        if (existingRecoveryCodes) {
            recoveryStatus.textContent = existingRecoveryCodes.length + ' recovery codes generated.';
        }
    </script>
</body>

</html>
