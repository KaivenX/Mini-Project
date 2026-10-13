<?php
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$requests = [];
$errorMessage = '';
$successMessage = '';

if (empty($_SESSION['request_admin_token'])) {
    $_SESSION['request_admin_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['request_admin_token'] ?? '');
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string) ($_POST['action'] ?? '');

    if (!hash_equals($_SESSION['request_admin_token'], $token)) {
        $errorMessage = 'Your session has expired. Please try again.';
    } elseif ($requestId === false || $requestId < 1) {
        $errorMessage = 'Choose a valid blood request.';
    } elseif (!in_array($action, ['approve', 'reject', 'fulfill'], true)) {
        $errorMessage = 'Choose a valid request action.';
    } elseif (!($pdo instanceof PDO)) {
        $errorMessage = 'Request management is unavailable because the database could not be reached.';
    } else {
        try {
            $pdo->beginTransaction();
            $requestStatement = $pdo->prepare(
                'SELECT request_id, blood_type, quantity_units, status
                 FROM blood_requests
                 WHERE request_id = :request_id
                 FOR UPDATE'
            );
            $requestStatement->execute(['request_id' => $requestId]);
            $request = $requestStatement->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $errorMessage = 'That blood request no longer exists.';
            } elseif ($action === 'approve' && $request['status'] === 'Pending') {
                $pdo->prepare(
                    'UPDATE blood_requests SET status = \'Approved\'
                     WHERE request_id = :request_id'
                )->execute(['request_id' => $requestId]);
                $successMessage = 'Request approved.';
            } elseif ($action === 'reject' && in_array($request['status'], ['Pending', 'Approved'], true)) {
                $pdo->prepare(
                    'UPDATE blood_requests SET status = \'Rejected\'
                     WHERE request_id = :request_id'
                )->execute(['request_id' => $requestId]);
                $successMessage = 'Request rejected.';
            } elseif ($action === 'fulfill' && $request['status'] === 'Approved') {
                $stockStatement = $pdo->prepare(
                    'SELECT stock_id, quantity_units
                     FROM blood_stock
                     WHERE blood_type = :blood_type
                     FOR UPDATE'
                );
                $stockStatement->execute(['blood_type' => $request['blood_type']]);
                $stock = $stockStatement->fetch(PDO::FETCH_ASSOC);

                if (!$stock || (int) $stock['quantity_units'] < (int) $request['quantity_units']) {
                    $errorMessage = 'There is not enough stock to fulfill this request.';
                } else {
                    $updateStock = $pdo->prepare(
                        'UPDATE blood_stock
                         SET quantity_units = quantity_units - :quantity_to_deduct
                         WHERE stock_id = :stock_id
                           AND quantity_units >= :minimum_quantity'
                    );
                    $updateStock->execute([
                        'quantity_to_deduct' => (int) $request['quantity_units'],
                        'stock_id' => (int) $stock['stock_id'],
                        'minimum_quantity' => (int) $request['quantity_units'],
                    ]);

                    if ($updateStock->rowCount() !== 1) {
                        $errorMessage = 'Stock changed while processing. Please try again.';
                    } else {
                        $pdo->prepare(
                            'UPDATE blood_requests SET status = \'Fulfilled\'
                             WHERE request_id = :request_id'
                        )->execute(['request_id' => $requestId]);
                        $successMessage = 'Request fulfilled and blood stock updated.';
                    }
                }
            } else {
                $errorMessage = 'That action is not allowed for the request’s current status.';
            }

            if ($errorMessage !== '') {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = 'The request could not be updated. Please try again.';
        }
    }
}

if ($pdo instanceof PDO) {
    try {
        $statement = $pdo->query(
            'SELECT r.request_id, r.hospital_name, r.blood_type,
                    r.quantity_units, r.urgency, r.status, r.created_at,
                    u.name AS requester_name, u.email AS requester_email
             FROM blood_requests r
             INNER JOIN users u ON u.user_id = r.requester_id
             ORDER BY FIELD(r.status, \'Pending\', \'Approved\', \'Rejected\', \'Fulfilled\'),
                      FIELD(r.urgency, \'Critical\', \'Normal\'),
                      r.created_at DESC'
        );
        $requests = $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $errorMessage = 'Blood requests could not be loaded. Please check the database setup.';
    }
} elseif ($errorMessage === '') {
    $errorMessage = 'Request management is unavailable because the database could not be reached.';
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$currentPage = 'manage_requests.php';
$siteTitle = 'BloodLink';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blood Requests | BloodLink</title>
    <link rel="stylesheet" href="../Styling/css/style.css">
</head>
<body class="page-admin-users">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    <main class="page-shell">
        <header class="page-top">
            <p class="eyebrow">Blood bank operations</p>
            <h1>Manage blood requests</h1>
            <p class="page-copy">Review requests, prioritize critical needs, and deduct stock when a request is fulfilled.</p>
        </header>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert error" role="alert"><?= $escape($errorMessage) ?></div>
        <?php elseif ($successMessage !== ''): ?>
            <div class="alert success" role="status"><?= $escape($successMessage) ?></div>
        <?php endif; ?>

        <section class="panel table-panel">
            <?php if ($requests): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Requester</th>
                            <th>Hospital</th>
                            <th>Blood type</th>
                            <th>Units</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?= $escape($request['requester_name']) ?><br><?= $escape($request['requester_email']) ?></td>
                                <td><?= $escape($request['hospital_name']) ?></td>
                                <td><?= $escape($request['blood_type']) ?></td>
                                <td><?= (int) $request['quantity_units'] ?></td>
                                <td><?= $escape($request['urgency']) ?></td>
                                <td><?= $escape($request['status']) ?></td>
                                <td><?= $escape(date('M j, Y', strtotime($request['created_at']))) ?></td>
                                <td>
                                    <?php if (in_array($request['status'], ['Pending', 'Approved'], true)): ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="request_admin_token" value="<?= $escape($_SESSION['request_admin_token']) ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                                            <?php if ($request['status'] === 'Pending'): ?>
                                                <button type="submit" name="action" value="approve">Approve</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="fulfill">Fulfill</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="reject" class="button danger">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="page-copy">There are no blood requests yet.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
