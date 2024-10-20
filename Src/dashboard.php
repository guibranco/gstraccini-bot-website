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

$data = array("openPullRequests" => [], "openIssues" => [], "repositories" => []);

if (isset($_SESSION["data"])) {
    $data = $_SESSION["data"];
}

$title = "Dashboard";

$name = $user["login"];
if (isset($user["first_name"])) {
    $name = $user["first_name"];
} else if (isset($user["name"])) {
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
    <link rel="stylesheet" href="user.css">
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5">
        <div class="user-info">
            <img src="<?php echo $user['avatar_url']; ?>" alt="User Avatar" width="80" height="80">
            <div>
                <h2>Welcome, <a href="<?php echo htmlspecialchars($user['html_url']); ?>" target="_blank"><?php echo htmlspecialchars($name); ?></a></h2>
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
                                <p class="card-text display-4">120</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Pull Requests Merged</h5>
                                <p class="card-text display-4">85</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Commits Analyzed</h5>
                                <p class="card-text display-4">320</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Issues Closed</h5>
                                <p class="card-text display-4">42</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Average Time to Merge (hrs)</h5>
                                <p class="card-text display-4">12</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">Active Repositories</h5>
                                <p class="card-text display-4">6</p>
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
                        id="openPullRequestsCount"><?php echo count($data["openPullRequests"]); ?></span></h3>
                <ul class="list-group" id="openPullRequests">
                    <?php if (count($data["openPullRequests"]) === 0): ?>
                        <li class="list-group-item">
                            <i class="fas fa-spinner fa-spin"></i> Loading data...
                        </li>
                    <?php endif; ?>
                    <?php foreach ($data["openPullRequests"] as $issue): ?>
                        <li class="list-group-item">
                            <strong><a
                                    href='<?php echo $issue['url']; ?>'><?php echo htmlspecialchars($issue['title']); ?></a></strong>
                            <br />
                            <span class="text-muted">
                                <a href='https://github.com/<?php echo htmlspecialchars($issue['full_name']); ?>'><?php echo htmlspecialchars($issue['repository']); ?></a>
                            </span> - 
                            <span class="text-muted">(Created at: <?php echo $issue['created_at']; ?>)</span>
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
                        id="openIssuesCount"><?php echo count($data["openIssues"]); ?></span></h3>
                <ul class="list-group" id="openIssues">
                    <?php if (count($data["openIssues"]) === 0): ?>
                        <li class="list-group-item">
                            <i class="fas fa-spinner fa-spin"></i> Loading data...
                        </li>
                    <?php endif; ?>
                    <?php foreach ($data["openIssues"] as $issue): ?>
                        <li class="list-group-item">
                            <strong><a
                                    href='<?php echo $issue['url']; ?>'><?php echo htmlspecialchars($issue['title']); ?></a></strong>
                            <br />                            
                            <span class="text-muted">
                                <a href='https://github.com/<?php echo htmlspecialchars($issue['full_name']); ?>'><?php echo htmlspecialchars($issue['repository']); ?></a>
                            </span> - 
                            <span class="text-muted">(Created at: <?php echo $issue['created_at']; ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-12">
                <h3>Your Repositories <span class="badge text-bg-warning rounded-pill"
                        id="repositoriesCount"><?php echo count($data["repositories"]); ?></span></h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Organization</th>
                            <th scope="col">Name</th>
                            <th scope="col">Stars</th>
                            <th scope="col">Fork</th>
                            <th scope="col">Forks</th>
                            <th scope="col">Open Issues</th>
                            <th scope="col">Languages</th>
                            <th scope="col">Visibility</th>
                        </tr>
                    </thead>
                    <tbody id="repositories">
                        <?php if (count($data["repositories"]) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($data["repositories"] as $repo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($repo['organization']) ?></td>
                                <td><a href='<?php echo $repo['url']; ?>'><?php echo htmlspecialchars($repo['name']); ?></a></td>
                                <td><i class="fas fa-star status-pending"></i> <?php echo $repo['stars']; ?></td>
                                <td><?php echo $repo['fork'] ? '<i class="fas fa-circle-check status-success"></i> Yes' : '<i class="fas fa-circle-xmark status-failed"></i> No'; ?></td>
                                <td><i class="fas fa-code-branch"></i> <?php echo $repo['forks']; ?></td>
                                <td><i class="fas fa-circle-exclamation"></i> <?php echo $repo['issues']; ?></td>
                                <td><span class="badge bg-primary"><?php echo empty($repo['language'])?'-':$repo['language']; ?></span></td>
                                <td><i class="fas fa-eye<?php echo ($repo['visibility']==='private')?'-slash':'';?>"></i> <?php echo $repo['visibility']; ?></td>                                
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                content += `<strong><a href='${item.url}'>${item.title}</a></strong><br />`;
                content += `<span class="text-muted"><a href='https://github.com/${item.full_name}'>${item.repository}</a></span> - `;
                content += `<span class="text-muted">(Created at: ${item.created_at})</span> ${state}`;
                itemLi.innerHTML = content;
                list.appendChild(itemLi);
            });
        }

        function populateRepositoriesTable(repositories) {
            $("#repositoriesCount").text(repositories.length);
            const repositoriesTable = document.getElementById('repositories');
            repositoriesTable.innerHTML = '';
            repositories.forEach(repo => {
                const slash = repo.visibility === 'private' ? '-slash' : '';
                const row = document.createElement('tr');
                row.innerHTML = `
                <td>${repo.organization}</td>
                <td><a href='${repo.url}'>${repo.name}</a></a></td>
                <td><i class="fas fa-star status-pending"></i> ${repo.stars}</td>
                <td>${repo.fork ? '<i class="fas fa-circle-check status-success"></i> Yes' : '<i class="fas fa-circle-xmark status-failed"></i> No'}
                <td><i class="fas fa-code-branch"></i> ${repo.forks}</td>
                <td><i class="fas fa-circle-exclamation"></i> ${repo.issues}</td>
                <td><span class="badge bg-primary">${repo.language ?? '-'}</span></td>
                <td><i class="fas fa-eye${slash}"></i> ${repo.visibility}                
            `;
                repositoriesTable.appendChild(row);
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
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    populateIssues(data.openPullRequests, "openPullRequests");
                    populateIssues(data.openIssues, "openIssues");
                    populateRepositoriesTable(data.repositories);
                    setTimeout(loadData, 1000 * 60);
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorAlert('Failed to complete the request. Please try again later.');;
                });
        }

        window.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>

</html>
