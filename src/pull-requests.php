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
    $owner = $pr['owner'] ?? 'Unknown';
    if (!isset($groupedPullRequests[$owner])) {
        $groupedPullRequests[$owner] = [];
    }
    $groupedPullRequests[$owner][] = $pr;
}

$stateOrder = ['success', 'failure', 'pending', 'error', 'skipped', ''];
foreach ($groupedPullRequests as &$prs) {
    usort($prs, function ($a, $b) use ($stateOrder) {
        return array_search($a['state'] ?? '', $stateOrder) - array_search($b['state'] ?? '', $stateOrder);
    });
}
unset($prs);

function luminance($color)
{
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 128) ? '#000' : '#fff';
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
    .label-badge {
        padding: 0.3em 0.5em;
        font-size: 0.85em;
        border-radius: 0.25rem;
        margin-right: 0.3em;
        white-space: nowrap;
    }
    .label-badge:hover {
        opacity: 0.9;
    }
    </style>
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5 d-none" id="alert-container"></div>

    <div class="container mt-5">
        <div class="row mt-5">
            <div class="col-md-12">
                <h3>Assigned Pull Requests <span class="badge text-bg-warning rounded-pill" id="openPullRequestsCount"><?php echo count($data["openPullRequests"]); ?></span></h3>
                <div id="groupedPullRequests">
                    <?php if (empty($groupedPullRequests)): ?>
                        <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading data...</p>
                    <?php else: ?>                    
                        <?php foreach ($groupedPullRequests as $owner => $pullRequests): ?>
                            <?php 
                                $groupId = "group-{$owner}";
                            ?>
                            <div class="mb-4">
                                <h5 class="text-primary mb-2">
                                    <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $groupId;?>" aria-expanded="true" aria-controls="<?php echo $groupId;?>"><?php echo htmlspecialchars($owner, ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($pullRequests); ?>) <i class="fas fa-chevron-down"></i></button>
                                </h5>
                                <ul class="list-group collapse show" id="<?php echo $groupId; ?>">
                                    <?php foreach ($pullRequests as $pr): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><a href='<?php echo filter_var($pr['url'], FILTER_SANITIZE_URL); ?>'
                                                            rel="noopener noreferrer"
                                                            target='_blank'><?php echo htmlspecialchars($pr['title'], ENT_QUOTES, 'UTF-8'); ?></a></strong>
                                                    <br />
                                                    <span class="text-muted">
                                                        <a href='https://github.com/<?php echo filter_var($pr['full_name'], FILTER_SANITIZE_URL); ?>'
                                                            rel="noopener noreferrer"
                                                            target='_blank'><?php echo htmlspecialchars($pr['repository'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    </span>
                                                    <span class="text-muted"> 🕐 <?php echo htmlspecialchars($pr['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <div class="mt-2">
                                                        <?php if (isset($pr['labels']) && is_array($pr['labels'])): ?>
                                                            <?php foreach ($pr['labels'] as $label): ?>
                                                                <span class="badge label-badge" 
                                                                      style="background-color: #<?php echo htmlspecialchars($label['color'], ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo luminance($label['color']); ?>;" 
                                                                      title="<?php echo htmlspecialchars($label['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?php echo htmlspecialchars($label['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div>
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
                                                        <?php elseif ($pr["state"] === "skipped"): ?>
                                                            <span class="badge bg-dark">
                                                                <i class="fas fa-arrow-circle-right"></i> Skipped
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                <i class="fas fa-question-circle"></i> Empty
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "includes/footer.php"; ?>
    <script>
        function populateIssuesGroupedByOwner(items) {
            const groupedData = {};
            const stateOrder = ['success', 'failure', 'pending', 'error', 'skipped', ''];
            
            items.forEach(item => {
                const owner = item?.owner || 'Unknown';
                if (!groupedData[owner]) {
                    groupedData[owner] = [];
                }
                groupedData[owner].push(item);
            });
            
            Object.keys(groupedData).forEach(owner => {
                groupedData[owner].sort((a, b) => {
                    return stateOrder.indexOf(a.state || '') - stateOrder.indexOf(b.state || '');
                });
            });

            const counterContainer = document.getElementById("openPullRequestsCount");
            if (!counterContainer) {
                console.error('Counter container element not found');
                return;
            }
            
            const groupedContainer = document.getElementById("groupedPullRequests");
            if (!groupedContainer) {
                console.error('Container element not found');
                return;
            }
            groupedContainer.innerHTML = '';

            if (items.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'list-group-item list-group-item-warning';
                emptyDiv.innerHTML = `<strong>No pull requests found!</strong>`;
                groupedContainer.appendChild(emptyDiv);
                return;
            }

            counterContainer.textContent = items.length;

            for (const [owner, pullRequests] of Object.entries(groupedData)) {
                const groupId = `group-${owner.replace(/\s+/g, '-')}`;
                const ownerDiv = document.createElement('div');
                ownerDiv.className = 'mb-4';

                const ownerHeader = document.createElement('h5');
                ownerHeader.className = 'text-primary mb-2';
                ownerDiv.appendChild(ownerHeader);

                const ownerButton = document.createElement('button');
                ownerButton.className = 'btn btn-link text-decoration-none';
                ownerButton.type = 'button';
                ownerButton.setAttribute('data-bs-toggle', 'collapse');
                ownerButton.setAttribute('data-bs-target', `#${groupId}`);
                ownerButton.setAttribute('aria-expanded', 'true');
                ownerButton.setAttribute('aria-controls', groupId);
                ownerButton.textContent = `${escapeHtml(owner)} (${pullRequests.length}) `;
                ownerHeader.appendChild(ownerButton);

                const ownerChevron = document.createElement("i");
                ownerChevron.className = 'fas fa-chevron-down';
                ownerButton.appendChild(ownerChevron);
                
                const pullRequestList = document.createElement('ul');
                pullRequestList.className = 'list-group collapse show';
                pullRequestList.id = groupId;
                
                pullRequests.forEach(pr => {
                    const state = getStateBadge(pr.state);
                    const itemLi = document.createElement('li');
                    itemLi.className = 'list-group-item';
                    pullRequestList.appendChild(itemLi);

                    const container = document.createElement("div");
                    container.className = "d-flex justify-content-between align-items-start";
                    itemLi.appendChild(container);

                    const leftSection = document.createElement("div");
                    container.appendChild(leftSection);
                    
                    const titleLink = document.createElement('a');
                    titleLink.href = escapeHtml(pr.url);
                    titleLink.target = '_blank';
                    titleLink.textContent = pr.title;
                    
                    const strong = document.createElement('strong');
                    strong.appendChild(titleLink);                    
                    leftSection.appendChild(strong);
                    
                    leftSection.appendChild(document.createElement('br'));
                    
                    const repoSpan = document.createElement('span');
                    repoSpan.className = 'text-muted';
                    
                    const repoLink = document.createElement('a');
                    repoLink.href = `https://github.com/${escapeHtml(pr.full_name)}`;
                    repoLink.target = '_blank';
                    repoLink.textContent = pr.repository;
                    repoSpan.appendChild(repoLink);
                    leftSection.appendChild(repoSpan);
                                       
                    const timeSpan = document.createElement('span');
                    timeSpan.className = 'text-muted';
                    timeSpan.textContent = ` 🕐 ${pr.created_at}`;
                    leftSection.appendChild(timeSpan);

                    const containerLabels = document.createElement('div');
                    containerLabels.className = 'mt-2';
                    leftSection.appendChild(containerLabels);

                    if (pr.labels && Array.isArray(pr.labels)) {
                        pr.labels.forEach(label => {
                            if (!label.color || !label.name) return;
                            
                            const color = label.color;
                            const r = parseInt(color.substr(0, 2), 16);
                            const g = parseInt(color.substr(2, 2), 16);
                            const b = parseInt(color.substr(4, 2), 16);
                            const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
                            const textColor = (yiq >= 128) ? '#000' : '#fff';
                            
                            const labelSpan = document.createElement("span");
                            labelSpan.classList.add("badge", "label-badge");
                            labelSpan.style.backgroundColor = `#${escapeHtml(label.color)}`;
                            labelSpan.style.color = textColor;
                            labelSpan.setAttribute("title", escapeHtml(label.description || ''));
                            labelSpan.textContent = escapeHtml(label.name);
                            containerLabels.appendChild(labelSpan);
                        });
                    }

                    const rightSection = document.createElement("div");
                    container.appendChild(rightSection);
                    
                    const stateSpan = document.createElement('span');
                    stateSpan.innerHTML = state;
                    rightSection.appendChild(stateSpan);
                });

                ownerDiv.appendChild(pullRequestList);
                groupedContainer.appendChild(ownerDiv);
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
                case 'skipped':
                    return '<span class="badge bg-dark"><i class="fas fa-arrow-circle-right"></i> Skipped</span>';
                default:
                    return '<span class="badge bg-secondary"><i class="fas fa-question-circle"></i> Empty</span>';
            }
        }

        function loadData() {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    populateIssuesGroupedByOwner(data.openPullRequests);
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
