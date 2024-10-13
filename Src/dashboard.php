<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: login.php');
    exit();
}

$token = $_SESSION['token'];
$user = $_SESSION['user'];

$apiUrl = 'https://api.github.com/repositories?per_page=100';
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
            'stars' => $repo['stargazers_count'],
            'forks' => $repo['forks_count'],
            'issues' => $repo['open_issues_count']
        ];
    }
}

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
</head>

<body>
    <div class="container mt-5">
        <h1 class="text-center">Welcome, <a href="<?php echo htmlspecialchars($user['html_url']); ?>"
                target="_blank"><?php echo htmlspecialchars($user['login']); ?></a>!</h1>
        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="GitHub Avatar" width="100">
        <p class="text-center">Here is an overview of your GitHub repositories and activities.</p>

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
                                <td><?php echo htmlspecialchars($repo['name']); ?></td>
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
                            <strong><?php echo htmlspecialchars($issue['title']); ?></strong>
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
