<?php
require_once "includes/session.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Introducing Your GStraccini-bot - Automate GitHub tasks with ease.">
  <title>GStraccini-bot - Automate Your Workflow</title>
  <link rel="stylesheet" href="/static/main.css" />
</head>

<body>
  <?php require_once "includes/header-public.php"; ?>

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
          <img src="/images/GitHub.png" width="20" height="20" alt="GitHub logo" />
          Login with GitHub
        </button>
      </form>
    <?php endif; ?>
  </section>

  <section class="features fade-in">
    <div class="feature">
      <img src="https://dummyimage.com/100/d3d3d3/fff" alt="Feature 1 Icon">
      <h3>Automated PRs</h3>
      <p>Auto-label them based on size and impact.</p>
    </div>
    <div class="feature">
      <img src="https://dummyimage.com/100/d3d3d3/fff" alt="Feature 2 Icon">
      <h3>Validates PRs description</h3>
      <p>Requires check-lists to be completed on PR description.</p>
    </div>
    <div class="feature">
      <img src="https://dummyimage.com/100/d3d3d3/fff" alt="Feature 3 Icon">
      <h3>Custom NPM Workflows</h3>
      <p>Generate distribuition/build files from NPM/YARN projects, run Prettier, and more.</p>
    </div>
    <div class="feature">
      <img src="https://dummyimage.com/100/d3d3d3/fff" alt="Feature 3 Icon">
      <h3>Custom .NET Workflows</h3>
      <p>Run liters, CSharpier and dotnet format commands from pull requests.</p>
    </div>
    <div class="feature">
      <img src="https://dummyimage.com/100/d3d3d3/fff" alt="Feature 3 Icon">
      <h3>Automate issues</h3>
      <p>Generate issues description using AI (OpenAI, Llama, Claude).</p>
    </div>
    <div class="feature">
      <img src="https://dummyimage.com/100/d3d3d3/fff" alt="Feature 3 Icon">
      <h3>CI/CD integration</h3>
      <p>Bump version (GitHub Actions/GitVersion/Appveyor).</p>
    </div>
    <div class="feature">
      <img src="https://dummyimage.com/100/d3d3d3/fff" alt="Feature 6 Icon">
      <h3>Code Quality</h3>
      <p>Integrate commands with CodeClimate, Codacy, Codecov, DeepSource, SonarQube, and more directly from pull requests.</p>
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
  </section>

  <?php require_once "includes/footer-public.php"; ?>
</body>

</html>
