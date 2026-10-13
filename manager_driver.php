<?php
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$currentPage = 'manager_driver.php';
$siteTitle = 'BloodLink';
require_once __DIR__ . '/../includes/header.php';

$adminId = (int) ($_SESSION['user']['id'] ?? 0);
$drives = [];
$errorMessage = '';
$successMessage = '';

if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->query(
            'SELECT drive_id, organized_by, location, drive_date, capacity
             FROM donation_drives
             ORDER BY drive_date DESC, drive_id DESC'
        );
        $drives = $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $errorMessage = 'The donation drive list could not be loaded.';
    }
} else {
    $drives = [
        ['drive_id' => 1, 'organized_by' => 1, 'location' => 'Kuala Lumpur City Hospital', 'drive_date' => '2026-10-15', 'capacity' => 25],
        ['drive_id' => 2, 'organized_by' => 1, 'location' => 'Petaling Jaya Community Hall', 'drive_date' => '2026-10-22', 'capacity' => 18],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_drive' && $pdo instanceof PDO) {
        $location = trim((string) ($_POST['location'] ?? ''));
        $driveDate = trim((string) ($_POST['drive_date'] ?? ''));
        $capacity = (int) ($_POST['capacity'] ?? 0);

        if ($location === '' || $driveDate === '' || $capacity <= 0) {
            $errorMessage = 'Please complete the drive location, date, and capacity.';
        } else {
            try {
                $insertStatement = $pdo->prepare(
                    'INSERT INTO donation_drives (organized_by, location, drive_date, capacity)
                     VALUES (:organized_by, :location, :drive_date, :capacity)'
                );
                $insertStatement->execute([
                    'organized_by' => $adminId,
                    'location' => $location,
                    'drive_date' => $driveDate,
                    'capacity' => $capacity,
                ]);
                $successMessage = 'Donation drive created successfully.';
            } catch (PDOException $exception) {
                $errorMessage = 'A new drive could not be created.';
            }
        }
    }

    if ($action === 'delete_drive' && $pdo instanceof PDO) {
        $driveId = (int) ($_POST['drive_id'] ?? 0);

        if ($driveId <= 0) {
            $errorMessage = 'Choose a valid drive to remove.';
        } else {
            try {
                $deleteStatement = $pdo->prepare('DELETE FROM donation_drives WHERE drive_id = :drive_id LIMIT 1');
                $deleteStatement->execute(['drive_id' => $driveId]);
                $successMessage = 'Donation drive removed successfully.';
            } catch (PDOException $exception) {
                $errorMessage = 'This drive could not be removed.';
            }
        }
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Donation Drives | BloodLink</title>
    <link rel="stylesheet" href="../Styling/css/style.css">
    
</head>
<body class="page-admin-drives">
    <main class="page-shell">
        <header class="page-top">
            <p class="eyebrow">Blood bank operations</p>
            <h1>Manage donation drives</h1>
            <p class="page-copy">Plan community drives and keep a clear record of upcoming blood collection events.</p>
        </header>

        <?php if ($errorMessage): ?>
            <div class="alert error" role="alert"><?= $escape($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage || isset($_GET['success'])): ?>
            <div class="alert success" role="status">The donation drive was updated successfully.</div>
        <?php endif; ?>

        <section class="content-grid">
            <div class="panel table-panel">
                <h2 class="section-title">Upcoming drives</h2>
                <?php if ($drives): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Capacity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drives as $drive): ?>
                                <tr>
                                    <td><?= $escape($drive['location'] ?? 'Unknown location') ?></td>
                                    <td><?= $escape($drive['drive_date'] ?? '') ?></td>
                                    <td><?= (int) ($drive['capacity'] ?? 0) ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this drive?');">
                                            <input type="hidden" name="action" value="delete_drive">
                                            <input type="hidden" name="drive_id" value="<?= (int) ($drive['drive_id'] ?? 0) ?>">
                                            <button type="submit" class="button danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="page-copy">No drives are scheduled yet.</p>
                <?php endif; ?>
            </div>

            <aside class="panel form-panel">
                <h2 class="section-title">Add a drive</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_drive">
                    <label>
                        Location
                        <input type="text" name="location" placeholder="Sunway Medical Centre" required>
                    </label>
                    <label>
                        Drive date
                        <input type="date" name="drive_date" required>
                    </label>
                    <label>
                        Capacity
                        <input type="number" name="capacity" min="1" value="20" required>
                    </label>
                    <button type="submit">Create drive</button>
                </form>
            </aside>
        </section>
    </main>
</body>
</html>
