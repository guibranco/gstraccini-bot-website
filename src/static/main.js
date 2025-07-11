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

function applyTheme(theme) {
    const themeIcon = document.getElementById('theme-icon');

    if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
        document.body.classList.remove('dark-theme');
        themeIcon.className = 'fas fa-sun';
    } else if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.body.classList.add('dark-theme');
        themeIcon.className = 'fas fa-moon';
    } else {
        document.documentElement.removeAttribute('data-theme');
        document.body.classList.remove('dark-theme');
        themeIcon.className = 'fas fa-adjust';

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            themeIcon.className = 'fas fa-moon';
        }
    }
}

window.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme') || 'system';
    const switcher = document.getElementById('theme');
    if (switcher) {
        switcher.value = savedTheme;
    }
    applyTheme(savedTheme);
});
