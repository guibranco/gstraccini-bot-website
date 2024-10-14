<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GStraccini-bot - Automate Your Workflow</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f9;
      color: #333;
      margin: 0;
      padding: 0;
      text-align: center;
    }

    header {
      background-color: #4CAF50;
      color: white;
      padding: 20px;
    }

    header img {
      width: 100px;
    }

    img.octocat {
      width: 20px;
      vertical-align: middle;
      margin-top: -2px;
    }

    section {
      padding: 40px 20px;
      max-width: 1000px;
      margin: 0 auto;
    }

    h1 {
      font-size: 2.5rem;
      margin: 20px 0;
    }

    h2 {
      font-size: 1.75rem;
      margin-top: 40px;
    }

    p {
      font-size: 1.2rem;
      line-height: 1.6;
    }

    section ul {
      list-style: none;
      padding: 0;
    }

    section ul li {
      margin: 10px 0;
    }

    img.octocat {
      width: 20px;
      vertical-align: middle;
      margin-top: -2px;
    }

    .badge {
      margin: 10px;
    }

    .badges {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 20px;
    }

    .badges img {
      margin: 5px;
    }

    footer {
      background-color: #333;
      color: white;
      padding: 20px;
      margin-top: 40px;
    }

    footer a {
      color: #4CAF50;
      text-decoration: none;
    }

    .icon {
      justify-content: center;
      width: 20px;
      height: 20px
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
  </style>
</head>

<body>

  <header>
    <a href="https://bot.straccini.com">
      <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png" alt="GStraccini-bot Logo" class="logo" alt="GStraccini-bot logo" >
    </a>
    <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat" class="octocat"> Automate your GitHub workflow effortlessly.</p>
  </header>

  <section>
    <h2>About the Bot</h2>
    <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat"
        class="octocat"> <strong>GStraccini-bot</strong>
      automates repository tasks like managing pull requests, issues,
      comments, and commits. This allows you to focus on solving real
      problems.</p>
    <img src="https://bot.straccini.com/gstraccini-bot.png" alt="GStraccini-bot" />
    <div class="badges">
      <a href="https://github.com/guibranco/gstraccini-bot/actions/workflows/deploy.yml">
        <img src="https://github.com/guibranco/gstraccini-bot/actions/workflows/deploy.yml/badge.svg"
          alt="Deploy Badge">
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
    </div>

    <section>
      <form action="login.php" method="get">
        <button type="submit" class="github-button">
          <img src="GitHub.png" width="20" height="20" alt="GitHub logo" />
          Login with GitHub
        </button>
      </form>
    </section>

    <section>
      <h2>Available Commands</h2>
      <ul>
        <li>
          <strong>@gstraccini help</strong>: Shows available commands.
        </li>
        <li>
          <strong>@gstraccini hello</strong>: Greets the invoker.
        </li>
        <li>
          <strong>@gstraccini thank you</strong>: Replies with a
          "You're welcome" message.
        </li>
        <li>
          <strong>@gstraccini add project &lt;projectPath&gt;</strong>: Adds a
          project to the solution file (for .NET projects).
        </li>
        <li>
          <strong>@gstraccini appveyor build &lt;type&gt;</strong>: Runs an
          AppVeyor build for a target commit/pull request.
        </li>
        <li>
          <strong>@gstraccini appveyor bump version
            &lt;component&gt;</strong>:
          Bumps the version in AppVeyor.
        </li>
        <li>
          <strong>@gstraccini appveyor register</strong>: Registers the
          repository in AppVeyor.
        </li>
        <li>
          <strong>@gstraccini appveyor reset</strong>: Resets the AppVeyor
          build
          number for a repository.
        </li>
        <li>
          <strong>@gstraccini bump version &lt;version&gt;
            &lt;project&gt;</strong>: Bumps the .NET version in .csproj files.
        </li>
        <li>
          <strong>@gstraccini change runner &lt;runner&gt; &lt;workflow&gt;
            &lt;jobs&gt;</strong>: Changes the GitHub Actions runner in a
          workflow file.
        </li>
        <li>
          <strong>@gstraccini csharpier</strong>: Formats C# code using
          CSharpier.
        </li>
        <li>
          <strong>@gstraccini fix csproj</strong>: Updates the .csproj file
          with
          NuGet package versions (for .NET Framework projects).
        </li>
        <li>
          <strong>@gstraccini prettier</strong>: Formats code using Prettier.
        </li>
        <li>
          <strong>@gstraccini rerun failed checks</strong>: Reruns failed
          checks
          in the target pull request.
        </li>
        <li>
          <strong>@gstraccini rerun failed workflows</strong>: Reruns failed
          GitHub Actions workflows in the target pull request.
        </li>
        <li>
          <strong>@gstraccini review</strong>: Enables review for the target
          pull request.
        </li>
        <li>
          <strong>@gstraccini track</strong>: Tracks a pull request, queues a
          build, and synchronizes merge branches.
        </li>
        <li>
          <strong>@gstraccini update snapshot</strong>: Updates test snapshots
          for Node.js projects.
        </li>
      </ul>
      <p>
        <strong>Note:</strong> If you are not allowed to use the bot, a
        thumbs-down reaction will be added to your comment.
      </p>
      <p>
        <strong>Tip:</strong> You can trigger commands with a ✅ tick (beta
        feature).
      </p>
    </section>

    <section>
      <h2>How It Works</h2>
      <p>The bot works by using various components to manage your
        repository:</p>
      <ul>
        <li><a href="https://github.com/guibranco/gstraccini-bot-api">API</a>
          – The bot's API for stats and configuration.</li>
        <li><a href="https://github.com/guibranco/gstraccini-bot-docsi">Docs</a>
          – The bot's documentation.</li>
        <li><a href="https://github.com/guibranco/gstraccini-bot-handler">Handler</a>
          – Handles incoming webhooks.</li>
        <li><a href="https://github.com/guibranco/gstraccini-bot-service">Service</a>
          – The main worker that processes tasks.</li>
        <li><a href="https://github.com/guibranco/gstraccini-bot-website">Website</a>
          – Provides the bot's landing page and dashboard.</li>
        <li><a href="https://github.com/guibranco/gstraccini-bot-workflows">Workflows</a>
          – Execute GitHub Actions.</li>
      </ul>
    </section>

    <section>
      <h2>Institutional</h2>
      <ul class="gh-links">
        <li>
          <a href="https://bot.straccini.com/status.php">
            Service Status
          </a>
        </li>
        <li>
          <a href="https://bot.straccini.com/privacy.php">
            Privacy notice
          </a>
        </li>
        <li>
          <a href="https://bot.straccini.com/tos.php">
            Terms of Service
          </a>
        </li>
      </ul>
    </section>

    <section>
      <h2>Useful Links</h2>
      <ul>
        <li>
          <a href="https://github.com/marketplace/gstraccini-bot">
            <svg class="icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M10.0074 1.5C5.02656 1.5 1 5.39582 1 10.2155C1 14.0681 3.57996 17.3292 7.15904 18.4835C7.60652 18.5702 7.77043 18.2959 7.77043 18.0652C7.77043 17.8631 7.75568 17.1706 7.75568 16.449C5.25002 16.9685 4.72824 15.41 4.72824 15.41C4.32557 14.3999 3.72893 14.1403 3.72893 14.1403C2.90883 13.6064 3.78867 13.6064 3.78867 13.6064C4.69837 13.6642 5.17572 14.501 5.17572 14.501C5.98089 15.8285 7.27833 15.4534 7.8003 15.2225C7.87478 14.6597 8.11355 14.2701 8.36706 14.0537C6.36863 13.8517 4.26602 13.1014 4.26602 9.75364C4.26602 8.80129 4.6237 8.02213 5.19047 7.41615C5.10105 7.19976 4.7878 6.30496 5.28008 5.10735C5.28008 5.10735 6.04062 4.87643 7.75549 6.00197C9.22525 5.62006 10.7896 5.61702 12.2592 6.00197C13.9743 4.87643 14.7348 5.10735 14.7348 5.10735C15.2271 6.30496 14.9137 7.19976 14.8242 7.41615C15.4059 8.02213 15.7489 8.80129 15.7489 9.75364C15.7489 13.1014 13.6463 13.8372 11.6329 14.0537C11.9611 14.3279 12.2443 14.8472 12.2443 15.6698C12.2443 16.8385 12.2295 17.7765 12.2295 18.065C12.2295 18.2959 12.3936 18.5702 12.8409 18.4836C16.42 17.3291 19 14.0681 19 10.2155C19.0147 5.39582 14.9734 1.5 10.0074 1.5Z">
              </path>
            </svg>
            GitHub Marketplace
          </a>
        </li>
        <li>
          <a href="https://github.com/apps/gstraccini">
            <svg class="icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M10.0074 1.5C5.02656 1.5 1 5.39582 1 10.2155C1 14.0681 3.57996 17.3292 7.15904 18.4835C7.60652 18.5702 7.77043 18.2959 7.77043 18.0652C7.77043 17.8631 7.75568 17.1706 7.75568 16.449C5.25002 16.9685 4.72824 15.41 4.72824 15.41C4.32557 14.3999 3.72893 14.1403 3.72893 14.1403C2.90883 13.6064 3.78867 13.6064 3.78867 13.6064C4.69837 13.6642 5.17572 14.501 5.17572 14.501C5.98089 15.8285 7.27833 15.4534 7.8003 15.2225C7.87478 14.6597 8.11355 14.2701 8.36706 14.0537C6.36863 13.8517 4.26602 13.1014 4.26602 9.75364C4.26602 8.80129 4.6237 8.02213 5.19047 7.41615C5.10105 7.19976 4.7878 6.30496 5.28008 5.10735C5.28008 5.10735 6.04062 4.87643 7.75549 6.00197C9.22525 5.62006 10.7896 5.61702 12.2592 6.00197C13.9743 4.87643 14.7348 5.10735 14.7348 5.10735C15.2271 6.30496 14.9137 7.19976 14.8242 7.41615C15.4059 8.02213 15.7489 8.80129 15.7489 9.75364C15.7489 13.1014 13.6463 13.8372 11.6329 14.0537C11.9611 14.3279 12.2443 14.8472 12.2443 15.6698C12.2443 16.8385 12.2295 17.7765 12.2295 18.065C12.2295 18.2959 12.3936 18.5702 12.8409 18.4836C16.42 17.3291 19 14.0681 19 10.2155C19.0147 5.39582 14.9734 1.5 10.0074 1.5Z">
              </path>
            </svg>
            GitHub App
          </a>
        </li>
        <li>
          <a href="https://github.com/guibranco/gstraccini-bot">
            <svg class="icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd"
                d="M10.0074 1.5C5.02656 1.5 1 5.39582 1 10.2155C1 14.0681 3.57996 17.3292 7.15904 18.4835C7.60652 18.5702 7.77043 18.2959 7.77043 18.0652C7.77043 17.8631 7.75568 17.1706 7.75568 16.449C5.25002 16.9685 4.72824 15.41 4.72824 15.41C4.32557 14.3999 3.72893 14.1403 3.72893 14.1403C2.90883 13.6064 3.78867 13.6064 3.78867 13.6064C4.69837 13.6642 5.17572 14.501 5.17572 14.501C5.98089 15.8285 7.27833 15.4534 7.8003 15.2225C7.87478 14.6597 8.11355 14.2701 8.36706 14.0537C6.36863 13.8517 4.26602 13.1014 4.26602 9.75364C4.26602 8.80129 4.6237 8.02213 5.19047 7.41615C5.10105 7.19976 4.7878 6.30496 5.28008 5.10735C5.28008 5.10735 6.04062 4.87643 7.75549 6.00197C9.22525 5.62006 10.7896 5.61702 12.2592 6.00197C13.9743 4.87643 14.7348 5.10735 14.7348 5.10735C15.2271 6.30496 14.9137 7.19976 14.8242 7.41615C15.4059 8.02213 15.7489 8.80129 15.7489 9.75364C15.7489 13.1014 13.6463 13.8372 11.6329 14.0537C11.9611 14.3279 12.2443 14.8472 12.2443 15.6698C12.2443 16.8385 12.2295 17.7765 12.2295 18.065C12.2295 18.2959 12.3936 18.5702 12.8409 18.4836C16.42 17.3291 19 14.0681 19 10.2155C19.0147 5.39582 14.9734 1.5 10.0074 1.5Z">
              </path>
            </svg>
            Repository on GitHub
          </a>
        </li>
      </ul>
    </section>
  </section>

  <footer>
    <p>© 2024 <a href="https://bot.straccini.com">GStraccini-bot</a> | <a
        href="https://github.com/guibranco/gstraccini-bot-service">GitHub
        Repository</a> | Created by <a href="https://guilherme.straccini.com">Guilherme Branco
        Stracini</a></p>
  </footer>

</body>

</html>
