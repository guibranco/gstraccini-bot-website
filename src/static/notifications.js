/**
 * Creates a plain dropdown menu item.
 * @param {string} text - Text to display.
 * @param {string} [extraClass] - Optional extra CSS class.
 * @returns {HTMLLIElement} The menu item.
 */
function createMenuItem(text, extraClass) {
  const item = document.createElement("li");
  item.className = extraClass ? `dropdown-item ${extraClass}` : "dropdown-item";
  item.textContent = text;
  return item;
}

/**
 * Creates a dropdown menu item containing a link.
 * External links (those with an aria label) open in a new tab.
 * @param {string} href - Link destination.
 * @param {string} text - Link text.
 * @param {string} [ariaLabel] - Accessible label for external links.
 * @returns {HTMLLIElement} The menu item.
 */
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

/**
 * Replaces the dropdown content with an error item.
 * @param {string} message - Error message to display.
 */
function showNotificationsError(message) {
  const notificationsMenu = document.getElementById("notificationsMenu");
  notificationsMenu.replaceChildren(createMenuItem(message, "text-bg-danger"));
}

/**
 * Fetches the signed-in user's notifications and renders them in the dropdown.
 */
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

/**
 * Returns a safe http(s) URL for a notification link, or "#" when missing or invalid.
 * The URL is not re-encoded, so existing escapes (e.g. %3A) are preserved.
 * @param {unknown} url - URL from the API.
 * @returns {string} The URL to use as the link href.
 */
function toSafeHref(url) {
  if (typeof url !== "string" || url === "") {
    return "#";
  }
  try {
    const parsed = new URL(url, window.location.origin);
    return parsed.protocol === "https:" || parsed.protocol === "http:"
      ? parsed.href
      : "#";
  } catch {
    return "#";
  }
}

/**
 * Renders notifications from the GStraccini API in the header dropdown.
 * @param {Array<{title: string, url: string, repositoryOwner: string, repositoryName: string}>} notifications - Notifications to render.
 */
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

    const title =
      notification.title === undefined || notification.title === null
        ? "(untitled)"
        : String(notification.title);
    const repo = [notification.repositoryOwner, notification.repositoryName]
      .filter(Boolean)
      .join("/");
    const label = repo ? `${title} - ${repo}` : title;
    const href = toSafeHref(notification.url);

    notificationsMenu.appendChild(
      createLinkItem(href, label, `Notification: ${title}`)
    );
  });
  notificationCount.textContent =
    notifications.length > 9 ? "9+" : notifications.length;
}

/**
 * Appends the divider and "View all notifications" link to the dropdown.
 */
function createViewAll() {
  const notificationsMenu = document.getElementById("notificationsMenu");

  const listDivider = document.createElement("li");
  const divider = document.createElement("hr");
  divider.className = "dropdown-divider";
  listDivider.appendChild(divider);
  notificationsMenu.appendChild(listDivider);

  notificationsMenu.appendChild(
    createLinkItem("notifications.php", "View all notifications")
  );
}

/**
 * Wires up the notifications dropdown once the DOM is ready.
 */
function initNotifications() {
  const notificationsDropdown = document.getElementById(
    "notificationsDropdown"
  );
  if (!notificationsDropdown) {
    console.error("Notifications dropdown element not found");
    return;
  }

  notificationsDropdown.addEventListener("click", fetchNotifications);
  fetchNotifications();
}

document.addEventListener("DOMContentLoaded", initNotifications);
