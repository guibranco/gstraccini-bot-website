<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];

$data = $_SESSION["data"] ?? array("openPullRequests" => []);

$title = "Pull Requests";

$name = $user["login"];
if (isset($user["first_name"])) {
    $name = $user["first_name"];
} else if (isset($user["name"])) {
    $name = $user["name"];
}

$groupedPullRequests = [];
foreach ($data["openPullRequests"] as $pr) {
    $account = $pr['owner'] ?? 'Unknown'; 
    if (!isset($groupedPullRequests[$account])) {
        $groupedPullRequests[$account] = [];
    }
    $groupedPullRequests[$account][] = $pr;
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

    <div class="container mt-5 d-none" id="alert-container"></div>

    <div class="container mt-5">
        <div class="row mt-5">
            <div class="col-md-12">
                <h3>Assigned Pull Requests <span class="badge text-bg-warning rounded-pill"
                        id="openPullRequestsCount"><?php echo count($data["openPullRequests"]); ?></span></h3>
                
                <?php if (empty($groupedPullRequests)): ?>
                    <p class="text-muted">No pull requests found.</p>
                <?php else: ?>
                    <?php foreach ($groupedPullRequests as $account => $pullRequests): ?>
                        <h4><?php echo htmlspecialchars($account, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <ul class="list-group mb-4">
                            <?php foreach ($pullRequests as $pr): ?>
                                <li class="list-group-item">
                                    <strong><a href='<?php echo htmlspecialchars($pr['url'], ENT_QUOTES, 'UTF-8'); ?>'
                                            rel="noopener noreferrer"
                                            target='_blank'><?php echo htmlspecialchars($pr['title']); ?></a></strong>
                                    <br />
                                    <span class="text-muted">
                                        <a href='https://github.com/<?php echo htmlspecialchars($pr['full_name'], ENT_QUOTES, 'UTF-8'); ?>'
                                            rel="noopener noreferrer"
                                            target='_blank'><?php echo htmlspecialchars($pr['repository']); ?></a>
                                    </span> -
                                    <span class="text-muted">(🕐 <?php echo $pr['created_at']; ?>)</span>
                                    <?php if (isset($pr["state"])): ?>
                                        <?php if ($pr["state"] === "success"): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Success
                                            </span>
                                        <?php elseif ($pr["state"] === "failure"): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle"></i> Failure
                                            </span>
                                        <?php elseif ($pr["state"] === "pending"): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-hourglass-half"></i> Pending
                                            </span>
                                        <?php elseif ($pr["state"] === "error"): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-exclamation-triangle"></i> Error
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-question-circle"></i> Empty
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>
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

        function populateIssuesGroupedByAccount(items) {
            const groupedData = {};
            items.forEach(item => {
                const account = item.account || 'Unknown';
                if (!groupedData[account]) {
                    groupedData[account] = [];
                }
                groupedData[account].push(item);
            });

            const groupedContainer = document.getElementById("groupedPullRequests");
            groupedContainer.innerHTML = '';

            if (items.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'list-group-item list-group-item-warning';
                emptyDiv.innerHTML = `<strong>No pull requests found!</strong>`;
                groupedContainer.appendChild(emptyDiv);
                return;
            }

            for (const [account, pullRequests] of Object.entries(groupedData)) {
                const accountDiv = document.createElement('div');
                accountDiv.className = 'mb-4';

                const accountHeader = document.createElement('h5');
                accountHeader.textContent = `${account} (${pullRequests.length})`;
                accountHeader.className = 'text-primary mb-2';
                accountDiv.appendChild(accountHeader);

                const pullRequestList = document.createElement('ul');
                pullRequestList.className = 'list-group';
                pullRequests.forEach(pr => {
                    const state = getStateBadge(pr.state);
                    const itemLi = document.createElement('li');
                    itemLi.className = 'list-group-item';
                    let content = '';
                    content += `<strong><a href='${pr.url}' target='_blank'>${pr.title}</a></strong><br />`;
                    content += `<span class="text-muted"><a href='https://github.com/${pr.full_name}' target='_blank'>${pr.repository}</a></span> - `;
                    content += `<span class="text-muted">(🕐 ${pr.created_at})</span> ${state}`;
                    itemLi.innerHTML = content;
                    pullRequestList.appendChild(itemLi);
                });

                accountDiv.appendChild(pullRequestList);
                groupedContainer.appendChild(accountDiv);
            }
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
                    populateIssuesGroupedByAccount(data.openPullRequests);
                    setTimeout(loadData, 1000 * 60);
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorAlert('Failed to complete the request. Please try again later.');
                });
        }

        window.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>

</html>
