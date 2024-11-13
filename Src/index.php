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

    .dashboard-button {
      background: linear-gradient(135deg, #28a745, #17a2b8);
      color: white;
      border: none;
      border-radius: 25px;
      padding: 15px 30px;
      font-size: 18px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.2);
    }

    .dashboard-button:hover {
      background: linear-gradient(135deg, #218838, #138496);
      box-shadow: 0px 6px 20px rgba(0, 0, 0, 0.3);
    }

    .dashboard-button:active {
      transform: scale(0.98);
    }
  </style>
<style>
  .commands {
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
    background: #ffffff;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
  }

  .commands h2 {
    text-align: center;
    color: #007bff;
    margin-bottom: 20px;
  }

  .commands-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
  }

  .command-card {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .command-card:hover {
    transform: translateY(-5px);
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.2);
  }

  .command-card strong {
    display: block;
    color: #007bff;
    margin-bottom: 10px;
    font-size: 1.1em;
  }

  .command-card p {
    margin: 0;
    color: #555;
  }
</style>
</head>

<body>
  <header>
    <a href="https://bot.straccini.com">
      <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png" alt="GStraccini-bot Logo" class="logo" alt="GStraccini-bot logo">
    </a>
    <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat" class="octocat"> Automate your GitHub workflow effortlessly.</p>
  </header>

  <section class="hero fade-in">    
    <h2>Boost Your GitHub Efficiency</h2>
    <p>Get more done by automating repetitive tasks.</p>
    <button class="cta-button" onclick="window.location.href='https://github.com/marketplace/gstraccini-bot'">Get
      Started</button>

    <?php if ($isAuthenticated): ?>
      <button class="dashboard-button" onclick="window.location.href='dashboard.php'">
        Go to Dashboard
      </button>
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

  <section class="commands fade-in">
      <h2>Available Commands</h2>
      <div class="commands-grid">
        <div class="command-card">
          <strong>@gstraccini help</strong>
          <p>Shows available commands.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini hello</strong>
          <p>Greets the invoker.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini thank you</strong>
          <p>Replies with a "You're welcome" message.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini add project &lt;projectPath&gt;</strong>
          <p>Adds a project to the solution file (for .NET projects).</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini appveyor build &lt;type&gt;</strong>
          <p>Runs an AppVeyor build for a target commit/pull request.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini appveyor bump version &lt;component&gt;</strong>
          <p>Bumps the version in AppVeyor.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini appveyor register</strong>
          <p>Registers the repository in AppVeyor.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini appveyor reset</strong>
          <p>Resets the AppVeyor build number for a repository.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini bump version &lt;version&gt; &lt;project&gt;</strong>
          <p>Bumps the .NET version in .csproj files.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini change runner &lt;runner&gt; &lt;workflow&gt; &lt;jobs&gt;</strong>
          <p>Changes the GitHub Actions runner in a workflow file.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini csharpier</strong>
          <p>Formats C# code using CSharpier.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini fix csproj</strong>
          <p>Updates the .csproj file with NuGet package versions (for .NET Framework projects).</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini prettier</strong>
          <p>Formats code using Prettier.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini rerun failed checks</strong>
          <p>Reruns failed checks in the target pull request.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini rerun failed workflows</strong>
          <p>Reruns failed GitHub Actions workflows in the target pull request.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini review</strong>
          <p>Enables review for the target pull request.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini track</strong>
          <p>Tracks a pull request, queues a build, and synchronizes merge branches.</p>
        </div>
        <div class="command-card">
          <strong>@gstraccini update snapshot</strong>
          <p>Updates test snapshots for Node.js projects.</p>
        </div>
      </div>
      <p><strong>Note:</strong> If you are not allowed to use the bot, a thumbs-down reaction will be added to your comment.</p>
      <p><strong>Tip:</strong> You can trigger commands with a ✅ tick (beta feature).</p>
    </section>

  <?php include_once "includes/footer_public.php"; ?>
</body>

</html>
