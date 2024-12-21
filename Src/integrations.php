<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
   header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
   exit();
}

$user = $_SESSION['user'];
$details = [];

if (isset($_SESSION['details'])) {
   $details = $_SESSION['details'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   header("Location: integrations.php?details_updated=true");
   exit();
}

$title = "Integration Details";

// Define integrations array
$integrations = [
   [
      'id' => 'sonarcloud',
      'label' => 'SonarCloud API Key',
      'icon' => 'https://cdn.simpleicons.org/Sonarcloud',
   ],
   [
      'id' => 'appveyor',
      'label' => 'AppVeyor API Token',
      'icon' => 'https://cdn.simpleicons.org/Appveyor',
   ],
   [
      'id' => 'codacy',
      'label' => 'Codacy Project Token',
      'icon' => 'https://cdn.simpleicons.org/Codacy',
   ],
   [
      'id' => 'codecov',
      'label' => 'Codecov Upload Token',
      'icon' => 'https://cdn.simpleicons.org/Codecov',
   ],
   [
      'id' => 'deepsource',
      'label' => 'DeepSource API Key',
      'icon' => '/images/Deepsource.png',
   ],
   [
      'id' => 'codeclimate',
      'label' => 'CodeClimate Test Reporter ID',
      'icon' => 'https://cdn.simpleicons.org/Codeclimate',
   ],
   [
      'id' => 'snyk',
      'label' => 'Snyk Auth Token',
      'icon' => 'https://cdn.simpleicons.org/Snyk',
   ],
   [
      'id' => 'openai',
      'label' => 'OpenAI API Key',
      'icon' => 'https://cdn.simpleicons.org/Openai',
   ],
   [
      'id' => 'llama',
      'label' => 'LLAMA API Key',
      'icon' => '/images/Llama.png',
   ],
   [
      'id' => 'cpanel',
      'label' => 'CPanel API Key',
      'icon' => 'https://cdn.simpleicons.org/Cpanel',
   ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>GStraccini-bot | <?php echo $title; ?></title>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   <link rel="stylesheet" href="/static/user.css">
</head>

<body>
   <?php require_once 'includes/header.php'; ?>
   <div class="container mt-5">
      <h1 class="text-center">Integrations</h1>
      <p class="text-center">Manage your account integrations below.</p>
      <?php if (isset($_GET['details_updated'])): ?>
         <div class="alert alert-success alert-dismissible fade show" role="alert">
            Your integration details have been updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
         </div>
      <?php endif; ?>
      <div class="row">
         <div class="col-md-8 offset-md-2">
            <form action="integrations.php" method="POST" id="integrationsForm" novalidate>
               <div class="card mt-4">
                  <div class="card-header">
                     <h2>Integration Details</h2>
                  </div>
                  <div class="card-body">
                     <?php foreach ($integrations as $integration): ?>
                        <div class="mb-3 position-relative">
                           <label for="<?php echo $integration['id']; ?>" class="form-label">
                              <img height="24" width="24" src="<?php echo $integration['icon']; ?>"
                                 alt="<?php echo $integration['id']; ?>" />
                              <?php echo $integration['label']; ?>
                           </label>
                           <div class="input-group">
                              <input type="password" class="form-control" id="<?php echo $integration['id']; ?>"
                                 placeholder="ENter <?php echo $integration['label']; ?>"
                                 name="<?php echo $integration['id']; ?>">
                              <span class="input-group-text">
                                 <i class="fas fa-eye toggle-visibility"
                                    data-target="<?php echo $integration['id']; ?>"></i>
                              </span>
                           </div>
                        </div>
                     <?php endforeach; ?>
                  </div>
               </div>

               <div class="text-center mt-4">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                  <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
               </div>
            </form>
         </div>
      </div>
   </div>

   <?php require_once "includes/footer.php"; ?>
   <script>
      document.addEventListener('DOMContentLoaded', function () {
         document.querySelectorAll('.input-group-text').forEach(btn => {
            btn.addEventListener('click', function () {
               const icon = this.querySelector('.fas');
               const targetInputId = this.getAttribute('data-target');
               const inputField = document.getElementById(targetInputId);

               if (inputField.type === 'password') {
                  inputField.type = 'text';
                  icon.classList.remove('fa-eye');
                  icon.classList.add('fa-eye-slash');
               } else {
                  inputField.type = 'password';
                  icon.classList.remove('fa-eye-slash');
                  icon.classList.add('fa-eye');
               }
            });
         });
      });
   </script>
</body>

</html>