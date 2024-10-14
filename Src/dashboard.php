<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];

$data = array("repositories" => [], "recentIssues" => []);

if (isset($_SESSION["data"])) {
    $data = $_SESSION["data"];
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
    <?php require_once 'header.php'; ?>

    <div class="container mt-5">
        <div class="user-info">
            <img src="<?php echo $user['avatar_url']; ?>" alt="User Avatar" width="80" height="80">
            <div>
                <h2>Welcome, <a href="<?php echo htmlspecialchars($user['html_url']); ?>"
                        target="_blank"><?php echo htmlspecialchars($user['login']); ?></a>!</h2>
                <p class="welcome-message">We're glad to have you back.</p>
            </div>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row">
            <section class="col-12">
                <h2 class="mb-4">GitHub Bot Usage Statistics</h2>
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
        <div class="row">
            <div class="col-md-6">
                <h3>Your Repositories</h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Repository</th>
                            <th>Stars</th>
                            <th>Forks</th>
                            <th>Open Issues</th>
                        </tr>
                    </thead>
                    <tbody id="repositories">
                        <?php foreach ($repositories as $repo): ?>
                            <tr>
                                <td><a
                                        href='<?php echo $repo['url']; ?>'><?php echo htmlspecialchars($repo['full_name']); ?></a>
                                </td>
                                <td><?php echo $repo['stars']; ?></td>
                                <td><?php echo $repo['forks']; ?></td>
                                <td><?php echo $repo['issues']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="col-md-6">
                <h3>Recent Issues</h3>
                <ul class="list-group" id="recentIssues">
                    <?php foreach ($recentIssues as $issue): ?>
                        <li class="list-group-item">
                            <strong><a
                                    href='<?php echo $issue['url']; ?>'><?php echo htmlspecialchars($issue['title']); ?></a></strong>
                            <span class="text-muted">(Created at: <?php echo $issue['created_at']; ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
        function populateRepositoriesTable(repositories) {
            const repositoriesTable = document.getElementById('repositories');
            repositoriesTable.innerHTML = '';
            repositories.forEach(repo => {
                const row = document.createElement('tr');
                row.innerHTML = `
                <td><a href='${repo.url}'>${repo.full_name}</a></td>
                <td>${repo.stars}</td>
                <td>${repo.forks}</td>
                <td>${repo.issues}</td>
            `;
                repositoriesTable.appendChild(row);
            });
        }

        function populateRecentIssuesList(recentIssues) {
            const recentIssuesList = document.getElementById('recentIssues');
            recentIssuesList.innerHTML = '';
            recentIssues.forEach(issue => {
                const item = document.createElement('li');
                item.className = 'list-group-item';
                item.innerHTML = `
                <strong><a href='${issue.url}'>${issue.title}</a></strong>
                <span class="text-muted">(Created at: ${issue.created_at})</span>
            `;
                recentIssuesList.appendChild(item);
            });
        }

        function loadData() {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    populateRepositoriesTable(data.repositories);
                    populateRecentIssuesList(data.recentIssues);
                });
        }
    </script>
</body>

</html>