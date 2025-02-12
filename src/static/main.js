function escapeHtml(unsafe) {
	return unsafe
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/'/g, "&#039;");
}

function showErrorAlert(message) {
	var alertHtml = `
	<div class="alert alert-danger alert-dismissible fade show" role="alert">
		<strong>Error!</strong> ${message}
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
	</div>`;

	$("#alert-container").toggleClass("d-none").toggleClass("d-block").html(alertHtml);

	setTimeout(function () {
		var alertElement = document.querySelector('.alert');
		if (alertElement) {
			var alertInstance = new bootstrap.Alert(alertElement);
			alertInstance.close();
		}
	}, 15000);
}
