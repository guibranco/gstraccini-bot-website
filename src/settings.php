<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

require_once "webhook.secrets.php";
require_once "includes/constants.php";
require_once "includes/log-stream.php";
require_once "includes/remote-json-proxy.php";
require_once "includes/user-scope.php";

// Saved through /api/v1/user-settings (see the inline script below) - this
// page only renders the initial values server-side.

$user = $_SESSION['user'];
$userData = [
    'create_labels' => 1,
    'notify_issues' => 1,
    'require_acceptance_criteria_checklist' => 1,
    'reminder_issues' => 1,
    'reminder_issues_days' => 10,
    'pr_template_description' => 1,
    'auto_review_pr' => 1,
    'auto_merge_pr' => 1,
    'create_issue' => 1,
    'notify_pull_requests' => 1
];

$settingsUrl = appendUserIdParam($gstracciniApiUrl."v1/user-settings/", getCurrentUserId());
$settingsResult = sendJsonToUpstream($settingsUrl, 'GET', null, ["X-Api-Key: $gstracciniApiKey"]);
if ($settingsResult['httpCode'] === 200 && is_array($settingsResult['decoded'])) {
    $remote = $settingsResult['decoded'];
    $userData = [
        'create_labels' => !empty($remote['createLabels']) ? 1 : 0,
        'notify_issues' => !empty($remote['notifyIssues']) ? 1 : 0,
        'require_acceptance_criteria_checklist' => !empty($remote['requireAcceptanceCriteriaChecklist']) ? 1 : 0,
        'reminder_issues' => !empty($remote['reminderIssues']) ? 1 : 0,
        'reminder_issues_days' => $remote['reminderIssuesDays'] ?? 10,
        'pr_template_description' => !empty($remote['prTemplateDescription']) ? 1 : 0,
        'auto_review_pr' => !empty($remote['autoReviewPr']) ? 1 : 0,
        'auto_merge_pr' => !empty($remote['autoMergePr']) ? 1 : 0,
        'create_issue' => !empty($remote['createIssue']) ? 1 : 0,
        'notify_pull_requests' => !empty($remote['notifyPullRequests']) ? 1 : 0,
    ];
}

// Opt-in features per GitHub account and per repository, saved one at a time
// through /api/v1/account-settings and /api/v1/repository-settings.
$apiKeyHeader = ["X-Api-Key: $gstracciniApiKey"];
$accountSettings = [];
$accountSettingsResult = sendJsonToUpstream(appendUserIdParam($gstracciniApiUrl."v1/account-settings/", getCurrentUserId()), 'GET', null, $apiKeyHeader);
if ($accountSettingsResult['httpCode'] === 200 && is_array($accountSettingsResult['decoded'])) {
    $accountSettings = $accountSettingsResult['decoded'];
}

$repositorySettings = [];
$repositorySettingsResult = sendJsonToUpstream(appendUserIdParam($gstracciniApiUrl."v1/repository-settings/", getCurrentUserId()), 'GET', null, $apiKeyHeader);
if ($repositorySettingsResult['httpCode'] === 200 && is_array($repositorySettingsResult['decoded'])) {
    $repositorySettings = $repositorySettingsResult['decoded'];
}

$featureSettingsUnavailable = $accountSettingsResult['httpCode'] !== 200 || $repositorySettingsResult['httpCode'] !== 200;

