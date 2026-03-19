<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUserRole(): string
{
    return (string) ($_SESSION['user_role'] ?? '');
}

function currentUserHome(): string
{
    $role = currentUserRole();

    if ($role === 'patient') {
        return 'patient_dashboard.php';
    }

    if (in_array($role, ['doctor', 'admin'], true)) {
        return 'doctor_dashboard.php';
    }

    return 'login.php';
}

function redirectToHome(): void
{
    header('Location: ' . currentUserHome());
    exit;
}

function requireRole(string $role): void
{
    requireLogin();

    if (currentUserRole() !== $role) {
        $_SESSION['flash_error'] = 'You do not have permission to access that page.';
        redirectToHome();
    }
}

function requireAnyRole(array $roles): void
{
    requireLogin();

    if (!in_array(currentUserRole(), $roles, true)) {
        $_SESSION['flash_error'] = 'You do not have permission to access that page.';
        redirectToHome();
    }
}

function requirePatientContext(): void
{
    requireRole('patient');

    if (!isset($_SESSION['patient_id']) || !is_numeric($_SESSION['patient_id'])) {
        setFlash('flash_error', 'Your patient account is missing a linked patient record.');
        header('Location: logout.php');
        exit;
    }
}

function requireDoctorAccess(): void
{
    requireAnyRole(['doctor', 'admin']);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $key, string $message): void
{
    $_SESSION[$key] = $message;
}

function getFlash(string $key): ?string
{
    if (!isset($_SESSION[$key])) {
        return null;
    }

    $message = (string) $_SESSION[$key];
    unset($_SESSION[$key]);

    return $message;
}
