<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.5/dist/umd/popper.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
 
<script>
      $(document).ready(function() {
          $.ajax({
              url: 'notifications.php',
              type: 'GET',
              success: function(data) {
                  $('#notificationsMenu').html(data);
                  $('#notification-count').text($('.notification-item').length); // Update count
              },
              error: function() {
                  $('#notificationsMenu').html('<li class="dropdown-item">Error loading notifications.</li>');
              }
          });
      });
</script>
