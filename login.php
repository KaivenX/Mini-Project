<?php
session_start();
require_once __DIR__ . '/bloodlink/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = null;

    if ($pdo) {
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => (int) $user['user_id'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];

            $redirect = ($user['role'] === 'admin')
                ? 'bloodlink/admin/dashboard.php'
                : 'donor/dashboard.php';

            header('Location: ' . $redirect);
            exit;
        }
    } else {
        $demoUsers = [
            'admin@bloodlink.com' => [
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'admin',
            ],
            'donor@bloodlink.com' => [
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'role' => 'donor',
            ],
        ];

        if (isset($demoUsers[$email]) && password_verify($password, $demoUsers[$email]['password'])) {
            $_SESSION['user'] = [
                'id' => 1,
                'email' => $email,
                'role' => $demoUsers[$email]['role'],
            ];

            $redirect = ($demoUsers[$email]['role'] === 'admin')
                ? 'bloodlink/admin/dashboard.php'
                : 'donor/dashboard.php';

            header('Location: ' . $redirect);
            exit;
        }
    }

    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloodLink Login</title>
    <link rel="stylesheet" href="bloodlink/Styling/css/style.css">
</head>

<body>
    <div class="login-shell">
        <?php if ($error): ?>
            <p class="error-message">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form">
            <h1>Welcome Back </h1>
            <span class="tt">Sign in to manage your appointments & donations</span>
            <label>
                <input type="email" name="email" placeholder="Email" required>
            </label>
            <label>
                <input type="password" name="password" placeholder="Password" required>
            </label>
            <button type="submit">Log In</button>
            <div class="divider">
                <span class="line"></span>
                <span class="divider-text">or sign in with</span>
                <span class="line"></span>
            </div>
            <div class="social-icons">
                <a class="social-icon apple" href="" aria-label="Continue with Apple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-apple"
                        viewBox="0 0 16 16">
                        <path
                            d="M11.182.008C11.148-.03 9.923.023 8.857 1.18c-1.066 1.156-.902 2.482-.878 2.516s1.52.087 2.475-1.258.762-2.391.728-2.43m3.314 11.733c-.048-.096-2.325-1.234-2.113-3.422s1.675-2.789 1.698-2.854-.597-.79-1.254-1.157a3.7 3.7 0 0 0-1.563-.434c-.108-.003-.483-.095-1.254.116-.508.139-1.653.589-1.968.607-.316.018-1.256-.522-2.267-.665-.647-.125-1.333.131-1.824.328-.49.196-1.422.754-2.074 2.237-.652 1.482-.311 3.83-.067 4.56s.625 1.924 1.273 2.796c.576.984 1.34 1.667 1.659 1.899s1.219.386 1.843.067c.502-.308 1.408-.485 1.766-.472.357.013 1.061.154 1.782.539.571.197 1.111.115 1.652-.105.541-.221 1.324-1.059 2.238-2.758q.52-1.185.473-1.282" />
                        <path
                            d="M11.182.008C11.148-.03 9.923.023 8.857 1.18c-1.066 1.156-.902 2.482-.878 2.516s1.52.087 2.475-1.258.762-2.391.728-2.43m3.314 11.733c-.048-.096-2.325-1.234-2.113-3.422s1.675-2.789 1.698-2.854-.597-.79-1.254-1.157a3.7 3.7 0 0 0-1.563-.434c-.108-.003-.483-.095-1.254.116-.508.139-1.653.589-1.968.607-.316.018-1.256-.522-2.267-.665-.647-.125-1.333.131-1.824.328-.49.196-1.422.754-2.074 2.237-.652 1.482-.311 3.83-.067 4.56s.625 1.924 1.273 2.796c.576.984 1.34 1.667 1.659 1.899s1.219.386 1.843.067c.502-.308 1.408-.485 1.766-.472.357.013 1.061.154 1.782.539.571.197 1.111.115 1.652-.105.541-.221 1.324-1.059 2.238-2.758q.52-1.185.473-1.282" />
                    </svg>
                </a>
                <a class="social-icon google" href="" aria-label="Continue with Google">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 48 48" aria-label="Google" role="img">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.72 1.22 9.22 3.62l6.85-6.85C35.92 2.84 30.42 0 24 0 14.6 0 6.45 5.38 2.5 13.22l7.98 6.2C12.12 13.94 17.57 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.5 24.5c0-1.62-.15-3.18-.42-4.68H24v8.85h12.7c-.55 2.96-2.22 5.47-4.74 7.16l7.69 5.97c4.49-4.14 7.06-10.25 7.06-17.3z"/>
                        <path fill="#FBBC05" d="M24 48c6.42 0 11.82-.96 15.76-2.92l-7.69-5.97c-2.13 1.44-4.86 2.3-8.07 2.3-6.43 0-11.88-4.34-13.82-10.18l-7.95 6.16C6.45 42.62 14.6 48 24 48z"/>
                        <path fill="#34A853" d="M10.18 34.43A14.95 14.95 0 0 1 9.5 24c0-1.68.29-3.3.8-4.82L2.5 13.22A23.97 23.97 0 0 0 0 24c0 3.84.92 7.47 2.5 10.62l7.68-6.19z"/>
                    </svg>
                </a>
            </div>
            <span class="Sign-text" >Dont have an account?   <a class="Sign" href="register.php">Sign Up</a></span>
        </form>
    </div>
</body>

</html>