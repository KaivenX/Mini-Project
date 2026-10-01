<?php
session_start();
require_once __DIR__ . '/bloodlink/config/db.php';

if (empty($_SESSION['registration_token'])) {
	$_SESSION['registration_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$name = '';
$email = '';
$bloodType = '';
$registered = false;
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = trim($_POST['name'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';
	$passwordConfirmation = $_POST['password_confirmation'] ?? '';
	$bloodType = $_POST['blood_type'] ?? '';
	$token = $_POST['registration_token'] ?? '';

	if (!hash_equals($_SESSION['registration_token'], $token)) {
		$errors[] = 'Your session has expired. Please submit the form again.';
	}
	if ($name === '' || mb_strlen($name) > 100) {
		$errors[] = 'Enter a name that is no longer than 100 characters.';
	}
	if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
		$errors[] = 'Enter a valid email address (up to 100 characters).';
	} elseif (strtolower(substr(strrchr($email, '@') ?: '', 1)) !== 'gmail.com') {
		$errors[] = 'Use a Gmail address ending in @gmail.com.';
	}
	if (strlen($password) < 8) {
		$errors[] = 'Your password must be at least 8 characters long.';
	}
	if ($password !== $passwordConfirmation) {
		$errors[] = 'The passwords do not match.';
	}
	if ($bloodType !== '' && !in_array($bloodType, $bloodTypes, true)) {
		$errors[] = 'Select a valid blood type.';
	}

	if (!$errors && !$pdo) {
		$errors[] = 'Registration is temporarily unavailable because the database could not be reached.';
	}

	if (!$errors) {
		try {
			$statement = $pdo->prepare(
				'INSERT INTO users (name, email, password, role, blood_type)
				 VALUES (:name, :email, :password, \'donor\', :blood_type)'
			);
			$statement->execute([
				'name' => $name,
				'email' => $email,
				'password' => password_hash($password, PASSWORD_DEFAULT),
				'blood_type' => $bloodType !== '' ? $bloodType : null,
			]);

			unset($_SESSION['registration_token']);
			$registered = true;
		} catch (PDOException $exception) {
			if ($exception->getCode() === '23000') {
				$errors[] = 'An account with this email address already exists.';
			} else {
				$errors[] = 'We could not create your account. Please try again.';
			}
		}
	}
}

function escape(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>BloodLink Registration</title>
	<link rel="stylesheet" href="bloodlink/Styling/css/style.css">
</head>

<body class="registration-page">
	<main class="registration-layout">
		<form method="POST" action="register.php" class="login-form registration-form">
			<header class="registration-heading">
				<h1 id="registration-title">Create your account</h1>
				<p>We're glad you're here. A few details will get you started.</p>
			</header>

			<?php if ($registered): ?>
				<div class="form-message success" role="status">
					Your donor account has been created. <a href="login.php">Sign in</a> to continue.
				</div>
			<?php else: ?>
				<?php if ($errors): ?>
					<div class="form-message error" role="alert">
						<ul>
							<?php foreach ($errors as $error): ?>
								<li><?= escape($error) ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<input type="hidden" name="registration_token" value="<?= escape($_SESSION['registration_token']) ?>">

				<div class="registration-fields">
				<label>
					<span class="field-label">Full name</span>
					<input type="text" name="name" value="<?= escape($name) ?>" maxlength="100" autocomplete="name" required>
				</label>
				<label>
					<span class="field-label">Email address</span>
					<input type="email" name="email" value="<?= escape($email) ?>" maxlength="100" autocomplete="email" placeholder="name@gmail.com" required>
				</label>
				<label>
					<span class="field-label">Blood type <small>Optional</small></span>
					<div class="select-control">
						<select name="blood_type">
							<option value="">Choose blood type</option>
							<?php foreach ($bloodTypes as $type): ?>
								<option value="<?= escape($type) ?>" <?= $bloodType === $type ? 'selected' : '' ?>><?= escape($type) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</label>
				<label>
					<span class="field-label">Password</span>
					<input type="password" name="password" minlength="8" autocomplete="new-password" required>
				</label>
				<label>
					<span class="field-label">Confirm password</span>
					<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
				</label>
				</div>
				<button type="submit">Create Account</button>
				<p class="Sign-text">Already have an account? <a class="Sign" href="login.php">Log in</a></p>
			<?php endif; ?>
		</form>
	</main>
</body>

</html>
