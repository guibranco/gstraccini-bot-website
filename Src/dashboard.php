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
    <title>GStraccini-bot Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
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
    </Style>
</head>

<body>
    <div class="container mt-5">
        <div class="user-info">
            <img src="<?php echo $user['avatar_url']; ?>" alt="User Avatar" width="80" height="80">
            <div>
                <h2>Welcome, <a href="<?php echo htmlspecialchars($user['html_url']); ?>"
                        target="_blank"><?php echo htmlspecialchars($user['login']); ?></a>!</h2>
                <p class="welcome-message">We're glad to have you back.</p>
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
                                <td><a href='<?php echo $repo['url']; ?>'><?php echo htmlspecialchars($repo['full_name']); ?></a>
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

        <div class="row mt-4">
            <div class="col-md-12 text-center">
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>

</html>
