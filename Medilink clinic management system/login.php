<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    redirectToHome();
}

$error = '';
$email = '';
$selectedRole = 'doctor';
$hasUsers = false;

try {
    $userCheckStatement = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM users LIMIT 1)');
    $userCheckStatement->execute();
    $hasUsers = (bool) $userCheckStatement->fetchColumn();
} catch (PDOException $exception) {
    $hasUsers = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $selectedRole = trim((string) ($_POST['role'] ?? 'doctor'));
    $allowedRoles = ['doctor', 'patient'];

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!in_array($selectedRole, $allowedRoles, true)) {
        $error = 'Please choose whether you are signing in as a doctor or a patient.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $statement = $pdo->prepare('SELECT id, name, email, password, role, patient_id FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Invalid email or password.';
            } elseif ($user['role'] !== $selectedRole) {
                $error = 'Role mismatch. This account is registered as ' . ucfirst((string) $user['role']) . '.';
            } elseif ($selectedRole === 'patient' && $user['patient_id'] === null) {
                $error = 'This patient account is not linked to a patient record yet.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['patient_id'] = $user['patient_id'] !== null ? (int) $user['patient_id'] : null;
                setFlash('flash_success', 'Welcome back, ' . $user['name'] . '.');
                redirectToHome();
            }
        } catch (PDOException $exception) {
            $error = 'Unable to complete login right now. Please try again.';
        }
    }
}

$flashError = getFlash('flash_error');
$flashSuccess = getFlash('flash_success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Medilink Emergency Clinic</title>
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
                <span class="eyebrow">Secure Portal Access</span>
                <h1 class="h2">Sign in to Medilink</h1>
                <p class="text-muted mb-0">Use your doctor or patient credentials and make sure the selected role matches your account.</p>
            </div>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= e($flashSuccess); ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-danger"><?= e($flashError); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= e($error); ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="role">Sign in as</label>
                    <div class="role-select-shell">
                        <select class="form-select form-select-lg" id="role" name="role" required>
                            <option value="doctor" <?= $selectedRole === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                            <option value="patient" <?= $selectedRole === 'patient' ? 'selected' : ''; ?>>Patient</option>
                        </select>
                    </div>
                    <div class="form-text role-badge-note">Choose the same role stored on your Medilink account.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        value="<?= e($email); ?>"
                        placeholder="doctor@medilinkclinic.com"
                        required
                    >
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <div class="mt-4 small text-muted text-center">
                <?php if (!$hasUsers): ?>
                    No users yet? <a href="setup.php">Run initial setup</a>.
                <?php else: ?>
                    Need a doctor or patient account? Contact an administrator.
                <?php endif; ?>
            </div>

            <div class="mt-2 small text-center">
                <a href="admin_login.php" class="text-decoration-none">Administrator sign-in</a>
            </div>
        </div>
    </main>
</body>
</html>
