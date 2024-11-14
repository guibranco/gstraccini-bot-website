<header>
  <a href="https://bot.straccini.com">
    <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png" alt="GStraccini-bot Logo" class="logo">
  </a>
  <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat" class="octocat"> Automate your GitHub workflow effortlessly.</p>
</header>

<nav class="menu">
  <ul>
    <li><a href="/">Home</a></li>
    <li><a href="<?php echo ($isAuthenticated) ? '/dashboard.php' : '/signin.php'; ?>">
        <?php echo ($isAuthenticated) ? 'Dashboard' : 'Sign-In/Sign-Up'; ?>
      </a>
    </li>
    <li><a href="https://docs.bot.straccini.com" target="_blank">Documentation</a></li>
  </ul>
</nav>

<script>
  document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('.menu');
  const headerHeight = document.querySelector('header').offsetHeight;

  window.addEventListener('scroll', () => {
    if (window.scrollY > headerHeight) {
      menu.classList.add('hidden');
    } else {
      menu.classList.remove('hidden');
    }
  });
});
</script>
