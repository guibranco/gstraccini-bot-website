<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];
$data = $_SESSION["data"] ?? array("openIssues" => []);

$title = "Issues";

$name = $user["login"];
if (isset($user["first_name"])) {
    $name = $user["first_name"];
} else if (isset($user["name"])) {
    $name = $user["name"];
}

$groupedIssues = [];
foreach ($data["openIssues"] as $issue) {
    $owner = $issue['owner'] ?? 'Unknown';
    if (!isset($groupedIssues[$owner])) {
        $groupedIssues[$owner] = [];
    }
    $groupedIssues[$owner][] = $issue;
}

function luminance($color)
{
    if (!preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
        throw new InvalidArgumentException('Invalid color format. Expected 6-digit hex color.');
    }
    $red = hexdec(substr($color, 0, 2));
    $green = hexdec(substr($color, 2, 2));
    $blue = hexdec(substr($color, 4, 2));
    $yiq = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
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
                <h3>Assigned Issues <span class="badge text-bg-warning rounded-pill" id="openIssuesCount"><?php echo count($data["openIssues"]); ?></span></h3>
                <div id="groupedIssues">
                    <?php if (empty($groupedIssues)): ?>
                        <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading data...</p>
                    <?php else: ?>       
                
                <ul class="list-group" id="openIssues">
                    <?php if (count($data["openIssues"]) === 0): ?>
                        <li class="list-group-item">
                            <i class="fas fa-spinner fa-spin"></i> Loading data...
                        </li>
                    <?php endif; ?>
                        <?php foreach ($groupedIssues as $owner => $issues): ?>
                            <?php 
                                $sanitizedOwner = preg_replace('/[^a-z0-9]+/', '-', strtolower($owner));
                                $sanitizedOwner = trim($sanitizedOwner, '-');
                                $groupId = "group-{$sanitizedOwner}";
                            ?>
                            <div class="mb-4">
                                <h5 class="text-primary mb-2">
                                    <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $groupId;?>" aria-expanded="true" aria-controls="<?php echo $groupId;?>"><?php echo htmlspecialchars($owner, ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($issues); ?>) <i class="fas fa-chevron-down"></i></button>
                                </h5>
                                <ul class="list-group collapse show" id="<?php echo $groupId; ?>">
                                    <?php foreach ($issues as $issue): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><a href='<?php echo filter_var($issue['url'], FILTER_SANITIZE_URL); ?>'
                                                            rel="noopener noreferrer"
                                                            target='_blank'><?php echo htmlspecialchars($issue['title'], ENT_QUOTES, 'UTF-8'); ?></a></strong>
                                                    <br />
                                                    <span class="text-muted">
                                                        <a href='https://github.com/<?php echo filter_var($issue['full_name'], FILTER_SANITIZE_URL); ?>'
                                                            rel="noopener noreferrer"
                                                            target='_blank'><?php echo htmlspecialchars($issue['repository'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    </span>
                                                    <span class="text-muted"> 🕐 <?php echo htmlspecialchars($issue['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <div class="mt-2">
                                                        <?php if (isset($issue['labels']) && is_array($issue['labels'])): ?>
                                                            <?php foreach ($issue['labels'] as $label): ?>
                                                                <span class="badge label-badge" 
                                                                      style="background-color: #<?php echo htmlspecialchars($label['color'], ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo luminance($label['color']); ?>;" 
                                                                      title="<?php echo htmlspecialchars($label['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?php echo htmlspecialchars($label['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
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
            
            items.forEach(item => {
                const owner = item?.owner || 'Unknown';
                if (!groupedData[owner]) {
                    groupedData[owner] = [];
                }
                groupedData[owner].push(item);
            });

            const counterContainer = document.getElementById("openIssuesCount");
            if (!counterContainer) {
                console.error('Counter container element not found');
                return;
            }
            
            const groupedContainer = document.getElementById("groupedIssues");
            if (!groupedContainer) {
                console.error('Container element not found');
                return;
            }
            groupedContainer.innerHTML = '';

            if (items.length === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'list-group-item list-group-item-warning';
                emptyDiv.innerHTML = `<strong>No issues found!</strong>`;
                groupedContainer.appendChild(emptyDiv);
                return;
            }

            counterContainer.textContent = items.length;

            for (const [owner, issues] of Object.entries(groupedData)) {
                const groupId = `group-${owner.replace(/[^a-zA-Z0-9]+/g, '-')}`;
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
                ownerButton.textContent = `${escapeHtml(owner)} (${issues.length}) `;
                ownerHeader.appendChild(ownerButton);

                const ownerChevron = document.createElement("i");
                ownerChevron.className = 'fas fa-chevron-down';
                ownerButton.appendChild(ownerChevron);
                
                const issueList = document.createElement('ul');
                issueList.className = 'list-group collapse show';
                issueList.id = groupId;
                
                issues.forEach(issue => {
                    const itemLi = document.createElement('li');
                    itemLi.className = 'list-group-item';
                    issueList.appendChild(itemLi);

                    const container = document.createElement("div");
                    container.className = "d-flex justify-content-between align-items-start";
                    itemLi.appendChild(container);

                    const leftSection = document.createElement("div");
                    container.appendChild(leftSection);
                    
                    const titleLink = document.createElement('a');
                    titleLink.href = escapeHtml(issue.url);
                    titleLink.target = '_blank';
                    titleLink.textContent = issue.title;
                    
                    const strong = document.createElement('strong');
                    strong.appendChild(titleLink);                    
                    leftSection.appendChild(strong);
                    
                    leftSection.appendChild(document.createElement('br'));
                    
                    const repoSpan = document.createElement('span');
                    repoSpan.className = 'text-muted';
                    
                    const repoLink = document.createElement('a');
                    repoLink.href = `https://github.com/${escapeHtml(issue.full_name)}`;
                    repoLink.target = '_blank';
                    repoLink.textContent = issue.repository;
                    repoSpan.appendChild(repoLink);
                    leftSection.appendChild(repoSpan);
                                       
                    const timeSpan = document.createElement('span');
                    timeSpan.className = 'text-muted';
                    timeSpan.textContent = ` 🕐 ${escapeHtml(issue.created_at)}`;
                    leftSection.appendChild(timeSpan);

                    const containerLabels = document.createElement('div');
                    containerLabels.className = 'mt-2';
                    leftSection.appendChild(containerLabels);

                    if (issue.labels && Array.isArray(issue.labels)) {
                        issue.labels.forEach(label => {
                            if (!label.color || !label.name) return;
                            
                            if (!/^[0-9A-Fa-f]{6}$/.test(label.color)) return;
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
                });

                ownerDiv.appendChild(issueList);
                groupedContainer.appendChild(ownerDiv);
            }
        } 

        function loadData() {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    populateIssuesGroupedByOwner(data.openIssues);
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
