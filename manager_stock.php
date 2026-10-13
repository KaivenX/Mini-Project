<?php
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$errorMessage = '';
$stock = [];

if (empty($_SESSION['stock_token'])) {
    $_SESSION['stock_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['stock_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');

    if (!hash_equals($_SESSION['stock_token'], $token)) {
        $errorMessage = 'Your session has expired. Please try again.';
    } elseif (!($pdo instanceof PDO)) {
        $errorMessage = 'Stock changes are unavailable because the database could not be reached.';
    } else {
        try {
            if ($action === 'create') {
                $bloodType = (string) ($_POST['blood_type'] ?? '');
                $quantity = filter_var($_POST['quantity_units'] ?? null, FILTER_VALIDATE_INT);

                if (!in_array($bloodType, $bloodTypes, true) || $quantity === false || $quantity < 0) {
                    $errorMessage = 'Choose a valid blood type and a non-negative whole-number quantity.';
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO blood_stock (blood_type, quantity_units)
                         VALUES (:blood_type, :quantity_units)'
                    );
                    $statement->execute([
                        'blood_type' => $bloodType,
                        'quantity_units' => $quantity,
                    ]);
                    header('Location: manager_stock.php?status=created');
                    exit;
                }
            } elseif ($action === 'update') {
                $stockId = filter_var($_POST['stock_id'] ?? null, FILTER_VALIDATE_INT);
                $quantity = filter_var($_POST['quantity_units'] ?? null, FILTER_VALIDATE_INT);

                if ($stockId === false || $stockId < 1 || $quantity === false || $quantity < 0) {
                    $errorMessage = 'Choose a valid stock record and a non-negative whole-number quantity.';
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE blood_stock
                         SET quantity_units = :quantity_units
                         WHERE stock_id = :stock_id'
                    );
                    $statement->execute([
                        'quantity_units' => $quantity,
                        'stock_id' => $stockId,
                    ]);
                    header('Location: manager_stock.php?status=updated');
                    exit;
                }
            } elseif ($action === 'delete') {
                $stockId = filter_var($_POST['stock_id'] ?? null, FILTER_VALIDATE_INT);

                if ($stockId === false || $stockId < 1) {
                    $errorMessage = 'Choose a valid stock record to remove.';
                } else {
                    $statement = $pdo->prepare(
                        'DELETE FROM blood_stock WHERE stock_id = :stock_id'
                    );
                    $statement->execute(['stock_id' => $stockId]);
                    header('Location: manager_stock.php?status=deleted');
                    exit;
                }
            } else {
                $errorMessage = 'The requested stock action is not valid.';
            }
        } catch (PDOException $exception) {
            $errorMessage = 'The stock change could not be saved. Check for duplicate blood types and try again.';
        }
    }
}

if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->query(
            'SELECT stock_id, blood_type, quantity_units
             FROM blood_stock
             ORDER BY blood_type ASC'
        );
        $stock = $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $errorMessage = 'Blood stock could not be loaded. Please check the database setup.';
    }
} elseif ($errorMessage === '') {
    $errorMessage = 'Stock records are unavailable because the database could not be reached.';
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusMessages = [
    'created' => 'Stock record added.',
    'updated' => 'Stock quantity updated.',
    'deleted' => 'Stock record deleted.',
];
$status = (string) ($_GET['status'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blood Stock | BloodLink</title>
    <link rel="stylesheet" href="../Styling/css/style.css">
    
</head>
<body class="page-admin-stock">
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="dashboard.php">BloodLink Admin</a>
            <nav aria-label="Admin navigation">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_users.php">Manage users</a>
                <a href="manager_driver.php">Manage drives</a>
                <a href="manager_stock.php" aria-current="page">Manage stock</a>
                <a class="logout" href="../../logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <main class="page-shell">
        <p class="eyebrow">Inventory</p>
        <h1>Manage blood stock</h1>
        <p class="intro">View and maintain the available units for each blood type.</p>

        <?php if ($errorMessage !== ''): ?>
            <div class="notice error" role="alert"><?= $escape($errorMessage) ?></div>
        <?php endif; ?>
        <?php if (isset($statusMessages[$status])): ?>
            <div class="notice success" role="status"><?= $escape($statusMessages[$status]) ?></div>
        <?php endif; ?>

        <div class="layout">
            <section class="panel" aria-labelledby="stock-heading">
                <h2 id="stock-heading">Current stock</h2>
                <?php if ($stock): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Blood type</th><th>Units</th><th>Update quantity</th><th>Remove</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock as $item): ?>
                                    <tr>
                                        <td><strong><?= $escape($item['blood_type'] ?? '') ?></strong></td>
                                        <td><?= (int) ($item['quantity_units'] ?? 0) ?></td>
                                        <td>
                                            <form method="post" class="row-form">
                                                <input type="hidden" name="stock_token" value="<?= $escape($_SESSION['stock_token']) ?>">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="stock_id" value="<?= (int) $item['stock_id'] ?>">
                                                <input type="number" name="quantity_units" min="0" step="1" value="<?= (int) $item['quantity_units'] ?>" aria-label="Units for <?= $escape($item['blood_type']) ?>" required>
                                                <button type="submit">Save</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Remove this stock record?');">
                                                <input type="hidden" name="stock_token" value="<?= $escape($_SESSION['stock_token']) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="stock_id" value="<?= (int) $item['stock_id'] ?>">
                                                <button class="danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="empty-state">No blood stock records are available yet. Add a record to get started.</p>
                <?php endif; ?>
            </section>

            <aside class="panel" aria-labelledby="add-heading">
                <h2 id="add-heading">Add stock record</h2>
                <form class="add-form" method="post">
                    <input type="hidden" name="stock_token" value="<?= $escape($_SESSION['stock_token']) ?>">
                    <input type="hidden" name="action" value="create">
                    <label>
                        Blood type
                        <select name="blood_type" required>
                            <option value="">Select a blood type</option>
                            <?php foreach ($bloodTypes as $bloodType): ?>
                                <option value="<?= $escape($bloodType) ?>"><?= $escape($bloodType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Quantity (units)
                        <input type="number" name="quantity_units" min="0" step="1" value="0" required>
                    </label>
                    <button type="submit">Add stock</button>
                </form>
            </aside>
        </div>
    </main>
</body>
</html>                                                                     