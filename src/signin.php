<?php
require_once "includes/session.php";

$redirectUrl = $_GET['redirectUrl'] ?? 'dashboard.php';
$redirectUrl = (substr($redirectUrl, 0, 1) === '/') ? $redirectUrl : '/' . $redirectUrl;
$baseUrl = 'https://bot.straccini.com';
$parsedUrl = parse_url($redirectUrl);
if (isset($parsedUrl['host']) && $parsedUrl['host'] !== parse_url($baseUrl, PHP_URL_HOST)) {
    error_log("Invalid redirect URL: " . $redirectUrl);
    $redirectUrl = 'dashboard.php';
}
$_SESSION['redirectUrl'] = $redirectUrl;

if ($isAuthenticated === true) {
    $redirectUrl = $_SESSION['redirectUrl'] ?? 'dashboard.php';
    $_SESSION['redirectUrl'] = null;
    header("Location: {$redirectUrl}");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GStraccini Bot | Login Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/static/main.css" />
    <style>
        .digit-inputs {
            display: flex;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .digit-inputs.digits-6 input {
            width: 40px;
            text-align: center;
        }

        .digit-inputs.digits-10 input {
            width: 32px;
            text-align: center;
        }

        #resendCodeBtn:disabled {
            background-color: lightgray;
            cursor: not-allowed;
        }

        .auth-step .btn-link {
            text-decoration: none;
        }

        .list-group-item-action i {
            width: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <?php require_once "includes/header-public.php"; ?>

    <div class="container my-5">
        <div class="row justify-content-center">

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="col-md-6">
                <h2 class="text-center mb-4">Sign in</h2>

                <!-- Step 1: GitHub or email -->
                <div id="step-start" class="auth-step">
                    <div class="mb-4">
                        <a href="https://bot.straccini.com/login.php" class="btn btn-dark w-100">
                            <i class="fab fa-github"></i> Continue with GitHub
                        </a>
                    </div>

                    <div class="text-center text-muted mb-3">or</div>

                    <form id="emailForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" placeholder="Enter your email"
                                required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Continue with email</button>
                    </form>
                </div>

                <!-- Step 2: choose how to verify the email -->
                <div id="step-method" class="auth-step d-none">
                    <button type="button" class="btn btn-link ps-0 mb-2" id="backToStart">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <p class="text-muted">Verifying <strong id="methodEmail"></strong></p>
                    <div class="list-group mb-3">
                        <button type="button" id="chooseFido"
                            class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-fingerprint"></i> Security key (FIDO)
                        </button>
                        <button type="button" id="choosePassword"
                            class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-key"></i> Password + Two-Factor Authentication
                        </button>
                        <button type="button" id="chooseRecovery"
                            class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-life-ring"></i> Recovery code
                        </button>
                    </div>
                </div>

                <!-- Step 3a: FIDO -->
                <div id="step-fido" class="auth-step d-none text-center">
                    <button type="button" class="btn btn-link ps-0 mb-2 back-btn" data-target="step-method">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <i class="fas fa-fingerprint fa-3x text-primary mb-3 d-block"></i>
                    <p>Follow your browser's prompt to verify with your security key or device biometrics.</p>
                    <div id="fidoError" class="text-danger mt-2"></div>
                    <button type="button" id="fidoRetryBtn" class="btn btn-primary w-100">Use security key</button>
                </div>

                <!-- Step 3b-i: password -->
                <div id="step-password" class="auth-step d-none">
                    <button type="button" class="btn btn-link ps-0 mb-2 back-btn" data-target="step-method">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <form id="passwordForm">
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password"
                                placeholder="Enter your password" required>
                        </div>
                        <div id="passwordError" class="text-danger mb-2"></div>
                        <div class="mb-3 text-end">
                            <a href="#" id="forgotPasswordLink" data-bs-toggle="modal"
                                data-bs-target="#forgotPasswordModal">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Continue</button>
                    </form>
                </div>

                <!-- Step 3b-ii: two-factor authentication -->
                <div id="step-2fa" class="auth-step d-none">
                    <button type="button" class="btn btn-link ps-0 mb-2 back-btn" data-target="step-password">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <p>Enter the 6-digit code from your authenticator app.</p>
                    <div class="digit-inputs digits-6 mb-3" data-length="6" id="twoFaInputs"></div>
                    <div id="twoFaError" class="text-danger mt-2"></div>
                    <button type="button" id="verify2faBtn" class="btn btn-primary w-100 mt-2">Verify</button>
                    <div class="text-center mt-3">
                        <a href="#" id="useRecoveryInstead">Use a recovery code instead</a>
                    </div>
                </div>

                <!-- Step 3c: recovery code -->
                <div id="step-recovery" class="auth-step d-none">
                    <button type="button" class="btn btn-link ps-0 mb-2 back-btn" data-target="step-method">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <p>Enter one of your 10-digit recovery codes.</p>
                    <div class="digit-inputs digits-10 mb-3" data-length="10" id="recoveryInputs"></div>
                    <div id="recoveryError" class="text-danger mt-2"></div>
                    <button type="button" id="verifyRecoveryBtn" class="btn btn-primary w-100 mt-2">Verify</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="resetRequestStep">
                        <p>We'll generate a password reset token for <strong id="resetEmailDisplay"></strong>.</p>
                        <div id="devResetTokenNotice" class="alert alert-warning d-none"></div>
                    </div>
                    <div id="resetCompleteStep" class="d-none">
                        <div class="mb-3">
                            <label for="resetToken" class="form-label">Reset token</label>
                            <input type="text" class="form-control" id="resetToken" placeholder="Paste the reset token">
                        </div>
                        <div class="mb-3">
                            <label for="resetNewPassword" class="form-label">New password</label>
                            <input type="password" class="form-control" id="resetNewPassword" minlength="6"
                                placeholder="At least 6 characters">
                        </div>
                    </div>
                    <div id="forgotPasswordError" class="text-danger mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="requestResetBtn" class="btn btn-primary">Send reset token</button>
                    <button type="button" id="verifyCodeBtn" class="btn btn-primary d-none">Reset password</button>
                </div>
            </div>
        </div>
    </div>

    <?php require_once "includes/footer-public.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const steps = ['step-start', 'step-method', 'step-fido', 'step-password', 'step-2fa', 'step-recovery'];

        function showStep(id) {
            steps.forEach(step => $('#' + step).toggleClass('d-none', step !== id));
        }

        function buildDigitInputs($container) {
            const length = parseInt($container.data('length'), 10);
            $container.empty();
            for (let i = 0; i < length; i++) {
                $container.append('<input type="text" maxlength="1" class="form-control" inputmode="numeric">');
            }
        }

        $('.digit-inputs').each(function () {
            buildDigitInputs($(this));
        });

        $(document).on('input', '.digit-inputs input', function () {
            if (this.value.length === 0) {
                $(this).prev().focus();
            } else if (this.value.length === 1) {
                $(this).next().focus();
            }
        });

        $(document).on('paste', '.digit-inputs input', function (e) {
            const $inputs = $(this).closest('.digit-inputs').find('input');
            const length = $inputs.length;
            const pasteData = (e.originalEvent || e).clipboardData.getData('text/plain').trim();

            if (pasteData.length === length) {
                $inputs.each(function (index) {
                    $(this).val(pasteData[index]);
                });
                $inputs.last().focus();
            }

            e.preventDefault();
        });

        function digitCode($container) {
            return $container.find('input').map(function () {
                return $(this).val();
            }).get().join('');
        }

        let currentEmail = '';
        let currentLoginUserId = null;

        $('#emailForm').submit(function (event) {
            event.preventDefault();
            currentEmail = $('#email').val();
            $('#methodEmail').text(currentEmail);
            showStep('step-method');
        });

        $('#backToStart').click(function () {
            showStep('step-start');
        });

        $('.back-btn').click(function () {
            showStep($(this).data('target'));
        });

        $('#chooseFido').click(function () {
            $('#fidoError').text('');
            showStep('step-fido');
        });

        $('#fidoRetryBtn').click(function () {
            alert('Security key sign-in is not available yet.');
        });

        $('#choosePassword').click(function () {
            $('#passwordError').text('');
            showStep('step-password');
        });

        /**
         * Redirects to the dashboard on a fully-verified login. Note: this
         * login path is not backed by a GitHub OAuth token (unlike "Continue
         * with GitHub"), so pages that call the GitHub API directly on the
         * user's behalf will not work for it yet.
         */
        function completeLogin() {
            window.location.href = 'dashboard.php';
        }

        $('#passwordForm').submit(function (event) {
            event.preventDefault();
            $('#passwordError').text('');

            const password = $('#password').val();

            $.ajax({
                url: '/api/v1/auth/login',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ email: currentEmail, password: password }),
            }).done(function (data) {
                currentLoginUserId = data.userId;
                if (data.status === 'totp_required') {
                    buildDigitInputs($('#twoFaInputs'));
                    $('#twoFaError').text('');
                    showStep('step-2fa');
                } else {
                    completeLogin();
                }
            }).fail(function (xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.error) || 'Invalid email or password.';
                $('#passwordError').text(message);
            });
        });

        $('#verify2faBtn').click(function () {
            const code = digitCode($('#twoFaInputs'));
            if (code.length !== 6) {
                $('#twoFaError').text('Please enter the 6-digit code.');
                return;
            }

            $.ajax({
                url: '/api/v1/auth/login/verify-totp',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ userId: currentLoginUserId, code: code }),
            }).done(function () {
                completeLogin();
            }).fail(function (xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.error) || 'Invalid code.';
                $('#twoFaError').text(message);
            });
        });

        $('#useRecoveryInstead').click(function (event) {
            event.preventDefault();
            buildDigitInputs($('#recoveryInputs'));
            $('#recoveryError').text('');
            showStep('step-recovery');
        });

        $('#chooseRecovery').click(function () {
            buildDigitInputs($('#recoveryInputs'));
            $('#recoveryError').text('');
            showStep('step-recovery');
        });

        $('#verifyRecoveryBtn').click(function () {
            const code = digitCode($('#recoveryInputs'));
            if (code.length !== 10) {
                $('#recoveryError').text('Please enter the full 10-digit recovery code.');
                return;
            }

            $.ajax({
                url: '/api/v1/auth/login/verify-recovery',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ userId: currentLoginUserId, code: code }),
            }).done(function () {
                completeLogin();
            }).fail(function (xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.error) || 'Invalid or already-used recovery code.';
                $('#recoveryError').text(message);
            });
        });

        $('#forgotPasswordModal').on('show.bs.modal', function () {
            $('#resetEmailDisplay').text(currentEmail);
            $('#resetRequestStep').removeClass('d-none');
            $('#resetCompleteStep').addClass('d-none');
            $('#devResetTokenNotice').addClass('d-none').text('');
            $('#requestResetBtn').removeClass('d-none');
            $('#verifyCodeBtn').addClass('d-none');
            $('#resetToken').val('');
            $('#resetNewPassword').val('');
            $('#forgotPasswordError').text('');
        });

        $('#requestResetBtn').click(function () {
            $('#forgotPasswordError').text('');

            $.ajax({
                url: '/api/v1/auth/password-reset/request',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ email: currentEmail }),
            }).done(function (data) {
                if (data.devResetToken) {
                    $('#devResetTokenNotice').removeClass('d-none').html(
                        '<strong>Dev mode:</strong> email delivery is not wired up yet, so here is the token directly: ' +
                        '<code>' + $('<div>').text(data.devResetToken).html() + '</code>'
                    );
                    $('#resetToken').val(data.devResetToken);
                }
                $('#resetCompleteStep').removeClass('d-none');
                $('#requestResetBtn').addClass('d-none');
                $('#verifyCodeBtn').removeClass('d-none');
            }).fail(function () {
                $('#forgotPasswordError').text('Failed to request a password reset. Please try again.');
            });
        });

        $('#verifyCodeBtn').click(function () {
            const token = $('#resetToken').val().trim();
            const newPassword = $('#resetNewPassword').val();

            if (token === '' || newPassword.length < 6) {
                $('#forgotPasswordError').text('Please provide the reset token and a password of at least 6 characters.');
                return;
            }

            $.ajax({
                url: '/api/v1/auth/password-reset/verify',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ token: token, newPassword: newPassword }),
            }).done(function () {
                $('#forgotPasswordModal').modal('hide');
                showStep('step-password');
                $('#passwordError').removeClass('text-danger').addClass('text-success')
                    .text('Password updated — sign in with your new password.');
            }).fail(function (xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.error) || 'Invalid or expired token.';
                $('#forgotPasswordError').text(message);
            });
        });
    </script>
</body>

</html>
