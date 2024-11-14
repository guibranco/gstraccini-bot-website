<footer>
  <p>© 2024 GStraccini-bot. All rights reserved.</p>
  <nav>
    <ul>
      <li><a href="privacy-policy.php">Privacy Policy</a></li>
      <li><a href="terms-of-service.php">Terms of Service</a></li>
      <li><a href="service-status.php">Service Status</a></li>
      <li><a href="https://docs.bot.straccini.com" target="_blank">Documentation</a></li>
      <li><a href="https://github.com/marketplace/gstraccini-bot" target="_blank">GitHub Marketplace</a></li>
      <li><a href="https://github.com/guibranco/gstraccini-bot-service" target="_blank">GitHub Repository</a></li>
    </ul>
  </nav>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.5/dist/umd/popper.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function fetchNotifications() {
            fetch('notifications_inline.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching notifications:', data.error);
                        document.getElementById('notificationsMenu').innerHTML = `<li class="dropdown-item text-bg-danger">${data.error}</li>`;
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
                const title = notification.subject.title;
                const repo = notification.repository.full_name;
                const url = notification.subject.url.replace('api.github.com/repos', 'github.com').replace('/issues', '/issues/');

                const listItem = document.createElement('li');
                listItem.classList.add('dropdown-item');

                listItem.innerHTML = `<a href="${url}" target="_blank">${title} - ${repo}</a>`;
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

        const notificationsDropdown = document.getElementById('notificationsDropdown');
        notificationsDropdown.addEventListener('click', function () {
            fetchNotifications();
        });
        fetchNotifications();
    });
</script>
