<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f4f4;
            color: #333;
        }

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

        img.octocat {
            width: 20px;
            vertical-align: middle;
            margin-top: -2px;
        }
    </style>
</head>

<body>
    <header>
        <a href="https://bot.straccini.com">
            <img src="https://bot.straccini.com/logo.png" alt="GStraccini-bot Logo" class="img-fluid" width="150">
        </a>
        <p>🤖 <img src="https://github.githubassets.com/images/icons/emoji/octocat.png" alt="GitHub Octocat"
                class="octocat"> Automate your GitHub workflow effortlessly.</p>
    </header>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center mb-4">Login</h2>

                <!-- GitHub Login -->
                <div class="mb-4">
                    <a href="https://bot.straccini.com/login.php" class="btn btn-dark w-100">
                        <i class="fab fa-github"></i> Login with GitHub
                    </a>
                </div>

                <div class="text-center mb-3">OR</div>

                <!-- Email/Password Login Form -->
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
                        <!-- Password Recovery Link -->
                        <a href="#" data-bs-toggle="modal" data-bs-target="#recoverModal">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Forgot Password Code -->
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

    <!-- Modal for Authentication Options -->
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

    <footer>
        <p>© 2024 GStraccini-bot. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // User account settings
        const fidoEnabled = true; // Replace with actual user account setting
        const authAppEnabled = true; // Replace with actual user account setting
        let timer;
        let countdown = 300; // 5 minutes in seconds

        // Update FIDO and Auth App links based on user settings
        if (fidoEnabled) {
            $('#fidoLink').removeClass('disabled-link').addClass('link').text('Select');
        }
        if (authAppEnabled) {
            $('#authAppLink').removeClass('disabled-link').addClass('link').text('Select');
        }

        // Handle Email/Password login submission
        $('#loginForm').submit(function (event) {
            event.preventDefault();
            const email = $('#email').val();
            const password = $('#password').val();

            // Show authentication options modal
            $('#authOptionsModal').modal('show');
        });

        // Handle Forgot Password
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

        // Verify 6-Digit Code in Forgot Password
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

        // Handle Authentication Method Selection
        $('#authOptionsModal').on('click', '.link', function (event) {
            const selectedLink = $(this).attr('id');
            $('#authMethodInput').removeClass('d-none');

            // Clear previous input fields
            $('#dynamicInputContainer').empty();
            $('#authError').text('');

            if (selectedLink === 'fidoLink') {
                alert('Requesting FIDO validation...');
                // Request FIDO validation logic here
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

                // Ensure the pasted data is exactly 6 digits
                if (pasteData.length === length && /^[0-9]+$/.test(pasteData)) {
                    $inputs.each(function (index) {
                        if (index < length) {  // Only fill the first N inputs
                            $(this).val(pasteData[index]);
                        }
                    });
                }

                // Prevent default paste behavior
                e.preventDefault();
            });
        });

        // Handle Authentication Button
        $('#authenticateBtn').click(function () {
            const authCode = $('#dynamicInputContainer').find('input').map(function () {
                return $(this).val();
            }).get().join('');

            if (authCode.length === 6 || authCode.length === 10) {
                alert('Authenticating with code: ' + authCode);
                // Proceed with authentication logic
            } else {
                $('#authError').text('Please enter a valid code.');
            }
        });

        // Countdown Timer for Resending Code
        function startCountdown() {
            $('#timer').text('You can resend the code in 5:00');
            $('#resendCodeBtn').prop('disabled', true);
            countdown = 300; // Reset countdown to 5 minutes

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