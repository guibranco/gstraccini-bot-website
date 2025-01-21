<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];

$data = array("openPullRequestsDashboard" => [], "openIssuesDashboard" => []);

if (isset($_SESSION["data_dashboard"])) {
    $data = $_SESSION["data_dashboard"];
}

$title = "Dashboard";

$name = $user["login"];
if (isset($user["first_name"])) {
    $name = $user["first_name"];
} elseif (isset($user["name"])) {
    $name = $user["name"];
}
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
        <div class="user-info">
            <img src="<?php echo $user['avatar_url']; ?>" alt="User Avatar" width="80" height="80">
            <div>
                <h2>Welcome, <a href="<?php echo htmlspecialchars($user['html_url']); ?>"
                        target="_blank"><?php echo htmlspecialchars($name); ?></a></h2>
                <p class="welcome-message">We're glad to have you back.</p>
            </div>
        </div>
    </div>

    <div class="container mt-5 d-none" id="alert-container"></div>

    <div class="container">
        <div class="row">
            <section class="col-12">
                <h2 class="mb-4">GStraccini-Bot Usage Statistics</h2>
                <div class="row">

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Total Pull Requests</h5>
                                <p class="card-text display-4" data-target="120">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Pull Requests Merged</h5>
                                <p class="card-text display-4" data-target="85">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Commits Analyzed</h5>
                                <p class="card-text display-4" data-target="320">0</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Issues Closed</h5>
                                <p class="card-text display-4" data-target="42">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Average Time to Merge (hrs)</h5>
                                <p class="card-text display-4" data-target="12">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Active Repositories</h5>
                                <p class="card-text display-4" data-target="6">0</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="row mt-5">
            <div class="col-md-6">
                <h3 class="mb-4">Recent Activities</h3>
                <ul class="list-group">
                    <li class="list-group-item">Created PR #45 in repo1</li>
                    <li class="list-group-item">Merged PR #44 in repo2</li>
                    <li class="list-group-item">Closed issue #10 in repo1</li>
                    <li class="list-group-item">Analyzed commits in repo3</li>
                    <li class="list-group-item">Opened PR #12 in repo2</li>
                </ul>
            </div>

            <div class="col-md-6">
                <h3 class="mb-4">Pending Actions</h3>
                <ul class="list-group">
                    <li class="list-group-item">Review PR #43 in repo3</li>
                    <li class="list-group-item">Close issue #11 in repo2</li>
                    <li class="list-group-item">Merge PR #42 in repo1</li>
                    <li class="list-group-item">Update README in repo2</li>
                    <li class="list-group-item">Respond to issue #9 in repo1</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row mt-5">
            <div class="col-md-6">
                <h3>Assigned Pull Requests <span class="badge text-bg-warning rounded-pill"
                        id="openPullRequestsCount"><?php echo count($data["openPullRequestsDashboard"]); ?></span></h3>
                <ul class="list-group" id="openPullRequests">
                    <?php if (count($data["openPullRequestsDashboard"]) === 0): ?>
                        <li class="list-group-item">
                            <i class="fas fa-spinner fa-spin"></i> Loading data...
                        </li>
                    <?php endif; ?>
                    <?php foreach ($data["openPullRequestsDashboard"] as $issue): ?>
                        <li class="list-group-item">
                            <strong><a href='<?php echo $issue['url']; ?>'
                                    target='_blank'><?php echo htmlspecialchars($issue['title']); ?></a></strong>
                            <br />
                            <span class="text-muted">
                                <a href='https://github.com/<?php echo htmlspecialchars($issue['full_name']); ?>'
                                    target='_blank'><?php echo htmlspecialchars($issue['repository']); ?></a>
                            </span>
                            <br />
                            <span class="text-muted">🕐 <?php echo $issue['created_at']; ?></span>
                            <?php if (isset($issue["state"]) && $issue["state"] === "success") { ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i> Success
                                </span>
                            <?php } else if (isset($issue["state"]) && $issue["state"] === "failure") { ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle"></i> Failure
                                    </span>
                            <?php } else if (isset($issue["state"]) && $issue["state"] === "pending") { ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-hourglass-half"></i> Pending
                                        </span>
                            <?php } else if (isset($issue["state"]) && $issue["state"] === "error") { ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-exclamation-triangle"></i> Error
                                            </span>
                            <?php } else { ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-question-circle"></i> Empty
                                            </span>
                            <?php } ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-md-6">
                <h3>Assigned Issues <span class="badge text-bg-warning rounded-pill"
                        id="openIssuesCount"><?php echo count($data["openIssuesDashboard"]); ?></span></h3>
                <ul class="list-group" id="openIssues">
                    <?php if (count($data["openIssuesDashboard"]) === 0): ?>
                        <li class="list-group-item">
                            <i class="fas fa-spinner fa-spin"></i> Loading data...
                        </li>
                    <?php endif; ?>
                    <?php foreach ($data["openIssuesDashboard"] as $issue): ?>
                        <li class="list-group-item">
                            <strong><a href='<?php echo $issue['url']; ?>'
                                    target='_blank'><?php echo htmlspecialchars($issue['title']); ?></a></strong>
                            <br />
                            <span class="text-muted">
                                <a href='https://github.com/<?php echo htmlspecialchars($issue['full_name']); ?>'
                                    target='_blank'><?php echo htmlspecialchars($issue['repository']); ?></a>
                            </span> -
                            <span class="text-muted">(🕐 <?php echo $issue['created_at']; ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <?php require_once "includes/footer.php"; ?>
    <script>
        function showErrorAlert(message) {
            var alertHtml = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;

            $("#alert-container").toggleClass("d-none").toggleClass("d-block").html(alertHtml);

            setTimeout(function () {
                var alertElement = document.querySelector('.alert');
                if (alertElement) {
                    var alertInstance = new bootstrap.Alert(alertElement);
                    alertInstance.close();
                }
            }, 15000);
        }

        function populateIssues(items, id) {
            $(`#${id}Count`).text(items.length);
            const list = document.getElementById(id);
            list.innerHTML = '';

            if (items.length === 0) {
                const itemLi = document.createElement('li');
                itemLi.className = 'list-group-item list-group-item-warning';
                itemLi.innerHTML = `<strong>No items found!</strong>`;
                list.appendChild(itemLi);
                return;
            }

            items.forEach(item => {
                let state = "";
                if (id === "openPullRequests") {
                    state = getStateBadge(item.state);
                }
                const itemLi = document.createElement('li');
                itemLi.className = 'list-group-item';
                let content = '';
                content += `<strong><a href='${item.url}' target='_blank'>${item.title}</a></strong><br />`;
                content += `<span class="text-muted"><a href='https://github.com/${item.full_name}' target='_blank'>${item.repository}</a></span><br />`;
                content += `<span class="text-muted">🕐 ${item.created_at}</span>`
                content += state;
                itemLi.innerHTML = content;
                list.appendChild(itemLi);
            });
        }

        function getStateBadge(state) {
            switch (state) {
                case 'success':
                    return '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Success</span>';
                case 'failure':
                    return '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Failure</span>';
                case 'pending':
                    return '<span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half"></i> Pending</span>';
                case 'error':
                    return '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Error</span>';
                default:
                    return '<span class="badge bg-secondary"><i class="fas fa-question-circle"></i> Empty</span>';
            }
        }

        function loadData() {
            fetch('api.php?dashboard=true')
                .then(response => response.json())
                .then(data => {
                    populateIssues(data.openPullRequestsDashboard, "openPullRequests");
                    populateIssues(data.openIssuesDashboard, "openIssues");
                    setTimeout(loadData, 1000 * 60);
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorAlert('Failed to complete the request. Please try again later.');;
                });
        }

        window.addEventListener('DOMContentLoaded', loadData);
        document.addEventListener('DOMContentLoaded', () => {
            const counters = document.querySelectorAll('.card-text');

            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const duration = 2000;
                const interval = 10;
                const increment = target / (duration / interval);

                let current = 0;

                const updateCounter = () => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                    } else {
                        counter.textContent = Math.ceil(current);
                        setTimeout(updateCounter, interval);
                    }
                };

                updateCounter();
            });
        });
    </script>
</body>

</html>
