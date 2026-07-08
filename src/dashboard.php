<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];

$data = array("openPullRequestsDashboard" => [], "openIssuesDashboard" => []);

if (isset($_SESSION["dashboard"])) {
    $data = $_SESSION["dashboard"]["data"];
}

$title = "Dashboard";

$name = $user["login"];
if (isset($user["first_name"])) {
    $name = $user["first_name"];
} elseif (isset($user["name"])) {
    $name = $user["name"];
}

function renderStateBadge($state)
{
    $badges = [
        'success' => ['class' => 'bg-success', 'icon' => 'fa-check-circle', 'text' => 'Success', 'title' => 'CI checks passed successfully'],
        'failure' => ['class' => 'bg-danger', 'icon' => 'fa-times-circle', 'text' => 'Failure', 'title' => 'CI checks failed'],
        'pending' => ['class' => 'bg-warning text-dark', 'icon' => 'fa-hourglass-half', 'text' => 'Pending', 'title' => 'CI checks are running'],
        'error' => ['class' => 'bg-danger', 'icon' => 'fa-exclamation-triangle', 'text' => 'Error', 'title' => 'CI checks encountered an error'],
        'skipped' => ['class' => 'bg-dark', 'icon' => 'fa-arrow-circle-right', 'text' => 'Skipped', 'title' => 'CI checks were skipped'],
    ];
    $badge = $badges[$state] ?? ['class' => 'bg-secondary', 'icon' => 'fa-question-circle', 'text' => 'Unknown', 'title' => 'CI status unknown'];

    return '<span class="badge ' . $badge['class'] . ' ms-2" title="' . htmlspecialchars($badge['title']) . '">'
        . '<i class="fas ' . $badge['icon'] . ' me-1"></i>' . htmlspecialchars($badge['text']) . '</span>';
}
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
        .stat-card .stat-icon {
            font-size: 1.75rem;
        }

        .assigned-list .list-group-item {
            border-left: 0;
            border-right: 0;
        }

        .assigned-list .list-group-item:hover {
            background-color: #f8f9fa;
        }

        [data-theme="dark"] .assigned-list .list-group-item:hover {
            background-color: #242424;
        }

        .assigned-list .list-group-item:first-child {
            border-top: 0;
        }

        .assigned-list .list-group-item:last-child {
            border-bottom: 0;
        }
    </style>
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="user-info flex-wrap justify-content-between">
            <div class="d-flex align-items-center">
                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="User Avatar" width="80"
                    height="80">
                <div>
                    <h2>Welcome, <a href="<?php echo htmlspecialchars($user['html_url']); ?>" target="_blank"
                            rel="noopener noreferrer"><?php echo htmlspecialchars($name); ?></a></h2>
                    <p class="welcome-message">We're glad to have you back.</p>
                </div>
            </div>
            <div class="text-center text-md-end mt-3 mt-md-0">
                <button type="button" id="refreshDashboard" class="btn btn-light btn-sm">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
                <div id="lastRefresh" class="small mt-2" style="color: #f0f0f0;"></div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET["error"]) && intval($_GET["error"]) === 404): ?>
        <div class="container mt-5">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Page not found!</strong>
                <p>The page <b><?php echo htmlspecialchars($_GET["path"]); ?></b> could not be found.</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="container mt-5 d-none" id="alert-container"></div>

    <div class="container">
        <div class="row">
            <section class="col-12">
                <h2 class="mb-4">GStraccini-Bot Usage Statistics</h2>
                <div class="row g-3">

                    <div class="col-md-4 col-lg-2">
                        <div class="card text-center stat-card border-top border-4 border-primary shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-code-branch stat-icon text-primary mb-2"></i>
                                <h5 class="card-title">Total Pull Requests</h5>
                                <p class="card-text display-4" id="statTotalPullRequests" data-target="0">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 col-lg-2">
                        <div class="card text-center stat-card border-top border-4 border-success shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-code-merge stat-icon text-success mb-2"></i>
                                <h5 class="card-title">Pull Requests Merged</h5>
                                <p class="card-text display-4" id="statPullRequestsMerged" data-target="0">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 col-lg-2">
                        <div class="card text-center stat-card border-top border-4 border-info shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-code stat-icon text-info mb-2"></i>
                                <h5 class="card-title">Commits Analyzed</h5>
                                <p class="card-text display-4" id="statCommitsAnalyzed" data-target="0">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 col-lg-2">
                        <div class="card text-center stat-card border-top border-4 border-success shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-check-double stat-icon text-success mb-2"></i>
                                <h5 class="card-title">Issues Closed</h5>
                                <p class="card-text display-4" id="statIssuesClosed" data-target="0">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 col-lg-2">
                        <div class="card text-center stat-card border-top border-4 border-warning shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-clock stat-icon text-warning mb-2"></i>
                                <h5 class="card-title">Avg. Time to Merge (hrs)</h5>
                                <p class="card-text display-4" id="statAverageTimeToMerge" data-target="0">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 col-lg-2">
                        <div class="card text-center stat-card border-top border-4 border-secondary shadow-sm h-100">
                            <div class="card-body">
                                <i class="fas fa-folder-open stat-icon text-secondary mb-2"></i>
                                <h5 class="card-title">Active Repositories</h5>
                                <p class="card-text display-4" id="statActiveRepositories" data-target="0">0</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="row mt-5">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0"><i class="fas fa-history"></i> Recent Activities</h3>
                    </div>
                    <ul class="list-group list-group-flush assigned-list">
                        <li class="list-group-item">Created PR #45 in repo1</li>
                        <li class="list-group-item">Merged PR #44 in repo2</li>
                        <li class="list-group-item">Closed issue #10 in repo1</li>
                        <li class="list-group-item">Analyzed commits in repo3</li>
                        <li class="list-group-item">Opened PR #12 in repo2</li>
                    </ul>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0"><i class="fas fa-list-check"></i> Pending Actions</h3>
                    </div>
                    <ul class="list-group list-group-flush assigned-list">
                        <li class="list-group-item">Review PR #43 in repo3</li>
                        <li class="list-group-item">Close issue #11 in repo2</li>
                        <li class="list-group-item">Merge PR #42 in repo1</li>
                        <li class="list-group-item">Update README in repo2</li>
                        <li class="list-group-item">Respond to issue #9 in repo1</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-1 mb-5">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0"><i class="fas fa-code-branch"></i> Assigned Pull Requests
                            <span class="badge text-bg-warning rounded-pill" id="openPullRequestsCount">
                                <?php echo count($data["openPullRequestsDashboard"]); ?>
                            </span>
                        </h3>
                    </div>
                    <ul class="list-group list-group-flush assigned-list" id="openPullRequests">
                        <?php if (count($data["openPullRequestsDashboard"]) === 0): ?>
                            <li class="list-group-item border-0 py-3">
                                <i class="fas fa-spinner fa-spin"></i> Loading data...
                            </li>
                        <?php endif; ?>
                        <?php foreach ($data["openPullRequestsDashboard"] as $issue): ?>
                            <li class="list-group-item border-0 py-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1 pe-3">
                                        <div class="mb-2">
                                            <a href="<?php echo htmlspecialchars($issue['url']); ?>" target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-decoration-none fw-bold text-dark"><?php echo htmlspecialchars($issue['title']); ?></a>
                                        </div>
                                        <div class="mb-2">
                                            <a href="https://github.com/<?php echo htmlspecialchars($issue['full_name']); ?>"
                                                target="_blank" rel="noopener noreferrer"
                                                class="text-muted text-decoration-none small">
                                                <i class="fab fa-github me-1"></i><?php echo htmlspecialchars($issue['repository']); ?>
                                            </a>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($issue['created_at']); ?>
                                        </div>
                                    </div>
                                    <?php if (isset($issue["state"])): ?>
                                        <div class="d-flex align-items-center">
                                            <?php echo renderStateBadge($issue["state"]); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0"><i class="fas fa-exclamation-circle"></i> Assigned Issues
                            <span class="badge text-bg-warning rounded-pill" id="openIssuesCount">
                                <?php echo count($data["openIssuesDashboard"]); ?>
                            </span>
                        </h3>
                    </div>
                    <ul class="list-group list-group-flush assigned-list" id="openIssues">
                        <?php if (count($data["openIssuesDashboard"]) === 0): ?>
                            <li class="list-group-item border-0 py-3">
                                <i class="fas fa-spinner fa-spin"></i> Loading data...
                            </li>
                        <?php endif; ?>
                        <?php foreach ($data["openIssuesDashboard"] as $issue): ?>
                            <li class="list-group-item border-0 py-3">
                                <div class="mb-2">
                                    <a href="<?php echo htmlspecialchars($issue['url']); ?>" target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-decoration-none fw-bold text-dark"><?php echo htmlspecialchars($issue['title']); ?></a>
                                </div>
                                <div class="mb-2">
                                    <a href="https://github.com/<?php echo htmlspecialchars($issue['full_name']); ?>"
                                        target="_blank" rel="noopener noreferrer"
                                        class="text-muted text-decoration-none small">
                                        <i class="fab fa-github me-1"></i><?php echo htmlspecialchars($issue['repository']); ?>
                                    </a>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($issue['created_at']); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "includes/footer.php"; ?>
    <script src="static/dashboard.js"></script>
</body>

</html>
