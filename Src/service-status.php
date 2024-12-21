<?php
require_once "includes/session.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description"
    content="GStraccini-bot Service Status - Check real-time updates about system health and uptime.">
  <title>GStraccini-bot Service Status</title>
  <link rel="stylesheet" href="/static/main.css" />
  <style>
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

    /* Badges section */
    .badges {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 15px;
      /* Adjust the space between badges */
      margin: 20px 0;
    }

    /* For each individual badge */
    .badges a {
      display: block;
    }

    .badges img {
      max-width: 100%;
      height: auto;
      margin: 5px;
      transition: transform 0.3s ease;
    }

    .badges img:hover {
      transform: scale(1.1);
      /* Optional: adds a hover effect for interactivity */
    }

    /* Ensure responsiveness of badges on small screens */
    @media (max-width: 768px) {
      .badges {
        justify-content: flex-start;
        gap: 10px;
      }
    }
  </style>
</head>

<body>
  <?php include_once "includes/header-public.php"; ?>

  <section class="content">
    <h1>Service Status</h1>
    <p>Welcome to the GStraccini-bot Service Status page. Below, you can find real-time updates about our system's
      health and service availability.</p>

    <div class="badges">
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/deploy.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/deploy.yml/badge.svg"
          alt="Deploy via FTP Badge">
      </a>
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/php-lint.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/php-lint.yml/badge.svg"
          alt="PHP Linting Badge">
      </a>
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/json-yaml-lint.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/json-yaml-lint.yml/badge.svg"
          alt="JSON/YAML Validation Badge">
      </a>
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/shell-cheker.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/shell-cheker.yml/badge.svg"
          alt="Shell Checker Badge">
      </a>
      <!-- GitHub Actions Badge -->
      <img src="https://healthchecks.io/b/3/82d0dec5-3ec1-41cc-8a35-ef1da42899e5.svg" alt="GStraccini Bot - Branches">
      <img src="https://healthchecks.io/b/3/31b38cb0-f8bd-42b1-b662-d5905b22cd94.svg" alt="GStraccini Bot - Comments">
      <img src="https://healthchecks.io/b/3/05666a6b-d35f-4cb8-abc8-25584cc9029b.svg" alt="GStraccini Bot - Issues">
      <img src="https://healthchecks.io/b/3/05c48393-c700-45b4-880f-59cb7b9b9f25.svg" alt="GStraccini Bot - Pull Requests">
      <img src="https://healthchecks.io/b/3/1e8724fa-8361-47d7-a4f6-901e8d4ff265.svg" alt="GStraccini Bot - Pushes">
      <img src="https://healthchecks.io/b/3/4ef0ee6c-38f8-4c79-b9f7-049438bd39a9.svg" alt="GStraccini Bot - Repositories">
      <img src="https://healthchecks.io/b/3/8303206b-2f4c-4300-ac64-5e9cd342c164.svg" alt="GStraccini Bot - Signature">
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
          <td>2024-12-21 01:30 AM UTC</td>
        </tr>
        <tr>
          <td>Webhook Processing</td>
          <td class="status-up">Operational</td>
          <td>2024-12-21 01:30 AM UTC</td>
        </tr>
        <tr>
          <td>Dashboard</td>
          <td class="status-up">Operational</td>
          <td>2024-12-21 01:30 AM UTC</td>
        </tr>
        <tr>
          <td>Documentation</td>
          <td class="status-up">Operational</td>
          <td>2024-12-21 01:30 AM UTC</td>
        </tr>
        <tr>
          <td>GitHub Integration (Service)</td>
          <td class="status-up">Operational</td>
          <td>2024-12-21 01:30 AM UTC</td>
        </tr>
        <tr>
          <td>GitHub Workflows</td>
          <td class="status-up">Operational</td>
          <td>2024-12-21 01:30 AM UTC</td>
        </tr>
      </tbody>
    </table>
    <p><strong>Note:</strong> If you are experiencing issues not listed above, please contact us at <a
        href="mailto:bot@straccini.com">bot@straccini.com</a>.</p>
  </section>

  <?php include_once "includes/footer-public.php"; ?>
</body>

</html>