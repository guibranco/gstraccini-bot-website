<?php
session_start();
$isAuthenticated = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Introducing Your GStraccini-bot - Automate GitHub tasks with ease.">
  <title>GStraccini-bot - Automate Your Workflow</title>
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

    .hero {
      background: linear-gradient(to right, #0069d9, #0056b3);
      color: white;
      padding: 100px 20px;
      text-align: center;
    }

    .hero h2 {
      font-size: 3em;
      margin-bottom: 20px;
    }

    .hero p {
      font-size: 1.5em;
      margin-bottom: 40px;
    }

    .cta-button {
      font-size: 1.2em;
      padding: 15px 30px;
      color: white;
      background-color: #007bff;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .cta-button:hover {
      background-color: #0056b3;
    }

    .github-button {
      font-size: 1.2em;
      padding: 15px 30px;
      color: white;
      background-color: #24292e;
      border: none;
      border-radius: 50px;
      cursor: pointer;
      margin-top: 20px;
      transition: background-color 0.3s ease;
    }

    .github-button:hover {
      background-color: #171a1d;
    }

    .github-button img {
      vertical-align: middle;
      margin-right: 10px;
    }

    .features {
      display: flex;
      justify-content: space-around;
      padding: 50px 20px;
      background: #ffffff;
    }

    .feature {
      text-align: center;
      max-width: 300px;
    }

    .feature img {
      width: 100px;
      height: 100px;
    }

    .feature h3 {
      margin: 20px 0;
      color: #007bff;
    }

    .feature p {
      font-size: 1em;
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

    .fade-in {
      opacity: 0;
      animation: fadeIn 1.5s forwards;
    }

    img.octocat {
      width: 20px;
      vertical-align: middle;
      margin-top: -2px;
    }

    @keyframes fadeIn {
      0% {
        opacity: 0;
      }

      100% {
        opacity: 1;
      }
    }
  </style>
</head>

<body>
  <header>
    <a href="https://bot.straccini.com">
      <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png"
        alt="GStraccini-bot Logo" class="logo" alt="GStraccini-bot logo">
    </a>
    <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat"
        class="octocat"> Automate your GitHub workflow effortlessly.</p>
  </header>

  <section class="hero fade-in">
    <img src="https://bot.straccini.com/gstraccini-bot.png" alt="GStraccini-bot" />
    <h2>Boost Your GitHub Efficiency</h2>
    <p>Get more done by automating repetitive tasks.</p>
    <button class="cta-button">Get Started</button>

    <?php if ($isAuthenticated): ?>
      <a href="dashboard.php" class="dashboard-link">Go to Dashboard</a>
    <?php else: ?>
      <form action="login.php" method="get">
        <button type="submit" class="github-button">
          <img src="GitHub.png" width="20" height="20" alt="GitHub logo" />
          Login with GitHub
        </button>
      </form>
    <?php endif; ?>
  </section>

  <section class="features fade-in">
    <div class="feature">
      <img src="https://via.placeholder.com/100" alt="Feature 1 Icon">
      <h3>Automated PRs</h3>
      <p>Auto-create pull requests and label them based on size and impact.</p>
    </div>
    <div class="feature">
      <img src="https://via.placeholder.com/100" alt="Feature 2 Icon">
      <h3>Link Checker</h3>
      <p>Automatically scan and validate links across your repositories.</p>
    </div>
    <div class="feature">
      <img src="https://via.placeholder.com/100" alt="Feature 3 Icon">
      <h3>Custom Workflows</h3>
      <p>Generate commit messages, manage issues, and more through custom workflows.</p>
    </div>
  </section>

  <footer>
    <p>© 2024 GStraccini-bot. All rights reserved.</p>
  </footer>
</body>

</html>