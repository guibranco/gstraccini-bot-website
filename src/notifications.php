<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];
$title = "Notifications";
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
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5">
        <h2>Pending Actions</h2>
        <div class="list-group mb-5" id="pending-actions-list"></div>

        <h2>Notifications</h2>

        <div class="mb-3">
            <button class="btn btn-primary" id="refresh-notifications-btn">
                <i class="fas fa-rotate"></i> Refresh
            </button>
        </div>

        <div class="list-group" id="notifications-list"></div>

    </div>


    <?php require_once "includes/footer.php"; ?>
    <script>
        function fetchNotifications() {
            fetch('/api/v1/notifications')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showErrorMessage('#notifications-list', data.error);
                        return;
                    }
                    renderListItems('#notifications-list', data, 'fa-envelope', 'No unread notifications.');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorMessage('#notifications-list', 'Failed to load notifications.');
                });
        }

        function fetchPendingActions() {
            fetch('/api/v1/pending-actions')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showErrorMessage('#pending-actions-list', data.error);
                        return;
                    }
                    renderListItems('#pending-actions-list', data, 'fa-exclamation-circle', 'No pending actions.');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorMessage('#pending-actions-list', 'Failed to load pending actions.');
                });
        }

        function showErrorMessage(listSelector, message) {
            $(listSelector).html(`<div class="list-group-item text-bg-danger">${escapeHtml(message)}</div>`);
        }

        function renderListItems(listSelector, items, icon, emptyText) {
            const $list = $(listSelector);
            $list.empty();

            if (!items || items.length === 0) {
                $list.html(`<div class="list-group-item">${escapeHtml(emptyText)}</div>`);
                return;
            }

            items.forEach(item => {
                const repo = `${item.repositoryOwner}/${item.repositoryName}`;
                const url = item.url ? encodeURI(item.url) : '#';
                $list.append(`
                <a href="${url}" target="_blank" class="list-group-item list-group-item-action">
                    <i class="fas ${icon}"></i> ${escapeHtml(item.title)} - ${escapeHtml(repo)}
                </a>
            `);
            });
        }

        $('#refresh-notifications-btn').on('click', function () {
            fetchNotifications();
        });

        $(document).ready(function () {
            fetchNotifications();
            fetchPendingActions();
        });
    </script>

</body>

</html>
