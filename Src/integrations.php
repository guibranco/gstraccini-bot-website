<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
   header('Location: login.php');
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>GStraccini-bot | <?php echo $title; ?></title>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   <link rel="stylesheet" href="user.css">
</head>

<body>
   <?php require_once 'includes/header.php'; ?>
   <div class="container mt-5">
      <h1 class="text-center">Integration details</h1>
      <p class="text-center">Manage your integrations.</p>
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
                     <div class="mb-3 position-relative">
                        <label for="sonarcloud" class="form-label">SonarCloud API Key</label>
                        <div class="input-group">
                           <input type="password" class="form-control" id="sonarcloud"
                              placeholder="Enter SonarCloud API Key">
                           <span class="input-group-text">
                              <i class="fas fa-eye toggle-visibility" data-target="sonarcloud"></i>
                           </span>
                        </div>
                     </div>
                     <div class="mb-3 position-relative">
                        <label for="appveyor" class="form-label">AppVeyor
                           API Token</label>
                        <div class="input-group">
                           <input type="password" class="form-control" id="appveyor"
                              placeholder="Enter AppVeyor API Token">
                           <span class="input-group-text">
                              <i class="fas fa-eye toggle-visibility" data-target="appveyor"></i>
                           </span>
                        </div>
                     </div>
                     <div class="mb-3 position-relative">
                        <label for="codacy" class="form-label">Codacy
                           Project Token</label>
                        <div class="input-group">
                           <input type="password" class="form-control" id="codacy"
                              placeholder="Enter Codacy Project Token">
                           <span class="input-group-text">
                              <i class="fas fa-eye toggle-visibility" data-target="codacy"></i>
                           </span>
                        </div>
                     </div>
                     <div class="mb-3 position-relative">
                        <label for="codecov" class="form-label">Codecov
                           Upload Token</label>
                        <div class="input-group">
                           <input type="password" class="form-control" id="codecov"
                              placeholder="Enter Codecov Upload Token">
                           <span class="input-group-text">
                              <i class="fas fa-eye toggle-visibility" data-target="codecov"></i>
                           </span>
                        </div>
                     </div>
                     <div class="mb-3 position-relative">
                        <label for="deepsource" class="form-label">DeepSource API Key</label>
                        <div class="input-group">
                           <input type="password" class="form-control" id="deepsource"
                              placeholder="Enter DeepSource API Key">
                           <span class="input-group-text">
                              <i class="fas fa-eye toggle-visibility" data-target="deepsource"></i>
                           </span>
                        </div>
                     </div>
                     <div class="mb-3 position-relative">
                        <label for="codeclimate" class="form-label">CodeClimate Test Reporter
                           ID</label>
                        <div class="input-group">
                           <input type="password" class="form-control" id="codeclimate"
                              placeholder="Enter CodeClimate Test Reporter ID">
                           <span class="input-group-text">
                              <i class="fas fa-eye toggle-visibility" data-target="codeclimate"></i>
                           </span>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="text-center">
                  <button type="submit" class="btn btn-primary">Save Integration Details</button>
                  <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
               </div>
            </form>
         </div>
      </div>
   </div>
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
   <script>
      $(document).ready(function () {
         $('.toggle-visibility').on('click', function () {
            const targetInputId = $(this).data('target');
            const inputField = $('#' + targetInputId);
            const icon = $(this);

            if (inputField.attr('type') === 'password') {
               inputField.attr('type', 'text');
               icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
               inputField.attr('type', 'password');
               icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
         });

         $('#integrationsForm').on('submit', function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (form.checkValidity() === false) {
               form.classList.add('was-validated');
            } else {
               form.classList.remove('was-validated');
               form.submit();
            }
         });
      });
   </script>
</body>

</html>