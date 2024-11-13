<?php
$cookie_lifetime = 604800;
session_set_cookie_params([
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'domain' => 'bot.straccini.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
$isAuthenticated = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="GStraccini-bot Service Status - Check real-time updates about system health and uptime.">
  <title>GStraccini-bot Service Status</title>
  <style>
    body {
      font-family: 'Arial', sans-serif;
      margin: 0;
      padding: 0;
      background: #f4f4f4;
      color: #333;
    }

    header {
      background-color: #007bff;
      color: white;
      padding: 20px 0;
      text-align: center;
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    header h1 {
      font-size: 2.5em;
      margin: 0;
    }

    header p {
      margin: 10px 0;
    }

    section {
      padding: 20px;
      max-width: 800px;
      margin: 0 auto;
      background: #ffffff;
      box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
      border-radius: 10px;
    }

    section h2 {
      color: #007bff;
      margin-bottom: 15px;
    }

    .status-table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }

    .status-table th,
    .status-table td {
      border: 1px solid #ddd;
      text-align: left;
      padding: 10px;
    }

    .status-table th {
      background-color: #007bff;
      color: white;
    }

    .status-up {
      color: #28a745;
      font-weight: bold;
    }

    .status-down {
      color: #dc3545;
      font-weight: bold;
    }

    .status-maintenance {
      color: #ffc107;
      font-weight: bold;
    }

    .badges {
      text-align: center;
      margin: 20px 0;
    }

    footer {
      background-color: #f1f1f1;
      padding: 20px;
      text-align: center;
    }

    footer p {
      margin: 0;
      color: #777;
    }
  </style>
</head>

<body>
  <header>
    <a href="https://bot.straccini.com">
      <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png" alt="GStraccini-bot Logo" class="logo" />
    </a>
    <p>🤖 Automate your GitHub workflow effortlessly.</p>
  </header>

  <section>
    <h2>Service Status</h2>
    <p>Welcome to the GStraccini-bot Service Status page. Below, you can find real-time updates about our system health and service availability.</p>

    <div class="badges">

        <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/deploy.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/deploy.yml/badge.svg" alt="Deploy via FTP Badge">
      </a>
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/php-lint.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/php-lint.yml/badge.svg" alt="PHP Linting Badge">
      </a>
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/json-yaml-lint.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/json-yaml-lint.yml/badge.svg" alt="JSON/YAML Validation Badge">
      </a>
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/shell-cheker.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/shell-cheker.yml/badge.svg" alt="Shell Checker Badge">
      </a>
      <!-- GitHub Actions Badge -->
     <img src="https://healthchecks.io/b/3/82d0dec5-3ec1-41cc-8a35-ef1da42899e5.svg" alt="GStraccini Bot - Branches">
     <img src="https://healthchecks.io/b/3/31b38cb0-f8bd-42b1-b662-d5905b22cd94.svg" alt="GStraccini Bot - Comments">
     <img src="https://healthchecks.io/b/3/05666a6b-d35f-4cb8-abc8-25584cc9029b.svg" alt="GStraccini Bot - Issues">
     <img src="https://healthchecks.io/b/3/05c48393-c700-45b4-880f-59cb7b9b9f25.svg" alt="GStraccini Bot - Pull Requests">
     <img src="https://healthchecks.io/b/3/1e8724fa-8361-47d7-a4f6-901e8d4ff265.svg" alt="GStraccini Bot - Pushes">
     <img src="https://healthchecks.io/b/3/4ef0ee6c-38f8-4c79-b9f7-049438bd39a9.svg" alt="GStraccini Bot - Repositories">
      <img src="https://healthchecks.io/b/3/8303206b-2f4c-4300-ac64-5e9cd342c164.svg" alt="GStraccini Bot - Signature">
    </ul>
    </div>

    <table class="status-table">
      <thead>
        <tr>
          <th>Service</th>
          <th>Status</th>
          <th>Last Updated</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>API</td>
          <td class="status-up">Operational</td>
          <td>2024-11-13 10:00 AM UTC</td>
        </tr>
        <tr>
          <td>Webhook Processing</td>
          <td class="status-up">Operational</td>
          <td>2024-11-13 10:00 AM UTC</td>
        </tr>
        <tr>
          <td>Dashboard</td>
          <td class="status-maintenance">Under Maintenance</td>
          <td>2024-11-13 9:00 AM UTC</td>
        </tr>
        <tr>
          <td>Documentation</td>
          <td class="status-up">Operational</td>
          <td>2024-11-13 10:00 AM UTC</td>
        </tr>
        <tr>
          <td>GitHub Integration</td>
          <td class="status-down">Outage</td>
          <td>2024-11-13 9:30 AM UTC</td>
        </tr>
      </tbody>
    </table>
    <p><strong>Note:</strong> If you are experiencing issues not listed above, please contact us at <a href="mailto:bot@straccini.com">bot@straccini.com</a>.</p>
  </section>

  <footer>
    <p>© 2024 GStraccini-bot. All rights reserved.</p>
  </footer>
</body>

</html>
