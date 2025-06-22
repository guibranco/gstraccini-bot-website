<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('Location: signin.php?redirectUrl=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit();
}

$user = $_SESSION['user'];

$data = array("openPullRequests" => [], "openIssues" => [], "repositories" => []);

if (isset($_SESSION["data"])) {
    $data = $_SESSION["data"];
}

$title = "Dashboard";

$name = $user["login"];
if (isset($user["first_name"])) {
    $name = $user["first_name"];
} else if (isset($user["name"])) {
    $name = $user["name"];
}
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
    <style>
        #scrollable-list {
            max-height: 400px;
            overflow-y: auto;
        }

        #loadMoreLink {
            display: none;
        }
    </style>
</head>

<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container mt-5">
        <h1 class="mb-4">Assigned Issues <span class="badge text-bg-warning rounded-pill" id="count">0</span>
        </h1>
        <div id="scrollable-list" class="list-group">
            <!-- Items will be appended here dynamically -->
        </div>
        <button id="loadMoreLink" class="btn btn-primary mt-3">Load More</button>
    </div>
    <?php require_once "includes/footer.php"; ?>
    <script>
        const apiUrl = 'api/v1/infinite';
        let page = 1;
        let loading = false;
        let infiniteScrollCount = 0;
        const maxInfiniteScrolls = 10;
        let total = 0;

        const scrollableList = document.getElementById('scrollable-list');
        const loadMoreLink = document.getElementById('loadMoreLink');
        const count = document.getElementById("count");

        function appendLoadingItem() {
            const loadingItem = document.createElement('a');
            loadingItem.href = '#';
            loadingItem.className = 'list-group-item list-group-item-action';
            loadingItem.id = 'loading-item';
            loadingItem.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading data...';
            scrollableList.appendChild(loadingItem);
        }

        function removeLoadingItem() {
            const loadingItem = document.getElementById('loading-item');
            if (loadingItem) {
                loadingItem.remove();
            }
        }

        function loadItems() {
            if (loading) return;
            loading = true;

            appendLoadingItem();

            fetch(`${apiUrl}/${page}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    removeLoadingItem();
                    if (data.openIssues.length === 0) {
                        loadMoreLink.style.display = 'none';
                        return;
                    }
                    total += data.openIssues.length;
                    count.innerHTML = total;

                    data.openIssues.forEach(item => {
                        const listItem = document.createElement('a');
                        listItem.href = '#';
                        listItem.className = 'list-group-item list-group-item-action';
                        listItem.textContent = item.title;
                        scrollableList.appendChild(listItem);
                    });
                    page++;
                    loading = false;

                    infiniteScrollCount++;
                    if (infiniteScrollCount >= maxInfiniteScrolls) {
                        loadMoreLink.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading items:', error);
                    const errorItem = document.createElement('div');
                    errorItem.className = 'alert alert-danger';
                    errorItem.textContent = 'Failed to load items. Please try again.';
                    scrollableList.appendChild(errorItem);
                    loading = false;
                    removeLoadingItem();
                });
        }

        scrollableList.addEventListener('scroll', () => {
            if (scrollableList.scrollTop + scrollableList.clientHeight >= scrollableList.scrollHeight) {
                if (infiniteScrollCount < maxInfiniteScrolls) {
                    loadItems();
                }
            }
        });

        loadMoreLink.addEventListener('click', () => {
            loadItems();
        });

        loadItems();
    </script>
</body>

</html>
