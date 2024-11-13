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
  <meta name="description" content="Privacy Notice for GStraccini-bot - Understand how your data is handled.">
  <title>GStraccini-bot Privacy Notice</title>
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

    section p, section ul {
      margin: 10px 0;
      line-height: 1.6;
      color: #555;
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
      <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png" alt="GStraccini-bot Logo" class="logo">
    </a>
    <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat" class="octocat"> Automate your GitHub workflow effortlessly.</p>
  </header>

  <section>
    <h1>Privacy Notice</h1>
    <p><strong>General Information:</strong></p>
    <p>GStraccini-bot is owned and operated by GuiBranco. This Privacy Notice ("Notice") explains how GStraccini-bot ("we," "us," or "our") collects, uses, and protects information provided by the Subscriber ("you" or "your"). By using GStraccini-bot, you consent to the practices outlined in this Notice.</p>
    <h3>Information Gathering and Usage</h3>
    <p>We collect only minimal data necessary for functionality:</p>
    <ul>
      <li>Repository names where GStraccini-bot is installed.</li>
      <li>Aggregated page access and visitation data.</li>
      <li>Information voluntarily provided by users.</li>
    </ul>
    <p>Your information is used to improve content and service quality. We do not share or sell data for commercial purposes, except:</p>
    <ul>
      <li>To fulfill requested services with your permission.</li>
      <li>To comply with legal requirements or prevent harm.</li>
      <li>If GStraccini-bot merges or is acquired by another entity.</li>
    </ul>
    <h3>Cookies</h3>
    <p>We use cookies to manage session information and track analytics. These cookies are non-permanent and essential for service functionality. You may disable non-essential cookies through your browser settings.</p>
    <h3>Data Storage</h3>
    <p>Your data is stored securely using third-party vendors and partners. You retain ownership of your data, while GStraccini-bot owns the application code and databases.</p>
    <h3>Source Code</h3>
    <p>GStraccini-bot does not store user source code unless explicitly permitted. We do not store GitHub credentials and use temporary tokens for limited interactions.</p>
    <h3>Security</h3>
    <p>We prioritize security but cannot guarantee data protection against all risks. Please report vulnerabilities to <a href="mailto:bot@straccini.com">bot@straccini.com</a>.</p>
    <h3>Changes</h3>
    <p>This policy may be updated without notice. Users should review it periodically. Continued use of the service implies acceptance of any changes.</p>
    <h3>Questions</h3>
    <p>For inquiries, contact us at <a href="mailto:bot@straccini.com">bot@straccini.com</a>.</p>
  </section>

  <?php include_once "includes/footer_public.php"; ?>
</body>

</html>
