<header>
  <a href="https://bot.straccini.com">
    <img src="https://raw.githubusercontent.com/guibranco/gstraccini-bot-website/main/Src/logo.png" alt="GStraccini-bot Logo" class="logo">
  </a>
  <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat" class="octocat"> Automate your GitHub workflow effortlessly.</p>
</header>

<nav class="navbar">
  <ul>
    <li><a href="index.php">Home</a></li>
    <li><a href="<?php echo $isAuthenticated ? 'dashboard.php' : 'signin.php'; ?>"><?php echo $isAuthenticated ? 'Dashboard' : 'Sign-In'; ?></a></li>
    <li><a href="https://docs.bot.straccini.com" target="_blank">Docs</a></li>
  </ul>
</nav>

<script>
window.addEventListener('scroll', function() {
  const navbar = document.querySelector('.navbar');
  if (window.scrollY > document.querySelector('header').offsetHeight) {
    navbar.classList.add('show');
  } else {
    navbar.classList.remove('show'); 
  }
});

const menuToggle = document.createElement('button');
menuToggle.textContent = '☰';
menuToggle.style.position = 'absolute';
menuToggle.style.top = '20px';
menuToggle.style.right = '20px';
menuToggle.style.fontSize = '30px';
menuToggle.style.backgroundColor = 'transparent';
menuToggle.style.border = 'none';
menuToggle.style.color = '#fff';

document.body.appendChild(menuToggle);

menuToggle.addEventListener('click', function() {
  const navbar = document.querySelector('.navbar');
  navbar.classList.toggle('show');
});

</script>
