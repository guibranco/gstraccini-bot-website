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
  <meta name="description" content="GStraccini-bot Terms of Service">
  <title>Terms of Service - GStraccini-bot</title>
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

    .content {
      max-width: 800px;
      margin: 50px auto;
      padding: 20px;
      background: #ffffff;
      box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
      border-radius: 8px;
    }

    .content h2 {
      color: #007bff;
      margin-bottom: 15px;
    }

    .content ul {
      padding-left: 20px;
      margin-bottom: 20px;
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

      footer p {
      margin: 5px 0;
      color: #777;
    }

    footer nav ul {
      list-style: none;
      margin: 10px 0 0;
      padding: 0;
      display: inline-flex;
      gap: 15px;
    }

    footer nav ul li {
      display: inline;
    }

    footer nav ul li a {
      text-decoration: none;
      color: #007bff;
      font-weight: bold;
    }

    footer nav ul li a:hover {
      text-decoration: underline;
    }
    
    img.octocat {
      width: 20px;
      vertical-align: middle;
      margin-top: -2px;
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

  <div class="content">
    <h1>Terms of Service</h1>
    <p><strong>Effective Date:</strong> 13/11/2024</p>
    <p>Welcome to GStraccini-bot! These Terms of Service ("Terms") govern your use of our GitHub App/Bot, which provides free automated actions for pull requests, issues, and repositories. By installing or using our app, you agree to these Terms.</p>

    <h2>1. Acceptance of Terms</h2>
    <p>By using this app, you confirm that you have read, understood, and agree to these Terms. If you do not agree to these Terms, you must not install or use the app.</p>

    <h2>2. Service Description</h2>
    <p>GStraccini-bot offers automation features for pull requests, issues, and repositories, free of charge. These services include, but are not limited to:</p>
    <ul>
      <li>Managing labels and comments.</li>
      <li>Triggering workflows or notifications.</li>
      <li>Performing repository-level maintenance tasks.</li>
    </ul>

    <h2>3. Your Responsibilities</h2>
    <ul>
      <li><strong>Compliance:</strong> You agree to comply with GitHub’s <a href="https://docs.github.com/en/github/site-policy/github-terms-of-service" target="_blank">Terms of Service</a> and all applicable laws.</li>
      <li><strong>Permissions:</strong> By installing the app, you grant the app access to the repository data necessary to perform its actions.</li>
      <li><strong>Data Usage:</strong> You are responsible for the content of your repositories and must ensure that the app’s actions align with your organization’s policies.</li>
    </ul>

    <h2>4. Data Collection and Usage</h2>
    <p>We value your privacy and do not collect or store personal data beyond what is necessary to provide the app’s functionality. For more details, please review our <a href="#">Privacy Policy</a>.</p>

    <h2>5. Limitations of Liability</h2>
    <p>To the fullest extent permitted by law, GStraccini-bot is provided "as is" without warranties of any kind, express or implied. We are not liable for any damages arising from:</p>
    <ul>
      <li>Misuse of the app.</li>
      <li>Actions performed by the app on your repositories.</li>
      <li>Unauthorized access to your repositories.</li>
    </ul>

    <h2>6. Modifications to the Service</h2>
    <p>We reserve the right to modify, suspend, or discontinue the app at any time without prior notice.</p>

    <h2>7. Termination</h2>
    <p>You may uninstall the app at any time to terminate your use. We reserve the right to terminate your access to the app for violation of these Terms.</p>

    <h2>8. Changes to These Terms</h2>
    <p>We may update these Terms periodically. Changes will be posted on this page, and your continued use of the app constitutes acceptance of the updated Terms.</p>

    <h2>9. Contact Information</h2>
    <p>If you have questions or concerns about these Terms, please contact us at <a href="mailto:[support email address]">[support email address]</a>.</p>
    <p>Thank you for using GStraccini-bot!</p>
  </div>

  <footer>
    <p>© 2024 GStraccini-bot. All rights reserved.</p>
    <nav>
      <ul>
        <li><a href="privacy-policy.php">Privacy Policy</a></li>
        <li><a href="terms-of-service.php">Terms of Service</a></li>
        <li><a href="service-status.php">Service Status</a></li>
        <li><a href="https://docs.bot.straccini.com" target="_blank">Documentation</a></li>
        <li><a href="https://github.com/marketplace/gstraccini-bot" target="_blank">GitHub Marketplace</a></li>
        <li><a href="https://github.com/guibranco/gstraccini-bot-service" target="_blank">GitHub Repository</a></li>
      </ul>
    </nav>
  </footer>
</body>

</html>
