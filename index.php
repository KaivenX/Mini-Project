<?php
session_start();
require_once __DIR__ . '/bloodlink/config/db.php';

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$stockByType = [];
$databaseError = '';

if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->query(
            'SELECT blood_type, SUM(quantity_units) AS quantity_units
             FROM blood_stock
             GROUP BY blood_type
             ORDER BY blood_type ASC'
        );
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $stockByType[(string) $item['blood_type']] = (int) $item['quantity_units'];
        }
    } catch (PDOException $exception) {
        $databaseError = 'Current blood stock could not be loaded. Please try again later.';
    }
} else {
    $databaseError = 'The stock dashboard is temporarily unavailable because the database connection could not be reached.';
}

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';
$dashboardUrl = $role === 'admin'
    ? 'bloodlink/admin/dashboard.php'
    : ($role === 'donor' ? 'donor/dashboard.php' : ($role === 'requester' ? 'requester/dashboard.php' : 'login.php'));
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="BloodLink connects blood donors and blood banks to help communities respond to urgent needs.">
    <title>BloodLink | Blood Donation Community</title>
    <link rel="stylesheet" href="bloodlink/Styling/css/style.css">
    
</head>
<body class="page-home">
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="index.php" aria-label="BloodLink home">
                <span class="brand-mark" aria-hidden="true">B</span>
                <span>BloodLink</span>
            </a>
            <nav aria-label="Main navigation">
                <?php if (in_array($role, ['admin', 'donor', 'requester'], true)): ?>
                    <a href="<?= $escape($dashboardUrl) ?>">My dashboard</a>
                    <a href="logout.php">Log out</a>
                <?php else: ?>
                    <a href="login.php">Log in</a>
                    <a class="primary" href="register.php">Become a donor</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div>
                <p class="eyebrow">Blood donation, connected</p>
                <h1>Every donation can make a difference.</h1>
                <p class="hero-copy">BloodLink brings donors and blood bank teams together to support a stronger, more responsive community. Check current stock or sign up to take part.</p>
                <div class="actions">
                    <?php if ($role === 'donor'): ?>
                        <a class="button primary" href="donor/book_donation.php">Book a donation</a>
                        <a class="button" href="donor/history.php">View donation history</a>
                    <?php elseif ($role === 'admin'): ?>
                        <a class="button primary" href="bloodlink/admin/dashboard.php">Open admin dashboard</a>
                    <?php elseif ($role === 'requester'): ?>
                        <a class="button primary" href="requester/dashboard.php">Manage blood requests</a>
                    <?php else: ?>
                        <a class="button primary" href="register.php">Register as a donor</a>
                        <a class="button" href="login.php">Log in</a>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="hero-note">
                <strong>One community. A lifesaving resource.</strong>
                <p>Donors can register, check their eligibility, and book a donation at an upcoming drive.</p>
            </aside>
        </section>

        <section class="stock-section" aria-labelledby="stock-heading">
            <div class="section-heading">
                <div>
                    <h2 id="stock-heading">Blood stock overview</h2>
                    <p>Current available units by blood type.</p>
                </div>
            </div>
            <?php if ($databaseError !== ''): ?>
                <div class="notice" role="alert"><?= $escape($databaseError) ?></div>
            <?php endif; ?>
            <div class="stock-grid">
                <?php foreach ($bloodTypes as $bloodType): ?>
                    <article class="stock-card">
                        <span class="blood-type"><?= $escape($bloodType) ?></span>
                        <strong class="units">
                            <?php if ($databaseError === ''): ?>
                                <?= (int) ($stockByType[$bloodType] ?? 0) ?>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </strong>
                        <span class="units-label">units available</span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="help-section" aria-labelledby="help-heading">
            <div>
                <h2 id="help-heading">Ready to help?</h2>
                <p>Register to see your eligibility and find a donation drive.</p>
            </div>
            <?php if ($role === 'donor'): ?>
                <a class="help-link" href="donor/book_donation.php">Book a donation &rarr;</a>
            <?php elseif ($role === 'admin'): ?>
                <a class="help-link" href="bloodlink/admin/dashboard.php">Go to dashboard &rarr;</a>
            <?php elseif ($role === 'requester'): ?>
                <a class="help-link" href="requester/dashboard.php">Manage your requests &rarr;</a>
            <?php else: ?>
                <a class="help-link" href="register.php">Create a donor account &rarr;</a>
            <?php endif; ?>
        </section>
    </main>

    <footer>BloodLink &mdash; supporting local blood donation.</footer>
</body>
</html>