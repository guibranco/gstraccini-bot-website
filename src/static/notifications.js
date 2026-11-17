function createMenuItem(text, extraClass) {
  const item = document.createElement("li");
  item.className = extraClass ? `dropdown-item ${extraClass}` : "dropdown-item";
  item.textContent = text;
  return item;
}

function createLinkItem(href, text, ariaLabel) {
  const item = document.createElement("li");
  item.classList.add("dropdown-item");

  const link = document.createElement("a");
  link.href = href;
  link.textContent = text;
  if (ariaLabel) {
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.setAttribute("aria-label", ariaLabel);
  }

  item.appendChild(link);
  return item;
}

function showNotificationsError(message) {
  const notificationsMenu = document.getElementById("notificationsMenu");
  notificationsMenu.replaceChildren(createMenuItem(message, "text-bg-danger"));
}

function fetchNotifications() {
  fetch("/api/v1/notifications")
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        console.error("Error fetching notifications:", data.error);
        showNotificationsError(data.error);
      } else {
        processNotifications(data);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotificationsError("Failed to load notifications.");
    })
    .finally(() => {
      createViewAll();
    });
}

function processNotifications(notifications) {
  const notificationsMenu = document.getElementById("notificationsMenu");
  const notificationCount = document.getElementById("notification-count");

  notificationsMenu.replaceChildren();

  if (!Array.isArray(notifications)) {
    console.error("Unexpected notifications payload:", notifications);
    showNotificationsError("Failed to load notifications.");
    notificationCount.textContent = "";
    return;
  }

  if (notifications.length === 0) {
    notificationsMenu.appendChild(createMenuItem("No new notifications."));
    notificationCount.textContent = "";
    return;
  }

  // Items come from the GStraccini API (via /api/v1/notifications), not straight from GitHub:
  // { title, url, repositoryOwner, repositoryName }
  notifications.forEach((notification) => {
    if (!notification || typeof notification !== "object") {
      console.warn("Skipping malformed notification:", notification);
      return;
    }

    const title = notification.title ?? "(untitled)";
    const repo = [notification.repositoryOwner, notification.repositoryName]
      .filter(Boolean)
      .join("/");
    const label = repo ? `${title} - ${repo}` : title;
    const href = notification.url ? encodeURI(notification.url) : "#";

    notificationsMenu.appendChild(
      createLinkItem(href, label, `Notification: ${title}`),
    );
  });
  notificationCount.textContent =
    notifications.length > 9 ? "9+" : notifications.length;
}

function createViewAll() {
  const notificationsMenu = document.getElementById("notificationsMenu");

  const listDivider = document.createElement("li");
  const divider = document.createElement("hr");
  divider.className = "dropdown-divider";
  listDivider.appendChild(divider);
  notificationsMenu.appendChild(listDivider);

  notificationsMenu.appendChild(
    createLinkItem("notifications.php", "View all notifications"),
  );
}

document.addEventListener("DOMContentLoaded", function () {
  const notificationsDropdown = document.getElementById(
    "notificationsDropdown",
  );
  if (!notificationsDropdown) {
    console.error("Notifications dropdown element not found");
    return;
  }

  notificationsDropdown.addEventListener("click", function () {
    fetchNotifications();
  });
  fetchNotifications();
});
