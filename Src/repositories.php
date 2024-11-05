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

$data = array("repositories" => []);

if (isset($_SESSION["data"])) {
    $data = $_SESSION["data"];
}

$title = "Repositories";

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

    <div class="container mt-5 d-none" id="alert-container"></div>
  
    <div class="container mt-5">
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
                                <td><a href='<?php echo $repo['url']; ?>' target='_blank'><?php echo htmlspecialchars($repo['name']); ?></a></td>
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

        function populateRepositoriesTable(repositories) {
            $("#repositoriesCount").text(repositories.length);
            const repositoriesTable = document.getElementById('repositories');
            repositoriesTable.innerHTML = '';
            repositories.forEach(repo => {
                const slash = repo.visibility === 'private' ? '-slash' : '';
                const row = document.createElement('tr');
                row.innerHTML = `
                <td>${repo.organization}</td>
                <td><a href='${repo.url}' target='_blank'>${repo.name}</a></a></td>
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

        function loadData() {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
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
