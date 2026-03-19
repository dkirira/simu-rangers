<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

try {
    $userCheckStatement = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM users LIMIT 1)');
    $userCheckStatement->execute();
    $hasUsers = (bool) $userCheckStatement->fetchColumn();
} catch (PDOException $exception) {
    $hasUsers = false;
}

if ($hasUsers) {
    setFlash('flash_error', 'Initial setup is disabled because users already exist.');
    header('Location: login.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '') {
        $errors[] = 'Administrator name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid administrator email is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
            ]);

            $userId = (int) $pdo->lastInsertId();
            $adminProfileStatement = $pdo->prepare('INSERT INTO admins (user_id) VALUES (:user_id)');
            $adminProfileStatement->execute(['user_id' => $userId]);

            setFlash(
                'flash_success',
                'Initial administrator created successfully. You can now sign in and create doctor or patient accounts.'
            );
            header('Location: login.php');
            exit;
        } catch (PDOException $exception) {
            $errors[] = 'Unable to create the initial administrator. Please verify the database schema and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial Setup | Medilink Emergency Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="pattern-overlay"></div>
    <main class="container py-5">
        <div class="auth-card mx-auto">
            <div class="text-center mb-4">
                <span class="eyebrow">First-Time Configuration</span>
                <h1 class="h2">Create the Initial Admin Account</h1>
                <p class="text-muted mb-0">This screen is available only when the system has no users.</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="name">Full name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($name); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="confirm_password">Confirm password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Create Administrator</button>
            </form>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
