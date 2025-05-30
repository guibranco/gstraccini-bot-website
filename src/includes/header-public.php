<header>
  <a href="https://bot.straccini.com">
    <img src="/images/logo.png" alt="GStraccini-bot Logo" class="logo">
  </a>
  <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat" class="octocat">
    Automate your GitHub workflow effortlessly.</p>
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
    let ticking = false;

    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          menu.classList.toggle('hidden', window.scrollY > headerHeight);
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  });
</script>