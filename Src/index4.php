<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Introducing Your GStraccini-bot - Automate GitHub tasks with ease.">
  <title>GStraccini-bot - Automate Your Workflow</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Global Styles */
    body {
      font-family: 'Arial', sans-serif;
      margin: 0;
      padding: 0;
      background: #f4f4f4;
      color: #333;
    }

    /* Header */
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

    /* Hero Section */
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

    /* Features Section */
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

    /* Footer */
    footer {
      background-color: #f1f1f1;
      padding: 20px;
      text-align: center;
    }

    footer p {
      margin: 0;
      color: #777;
    }

    /* Animation */
    .fade-in {
      opacity: 0;
      animation: fadeIn 1.5s forwards;
    }

    @keyframes fadeIn {
      0% { opacity: 0; }
      100% { opacity: 1; }
    }
  </style>
</head>
<body>
  <header>
    <h1>GStraccini-bot</h1>
    <p>Automate your GitHub workflow effortlessly</p>
  </header>

  <section class="hero fade-in">
    <h2>Boost Your GitHub Efficiency</h2>
    <p>Get more done by automating repetitive tasks.</p>
    <button class="cta-button">Get Started</button>
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
