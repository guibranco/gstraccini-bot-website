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

            if (notifications.length === 0) {
                notificationsMenu.innerHTML = '<li class="dropdown-item">No new notifications.</li>';
                notificationCount.textContent = '';
                return;
            }

            notifications.forEach(notification => {
                const {title} = notification.subject;
                const repo = notification.repository.full_name;
                const url = notification.subject.url.replace('api.github.com/repos', 'github.com').replace('/issues', '/issues/');

                const listItem = document.createElement('li');
                listItem.classList.add('dropdown-item');

                const safeUrl = encodeURI(url);
                const safeTitle = escapeHtml(title);
                const safeRepo = escapeHtml(repo);
                listItem.innerHTML = `<a href="${safeUrl}" target="_blank" aria-label="Notification: ${safeTitle}">${safeTitle} - ${safeRepo}</a>`;
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
