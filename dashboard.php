<?php
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'donor') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../bloodlink/config/db.php';

$sessionUser = $_SESSION['user'];
$donor = [
    'name' => $sessionUser['name'] ?? 'Donor',
    'email' => $sessionUser['email'] ?? '',
    'blood_type' => null,
    'last_donation_date' => null,
];
$drives = [];

if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->prepare(
            'SELECT name, email, blood_type, last_donation_date
             FROM users
             WHERE user_id = :user_id AND role = \'donor\'
             LIMIT 1'
        );
        $statement->execute(['user_id' => (int) ($sessionUser['id'] ?? 0)]);
        $databaseDonor = $statement->fetch();
        if ($databaseDonor) {
            $donor = $databaseDonor;
        }

        $driveStatement = $pdo->query(
            'SELECT location, drive_date, capacity
             FROM donation_drives
             WHERE drive_date >= CURRENT_DATE
             ORDER BY drive_date ASC
             LIMIT 3'
        );
        $drives = $driveStatement->fetchAll();
    } catch (PDOException $exception) {
        $drives = [];
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$lastDonation = $donor['last_donation_date'];
$nextEligibleDate = $lastDonation
    ? (new DateTimeImmutable($lastDonation))->modify('+56 days')
    : null;
$isEligible = $nextEligibleDate === null || $nextEligibleDate <= new DateTimeImmutable('today');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard | BloodLink</title>
    <link rel="stylesheet" href="../bloodlink/Styling/css/style.css">
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
        body {
            display: block;
            min-height: 100vh;
            background: var(--wash);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
        }
        a { color: inherit; }
        .site-header {
            background: var(--paper);
            border-bottom: 1px solid var(--line);
        }
        .header-inner, .page-shell, .footer-inner {
            width: min(1120px, calc(100% - 40px));
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
            border-radius: 8px;
            background: var(--red);
            color: white;
            font-family: Georgia, serif;
            font-size: 23px;
        }
        .main-nav { display: flex; align-items: center; gap: 8px; }
        .main-nav a {
            padding: 10px 13px;
            border-radius: 6px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 650;
            text-decoration: none;
        }
        .main-nav a:hover, .main-nav a[aria-current="page"] {
            background: #edf4f1;
            color: var(--green);
        }
        .main-nav .logout { color: var(--red); }
        .page-shell { padding: 42px 0 56px; }
        .welcome-row {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }
        .eyebrow {
            margin: 0 0 8px;
            color: var(--red);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 7px; color: var(--green); font-size: 30px; line-height: 1.15; }
        .welcome-copy { margin: 0; color: var(--muted); font-size: 14px; }
        .welcome-email { color: var(--muted); font-size: 13px; }
        .overview-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(260px, .8fr);
            gap: 18px;
            margin-bottom: 22px;
        }
        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--paper);
        }
        .eligibility-panel {
            display: flex;
            min-height: 200px;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 28px;
            color: #fff;
            background: var(--green);
            border-color: var(--green);
        }
        .eligibility-panel .eyebrow { color: #b8e3d0; }
        .eligibility-panel h2 { margin-bottom: 8px; color: #fff; font-size: 23px; }
        .eligibility-panel p { max-width: 480px; margin-bottom: 0; color: #d5e5df; font-size: 14px; line-height: 1.6; }
        .status-mark {
            display: grid;
            width: 58px;
            aspect-ratio: 1;
            flex: 0 0 auto;
            place-items: center;
            border: 1px solid rgba(255,255,255,.32);
            border-radius: 50%;
            color: #b8e3d0;
            font-size: 25px;
        }
        .quick-panel { padding: 24px; }
        .quick-panel h2, .section-heading { margin-bottom: 16px; color: var(--green); font-size: 17px; }
        .action-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 13px 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            color: var(--green);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }
        .action-link + .action-link { margin-top: 9px; }
        .action-link.primary { border-color: var(--red); background: var(--red); color: #fff; }
        .action-link:hover { filter: brightness(.96); }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }
        .stat-panel { min-height: 120px; padding: 20px; }
        .stat-label { display: block; margin-bottom: 11px; color: var(--muted); font-size: 12px; font-weight: 700; }
        .stat-value { display: block; color: var(--green); font-size: 21px; font-weight: 800; }
        .stat-note { display: block; margin-top: 5px; color: var(--muted); font-size: 12px; }
        .drives-section { padding-top: 3px; }
        .section-heading { display: flex; justify-content: space-between; align-items: center; }
        .drive-list { overflow: hidden; }
        .drive-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 17px 20px;
        }
        .drive-row + .drive-row { border-top: 1px solid var(--line); }
        .drive-location { margin: 0 0 4px; color: var(--ink); font-size: 14px; font-weight: 750; }
        .drive-meta { margin: 0; color: var(--muted); font-size: 12px; }
        .drive-date { color: var(--green); font-size: 13px; font-weight: 750; white-space: nowrap; }
        .empty-state { padding: 22px; color: var(--muted); font-size: 14px; }
        .site-footer { border-top: 1px solid var(--line); background: var(--paper); }
        .footer-inner { padding: 18px 0; color: var(--muted); font-size: 12px; }
        @media (max-width: 720px) {
            .header-inner { min-height: auto; flex-wrap: wrap; padding: 14px 0; }
            .main-nav { width: 100%; overflow-x: auto; }
            .main-nav a { padding: 9px 10px; white-space: nowrap; }
            .welcome-row { align-items: start; flex-direction: column; }
            .overview-grid { grid-template-columns: 1fr; }
            .eligibility-panel { min-height: 180px; padding: 22px; }
            .stats-grid { grid-template-columns: 1fr; }
            .stat-panel { min-height: auto; }
            .page-shell { padding-top: 30px; }
            h1 { font-size: 26px; }
        }
        @media (max-width: 420px) {
            .header-inner, .page-shell, .footer-inner { width: min(100% - 28px, 1120px); }
            .eligibility-panel { align-items: start; }
            .status-mark { width: 46px; font-size: 20px; }
            .drive-row { grid-template-columns: 1fr; }
            .drive-date { white-space: normal; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="dashboard.php" aria-label="BloodLink donor dashboard">
                <span class="brand-mark" aria-hidden="true">+</span>
                <span>BloodLink</span>
            </a>
            <nav class="main-nav" aria-label="Donor navigation">
                <a href="dashboard.php" aria-current="page">Dashboard</a>
                <a href="book_donation.php">Book a donation</a>
                <a href="history.php">Donation history</a>
                <a class="logout" href="../logout.php">Log out</a>
            </nav>
        </div>
    </header>

    <main class="page-shell">
        <section class="welcome-row" aria-labelledby="welcome-heading">
            <div>
                <p class="eyebrow">Donor portal</p>
                <h1 id="welcome-heading">Welcome back, <?= $escape($donor['name']) ?></h1>
                <p class="welcome-copy">Your donations make a real difference in your community.</p>
            </div>
            <?php if ($donor['email']): ?>
                <span class="welcome-email"><?= $escape($donor['email']) ?></span>
            <?php endif; ?>
        </section>

        <section class="overview-grid" aria-label="Donation overview">
            <div class="panel eligibility-panel">
                <div>
                    <p class="eyebrow">Donation eligibility</p>
                    <?php if ($isEligible): ?>
                        <h2>You may be ready to donate</h2>
                        <p><?= $lastDonation ? 'Your 56-day waiting period has passed. Choose a drive and book your next donation.' : 'You have no previous donation recorded. Choose a drive to get started.' ?></p>
                    <?php else: ?>
                        <h2>Your next donation date is approaching</h2>
                        <p>You may book again from <?= $escape($nextEligibleDate->format('F j, Y')) ?>, based on a 56-day interval after your last donation.</p>
                    <?php endif; ?>
                </div>
                <span class="status-mark" aria-hidden="true"><?= $isEligible ? '&#10003;' : '&#8230;' ?></span>
            </div>

            <div class="panel quick-panel">
                <h2>Quick actions</h2>
                <a class="action-link primary" href="book_donation.php">Book a donation <span aria-hidden="true">&#8594;</span></a>
                <a class="action-link" href="history.php">View donation history <span aria-hidden="true">&#8594;</span></a>
            </div>
        </section>

        <section class="stats-grid" aria-label="Donor details">
            <div class="panel stat-panel">
                <span class="stat-label">Blood type</span>
                <span class="stat-value"><?= $escape($donor['blood_type'] ?: 'Not set') ?></span>
                <span class="stat-note">Your profile blood type</span>
            </div>
            <div class="panel stat-panel">
                <span class="stat-label">Last donation</span>
                <span class="stat-value"><?= $lastDonation ? $escape((new DateTimeImmutable($lastDonation))->format('M j, Y')) : 'None yet' ?></span>
                <span class="stat-note">Based on your donor record</span>
            </div>
            <div class="panel stat-panel">
                <span class="stat-label">Next eligible date</span>
                <span class="stat-value"><?= $nextEligibleDate ? $escape($nextEligibleDate->format('M j, Y')) : 'Ready to book' ?></span>
                <span class="stat-note">Estimated using a 56-day interval</span>
            </div>
        </section>

        <section class="drives-section" aria-labelledby="drives-heading">
            <h2 class="section-heading" id="drives-heading">Upcoming donation drives</h2>
            <div class="panel drive-list">
                <?php if ($drives): ?>
                    <?php foreach ($drives as $drive): ?>
                        <article class="drive-row">
                            <div>
                                <h3 class="drive-location"><?= $escape($drive['location']) ?></h3>
                                <p class="drive-meta"><?= (int) $drive['capacity'] ?> donor spaces</p>
                            </div>
                            <time class="drive-date" datetime="<?= $escape($drive['drive_date']) ?>"><?= $escape((new DateTimeImmutable($drive['drive_date']))->format('F j, Y')) ?></time>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-state">There are no upcoming drives scheduled right now.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">BloodLink donor portal</div>
    </footer>
</body>
</html>