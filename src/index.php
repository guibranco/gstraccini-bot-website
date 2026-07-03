<?php
require_once "includes/session.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Introducing Your GStraccini-bot - Automate GitHub tasks with ease.">
  <title>GStraccini Bot | Automate Your Workflow</title>
  <link rel="stylesheet" href="/static/main.css" />
  <script src="/static/commands.js" defer></script>
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
      <div class="feature-icon feature-icon-pr" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3" /><circle cx="6" cy="6" r="3" /><path d="M13 6h3a2 2 0 0 1 2 2v7" /><line x1="6" y1="9" x2="6" y2="21" /></svg>
      </div>
      <h3>Automated PRs</h3>
      <p>Auto-label them based on size and impact.</p>
    </div>
    <div class="feature">
      <div class="feature-icon feature-icon-checklist" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1" /><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /><path d="m9 14 2 2 4-4" /></svg>
      </div>
      <h3>Validates PRs description</h3>
      <p>Requires check-lists to be completed on PR description.</p>
    </div>
    <div class="feature">
      <div class="feature-icon feature-icon-npm" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" /><path d="m3.3 7 8.7 5 8.7-5" /><path d="M12 22V12" /></svg>
      </div>
      <h3>Custom NPM Workflows</h3>
      <p>Generate distribuition/build files from NPM/YARN projects, run Prettier, and more.</p>
    </div>
    <div class="feature">
      <div class="feature-icon feature-icon-dotnet" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5" /><line x1="12" y1="19" x2="20" y2="19" /></svg>
      </div>
      <h3>Custom .NET Workflows</h3>
      <p>Run liters, CSharpier and dotnet format commands from pull requests.</p>
    </div>
    <div class="feature">
      <div class="feature-icon feature-icon-ai" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.3L19 10l-5.1 1.7L12 17l-1.9-5.3L5 10l5.1-1.7L12 3Z" /><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z" /></svg>
      </div>
      <h3>Automate issues</h3>
      <p>Generate issues description using AI (OpenAI, Llama, Claude).</p>
    </div>
    <div class="feature">
      <div class="feature-icon feature-icon-cicd" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10" /><polyline points="1 20 1 14 7 14" /><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" /></svg>
      </div>
      <h3>CI/CD integration</h3>
      <p>Bump version (GitHub Actions/GitVersion/Appveyor).</p>
    </div>
    <div class="feature">
      <div class="feature-icon feature-icon-quality" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" /><path d="m9 12 2 2 4-4" /></svg>
      </div>
      <h3>Code Quality</h3>
      <p>Integrate commands with CodeClimate, Codacy, Codecov, DeepSource, SonarQube, and more directly from pull requests.</p>
    </div>
  </section>

  <section class="commands fade-in">
    <h2>Available Commands</h2>
    <div class="commands-toolbar">
      <input type="search" id="commands-search" placeholder="Filter commands&hellip;" aria-label="Filter commands" />
      <span id="commands-count" class="commands-count"></span>
    </div>
    <div class="commands-grid"></div>
  </section>

  <?php require_once "includes/footer-public.php"; ?>
</body>

</html>
