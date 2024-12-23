<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];
$integrations = $_SESSION['integrations'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['provider'] ?? "";
    $apiKey = $_POST['apiKey'] ?? "";
    if (!empty($provider) && !empty($apiKey)) {
        $isValid = strlen($apiKey) >= 10;
        if ($isValid) {
            if (!isset($integrations[$provider])) {
                $integrations[$provider] = [
                    'apiKey' => $apiKey,
                    'status' => 'Validated',
                    'lastUsage' => 'N/A',
                    'lastError' => 'N/A',
                ];
                $_SESSION['integrations'] = $integrations;
                $message = "Integration for $provider added successfully!";
            } else {
                $error = "Integration for $provider already exists.";
            }
        } else {
            $error = "Invalid API key for $provider.";
        }
    } else {
        $error = "Please select a provider and enter a valid API Key.";
    }
}

if (isset($_GET['remove'])) {
    $providerToRemove = $_GET['remove'];
    unset($integrations[$providerToRemove]);
    $_SESSION['integrations'] = $integrations;
    $message = "Integration for $providerToRemove removed successfully!";
}

$title = "Integration Details";
$providers = [
    "SonarCloud" => "https://cdn.simpleicons.org/Sonarcloud",
    "AppVeyor" => "https://cdn.simpleicons.org/Appveyor",
    "Codacy" => "https://cdn.simpleicons.org/Codacy",
    "Codecov" => "https://cdn.simpleicons.org/Codecov",
    "DeepSource" => "/images/Deepsource.png",
    "CodeClimate" => "https://cdn.simpleicons.org/Codeclimate",
    "Snyk" => "https://cdn.simpleicons.org/Snyk",
    "OpenAI" => "https://cdn.simpleicons.org/Openai",
    "Llama" => "/images/Llama.png",
    "CPanel" => "https://cdn.simpleicons.org/Cpanel",
];

function maskApiKey($apiKey)
{
    $visibleLength = 4;
    $maskedLength = strlen($apiKey) - ($visibleLength * 2);
    return substr($apiKey, 0, $visibleLength) . str_repeat('*', $maskedLength) . substr($apiKey, -$visibleLength);
}
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

        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header">
                <h2>Add Integration</h2>
            </div>
            <div class="card-body">
                <form action="integrations.php" method="POST" id="addIntegrationForm" novalidate>
                    <div class="mb-3 position-relative">
                        <label for="providerDropdown" class="form-label">Select Provider</label>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle w-100" type="button" id="providerDropdown"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                Select a provider
                            </button>
                            <ul class="dropdown-menu w-100" aria-labelledby="providerDropdown">
                                <?php foreach ($providers as $providerName => $logo): ?>
                                    <a class="dropdown-item d-flex align-items-center" data-value="<?php echo $providerName; ?>"
                                        data-logo="<?php echo $logo; ?>">
                                        <img src="<?php echo $logo; ?>" alt="<?php echo $providerName; ?> logo"
                                            class="provider-logo me-2" /> <?php echo $providerName; ?>
                                    </a>
                                <?php endforeach; ?>
                                <input type="hidden" name="provider" id="providerSelect">
                            </ul>
                        </div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="apiKey" class="form-label">API Key</label>
                        <div class="input-group">
                            <input type="password" name="apiKey" id="apiKey" class="form-control"
                                placeholder="Enter API Key" aria-label="API Key">
                            <span class="input-group-text" id="toggleVisibility">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span id="badgeStatus" class="badge" style="display:none;"></span>
                            <button class="btn btn-primary" id="saveBtn">Save API Key</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($integrations)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Integrations</h2>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>API Key</th>
                                <th>Status</th>
                                <th>Last Usage</th>
                                <th>Last Error</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($integrations as $provider => $details): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $providers[$provider]; ?>" alt="<?php echo $provider; ?>"
                                            class="provider-logo">
                                        <?php echo htmlspecialchars($provider); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(maskApiKey($details['apiKey'])); ?></td>
                                    <td><?php echo htmlspecialchars($details['status']); ?></td>
                                    <td><?php echo htmlspecialchars($details['lastUsage']); ?></td>
                                    <td><?php echo htmlspecialchars($details['lastError']); ?></td>
                                    <td>
                                        <a href="integrations.php?remove=<?php echo urlencode($provider); ?>"
                                            class="btn btn-danger btn-sm">Remove</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once "includes/footer.php"; ?>
    <script>
        $(document).ready(function () {
            $("#toggleVisibility").click(toggleVisibility);
            $("#saveBtn").click(saveIntegration);

            $(document).on('click', '.dropdown-item', function () {
                const selectedProvider = $(this).data('value');
                const providerLogo = $(this).data('logo');
                const providerName = $(this).data('value');

                $('#providerDropdown')
                    .html(
                        `<span class="provider-label">
                    <img src="${providerLogo}" alt="${providerName} Logo" class="provider-logo me-2" />
                    ${providerName}
                </span>`
                    );
                $('#providerSelect').val(selectedProvider);
            });
        });

        let isVisible = false;

        function toggleVisibility() {
            const input = $('#apiKey');
            const icon = $('#toggleVisibility svg');

            if (isVisible) {
                input.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                input.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
            isVisible = !isVisible;
        }

        function saveIntegration() {
            const provider = $('#providerSelect').val();
            const apiKey = $('#apiKey').val();

            if (!provider || apiKey.length < 10) {
                alert('Please select a provider and enter a valid API Key (minimum 8 characters).');
                return;
            }

            $("#addIntegrationForm").submit();
        }
    </script>
</body>

</html>