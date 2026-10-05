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
$history = [];

if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->prepare(
            'SELECT name, email, blood_type, last_donation_date
             FROM users
             WHERE user_id = :user_id AND role = :role
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => (int) ($sessionUser['id'] ?? 0),
            'role' => 'donor',
        ]);
        $databaseDonor = $statement->fetch(PDO::FETCH_ASSOC);

        if ($databaseDonor) {
            $donor = array_merge($donor, $databaseDonor);
        }

        $historyStatement = $pdo->prepare(
            'SELECT d.donation_id, d.donation_date, d.quantity_ml,
                    dd.location, dd.drive_date
             FROM donations d
             LEFT JOIN donation_drives dd ON dd.drive_id = d.drive_id
             WHERE d.donor_id = :user_id
             ORDER BY d.donation_date DESC, d.donation_id DESC'
        );
        $historyStatement->execute(['user_id' => (int) ($sessionUser['id'] ?? 0)]);
        $history = $historyStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $history = [];
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$totalDonations = count($history);
$totalVolume = array_sum(array_map(static fn ($entry) => (int) ($entry['quantity_ml'] ?? 0), $history));
$lastDonationDate = $history[0]['donation_date'] ?? $donor['last_donation_date'] ?? null;
$nextEligibleDate = $lastDonationDate
    ? (new DateTimeImmutable($lastDonationDate))->modify('+56 days')
    : null;
$isEligible = $nextEligibleDate === null || $nextEligibleDate <= new DateTimeImmutable('today');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History | BloodLink</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #21312d;
            --muted: #687975;
            --line: #dfeae7;
            --paper: #ffffff;
            --wash: #f3f7f5;
            --green: #173a36;
            --red: #b42318;
            --soft-red: #f2d8d6;
            --soft-green: #dff3ea;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
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
            text-decoration: none;
            font-size: 19px;
            font-weight: 800;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 38px;
            aspect-ratio: 1;
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

        .page-shell {
            padding: 42px 0 56px;
        }

        .welcome-row {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
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

        h1 {
            margin-bottom: 7px;
            color: var(--green);
            font-size: 30px;
            line-height: 1.15;
        }

        .welcome-copy {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .welcome-email {
            color: var(--muted);
            font-size: 13px;
            text-align: right;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--paper);
        }

        .summary-card {
            padding: 22px 20px;
        }

        .summary-label {
            display: block;
            margin-bottom: 12px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .summary-value {
            display: block;
            color: var(--green);
            font-size: 26px;
            font-weight: 800;
            line-height: 1.1;
        }

        .summary-foot {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
        }

        .history-panel {
            padding: 24px 0 0;
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 0 24px 16px;
        }

        .section-header h2 {
            margin: 0;
            color: var(--green);
            font-size: 20px;
        }

        .mini-link {
            color: var(--red);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .history-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .history-item {
            display: grid;
            grid-template-columns: 52px minmax(0, 1fr) auto;
            align-items: center;
            gap: 16px;
            padding: 18px 24px;
            border-top: 1px solid var(--line);
        }

        .history-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--soft-green);
            color: var(--green);
            font-size: 22px;
            font-weight: 700;
        }

        .history-title {
            margin: 0 0 4px;
            color: var(--ink);
            font-size: 15px;
            font-weight: 700;
        }

        .history-meta {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .history-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 94px;
            padding: 8px 12px;
            border: 1px solid var(--soft-red);
            border-radius: 999px;
            background: #fff7f5;
            color: var(--red);
            font-size: 12px;
            font-weight: 700;
        }

        .empty-state {
            padding: 24px;
            color: var(--muted);
            font-size: 14px;
        }

        .site-footer {
            border-top: 1px solid var(--line);
            background: var(--paper);
        }

        .footer-inner {
            padding: 18px 0;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 820px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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

            .welcome-row {
                align-items: start;
                flex-direction: column;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .history-item {
                grid-template-columns: 42px minmax(0, 1fr);
            }

            .history-badge {
                grid-column: 2;
                justify-self: start;
            }

            .page-shell {
                padding-top: 30px;
            }

            h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="dashboard.php" class="brand" aria-label="BloodLink home">
                <span class="brand-mark">B</span>
                <span>BloodLink</span>
            </a>

            <nav class="main-nav" aria-label="Main navigation">
                <a href="dashboard.php">Dashboard</a>
                <a href="book_donation.php">Book a donation</a>
                <a href="history.php" aria-current="page">Donation history</a>
                <a href="../logout.php" class="logout">Logout</a>
            </nav>
        </div>
    </header>

    <main class="page-shell">
        <div class="welcome-row">
            <div>
                <p class="eyebrow">Donor profile</p>
                <h1>Donation history</h1>
                <p class="welcome-copy">Track every donation and stay ready to help save lives.</p>
            </div>
            <div class="welcome-email"><?= $escape($donor['email']) ?></div>
        </div>

        <section class="summary-grid" aria-label="Donation summary">
            <article class="panel summary-card">
                <span class="summary-label">Total donations</span>
                <span class="summary-value"><?= $totalDonations ?></span>
                <span class="summary-foot">Recorded blood drives</span>
            </article>

            <article class="panel summary-card">
                <span class="summary-label">Total donated</span>
                <span class="summary-value"><?= $totalVolume ?> ml</span>
                <span class="summary-foot">Life-saving impact</span>
            </article>

            <article class="panel summary-card">
                <span class="summary-label">Last donation</span>
                <span class="summary-value"><?= $lastDonationDate ? date('M j, Y', strtotime($lastDonationDate)) : 'None yet' ?></span>
                <span class="summary-foot"><?= $lastDonationDate ? 'Thank you for your support' : 'Your first donation will appear here' ?></span>
            </article>

            <article class="panel summary-card">
                <span class="summary-label">Eligibility</span>
                <span class="summary-value"><?= $isEligible ? 'Eligible' : 'Waiting' ?></span>
                <span class="summary-foot"><?= $nextEligibleDate ? 'Next date: ' . $nextEligibleDate->format('M j, Y') : 'No donation recorded yet' ?></span>
            </article>
        </section>

        <section class="panel history-panel" aria-label="Donation records">
            <div class="section-header">
                <h2>Your donation timeline</h2>
                <a href="book_donation.php" class="mini-link">Book another donation</a>
            </div>

            <?php if (!empty($history)): ?>
                <ul class="history-list">
                    <?php foreach ($history as $entry): ?>
                        <?php
                        $entryDate = date('F j, Y', strtotime($entry['donation_date']));
                        $location = $entry['location'] ?? 'Blood donation drive';
                        $quantity = (int) ($entry['quantity_ml'] ?? 450);
                        ?>
                        <li class="history-item">
                            <div class="history-icon">+</div>
                            <div>
                                <p class="history-title"><?= $escape($location) ?></p>
                                <p class="history-meta"><?= $escape($entryDate) ?> • <?= $quantity ?> ml collected</p>
                            </div>
                            <div class="history-badge"><?= $escape($donor['blood_type'] ?: 'Donor') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    You have not recorded any donations yet. Once you complete a blood drive, your record will appear here.
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            &copy; <?= date('Y') ?> BloodLink. Supporting safe, timely blood donation care.
        </div>
    </footer>
</body>
</html>
