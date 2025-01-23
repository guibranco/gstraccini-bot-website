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
foreach ($data["openIssues"] as $pr) {
    $owner = $pr['owner'] ?? 'Unknown';
    if (!isset($groupedIssues[$owner])) {
        $groupedIssues[$owner] = [];
    }
    $groupedIssues[$owner][] = $pr;
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
    <title>GStraccini-bot | <?php echo $title; ?></title>
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
                            <strong><a href='<?php echo htmlspecialchars($issue['url'], ENT_QUOTES, 'UTF-8'); ?>'
                                    rel="noopener noreferrer"
                                    target='_blank'><?php echo htmlspecialchars($issue['title']); ?></a></strong>
                            <br />
                            <span class="text-muted">
                                <a href='https://github.com/<?php echo htmlspecialchars($issue['full_name'], ENT_QUOTES, 'UTF-8'); ?>'
                                    rel="noopener noreferrer"
                                    target='_blank'><?php echo htmlspecialchars($issue['repository']); ?></a>
                            </span>
                            <br />
                            <span class="text-muted">🕐 <?php echo htmlspecialchars($issue['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="mt-2">
                                <?php if (isset($issue['labels']) && is_array($issue['labels'])): ?>
                                    <?php foreach ($issue['labels'] as $label): ?>
                                        <span class="badge label-badge"
                                            style="background-color: #<?php echo htmlspecialchars($label['color'], ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo luminance($label['color']); ?>;"
                                            title="<?php echo htmlspecialchars($label['description'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($label['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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
                const itemLi = document.createElement('li');
                itemLi.className = 'list-group-item';
                let content = '';
                content += `<strong><a href='${item.url}' target='_blank'>${item.title}</a></strong><br />`;
                const sanitize = (str) => str.replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[char]);
                content += `<span class="text-muted"><a href='https://github.com/${sanitize(item.full_name)}' target='_blank'>${sanitize(item.repository)}</a></span> `;
                content += `<span class="text-muted">🕐 ${sanitize(item.created_at)}</span>`;                
                itemLi.innerHTML = content;

                const containerLabels = document.createElement("div");
                containerLabels.className = "mt-2";
                itemLi.appendChild(containerLabels);
                
                if (item.labels && Array.isArray(item.labels)) {
                  item.labels.forEach((label) => {
                    if (!label.color || !label.name) return;
                
                    const color = label.color;
                    const r = parseInt(color.substr(0, 2), 16);
                    const g = parseInt(color.substr(2, 2), 16);
                    const b = parseInt(color.substr(4, 2), 16);
                    const yiq = (r * 299 + g * 587 + b * 114) / 1000;
                    const textColor = yiq >= 128 ? "#000" : "#fff";
                
                    const labelSpan = document.createElement("span");
                    labelSpan.classList.add("badge", "label-badge");
                    labelSpan.style.backgroundColor = `#${escapeHtml(label.color)}`;
                    labelSpan.style.color = textColor;
                    labelSpan.setAttribute("title", escapeHtml(label.description || ""));
                    labelSpan.textContent = escapeHtml(label.name);
                    containerLabels.appendChild(labelSpan);
                  });
                }
                
                list.appendChild(itemLi);
            });
        }

        function escapeHtml(unsafe) {
        	return unsafe
        	.replace(/&/g, "&amp;")
        	.replace(/</g, "&lt;")
        	.replace(/>/g, "&gt;")
        	.replace(/"/g, "&quot;")
        	.replace(/'/g, "&#039;");
        }

        function loadData() {
            fetch('api.php')
                .then(response => response.json())
                .then(data => {
                    populateIssues(data.openIssues, "openIssues");
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