$title = "Settings";
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
    </style>
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <h1 class="text-center">Settings</h1>
        <p class="text-center">Manage your account settings below.</p>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Your settings have been updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="list-group account-nav" id="settingsTabs" role="tablist">
                    <a class="list-group-item list-group-item-action active" data-bs-toggle="list"
                        href="#tab-repository" role="tab"><i class="fas fa-folder-open"></i> Repository</a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#tab-issues"
                        role="tab"><i class="fas fa-exclamation-circle"></i> Issues</a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list"
                        href="#tab-pull-requests" role="tab"><i class="fas fa-code-branch"></i> Pull Requests</a>
                </div>
            </div>

            <div class="col-md-9">
                <form action="settings.php" method="POST" id="settingsForm" novalidate>
                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="tab-repository" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0"><i class="fas fa-folder-open"></i> Repository Settings</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-0">
                                        <label for="create_labels" class="form-label"><i class="fas fa-tags"></i>
                                            Create labels
                                            on new repository</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="create_labels"
                                                name="create_labels" <?php if ($userData['create_labels']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="create_labels">Automatically create
                                                labels on
                                                new repositories</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-issues" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0"><i class="fas fa-exclamation-circle"></i> Issues Settings</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="require_acceptance_criteria_checklist" class="form-label">
                                            <i class="fas fa-list-check"></i> Require Acceptance Criteria checklist
                                        </label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                id="require_acceptance_criteria_checklist"
                                                name="require_acceptance_criteria_checklist" <?php if ($userData['require_acceptance_criteria_checklist']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label"
                                                for="require_acceptance_criteria_checklist">
                                                Requires issue description to have an acceptance criteria checklist
                                                section.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reminder_issues" class="form-label"><i
                                                class="fas fa-calendar-alt"></i>
                                            Issues Reminder</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="reminder_issues"
                                                name="reminder_issues" <?php if ($userData['reminder_issues']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="reminder_issues">
                                                Remind the assigned user when the issue has been inactive (no pull
                                                request and
                                                no comments) for at least <input type="number"
                                                    class="form-control d-inline-block text-center"
                                                    id="reminder_issues_days" name="reminder_issues_days" min="1"
                                                    max="99" style="width: 60px;" <?php if ($userData['reminder_issues_days']) {
                                                        echo 'value="' . $userData["reminder_issues_days"] . '"';
                                                    } else {
                                                        echo "disabled";
                                                    } ?>> days
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-0">
                                        <label for="notify_issues" class="form-label"><i class="fas fa-bell"></i>
                                            Issues
                                            Notification</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="notify_issues"
                                                name="notify_issues" <?php if ($userData['notify_issues']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="notify_issues">Notify me when new
                                                issues are
                                                created</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-pull-requests" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0"><i class="fas fa-code-branch"></i> Pull Requests Settings</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="pr_template_description" class="form-label">
                                            <i class="fas fa-file-alt"></i> Add PR description from template
                                        </label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                id="pr_template_description" name="pr_template_description" <?php if ($userData['pr_template_description']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="pr_template_description">
                                                Automatically add a description to pull requests based on a
                                                predefined template
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">
                                            This action will only be applied if the pull request description is
                                            empty.
                                        </small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="auto_review_pr" class="form-label">
                                            <i class="fas fa-user-check"></i> Auto Approval Pull Request
                                        </label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="auto_review_pr"
                                                name="auto_review_pr" <?php if ($userData['auto_review_pr']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="auto_review_pr">
                                                Automatically approve the pull request if no issues are found
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="auto_merge_pr" class="form-label"><i
                                                class="fas fa-code-merge"></i> Enable
                                            Auto-Merge</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="auto_merge_pr"
                                                name="auto_merge_pr" <?php if ($userData['auto_merge_pr']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="auto_merge_pr">Automatically merge
                                                pull
                                                requests when all checks pass from trusted senders</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="create_issue" class="form-label">
                                            <i class="fas fa-tasks"></i>
                                            Create issues for pending tasks in code comments
                                            <span class="tag-chip">
                                                <i class="fas fa-wrench"></i> Fixme
                                            </span>
                                            <span class="tag-chip">
                                                <i class="fas fa-tasks"></i> Todo
                                            </span>
                                            <span class="tag-chip">
                                                <i class="fas fa-bug"></i> Bug
                                            </span>
                                        </label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="create_issue"
                                                name="create_issue" <?php if ($userData['create_issue']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="create_issue">Automatically create
                                                issues for
                                                specific keywords found in pull request content</label>
                                        </div>
                                    </div>

                                    <div class="mb-0">
                                        <label for="notify_pull_requests" class="form-label"><i
                                                class="fas fa-bell"></i> Pull
                                            Requests Notification</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                id="notify_pull_requests" name="notify_pull_requests" <?php if ($userData['notify_pull_requests']) {
                                                    echo 'checked';
                                                } ?>>
                                            <label class="form-check-label" for="notify_pull_requests">Notify me when
                                                new pull
                                                requests are created</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-4" id="version-bump-settings">
                                <div class="card-header">
                                    <h3 class="h5 mb-0"><i class="fas fa-code-compare"></i> Request version bump</h3>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        When enabled, the bot asks for a version bump decision (major, minor or
                                        none) on pull requests that look like a feature and have no
                                        <code>+semver:</code> directive, holding the <em>pull request</em> check
                                        run until one is chosen. Disabled by default. Repositories inherit their
                                        account unless set otherwise. Changes here are saved immediately.
                                    </p>
                                    <div id="version_bump_status" class="small mb-3" role="status" aria-live="polite"></div>

                                    <?php if ($featureSettingsUnavailable): ?>
                                        <div class="alert alert-warning mb-0">Unable to load the account and repository settings right now.</div>
                                    <?php elseif (count($accountSettings) === 0): ?>
                                        <div class="alert alert-info mb-0">No installation found for your account.</div>
                                    <?php else: ?>
                                        <h4 class="h6"><i class="fas fa-user-circle"></i> Accounts</h4>
                                        <?php foreach ($accountSettings as $account): ?>
                                            <?php $accountInputId = 'request_version_bump_account_' . (int) $account['accountId']; ?>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input js-account-version-bump" type="checkbox"
                                                    id="<?php echo $accountInputId; ?>"
                                                    data-account-id="<?php echo (int) $account['accountId']; ?>"
                                                    <?php echo !empty($account['requestVersionBump']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="<?php echo $accountInputId; ?>">
                                                    <?php echo htmlspecialchars($account['accountLogin'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                    <span class="text-muted small">(<?php echo htmlspecialchars($account['accountType'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>

                                        <h4 class="h6 mt-4"><i class="fas fa-folder-open"></i> Repositories</h4>
                                        <?php if (count($repositorySettings) === 0): ?>
                                            <p class="text-muted mb-0">No repository found.</p>
                                        <?php else: ?>
                                            <input type="search" class="form-control form-control-sm mb-2" id="version_bump_filter"
                                                placeholder="Filter repositories" aria-label="Filter repositories">
                                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Repository</th>
                                                            <th scope="col" style="width: 200px;">Setting</th>
                                                            <th scope="col" style="width: 110px;">Effective</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($repositorySettings as $repository): ?>
                                                            <?php
                                                            $repositoryValue = $repository['requestVersionBump'] ?? null;
                                                            $selected = $repositoryValue === null ? 'inherit' : ($repositoryValue ? 'on' : 'off');
                                                            $repositoryInputId = 'request_version_bump_repository_' . (int) $repository['repositoryId'];
                                                            ?>
                                                            <tr class="js-version-bump-row"
                                                                data-full-name="<?php echo htmlspecialchars(strtolower($repository['fullName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-account-id="<?php echo (int) $repository['accountId']; ?>">
                                                                <td>
                                                                    <label for="<?php echo $repositoryInputId; ?>" class="mb-0">
                                                                        <?php if (!empty($repository['private'])): ?><i class="fas fa-lock text-muted" title="Private"></i><?php endif; ?>
                                                                        <?php echo htmlspecialchars($repository['fullName'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                                    </label>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select form-select-sm js-repository-version-bump"
                                                                        id="<?php echo $repositoryInputId; ?>"
                                                                        data-repository-id="<?php echo (int) $repository['repositoryId']; ?>">
                                                                        <option value="inherit" <?php echo $selected === 'inherit' ? 'selected' : ''; ?>>Inherit from account</option>
                                                                        <option value="on" <?php echo $selected === 'on' ? 'selected' : ''; ?>>Enabled</option>
                                                                        <option value="off" <?php echo $selected === 'off' ? 'selected' : ''; ?>>Disabled</option>
                                                                    </select>
                                                                </td>
                                                                <td class="js-effective">
                                                                    <?php if (!empty($repository['effectiveRequestVersionBump'])): ?>
                                                                        <span class="badge bg-success">On</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">Off</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save
                            Settings</button>
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
                    return;
                }

                form.classList.remove('was-validated');

                const payload = {
                    createLabels: document.getElementById('create_labels').checked,
                    notifyIssues: document.getElementById('notify_issues').checked,
                    requireAcceptanceCriteriaChecklist: document.getElementById('require_acceptance_criteria_checklist').checked,
                    reminderIssues: document.getElementById('reminder_issues').checked,
                    reminderIssuesDays: document.getElementById('reminder_issues_days').value
                        ? parseInt(document.getElementById('reminder_issues_days').value, 10)
                        : null,
                    prTemplateDescription: document.getElementById('pr_template_description').checked,
                    autoReviewPr: document.getElementById('auto_review_pr').checked,
                    autoMergePr: document.getElementById('auto_merge_pr').checked,
                    createIssue: document.getElementById('create_issue').checked,
                    notifyPullRequests: document.getElementById('notify_pull_requests').checked,
                };

                fetch('/api/v1/user-settings', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            const data = await response.json().catch(() => ({}));
                            throw new Error(data.error || 'Failed to save settings.');
                        }
                        window.location.href = 'settings.php?updated=true';
                    })
                    .catch((error) => {
                        alert(error.message || 'Failed to save settings.');
                    });
            });

            const versionBumpStatus = document.getElementById('version_bump_status');

            function showVersionBumpStatus(message, isError) {
                if (!versionBumpStatus) {
                    return;
                }
                versionBumpStatus.textContent = message;
                versionBumpStatus.className = 'small mb-3 ' + (isError ? 'text-danger' : 'text-success');
            }

            function saveFeatureSetting(url, value) {
                return fetch(url, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ requestVersionBump: value }),
                }).then(async (response) => {
                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        throw new Error(data.error || 'Failed to save the setting.');
                    }
                });
            }

            function accountEnabled(accountId) {
                const input = document.querySelector('.js-account-version-bump[data-account-id="' + accountId + '"]');
                return input ? input.checked : false;
            }

            function refreshEffective(row) {
                const select = row.querySelector('.js-repository-version-bump');
                const effective = select.value === 'inherit' ? accountEnabled(row.dataset.accountId) : select.value === 'on';
                row.querySelector('.js-effective').innerHTML = effective
                    ? '<span class="badge bg-success">On</span>'
                    : '<span class="badge bg-secondary">Off</span>';
            }

            document.querySelectorAll('.js-account-version-bump').forEach(function (input) {
                input.addEventListener('change', function () {
                    const accountId = this.dataset.accountId;
                    const value = this.checked;
                    this.disabled = true;
                    saveFeatureSetting('/api/v1/account-settings/' + accountId, value)
                        .then(() => {
                            document.querySelectorAll('.js-version-bump-row[data-account-id="' + accountId + '"]').forEach(refreshEffective);
                            showVersionBumpStatus('Account setting saved.', false);
                        })
                        .catch((error) => {
                            this.checked = !value;
                            // A repository saved meanwhile may have shown the failed value.
                            document.querySelectorAll('.js-version-bump-row[data-account-id="' + accountId + '"]').forEach(refreshEffective);
                            showVersionBumpStatus(error.message, true);
                        })
                        .finally(() => {
                            this.disabled = false;
                        });
                });
            });

            document.querySelectorAll('.js-repository-version-bump').forEach(function (select) {
                select.dataset.previous = select.value;
                select.addEventListener('change', function () {
                    const value = this.value === 'inherit' ? null : this.value === 'on';
                    const row = this.closest('.js-version-bump-row');
                    this.disabled = true;
                    saveFeatureSetting('/api/v1/repository-settings/' + this.dataset.repositoryId, value)
                        .then(() => {
                            this.dataset.previous = this.value;
                            refreshEffective(row);
                            showVersionBumpStatus('Repository setting saved.', false);
                        })
                        .catch((error) => {
                            this.value = this.dataset.previous;
                            refreshEffective(row);
                            showVersionBumpStatus(error.message, true);
                        })
                        .finally(() => {
                            this.disabled = false;
                        });
                });
            });

            const versionBumpFilter = document.getElementById('version_bump_filter');
            if (versionBumpFilter) {
                // The filter sits inside the settings form: Enter must not submit it.
                versionBumpFilter.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                    }
                });
                versionBumpFilter.addEventListener('input', function () {
                    const term = this.value.trim().toLowerCase();
                    document.querySelectorAll('.js-version-bump-row').forEach(function (row) {
                        row.style.display = row.dataset.fullName.includes(term) ? '' : 'none';
                    });
                });
            }

            const reminder_issues = document.getElementById('reminder_issues');
            const reminder_issues_days = document.getElementById('reminder_issues_days');

            reminder_issues.addEventListener('change', function () {
                reminder_issues_days.disabled = !this.checked;
                if (!this.checked) {
                    reminder_issues_days.value = '';
                }
            });
        });
    </script>
</body>

</html>
