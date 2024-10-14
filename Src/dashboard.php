<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: login.php');
    exit();
}

$token = $_SESSION['token'];
$user = $_SESSION['user'];

$apiUrl = 'https://api.github.com/user/repos?per_page=100';
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'User-Agent: GStraccini-bot-website/1.0'
]);

$reposData = curl_exec($ch);
curl_close($ch);

$repositories = [];
if ($reposData) {
    $repos = json_decode($reposData, true);
    foreach ($repos as $repo) {
        $repositories[] = [
            'name' => $repo['name'],
            'full_name' => $repo['full_name'],
            'url' => $repo['html_url'],
            'stars' => $repo['stargazers_count'],
            'forks' => $repo['forks_count'],
            'issues' => $repo['open_issues_count']
        ];
    }
}

sort($repositories);

$apiUrl = "https://api.github.com/issues?per_page=100";
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'User-Agent: GStraccini-bot-website/1.0'
]);

$issuesData = curl_exec($ch);
curl_close($ch);

$recentIssues = [];
if ($issuesData) {
    $issues = json_decode($issuesData, true);
    foreach ($issues as $issue) {
        $recentIssues[] = [
            'title' => $issue['title'],
            'url' => $issue['html_url'],
            'created_at' => $issue['created_at']
        ];
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GStraccini-bot | Activity Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <Style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f4f4;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            background-color: #007bff;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .user-info a {
            color: white;
            font-weight: bold;
        }

        .user-info img {
            border-radius: 50%;
            margin-right: 20px;
        }

        .user-info h2 {
            margin: 0;
            font-size: 2em;
        }

        .welcome-message {
            font-size: 1.2em;
            color: #f0f0f0;
        }

        .dropdown-item.logout {
            background-color: #dc3545;
            color: white;
        }

        .dropdown-item.logout:hover {
            background-color: #c82333;
        }
    </style>
</head>

<body>
    <header class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png"
                alt="Bot Logo" class="me-2">
            <span class="navbar-brand">Activity Dashboard</span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <strong>guibranco</strong>!</span>
                <img src="https://avatars.githubusercontent.com/u/3362854?v=4" alt="User Avatar" width="40" height="40"
                    class="rounded-circle me-2">
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" id="dropdownMenuButton"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                        <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item logout" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="container mt-5">
        <div class="user-info">
            <img src="<?php echo $user['avatar_url']; ?>" alt="User Avatar" width="80" height="80">
            <div>
                <h2>Welcome, <a href="<?php echo htmlspecialchars($user['html_url']); ?>"
                        target="_blank"><?php echo htmlspecialchars($user['login']); ?></a>!</h2>
                <p class="welcome-message">We're glad to have you back.</p>
            </div>
        </div>

        <div class="container mt-4">
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
                    <tbody>
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
                <ul class="list-group">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>