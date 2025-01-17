<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];
$data = $_SESSION["data"] ?? array("repositories" => []);

$title = "Repositories";

$name = $user["login"];
if (isset($user["first_name"])) {
    $name = $user["first_name"];
} else if (isset($user["name"])) {
    $name = $user["name"];
}

$selectedOrganization = $_GET['organization'] ?? '';
$filteredRepositories = $data['repositories'];
if (!empty($selectedOrganization)) {
    $filteredRepositories = array_filter($filteredRepositories, function ($repo) use ($selectedOrganization) {
        return $repo['organization'] === $selectedOrganization;
    });
}
$organizations = array_unique(array_column($data['repositories'], 'organization'));
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

    <div class="container mt-5 d-none" id="alert-container"></div>

    <div class="container mt-5">
        <div class="row mt-3">
            <div class="col-md-4">
                <label for="organizationFilter" class="form-label">Filter by Organization</label>
                <select id="organizationFilter" class="form-select">
                    <option value="">All Organizations</option>
                    <?php
                    foreach ($organizations as $organization) {
                        $selected = $selectedOrganization === $organization ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($organization) . "\" $selected>" . htmlspecialchars($organization) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="row mt-5">
            <div class="col-md-12">
                <h3>Your Repositories <span class="badge text-bg-warning rounded-pill"
                        id="repositoriesCount"><?php echo count($filteredRepositories); ?></span></h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Organization</th>
                            <th scope="col">Name</th>
                            <th scope="col">Stars</th>
                            <th scope="col">Fork</th>
                            <th scope="col">Forks</th>
                            <th scope="col">Open Issues</th>
                            <th scope="col">Languages</th>
                            <th scope="col">Visibility</th>
                        </tr>
                    </thead>
                    <tbody id="repositories">
                        <?php if (count($filteredRepositories) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($filteredRepositories as $repo): ?>
                            <tr class="repository-row"
                                data-organization="<?php echo htmlspecialchars($repo['organization']); ?>">
                                <td><?php echo htmlspecialchars($repo['organization']) ?></td>
                                <td><a href='<?php echo $repo['url']; ?>'
                                        target='_blank'><?php echo htmlspecialchars($repo['name']); ?></a></td>
                                <td><i class="fas fa-star status-suspended"></i> <?php echo $repo['stars']; ?></td>
                                <td><?php echo $repo['fork'] ? '<i class="fas fa-circle-check status-installed"></i> Yes' : '<i class="fas fa-circle-xmark status-uninstalled"></i> No'; ?>
                                </td>
                                <td><i class="fas fa-code-branch"></i> <?php echo $repo['forks']; ?></td>
                                <td><i class="fas fa-circle-exclamation"></i> <?php echo $repo['issues']; ?></td>
                                <td><span
                                        class="badge bg-primary"><?php echo empty($repo['language']) ? '-' : $repo['language']; ?></span>
                                </td>
                                <td><i
                                        class="fas fa-eye<?php echo ($repo['visibility'] === 'private') ? '-slash status-uninstalled' : ' status-installed'; ?>"></i>
                                    <?php echo $repo['visibility']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once "includes/footer.php"; ?>
    <script>
        function showErrorAlert(message) {
            var alertHtml = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;

            $("#alert-container").toggleClass("d-none").toggleClass("d-block").html(alertHtml);

            setTimeout(function () {
                var alertElement = document.querySelector('.alert');
                if (alertElement) {
                    var alertInstance = new bootstrap.Alert(alertElement);
                    alertInstance.close();
                }
            }, 15000);
        }

        function loadData() {
            fetch('api.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch repositories');
                    }
                    return response.json();
                })
                .then(data => {
                    populateRepositoriesTable(data.repositories);
                    updateOrganizationFilter(data.repositories);
                    setTimeout(loadData, 1000 * 60);
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorAlert('Failed to load repository data.');
                });
        }

        function populateRepositoriesTable(repositories) {
            const repositoriesTable = document.getElementById('repositories');
            repositoriesTable.innerHTML = '';

            repositories.forEach(repo => {
                const slash = repo.visibility === 'private' ? '-slash' : '';
                const row = document.createElement('tr');
                row.classList.add('repository-row');
                row.setAttribute('data-organization', repo.organization);
                row.innerHTML = `
                <td>${repo.organization}</td>
                <td><a href='${repo.url}' target='_blank'>${repo.name}</a></td>
                <td><i class="fas fa-star status-suspended"></i> ${repo.stars}</td>
                <td>${repo.fork ? '<i class="fas fa-circle-check status-installed"></i> Yes' : '<i class="fas fa-circle-xmark status-uninstalled"></i> No'}</td>
                <td><i class="fas fa-code-branch"></i> ${repo.forks}</td>
                <td><i class="fas fa-circle-exclamation"></i> ${repo.issues}</td>
                <td><span class="badge bg-primary">${repo.language ?? '-'}</span></td>
                <td><i class="fas fa-eye${slash}"></i> ${repo.visibility}</td>
                `;
                repositoriesTable.appendChild(row);
            });

            document.getElementById('repositoriesCount').textContent = repositories.length;
            filterRepositories();
        }

        function updateOrganizationFilter(repositories) {
            const organizationFilter = document.getElementById('organizationFilter');
            const existingValue = organizationFilter.value;

            const organizations = Array.from(new Set(repositories.map(repo => repo.organization)));
            organizationFilter.innerHTML = `<option value="">All Organizations</option>`;
            organizations.forEach(organization => {
                const option = document.createElement('option');
                option.value = organization;
                option.textContent = organization;
                if (organization === existingValue) {
                    option.selected = true;
                }
                organizationFilter.appendChild(option);
            });
        }

        function filterRepositories() {
            const organization = document.getElementById('organizationFilter').value;
            const queryString = new URLSearchParams(window.location.search);
            queryString.set('organization', organization);
            window.history.replaceState({}, '', '?' + queryString.toString());

            let counter = 0;
            document.querySelectorAll('.repository-row').forEach(row => {
                const matches = !organization || row.dataset.organization === organization;
                row.style.display = matches ? '' : 'none';
                if (matches) {
                    counter++;
                }
            });

            document.getElementById('repositoriesCount').textContent = counter;
        }

        document.getElementById('organizationFilter').addEventListener('change', filterRepositories);

        window.addEventListener('DOMContentLoaded', () => {
            loadData(); // Load repository data when the page is ready
        });
    </script>
</body>

</html>
