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

if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    header('Location: login.php');
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
    <title>GStraccini-bot | <?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="user.css">
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
        <h1 class="mb-4">Infinite Scroll List Group</h1>
        <div id="scrollable-list" class="list-group">
            <!-- Items will be appended here dynamically -->
        </div>
        <button id="loadMoreLink" class="btn btn-primary mt-3">Load More</button>
    </div>
    <?php require_once "includes/footer.php"; ?>
    <script>
    const apiUrl = 'api.php';
    let page = 1;
    let loading = false;
    let infiniteScrollCount = 0;
    const maxInfiniteScrolls = 10;

    const scrollableList = document.getElementById('scrollable-list');
    const loadMoreLink = document.getElementById('loadMoreLink');

    function loadItems() {
        if (loading) return;
        loading = true;

        fetch(`${apiUrl}?page=${page}`)
            .then(response => response.json())
            .then(data => {
                data.items.forEach(item => {
                    const listItem = document.createElement('a');
                    listItem.href = '#';
                    listItem.className = 'list-group-item list-group-item-action';
                    listItem.textContent = item.name;
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
                loading = false;
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