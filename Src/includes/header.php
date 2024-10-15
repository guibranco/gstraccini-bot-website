<header class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <a href="https://bot.straccini.com/dashboard.php"><img src="logo-white.png" alt="Bot Logo" class="me-2"></a>
        
        <nav class="navbar-nav flex-row me-auto">
            <a href="dashboard.php" class="nav-link text-white me-3">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="repositories.php" class="nav-link text-white me-3">
                <i class="fas fa-folder-open"></i> Repositories
            </a>
            <a href="issues.php" class="nav-link text-white me-3">
                <i class="fas fa-exclamation-circle"></i> Issues
            </a>
            <a href="pull-requests.php" class="nav-link text-white me-3">
                <i class="fas fa-code-branch"></i> Pull Requests
            </a>
        </nav>

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
                    <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                    <li><a class="dropdown-item" href="account.php"><i class="fas fa-user me-2"></i> Account</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    <li><a class="dropdown-item" href="integrations.php"><i class="fas fa-plug me-2"></i> Integrations</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item logout" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>
