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
    <title>GStraccini Bot | <?php echo $title; ?></title>
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
            
            <div class="col-md-4">
                <label for="languageFilter" class="form-label">Filter by Language</label>
                <select id="languageFilter" class="form-select">
                    <option value="">All Languages</option>
                    <?php
                    $languages = array_filter(array_unique(array_column($data['repositories'], 'language')));
                    sort($languages);
                    foreach ($languages as $language) {
                        if (!empty($language)) {
                            echo "<option value=\"" . htmlspecialchars($language) . "\">" . htmlspecialchars($language) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
    
            <div class="col-md-4">
                <label for="visibilityFilter" class="form-label">Filter by Visibility</label>
                <select id="visibilityFilter" class="form-select">
                    <option value="">All</option>
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="isForkFilter">
                    <label class="form-check-label" for="isForkFilter">Is Fork</label>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="hasForkFilter">
                    <label class="form-check-label" for="hasForkFilter">Has Forks</label>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="hasIssuesFilter">
                    <label class="form-check-label" for="hasIssuesFilter">Has Issues</label>
                </div>
            </div>
            
            <div class="col-md-3">
                <button id="resetFilters" class="btn btn-outline-secondary">Reset Filters</button>
            </div>
        </div>
    </div>

    <div class="container mt-5">
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
                    updateLanguageFilter(data.repositories);
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
                const slash = repo.visibility === 'private' ? '-slash status-uninstalled' : ' status-installed';
                const row = document.createElement('tr');
                row.classList.add('repository-row');
                row.setAttribute('data-organization', repo.organization);
                row.setAttribute('data-language', repo.language || '');
                row.setAttribute('data-visibility', repo.visibility);
                row.setAttribute('data-fork', repo.fork ? 'true' : 'false');
                row.setAttribute('data-has-forks', repo.forks > 0 ? 'true' : 'false');
                row.setAttribute('data-has-issues', repo.issues > 0 ? 'true' : 'false');
                
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
    
        function updateLanguageFilter(repositories) {
            const languageFilter = document.getElementById('languageFilter');
            const existingValue = languageFilter.value;
    
            const languages = Array.from(new Set(repositories.map(repo => repo.language)))
                .filter(lang => lang)
                .sort();
                
            languageFilter.innerHTML = `<option value="">All Languages</option>`;
            languages.forEach(language => {
                const option = document.createElement('option');
                option.value = language;
                option.textContent = language;
                if (language === existingValue) {
                    option.selected = true;
                }
                languageFilter.appendChild(option);
            });
        }
    
        function filterRepositories() {
            const organization = document.getElementById('organizationFilter').value;
            const language = document.getElementById('languageFilter').value;
            const visibility = document.getElementById('visibilityFilter').value;
            const isFork = document.getElementById('isForkFilter').checked;
            const hasForks = document.getElementById('hasForkFilter').checked;
            const hasIssues = document.getElementById('hasIssuesFilter').checked;
    
            // Update URL query parameters
            const queryString = new URLSearchParams(window.location.search);
            queryString.set('organization', organization);
            if (language) queryString.set('language', language);
            else queryString.delete('language');
            if (visibility) queryString.set('visibility', visibility);
            else queryString.delete('visibility');
            queryString.set('isFork', isFork ? 'true' : '');
            queryString.set('hasForks', hasForks ? 'true' : '');
            queryString.set('hasIssues', hasIssues ? 'true' : '');
            window.history.replaceState({}, '', '?' + queryString.toString());
    
            let counter = 0;
            document.querySelectorAll('.repository-row').forEach(row => {
                const organizationMatch = !organization || row.dataset.organization === organization;
                const languageMatch = !language || row.dataset.language === language;
                const visibilityMatch = !visibility || row.dataset.visibility === visibility;
                const isForkMatch = !isFork || row.dataset.fork === 'true';
                const hasForksMatch = !hasForks || row.dataset.hasForks === 'true';
                const hasIssuesMatch = !hasIssues || row.dataset.hasIssues === 'true';
    
                const matches = organizationMatch && languageMatch && visibilityMatch && 
                                isForkMatch && hasForksMatch && hasIssuesMatch;
                
                row.style.display = matches ? '' : 'none';
                if (matches) {
                    counter++;
                }
            });
    
            document.getElementById('repositoriesCount').textContent = counter;
        }
    
        function resetFilters() {
            document.getElementById('organizationFilter').value = '';
            document.getElementById('languageFilter').value = '';
            document.getElementById('visibilityFilter').value = '';
            document.getElementById('isForkFilter').checked = false;
            document.getElementById('hasForkFilter').checked = false;
            document.getElementById('hasIssuesFilter').checked = false;
            
            filterRepositories();
        }
    
        // Load saved filters from URL
        function loadFiltersFromURL() {
            const params = new URLSearchParams(window.location.search);
            
            // Set organization filter (already handled in PHP)
            
            // Set language filter
            if (params.has('language')) {
                const language = params.get('language');
                const languageFilter = document.getElementById('languageFilter');
                if (languageFilter.querySelector(`option[value="${language}"]`)) {
                    languageFilter.value = language;
                }
            }
            
            // Set visibility filter
            if (params.has('visibility')) {
                document.getElementById('visibilityFilter').value = params.get('visibility');
            }
            
            // Set checkbox filters
            document.getElementById('isForkFilter').checked = params.get('isFork') === 'true';
            document.getElementById('hasForkFilter').checked = params.get('hasForks') === 'true';
            document.getElementById('hasIssuesFilter').checked = params.get('hasIssues') === 'true';
        }
    
        // Event listeners
        document.getElementById('organizationFilter').addEventListener('change', filterRepositories);
        document.getElementById('languageFilter').addEventListener('change', filterRepositories);
        document.getElementById('visibilityFilter').addEventListener('change', filterRepositories);
        document.getElementById('isForkFilter').addEventListener('change', filterRepositories);
        document.getElementById('hasForkFilter').addEventListener('change', filterRepositories);
        document.getElementById('hasIssuesFilter').addEventListener('change', filterRepositories);
        document.getElementById('resetFilters').addEventListener('click', resetFilters);
    
window.addEventListener('DOMContentLoaded', () => {
            loadData();
            loadFiltersFromURL();
            filterRepositories();
        });
    </script>
</body>

</html>
