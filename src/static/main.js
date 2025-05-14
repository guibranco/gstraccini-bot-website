function escapeHtml(unsafe) {
	return unsafe
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");
}

const showErrorAlert = (message) => {
  const alertHtml = `
	<div class="alert alert-danger alert-dismissible fade show" role="alert">
		<strong>Error!</strong> ${message}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>`;

  try {
    if (typeof $ === 'undefined') {
      throw new Error('jQuery is not loaded');
    }
    if (typeof bootstrap === 'undefined') {
      throw new Error('Bootstrap is not loaded');
    }
    
    const container = $("#alert-container");
    container
      .toggleClass("d-none")
      .toggleClass("d-block")
      .html(alertHtml);

    setTimeout(() => {
      const alertElement = document.querySelector('.alert');
      if (alertElement) {
        const alertInstance = new bootstrap.Alert(alertElement);
        alertInstance.close();
      }
    }, 15000);
  } catch (error) {
    console.error('Error showing alert:', error.message);
  }
};

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
    const switcher = document.getELementById('theme');
    if (switcher !== null && typeof (switcher) !== "undefined") {
    	switcher.value = savedTheme;
    }
    applyTheme(savedTheme);
});
