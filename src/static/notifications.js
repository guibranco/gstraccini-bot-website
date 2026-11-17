        function fetchNotifications() {
            fetch('/api/v1/notifications')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching notifications:', data.error);
                        const notificationsMenu = document.getElementById('notificationsMenu');
                        notificationsMenu.innerHTML = ''; // Clear any previous content
                        const errorItem = document.createElement('li');
                        errorItem.className = 'dropdown-item text-bg-danger';
                        errorItem.textContent = data.error;
                        notificationsMenu.appendChild(errorItem);
                    } else {
                        processNotifications(data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('notificationsMenu').innerHTML = '<li class="dropdown-item text-bg-danger">Failed to load notifications.</li>';
                })
                .finally(() => { createViewAll(); });
        }

        function processNotifications(notifications) {
            const notificationsMenu = document.getElementById('notificationsMenu');
            const notificationCount = document.getElementById('notification-count');

            notificationsMenu.innerHTML = '';

            if (!Array.isArray(notifications)) {
                console.error('Unexpected notifications payload:', notifications);
                notificationsMenu.innerHTML = '<li class="dropdown-item text-bg-danger">Failed to load notifications.</li>';
                notificationCount.textContent = '';
                return;
            }

            if (notifications.length === 0) {
                notificationsMenu.innerHTML = '<li class="dropdown-item">No new notifications.</li>';
                notificationCount.textContent = '';
                return;
            }

            // Items come from the GStraccini API (via /api/v1/notifications), not straight from GitHub:
            // { title, url, repositoryOwner, repositoryName }
            notifications.forEach(notification => {
                if (!notification || typeof notification !== 'object') {
                    console.warn('Skipping malformed notification:', notification);
                    return;
                }

                const title = notification.title ?? '(untitled)';
                const repo = [notification.repositoryOwner, notification.repositoryName].filter(Boolean).join('/');

                const listItem = document.createElement('li');
                listItem.classList.add('dropdown-item');

                const safeUrl = notification.url ? encodeURI(notification.url) : '#';
                const safeTitle = escapeHtml(title);
                const safeRepo = escapeHtml(repo);
                const label = safeRepo ? `${safeTitle} - ${safeRepo}` : safeTitle;
                listItem.innerHTML = `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer" aria-label="Notification: ${safeTitle}">${label}</a>`;
                notificationsMenu.appendChild(listItem);
            });
            notificationCount.textContent = notifications.length > 9 ? '9+' : notifications.length;
        }

        function createViewAll() {
            const notificationsMenu = document.getElementById('notificationsMenu');

            const listDivider = document.createElement('li');
            listDivider.innerHTML = '<hr class="dropdown-divider">';
            notificationsMenu.appendChild(listDivider);

            const listViewAll = document.createElement('li');
            listViewAll.classList.add('dropdown-item');
            listViewAll.innerHTML = '<a href="notifications.php">View all notifications</a>';
            notificationsMenu.appendChild(listViewAll);
        }

document.addEventListener('DOMContentLoaded', function () {

        const notificationsDropdown = document.getElementById('notificationsDropdown');
        if (!notificationsDropdown) {
            console.error('Notifications dropdown element not found');
            return;
        }

        notificationsDropdown.addEventListener('click', function () {
            fetchNotifications();
        });
        fetchNotifications();
});
