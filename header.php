<?php
$siteTitle = $siteTitle ?? 'BloodLink';
$currentPage = $currentPage ?? basename($_SERVER['PHP_SELF'] ?? 'index.php');
$navLinks = [
    ['label' => 'Home', 'url' => '../index.php', 'page' => 'index.php'],
    ['label' => 'About', 'url' => '../about.php', 'page' => 'about.php'],
    ['label' => 'Donors', 'url' => '../donor/dashboard.php', 'page' => 'dashboard.php'],
    ['label' => 'Log in', 'url' => '../login.php', 'page' => 'login.php'],
];

if (!isset($showAuthLinks)) {
    $showAuthLinks = true;
}

$sessionUser = $_SESSION['user'] ?? null;
$userRole = $sessionUser['role'] ?? null;

if ($userRole === 'admin') {
    $navLinks = [
        ['label' => 'Dashboard', 'url' => 'dashboard.php', 'page' => 'dashboard.php'],
        ['label' => 'Manage users', 'url' => 'manage_users.php', 'page' => 'manage_users.php'],
        ['label' => 'Manage drives', 'url' => 'manager_driver.php', 'page' => 'manager_driver.php'],
        ['label' => 'Manage stock', 'url' => 'manager_stock.php', 'page' => 'manager_stock.php'],
        ['label' => 'Blood requests', 'url' => 'manage_requests.php', 'page' => 'manage_requests.php'],
        ['label' => 'Logout', 'url' => '../logout.php', 'page' => 'logout.php'],
    ];
} elseif ($userRole === 'donor') {
    $navLinks = [
        ['label' => 'Dashboard', 'url' => '../donor/dashboard.php', 'page' => 'dashboard.php'],
        ['label' => 'Book donation', 'url' => '../donor/book_donation.php', 'page' => 'book_donation.php'],
        ['label' => 'History', 'url' => '../donor/history.php', 'page' => 'history.php'],
        ['label' => 'Logout', 'url' => '../logout.php', 'page' => 'logout.php'],
    ];
} elseif ($userRole === 'requester') {
    $navLinks = [
        ['label' => 'Home', 'url' => '../index.php', 'page' => 'index.php'],
        ['label' => 'Requests', 'url' => '../requester/dashboard.php', 'page' => 'dashboard.php'],
        ['label' => 'Logout', 'url' => '../logout.php', 'page' => 'logout.php'],
    ];
}
?>
<header class="site-header">
    <div class="header-inner">
        <a href="<?= htmlspecialchars((string) ($userRole === 'admin' ? 'dashboard.php' : ($userRole === 'donor' ? '../donor/dashboard.php' : ($userRole === 'requester' ? '../requester/dashboard.php' : '../index.php'))), ENT_QUOTES, 'UTF-8') ?>" class="brand" aria-label="<?= htmlspecialchars((string) $siteTitle, ENT_QUOTES, 'UTF-8') ?> home">
            <span class="brand-mark" aria-hidden="true">B</span>
            <span><?= htmlspecialchars((string) $siteTitle, ENT_QUOTES, 'UTF-8') ?></span>
        </a>

        <nav class="main-nav" aria-label="Main navigation">
            <?php foreach ($navLinks as $link): ?>
                <a href="<?= htmlspecialchars((string) $link['url'], ENT_QUOTES, 'UTF-8') ?>"
                   <?= $currentPage === $link['page'] ? 'aria-current="page"' : '' ?>
                   class="<?= ($link['label'] === 'Logout' || $link['label'] === 'Log in') ? 'logout' : '' ?>">
                    <?= htmlspecialchars((string) $link['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
