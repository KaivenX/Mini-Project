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
                (SELECT COUNT(*) FROM users WHERE `role` = \'donor\') AS total_donors,
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
    <link rel="stylesheet" href="../Styling/css/style.css">
    
</head>
<body class="page-admin-dashboard">
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
                <a href="manage_requests.php">Blood requests</a>
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

                <div class="section-heading dashboard-secondary-heading">
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
