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
        ['label' => 'Dashboard', 'url' => '../bloodlink/admin/dashboard.php', 'page' => 'dashboard.php'],
        ['label' => 'Manage users', 'url' => '../bloodlink/admin/manage_users.php', 'page' => 'manage_users.php'],
        ['label' => 'Manage drives', 'url' => '../bloodlink/admin/manager_driver.php', 'page' => 'manager_driver.php'],
        ['label' => 'Manage stock', 'url' => '../bloodlink/admin/manager_stock.php', 'page' => 'manager_stock.php'],
        ['label' => 'Logout', 'url' => '../logout.php', 'page' => 'logout.php'],
    ];
} elseif ($userRole === 'donor') {
    $navLinks = [
        ['label' => 'Dashboard', 'url' => '../donor/dashboard.php', 'page' => 'dashboard.php'],
        ['label' => 'Book donation', 'url' => '../donor/book_donation.php', 'page' => 'book_donation.php'],
        ['label' => 'History', 'url' => '../donor/history.php', 'page' => 'history.php'],
        ['label' => 'Logout', 'url' => '../logout.php', 'page' => 'logout.php'],
    ];
}
?>
<header class="site-header">
    <div class="header-inner">
        <a href="<?= htmlspecialchars((string) ($userRole === 'admin' ? '../bloodlink/admin/dashboard.php' : ($userRole === 'donor' ? '../donor/dashboard.php' : '../index.php')), ENT_QUOTES, 'UTF-8') ?>" class="brand" aria-label="<?= htmlspecialchars((string) $siteTitle, ENT_QUOTES, 'UTF-8') ?> home">
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

<style>
    :root {
        color-scheme: light;
        --ink: #20312f;
        --muted: #687975;
        --line: #dce6e2;
        --paper: #fff;
        --wash: #f3f7f5;
        --green: #173a36;
        --red: #b42318;
    }

    .site-header {
        background: var(--paper);
        border-bottom: 1px solid var(--line);
    }

    .header-inner, .page-shell, .footer-inner {
        width: min(1180px, calc(100% - 40px));
        margin: 0 auto;
    }

    .header-inner {
        min-height: 76px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
    }

    .brand {
        display: inline-flex;
        align-items: center;
        gap: 11px;
        color: var(--green);
        font-size: 19px;
        font-weight: 800;
        text-decoration: none;
    }

    .brand-mark {
        display: grid;
        width: 38px;
        aspect-ratio: 1;
        place-items: center;
        border-radius: 9px;
        background: var(--red);
        color: #fff;
        font-family: Georgia, serif;
        font-size: 23px;
    }

    .main-nav {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .main-nav a {
        padding: 10px 13px;
        border-radius: 7px;
        color: var(--muted);
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
    }

    .main-nav a:hover,
    .main-nav a[aria-current="page"] {
        background: #edf4f1;
        color: var(--green);
    }

    .main-nav .logout {
        color: var(--red);
    }

    @media (max-width: 720px) {
        .header-inner {
            min-height: auto;
            flex-wrap: wrap;
            padding: 14px 0;
        }

        .main-nav {
            width: 100%;
            overflow-x: auto;
        }

        .main-nav a {
            white-space: nowrap;
            padding: 9px 10px;
        }
    }
</style>
