<?php
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'donor') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../bloodlink/config/db.php';

$sessionUser = $_SESSION['user'];
$donorId = (int) ($sessionUser['id'] ?? 0);
$errorMessage = '';
$successMessage = '';
$drives = [];

if ($pdo instanceof PDO) {
    try {
        $driveStatement = $pdo->query(
            'SELECT d.drive_id, d.location, d.drive_date, d.capacity,
                    COUNT(dn.donation_id) AS booked_count
             FROM donation_drives d
             LEFT JOIN donations dn ON dn.drive_id = d.drive_id
             WHERE d.drive_date >= CURRENT_DATE
             GROUP BY d.drive_id, d.location, d.drive_date, d.capacity
             ORDER BY d.drive_date ASC'
        );
        $drives = $driveStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $drives = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driveId = (int) ($_POST['drive_id'] ?? 0);
    $donationDate = trim((string) ($_POST['donation_date'] ?? ''));
    $quantityMl = (int) ($_POST['quantity_ml'] ?? 450);

    if ($donorId <= 0) {
        $errorMessage = 'Your donor session is invalid. Please log in again.';
    } elseif ($driveId <= 0) {
        $errorMessage = 'Please choose a valid donation drive.';
    } elseif ($quantityMl <= 0 || $quantityMl > 900) {
        $errorMessage = 'Please enter a realistic donation quantity between 1 and 900 ml.';
    } elseif ($donationDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $donationDate)) {
        $errorMessage = 'Please select a valid donation date.';
    } elseif ($pdo instanceof PDO) {
        try {
            $driveStatement = $pdo->prepare(
                'SELECT drive_id, location, drive_date, capacity
                 FROM donation_drives
                 WHERE drive_id = :drive_id AND drive_date >= CURRENT_DATE
                 LIMIT 1'
            );
            $driveStatement->execute(['drive_id' => $driveId]);
            $drive = $driveStatement->fetch(PDO::FETCH_ASSOC);

            if (!$drive) {
                $errorMessage = 'This donation drive is no longer available.';
            } else {
                $countStatement = $pdo->prepare(
                    'SELECT COUNT(*) AS total
                     FROM donations
                     WHERE drive_id = :drive_id'
                );
                $countStatement->execute(['drive_id' => $driveId]);
                $currentCount = (int) $countStatement->fetchColumn();

                if ($currentCount >= (int) $drive['capacity']) {
                    $errorMessage = 'This donation drive is already full.';
                } else {
                    $existingStatement = $pdo->prepare(
                        'SELECT donation_id
                         FROM donations
                         WHERE donor_id = :donor_id AND drive_id = :drive_id
                         LIMIT 1'
                    );
                    $existingStatement->execute([
                        'donor_id' => $donorId,
                        'drive_id' => $driveId,
                    ]);

                    if ($existingStatement->fetch()) {
                        $errorMessage = 'You already recorded a donation for this drive.';
                    } else {
                        $insertStatement = $pdo->prepare(
                            'INSERT INTO donations (donor_id, drive_id, donation_date, quantity_ml)
                             VALUES (:donor_id, :drive_id, :donation_date, :quantity_ml)'
                        );
                        $insertStatement->execute([
                            'donor_id' => $donorId,
                            'drive_id' => $driveId,
                            'donation_date' => $donationDate,
                            'quantity_ml' => $quantityMl,
                        ]);

                        $userStatement = $pdo->prepare(
                            'UPDATE users
                             SET last_donation_date = :donation_date
                             WHERE user_id = :donor_id'
                        );
                        $userStatement->execute([
                            'donation_date' => $donationDate,
                            'donor_id' => $donorId,
                        ]);

                        $successMessage = 'Donation recorded successfully. Your contribution has been saved to your donor history.';
                        header('Location: history.php?success=1');
                        exit;
                    }
                }
            }
        } catch (PDOException $exception) {
            $errorMessage = 'The donation could not be recorded right now. Please try again.';
        }
    } else {
        $errorMessage = 'Database connection is unavailable. Please try again later.';
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Donation | BloodLink</title>
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
            border-radius: 6px;
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

        .page-top {
            margin-bottom: 22px;
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

        .page-copy {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 20px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--paper);
        }

        .booking-panel,
        .side-panel {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            gap: 18px;
        }

        label {
            display: block;
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
        }

        input, select {
            width: 100%;
            margin-top: 8px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            font: inherit;
        }

        input:focus, select:focus {
            border-color: var(--green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(23, 58, 54, 0.09);
        }

        .submit-button {
            border: none;
            border-radius: 8px;
            padding: 13px 18px;
            background: var(--red);
            color: white;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit-button:hover {
            filter: brightness(0.96);
        }

        .alert {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .alert.error {
            background: var(--soft-red);
            color: #7a2524;
            border: 1px solid #ebc5c2;
        }

        .alert.success {
            background: var(--soft-green);
            color: var(--green);
            border: 1px solid #c8e7d7;
        }

        .drive-list {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .drive-item {
            padding: 14px 15px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fbfdfc;
        }

        .drive-item strong {
            display: block;
            margin-bottom: 4px;
            color: var(--green);
            font-size: 14px;
        }

        .drive-item span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .side-panel h2 {
            margin-bottom: 14px;
            color: var(--green);
            font-size: 18px;
        }

        .hint {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
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
            .content-grid {
                grid-template-columns: 1fr;
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
                <a href="book_donation.php" aria-current="page">Book a donation</a>
                <a href="history.php">Donation history</a>
                <a href="../logout.php" class="logout">Logout</a>
            </nav>
        </div>
    </header>

    <main class="page-shell">
        <div class="page-top">
            <p class="eyebrow">Donor action</p>
            <h1>Book a donation</h1>
            <p class="page-copy">Choose an upcoming blood drive and record your donation for the donor history.</p>
        </div>

        <div class="content-grid">
            <section class="panel booking-panel" aria-label="Book a donation form">
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert error"><?= $escape($errorMessage) ?></div>
                <?php endif; ?>

                <form method="POST" action="book_donation.php" class="form-grid">
                    <div>
                        <label for="drive_id">Donation drive</label>
                        <select id="drive_id" name="drive_id" required>
                            <option value="">Select a drive</option>
                            <?php foreach ($drives as $drive): ?>
                                <?php
                                $capacity = (int) ($drive['capacity'] ?? 0);
                                $booked = (int) ($drive['booked_count'] ?? 0);
                                $remaining = max(0, $capacity - $booked);
                                ?>
                                <option value="<?= (int) $drive['drive_id'] ?>"><?= $escape($drive['location']) ?> — <?= $escape(date('M j, Y', strtotime($drive['drive_date']))) ?> (<?= $remaining ?> spots left)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="donation_date">Donation date</label>
                        <input id="donation_date" name="donation_date" type="date" required>
                    </div>

                    <div>
                        <label for="quantity_ml">Donation amount (ml)</label>
                        <input id="quantity_ml" name="quantity_ml" type="number" min="1" max="900" value="450" required>
                    </div>

                    <button type="submit" class="submit-button">Record donation</button>
                </form>
            </section>

            <aside class="panel side-panel" aria-label="Upcoming drives">
                <h2>Upcoming blood drives</h2>

                <?php if (!empty($drives)): ?>
                    <div class="drive-list">
                        <?php foreach ($drives as $drive): ?>
                            <?php
                            $capacity = (int) ($drive['capacity'] ?? 0);
                            $booked = (int) ($drive['booked_count'] ?? 0);
                            $remaining = max(0, $capacity - $booked);
                            ?>
                            <div class="drive-item">
                                <strong><?= $escape($drive['location']) ?></strong>
                                <span><?= $escape(date('F j, Y', strtotime($drive['drive_date']))) ?></span>
                                <span><?= $remaining ?> of <?= $capacity ?> spots remaining</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="hint">There are no upcoming donation drives right now. Please check back later.</p>
                <?php endif; ?>
            </aside>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            &copy; <?= date('Y') ?> BloodLink. Supporting safe, timely blood donation care.
        </div>
    </footer>
</body>
</html>
