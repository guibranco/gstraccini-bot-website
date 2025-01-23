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
        <div class="list-group mb-5" id="pending-actions-list">
            <a href="#" class="list-group-item list-group-item-action">
                <i class="fas fa-exclamation-circle"></i> Complete Profile Setup
            </a>
            <a href="#" class="list-group-item list-group-item-action">
                <i class="fas fa-tasks"></i> Approve New User Requests
            </a>
        </div>

        <h2>Notifications</h2>

        <div class="mb-3">
            <button class="btn btn-secondary" id="unread-btn">
                <i class="fas fa-envelope"></i> Unread
            </button>
            <button class="btn btn-success" id="read-btn">
                <i class="fas fa-envelope-open"></i> Read
            </button>
            <button class="btn btn-primary" id="view-all-btn">
                <i class="fas fa-list"></i> View All
            </button>
        </div>

        <div class="list-group" id="notifications-list">
            <a href="#" class="list-group-item list-group-item-action">
                <i class="fas fa-envelope"></i> You have a new message from John.
            </a>
            <a href="#" class="list-group-item list-group-item-action">
                <i class="fas fa-envelope-open"></i> System maintenance scheduled for tonight.
            </a>
        </div>

    </div>


    <?php require_once "includes/footer.php"; ?>
    <script>
        function fetchNotifications(filter) {
            console.log('Fetching notifications:', filter);

            let notifications = [];
            if (filter === 'all') {
                notifications = [
                    { id: 1, type: 'unread', text: 'You have a new message from John.' },
                    { id: 2, type: 'read', text: 'System maintenance scheduled for tonight.' },
                    { id: 3, type: 'unread', text: 'New updates are available.' },
                ];
            } else if (filter === 'unread') {
                notifications = [
                    { id: 1, type: 'unread', text: 'You have a new message from John.' },
                    { id: 3, type: 'unread', text: 'New updates are available.' },
                ];
            } else if (filter === 'read') {
                notifications = [
                    { id: 2, type: 'read', text: 'System maintenance scheduled for tonight.' },
                ];
            }

            updateNotificationList(notifications);

            // const params = new URLSearchParams({ filter, page: 1, limit: 10 });
            // fetch(`/api/notifications?${params}`)
            //     .then(response => {
            //         if (!response.ok) throw new Error('Failed to fetch notifications');
            //         return response.json();
            //     })
            //     .then(data => {
            //         updateNotificationList(data.notifications);
            //         updatePagination(data.pagination);
            //     })
            //     .catch(error => {
            //         console.error('Error:', error);
            //         showErrorMessage('Failed to load notifications');
            //     });
        }

        function updateNotificationList(notifications) {
            const $notificationsList = $('#notifications-list');
            $notificationsList.empty();

            notifications.forEach(notification => {
                const icon = notification.type === 'unread' ? 'fa-envelope' : 'fa-envelope-open';
                const notificationItem = `
                <a href="#" class="list-group-item list-group-item-action">
                    <i class="fas ${icon}"></i> ${notification.text}
                </a>
            `;
                $notificationsList.append(notificationItem);
            });
        }

        $('#view-all-btn').on('click', function () {
            fetchNotifications('all');
        });

        $('#unread-btn').on('click', function () {
            fetchNotifications('unread');
        });

        $('#read-btn').on('click', function () {
            fetchNotifications('read');
        });

        $(document).ready(function () {
            fetchNotifications('all');
        });
    </script>

</body>

</html>
