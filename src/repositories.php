<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];
$data = $_SESSION["repositories"]["data"] ?? array("repositories" => []);

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
    <script src="static/repositories.js"></script>
</body>

</html>
