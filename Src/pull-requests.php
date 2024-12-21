<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];

$data = array("openPullRequests" => []);

if (isset($_SESSION["data"])) {
    $data = $_SESSION["data"];
}

$title = "Pull Requests";

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
    <link rel="stylesheet" href="static/user.css">
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5 d-none" id="alert-container"></div>

    <div class="container mt-5">
        <div class="row mt-5">
            <div class="col-md-12">
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
                            <strong><a href='<?php echo $issue['url']; ?>'
                                    target='_blank'><?php echo htmlspecialchars($issue['title']); ?></a></strong>
                            <br />
                            <span class="text-muted">
                                <a href='https://github.com/<?php echo htmlspecialchars($issue['full_name']); ?>'
                                    target='_blank'><?php echo htmlspecialchars($issue['repository']); ?></a>
                            </span> -
                            <span class="text-muted">(🕐 <?php echo $issue['created_at']; ?>)</span>
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
                let state = getStateBadge(item.state);
                const itemLi = document.createElement('li');
                itemLi.className = 'list-group-item';
                let content = '';
                content += `<strong><a href='${item.url}' target='_blank'>${item.title}</a></strong><br />`;
                content += `<span class="text-muted"><a href='https://github.com/${item.full_name}' target='_blank'>${item.repository}</a></span> - `;
                content += `<span class="text-muted">(🕐 ${item.created_at})</span> ${state}`;
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
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    populateIssues(data.openPullRequests, "openPullRequests");
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