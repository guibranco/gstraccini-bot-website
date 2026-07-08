function escapeHtml(unsafe) {
	return unsafe
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");
}

/**
 * Displays an error alert with a message and optional retry information.
 */
function showErrorAlert(message, isRetriable = false) {
    console.error(message);
    
    let alertContainer = document.getElementById('error-alert-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'error-alert-container';
        alertContainer.className = 'position-fixed top-0 start-50 translate-middle-x';
        alertContainer.style.zIndex = '9999';
        alertContainer.style.marginTop = '20px';
        document.body.appendChild(alertContainer);
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-danger alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <strong>Error:</strong> ${escapeHtml(message)}
        ${isRetriable ? '<br><small>Retrying automatically...</small>' : ''}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function isDarkTheme(theme) {
    return theme === 'dark' || (theme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
}

function applyTheme(theme) {
    const themeIcon = document.getElementById('theme-icon');
    const iconEl = themeIcon ? (themeIcon.querySelector('i') || themeIcon) : null;
    const effectiveDark = isDarkTheme(theme);

    // Drives this site's own CSS variables (main.css/user.css) as well as
    // Bootstrap 5.3's built-in dark mode for cards, modals, tables, forms, etc.
    document.documentElement.setAttribute('data-theme', effectiveDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', effectiveDark ? 'dark' : 'light');
    document.body.classList.toggle('dark-theme', effectiveDark);

    if (iconEl) {
        if (theme === 'system') {
            iconEl.className = effectiveDark ? 'fas fa-moon' : 'fas fa-adjust';
        } else {
            iconEl.className = effectiveDark ? 'fas fa-moon' : 'fas fa-sun';
        }
    }
}

const GROUP_BY_ORG_STORAGE_KEY = 'groupByOrgAccount';

/**
 * Whether repositories, issues, and pull requests should be grouped by
 * organization/account, per the user's account preference. Defaults to enabled.
 */
function isGroupByOrgEnabled() {
    return localStorage.getItem(GROUP_BY_ORG_STORAGE_KEY) !== 'false';
}

window.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'system';
    const switcher = document.getElementById('theme');
    if (switcher) {
        switcher.value = savedTheme;
    }
    applyTheme(savedTheme);

    const themeIcon = document.getElementById('theme-icon');
    if (themeIcon) {
        themeIcon.addEventListener('click', () => {
            const current = localStorage.getItem('theme') || 'system';
            const nextTheme = isDarkTheme(current) ? 'light' : 'dark';
            localStorage.setItem('theme', nextTheme);
            if (switcher) {
                switcher.value = nextTheme;
            }
            applyTheme(nextTheme);
        });
    }

    const groupByOrgToggle = document.getElementById('groupByOrgToggle');
    if (groupByOrgToggle) {
        groupByOrgToggle.checked = isGroupByOrgEnabled();
        groupByOrgToggle.addEventListener('change', () => {
            localStorage.setItem(GROUP_BY_ORG_STORAGE_KEY, groupByOrgToggle.checked ? 'true' : 'false');
        });
    }
});
