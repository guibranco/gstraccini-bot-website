<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];
$data = $_SESSION["pull-requests"]["data"] ?? array("openPullRequests" => []);

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

        if (isset($a['is_valid_pr']) && isset($b['is_valid_pr'])) {
            if ($a['is_valid_pr'] && !$b['is_valid_pr']) return -1;
            if (!$a['is_valid_pr'] && $b['is_valid_pr']) return 1;
        } else if (isset($a['is_valid_pr']) && $a['is_valid_pr']) {
            return -1;
        } else if (isset($b['is_valid_pr']) && $b['is_valid_pr']) {
            return 1;
        }
        
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

function getMergeableBadge($mergeable, $mergeable_state) {
    if ($mergeable === null) {
        return '<span class="badge bg-secondary"><i class="fas fa-question-circle"></i> Unknown</span>';
    } else if ($mergeable === true) {
        if ($mergeable_state === 'clean') {
            return '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Mergeable</span>';
        } else if ($mergeable_state === 'unstable') {
            return '<span class="badge bg-info"><i class="fas fa-info-circle"></i> Unstable</span>';
        } else if ($mergeable_state === 'blocked') {
            return '<span class="badge bg-warning text-dark"><i class="fas fa-lock"></i> Blocked</span>';
        } else if ($mergeable_state === 'behind') {
            return '<span class="badge bg-secondary"><i class="fas fa-arrow-circle-down"></i> Behind</span>';
        } else if ($mergeable_state === 'dirty') {
            return '<span class="badge bg-danger"><i class="fas fa-exclamation-circle"></i> Dirty</span>';
        } else {
            return '<span class="badge bg-info"><i class="fas fa-info-circle"></i> ' . htmlspecialchars($mergeable_state, ENT_QUOTES, 'UTF-8') . '</span>';
        }
    } else {
        return '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Not Mergeable</span>';
    }
}

function isValidPR($pr) {
    if (isset($pr['is_valid_pr'])) {
        return $pr['is_valid_pr'];
    }
    
    return ($pr['state'] === 'success' && 
            isset($pr['mergeable']) && $pr['mergeable'] === true && 
            isset($pr['mergeable_state']) && in_array($pr['mergeable_state'], ['clean', 'unstable']));
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
    .badge-container {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
    }
    .valid-pr {
        border-left: 4px solid #198754;
    }
    </style>
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5 d-none" id="alert-container"></div>

    <div class="container mt-5">
        <div class="row mt-5">
            <div class="col-md-12">
                <h3>Assigned Pull Requests 
                    <span class="badge text-bg-warning rounded-pill" id="openPullRequestsCount"><?php echo count($data["openPullRequests"]); ?></span>
                    <span class="badge text-bg-success rounded-pill" id="validPullRequestsCount">
                        <?php 
                            $validPRs = array_filter($data["openPullRequests"], function($pr) {
                                return isValidPR($pr);
                            });
                            echo count($validPRs);
                        ?>
                    </span>
                </h3>
                <div id="groupedPullRequests">
                    <?php if (empty($groupedPullRequests)): ?>
                        <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading data...</p>
                    <?php else: ?>                    
                        <?php foreach ($groupedPullRequests as $owner => $pullRequests): ?>
                            <?php 
                                $groupId = "group-" . preg_replace('/\s+/', '-', $owner);
                            ?>
                            <div class="mb-4">
                                <h5 class="text-primary mb-2">
                                    <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $groupId;?>" aria-expanded="true" aria-controls="<?php echo $groupId;?>"><?php echo htmlspecialchars($owner, ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($pullRequests); ?>) <i class="fas fa-chevron-down"></i></button>
                                </h5>
                                <ul class="list-group collapse show" id="<?php echo $groupId; ?>">
                                    <?php foreach ($pullRequests as $pr): ?>
                                        <li class="list-group-item <?php echo isValidPR($pr) ? 'valid-pr' : ''; ?>">
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
                                                <div class="badge-container">
                                                    <?php if (isset($pr["state"])): ?>
                                                        <?php if ($pr["state"] === "success"): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check-circle"></i> CI Success
                                                            </span>
                                                        <?php elseif ($pr["state"] === "failure"): ?>
                                                            <span class="badge bg-danger">
                                                                <i class="fas fa-times-circle"></i> CI Failure
                                                            </span>
                                                        <?php elseif ($pr["state"] === "pending"): ?>
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="fas fa-hourglass-half"></i> CI Pending
                                                            </span>
                                                        <?php elseif ($pr["state"] === "error"): ?>
                                                            <span class="badge bg-danger">
                                                                <i class="fas fa-exclamation-triangle"></i> CI Error
                                                            </span>
                                                        <?php elseif ($pr["state"] === "skipped"): ?>
                                                            <span class="badge bg-dark">
                                                                <i class="fas fa-arrow-circle-right"></i> CI Skipped
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                <i class="fas fa-question-circle"></i> CI Unknown
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (isset($pr["mergeable"]) || isset($pr["mergeable_state"])): ?>
                                                        <?php echo getMergeableBadge($pr["mergeable"] ?? null, $pr["mergeable_state"] ?? null); ?>
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
    <script src="static/pull-requests.js"></script>
</body>

</html>
