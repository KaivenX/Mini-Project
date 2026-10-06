<?php
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$stats = [
    'total_donors' => 0,
    'total_drives' => 0,
    'total_donations' => 0,
    'total_stock' => 0,
];
$recentDonations = [];
$stock = [];
$errorMessage = '';

if ($pdo instanceof PDO) {
    try {
        $statsStatement = $pdo->query(
            'SELECT
                (SELECT COUNT(*) FROM users WHERE role = "donor") AS total_donors,
                (SELECT COUNT(*) FROM donation_drives) AS total_drives,
                (SELECT COUNT(*) FROM donations) AS total_donations,
                (SELECT COALESCE(SUM(quantity_units), 0) FROM blood_stock) AS total_stock'
        );
        $stats = $statsStatement->fetch(PDO::FETCH_ASSOC) ?: $stats;

        $recentStatement = $pdo->query(
            'SELECT d.donation_date, d.quantity_ml, u.name, u.email,
                    dd.location, dd.drive_date
             FROM donations d
             INNER JOIN users u ON u.user_id = d.donor_id
             INNER JOIN donation_drives dd ON dd.drive_id = d.drive_id
             ORDER BY d.donation_date DESC, d.donation_id DESC
             LIMIT 6'
        );
        $recentDonations = $recentStatement->fetchAll(PDO::FETCH_ASSOC);

        $stockStatement = $pdo->query(
            'SELECT blood_type, quantity_units
             FROM blood_stock
             ORDER BY blood_type ASC'
        );
        $stock = $stockStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $errorMessage = 'Dashboard statistics could not be loaded. Please try again.';
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | BloodLink</title>
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
            --soft-red: #f7e2e1;
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
            color: white;
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

        .main-nav .logout { color: var(--red); }

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

        .admin-name {
            color: var(--muted);
            font-size: 13px;
            text-align: right;
        }

        .admin-name strong {
            display: block;
            color: var(--green);
            font-size: 14px;
        }

        .alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #ebc5c2;
            border-radius: 8px;
            background: var(--soft-red);
            color: #7a2524;
            font-size: 14px;
        }

        .stats-grid {
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

        .stat-card {
            min-height: 132px;
            padding: 21px;
        }

        .stat-icon {
            display: grid;
            width: 38px;
            height: 38px;
            margin-bottom: 16px;
            place-items: center;
            border-radius: 8px;
            background: var(--soft-green);
            color: var(--green);
            font-size: 18px;
            font-weight: 800;
        }

        .stat-label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .stat-value {
            color: var(--green);
            font-size: 25px;
            font-weight: 800;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.35fr .65fr;
            gap: 18px;
        }

        .section-panel {
            padding: 23px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--green);
            font-size: 18px;
        }

        .section-link {
            color: var(--red);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .activity-list {
            display: grid;
        }

        .activity-row {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr) auto;
            align-items: center;
            gap: 13px;
            padding: 14px 0;
            border-top: 1px solid var(--line);
        }

        .activity-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 50%;
            background: var(--soft-red);
            color: var(--red);
            font-size: 18px;
            font-weight: 800;
        }

        .activity-name {
            margin: 0 0 3px;
            font-size: 13px;
            font-weight: 700;
        }

        .activity-meta {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
        }

        .activity-amount {
            color: var(--green);
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .empty-state {
            padding: 24px 0;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        .management-list {
            display: grid;
            gap: 11px;
        }

        .management-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfdfc;
            color: var(--green);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .management-link:hover {
            border-color: #a8c2b9;
            background: #f2f8f5;
        }

        .management-link span:last-child {
            color: var(--red);
        }

        .stock-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 18px;
        }

        .stock-item {
            padding: 13px;
            border-radius: 8px;
            background: var(--wash);
        }

        .stock-type {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
        }

        .stock-value {
            color: var(--green);
            font-size: 16px;
            font-weight: 800;
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

        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .content-grid { grid-template-columns: 1fr; }
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

            .admin-name { text-align: left; }
            .stats-grid { grid-template-columns: 1fr; }
            .stock-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .page-shell { padding-top: 30px; }
            h1 { font-size: 26px; }
        }

        @media (max-width: 460px) {
            .stock-grid { grid-template-columns: 1fr; }
            .activity-row { grid-template-columns: 38px minmax(0, 1fr); }
            .activity-amount { grid-column: 2; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="dashboard.php" class="brand" aria-label="BloodLink admin dashboard">
                <span class="brand-mark">B</span>
                <span>BloodLink Admin</span>
            </a>

            <nav class="main-nav" aria-label="Admin navigation">
                <a href="dashboard.php" aria-current="page">Dashboard</a>
                <a href="manage_users.php">Manage users</a>
                <a href="manager_driver.php">Manage drives</a>
                <a href="manager_stock.php">Manage stock</a>
                <a href="../../logout.php" class="logout">Logout</a>
            </nav>
        </div>
    </header>

    <main class="page-shell">
        <div class="welcome-row">
            <div>
                <p class="eyebrow">Administration center</p>
                <h1>Blood bank overview</h1>
                <p class="welcome-copy">Monitor donors, donation drives, and available blood stock.</p>
            </div>
            <div class="admin-name">
                <strong><?= $escape($_SESSION['user']['name'] ?? 'Administrator') ?></strong>
                Signed in as administrator
            </div>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert" role="alert"><?= $escape($errorMessage) ?></div>
        <?php endif; ?>

        <section class="stats-grid" aria-label="Blood bank statistics">
            <article class="panel stat-card">
                <span class="stat-icon">D</span>
                <span class="stat-label">Registered donors</span>
                <strong class="stat-value"><?= (int) ($stats['total_donors'] ?? 0) ?></strong>
            </article>

            <article class="panel stat-card">
                <span class="stat-icon">↗</span>
                <span class="stat-label">Donation drives</span>
                <strong class="stat-value"><?= (int) ($stats['total_drives'] ?? 0) ?></strong>
            </article>

            <article class="panel stat-card">
                <span class="stat-icon">+</span>
                <span class="stat-label">Total donations</span>
                <strong class="stat-value"><?= (int) ($stats['total_donations'] ?? 0) ?></strong>
            </article>

            <article class="panel stat-card">
                <span class="stat-icon">◒</span>
                <span class="stat-label">Blood units available</span>
                <strong class="stat-value"><?= (int) ($stats['total_stock'] ?? 0) ?></strong>
            </article>
        </section>

        <div class="content-grid">
            <section class="panel section-panel" aria-labelledby="recent-donations-heading">
                <div class="section-heading">
                    <h2 id="recent-donations-heading">Recent donations</h2>
                    <a href="manager_driver.php" class="section-link">View drives</a>
                </div>

                <?php if (!empty($recentDonations)): ?>
                    <div class="activity-list">
                        <?php foreach ($recentDonations as $donation): ?>
                            <?php
                            $donationDate = date('M j, Y', strtotime($donation['donation_date']));
                            ?>
                            <article class="activity-row">
                                <div class="activity-icon">+</div>
                                <div>
                                    <p class="activity-name"><?= $escape($donation['name']) ?></p>
                                    <p class="activity-meta"><?= $escape($donation['location']) ?> • <?= $escape($donationDate) ?></p>
                                </div>
                                <span class="activity-amount"><?= (int) $donation['quantity_ml'] ?> ml</span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No donations have been recorded yet.</div>
                <?php endif; ?>
            </section>

            <aside class="panel section-panel" aria-labelledby="management-heading">
                <div class="section-heading">
                    <h2 id="management-heading">Quick management</h2>
                </div>

                <div class="management-list">
                    <a class="management-link" href="manage_users.php">
                        <span>Manage donors</span><span>&#8594;</span>
                    </a>
                    <a class="management-link" href="manager_driver.php">
                        <span>Manage donation drives</span><span>&#8594;</span>
                    </a>
                    <a class="management-link" href="manager_stock.php">
                        <span>Manage blood stock</span><span>&#8594;</span>
                    </a>
                </div>

                <div class="section-heading" style="margin-top: 24px;">
                    <h2>Blood stock</h2>
                </div>

                <div class="stock-grid">
                    <?php foreach ($stock as $item): ?>
                        <div class="stock-item">
                            <span class="stock-type"><?= $escape($item['blood_type']) ?></span>
                            <strong class="stock-value"><?= (int) $item['quantity_units'] ?> units</strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            &copy; <?= date('Y') ?> BloodLink. Blood donation management system.
        </div>
    </footer>
</body>
</html>
