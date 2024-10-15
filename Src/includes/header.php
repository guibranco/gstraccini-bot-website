<header class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <a href="https://bot.straccini.com/dashboard.php"><img src="logo.png" alt="Bot Logo" class="me-2"></a>
        <div class="d-flex align-items-center">
            <div class="dropdown me-3">
                  <a href="#" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="fas fa-bell text-white" style="font-size: 24px;"></i>
                      <span id="notification-count" class="badge bg-danger"></span>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notificationsMenu">
                      <li class="dropdown-header">Notifications</li>
                      <li class="dropdown-item">Loading...</li>
                  </ul>
            </div>
            <a href="https://docs.gstraccini.bot" class="me-3" target="_blank">
                <i class="fas fa-question-circle text-white" style="font-size: 24px;"></i>
            </a>
            <div class="dropdown me-3">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?php echo $user['avatar_url']; ?>" alt="<?php echo $user['login']; ?> avatar" class="rounded-circle" width="40" height="40">
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                    <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                    <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                    <li><a class="dropdown-item" href="integrations.php">Integrations</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item logout" href="logout.php">Logout</a></li>
                </ul>
            </div>
            
        </div>
    </div>
</header>
