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
if ($isAuthenticated === true) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="main.css" />
    <style>
        .input-group-6digit input {
            width: 40px;
            text-align: center;
            margin: 0 2px;
        }

        .input-group-10digit input {
            width: 30px;
            text-align: center;
            margin: 0 2px;
        }

        .disabled-link {
            pointer-events: none;
            color: gray;
        }

        #resendCodeBtn:disabled {
            background-color: lightgray;
            cursor: not-allowed;
        }       
    </style>
</head>

<body>
    <?php include_once "includes/header-public.php"; ?>

    <div class="container my-5">
        <div class="row justify-content-center">

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="col-md-6">
                <h2 class="text-center mb-4">Login</h2>

                <div class="mb-4">
                    <a href="https://bot.straccini.com/login.php" class="btn btn-dark w-100">
                        <i class="fab fa-github"></i> Login with GitHub
                    </a>
                </div>

                <div class="text-center mb-3">OR</div>

                <form id="loginForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" placeholder="Enter your email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" placeholder="Enter your password"
                            required>
                    </div>
                    <div class="mb-3 text-end">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#recoverModal">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Verify Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>A 6-digit code has been sent to your email. Please enter it below to reset your password.</p>
                    <div class="input-group input-group-6digit">
                        <input type="text" maxlength="1" class="form-control" id="code1" required>
                        <input type="text" maxlength="1" class="form-control" id="code2" required>
                        <input type="text" maxlength="1" class="form-control" id="code3" required>
                        <input type="text" maxlength="1" class="form-control" id="code4" required>
                        <input type="text" maxlength="1" class="form-control" id="code5" required>
                        <input type="text" maxlength="1" class="form-control" id="code6" required>
                    </div>
                    <div id="forgotPasswordError" class="text-danger mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="verifyCodeBtn" class="btn btn-primary">Verify Code</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="authOptionsModal" tabindex="-1" aria-labelledby="authOptionsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="authOptionsModalLabel">Select Authentication Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please select an authentication method:</p>
                    <ul class="list-group">
                        <li class="list-group-item">
                            <span class="me-2">FIDO</span>
                            <a href="#" id="fidoLink" class="disabled-link">Disabled</a>
                        </li>
                        <li class="list-group-item">
                            <span class="me-2">Auth App (2FA)</span>
                            <a href="#" id="authAppLink" class="disabled-link">Disabled</a>
                        </li>
                        <li class="list-group-item">
                            <span class="me-2">E-mail Code (6-digit)</span>
                            <a href="#" id="emailCodeLink" class="link">Select</a>
                        </li>
                        <li class="list-group-item">
                            <span class="me-2">Recovery Code (10-digit)</span>
                            <a href="#" id="recoveryCodeLink" class="link">Select</a>
                        </li>
                    </ul>
                    <div id="authMethodInput" class="mt-3 d-none">
                        <div class="mb-3">
                            <label for="authCode" class="form-label">Enter Code</label>
                            <div id="dynamicInputContainer"></div>
                        </div>
                        <div id="authError" class="text-danger mt-2"></div>
                        <div id="timer" class="text-warning"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="authenticateBtn" class="btn btn-primary">Authenticate</button>
                    <button type="button" id="resendCodeBtn" class="btn btn-secondary" disabled>Resend Code</button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once "includes/footer-public.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const fidoEnabled = true;
        const authAppEnabled = true;
        let timer;
        let countdown = 300;

        if (fidoEnabled) {
            $('#fidoLink').removeClass('disabled-link').addClass('link').text('Select');
        }
        if (authAppEnabled) {
            $('#authAppLink').removeClass('disabled-link').addClass('link').text('Select');
        }

        $('#loginForm').submit(function (event) {
            event.preventDefault();
            const email = $('#email').val();
            const password = $('#password').val();
            $('#authOptionsModal').modal('show');
        });

        $('#forgotPasswordLink').click(function (event) {
            event.preventDefault();
            const email = $('#email').val();
            if (email === '') {
                alert('Please enter your email before requesting a password reset.');
            } else {
                alert('Sending password reset code to ' + email);
                $('#forgotPasswordModal').modal('show');
            }
        });

        $('#verifyCodeBtn').click(function () {
            const code = $('#code1').val() + $('#code2').val() + $('#code3').val() + $('#code4').val() + $('#code5').val() + $('#code6').val();
            if (code.length === 6) {
                alert('Code verified successfully');
                $('#forgotPasswordModal').modal('hide');
                // Proceed with password reset
            } else {
                $('#forgotPasswordError').text('Invalid code. Please check the digits.');
            }
        });

        $('#authOptionsModal').on('click', '.link', function (event) {
            const selectedLink = $(this).attr('id');
            $('#authMethodInput').removeClass('d-none');
            $('#dynamicInputContainer').empty();
            $('#authError').text('');

            if (selectedLink === 'fidoLink') {
                alert('Requesting FIDO validation...');
                $('#authOptionsModal').modal('hide');
            } else if (selectedLink === 'authAppLink') {
                $('#dynamicInputContainer').append(`
                <div class="input-group input-group-6digit">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                </div>
            `);
            } else if (selectedLink === 'emailCodeLink') {
                $('#dynamicInputContainer').append(`
                <div class="input-group input-group-6digit">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                </div>
            `);
                startCountdown();
            } else if (selectedLink === 'recoveryCodeLink') {
                $('#dynamicInputContainer').append(`
                <div class="input-group input-group-10digit">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                    <input type="text" maxlength="1" class="form-control">
                </div>
            `);
            }

            $('#dynamicInputContainer').on('input', 'input', function () {
                if (this.value.length === 0) {
                    $(this).prev().focus();
                } else if (this.value.length === 1) {
                    $(this).next().focus();
                }
            });

            $('#dynamicInputContainer input').on('paste', function (e) {
                const $inputs = $('#dynamicInputContainer input');
                const length = $inputs.length;
                const pasteData = (e.originalEvent || e).clipboardData.getData('text/plain');

                if (pasteData.length === length && /^[0-9]+$/.test(pasteData)) {
                    $inputs.each(function (index) {
                        if (index < length) {
                            $(this).val(pasteData[index]);
                        }
                    });
                }

                e.preventDefault();
            });
        });

        $('#authenticateBtn').click(function () {
            const authCode = $('#dynamicInputContainer').find('input').map(function () {
                return $(this).val();
            }).get().join('');

            if (authCode.length === 6 || authCode.length === 10) {
                alert('Authenticating with code: ' + authCode);
            } else {
                $('#authError').text('Please enter a valid code.');
            }
        });

        function startCountdown() {
            $('#timer').text('You can resend the code in 5:00');
            $('#resendCodeBtn').prop('disabled', true);
            countdown = 300;

            timer = setInterval(function () {
                const minutes = Math.floor(countdown / 60);
                const seconds = countdown % 60;
                $('#timer').text(`You can resend the code in ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`);
                countdown--;

                if (countdown < 0) {
                    clearInterval(timer);
                    $('#resendCodeBtn').prop('disabled', false);
                    $('#timer').text('You can now resend the code.');
                }
            }, 1000);
        }
    </script>
</body>
</html>
