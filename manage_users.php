<?php
session_start();

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$currentPage = 'manage_users.php';
$siteTitle = 'BloodLink';
require_once __DIR__ . '/../includes/header.php';

$adminId = (int) ($_SESSION['user']['id'] ?? 0);
$users = [];
$stats = [
    'total' => 0,
    'donors' => 0,
    'admins' => 0,
];
$errorMessage = '';
$successMessage = '';

if ($pdo instanceof PDO) {
    try {
        $userStatement = $pdo->query(
            'SELECT user_id, name, email, `role`, blood_type, last_donation_date
             FROM users
             ORDER BY `role` ASC, name ASC'
        );
        $users = $userStatement->fetchAll(PDO::FETCH_ASSOC);

        $stats['total'] = count($users);
        $stats['donors'] = count(array_filter($users, static fn ($user) => ($user['role'] ?? '') === 'donor'));
        $stats['admins'] = count(array_filter($users, static fn ($user) => ($user['role'] ?? '') === 'admin'));
    } catch (PDOException $exception) {
        $errorMessage = 'The user list could not be loaded right now.';
    }
} else {
    $users = [
        ['user_id' => 1, 'name' => 'Admin User', 'email' => 'admin@bloodlink.com', 'role' => 'admin', 'blood_type' => 'O+', 'last_donation_date' => null],
        ['user_id' => 2, 'name' => 'Jane Donor', 'email' => 'donor@bloodlink.com', 'role' => 'donor', 'blood_type' => 'A+', 'last_donation_date' => '2026-09-12'],
    ];
    $stats['total'] = 2;
    $stats['donors'] = 1;
    $stats['admins'] = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user' && $pdo instanceof PDO) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? 'donor'));
        $bloodType = trim((string) ($_POST['blood_type'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            $errorMessage = 'Please complete the name, email, and password fields.';
        } elseif (!in_array($role, ['admin', 'donor', 'requester'], true)) {
            $errorMessage = 'Choose a valid user role.';
        } else {
            try {
                $insertStatement = $pdo->prepare(
                    'INSERT INTO users (name, email, password, `role`, blood_type)
                     VALUES (:name, :email, :password, :role, :blood_type)'
                );
              $insertStatement->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'blood_type' => $bloodType !== '' ? $bloodType : null,
                ]);
                $successMessage = 'User created successfully.';
            } catch (PDOException $exception) {
                $errorMessage = 'A user with this email address may already exist.';
            }
        }
    }

    if ($action === 'update_role' && $pdo instanceof PDO) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = trim((string) ($_POST['role'] ?? 'donor'));

        if ($userId <= 0 || !in_array($role, ['admin', 'donor', 'requester'], true)) {
            $errorMessage = 'Please select a valid user and role.';
        } elseif ($userId === $adminId) {
            $errorMessage = 'You cannot change the current admin account role.';
        } else {
            try {
                $updateStatement = $pdo->prepare(
                    'UPDATE users
                     SET `role` = :role
                     WHERE user_id = :user_id'
                );
                $updateStatement->execute([
                    'role' => $role,
                    'user_id' => $userId,
                ]);
                $successMessage = 'User role updated successfully.';
            } catch (PDOException $exception) {
                $errorMessage = 'The role could not be updated right now.';
            }
        }
    }

    if ($action === 'delete_user' && $pdo instanceof PDO) {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            $errorMessage = 'Please select a user to delete.';
        } elseif ($userId === $adminId) {
            $errorMessage = 'You cannot delete your own admin account.';
        } else {
            try {
                $deleteStatement = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id LIMIT 1');
                $deleteStatement->execute(['user_id' => $userId]);
                $successMessage = 'User deleted successfully.';
            } catch (PDOException $exception) {
                $errorMessage = 'This user could not be removed.';
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
    <title>Manage Users | BloodLink</title>
    <link rel="stylesheet" href="../Styling/css/style.css">
    
</head>
<body class="page-admin-users">
    <main class="page-shell">
        <header class="page-top">
            <p class="eyebrow">Admin controls</p>
            <h1>Manage users</h1>
            <p class="page-copy">Keep donor and admin records organised and up to date.</p>
        </header>

        <?php if ($errorMessage): ?>
            <div class="alert error" role="alert"><?= $escape($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage || isset($_GET['success'])): ?>
            <div class="alert success" role="status">User changes saved successfully.</div>
        <?php endif; ?>

        <section class="stats-grid" aria-label="User statistics">
            <article class="panel stat-card">
                <span class="stat-label">Total users</span>
                <strong class="stat-value"><?= (int) $stats['total'] ?></strong>
            </article>
            <article class="panel stat-card">
                <span class="stat-label">Donors</span>
                <strong class="stat-value"><?= (int) $stats['donors'] ?></strong>
            </article>
            <article class="panel stat-card">
                <span class="stat-label">Admins</span>
                <strong class="stat-value"><?= (int) $stats['admins'] ?></strong>
            </article>
        </section>

        <section class="content-grid">
            <div class="panel table-panel">
                <h2 class="section-title">User directory</h2>
                <?php if ($users): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Blood</th>
                                <th>Role</th>
                                <th>Last donation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $escape($user['name'] ?? 'Unknown user') ?></td>
                                    <td><?= $escape($user['email'] ?? '') ?></td>
                                    <td><?= $escape($user['blood_type'] ?? 'Not set') ?></td>
                                    <td>
                                        <span class="badge <?= ($user['role'] ?? 'donor') === 'admin' ? 'admin' : 'donor' ?>">
                                            <?= $escape($user['role'] ?? 'donor') ?>
                                        </span>
                                    </td>
                                    <td><?= $escape($user['last_donation_date'] ?? 'Not donated yet') ?></td>
                                    <td>
                                        <div class="actions">
                                            <?php if ((int) ($user['user_id'] ?? 0) !== $adminId): ?>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Update this user role?');">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <input type="hidden" name="user_id" value="<?= (int) ($user['user_id'] ?? 0) ?>">
                                                    <select name="role" aria-label="Role">
                                                        <option value="donor" <?= (($user['role'] ?? 'donor') === 'donor') ? 'selected' : '' ?>>Donor</option>
                                                        <option value="admin" <?= (($user['role'] ?? 'donor') === 'admin') ? 'selected' : '' ?>>Admin</option>
                                                        <option value="requester" <?= (($user['role'] ?? 'donor') === 'requester') ? 'selected' : '' ?>>Requester</option>
                                                    </select>
                                                    <button type="submit" class="button secondary">Save</button>
                                                </form>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this user?');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= (int) ($user['user_id'] ?? 0) ?>">
                                                    <button type="submit" class="button danger">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge admin">Current</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="page-copy">No users found.</p>
                <?php endif; ?>
            </div>

            <aside class="panel form-panel">
                <h2 class="section-title">Add a user</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_user">
                    <label>
                        Full name
                        <input type="text" name="name" placeholder="Alicia Tan" required>
                    </label>
                    <label>
                        Email
                        <input type="email" name="email" placeholder="name@gmail.com" required>
                    </label>
                    <div class="inline-row">
                        <label>
                            Role
                            <select name="role">
                                <option value="donor">Donor</option>
                                <option value="admin">Admin</option>
                                <option value="requester">Requester</option>
                            </select>
                        </label>
                        <label>
                            Blood type
                            <select name="blood_type">
                                <option value="">Select</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </label>
                    </div>
                    <label>
                        Initial password
                        <input type="password" name="password" placeholder="Minimum 8 characters" required>
                    </label>
                    <button type="submit">Create user</button>
                </form>
            </aside>
        </section>
    </main>
</body>
</html>
