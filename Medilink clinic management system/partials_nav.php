<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark clinic-nav sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(currentUserHome()); ?>">Medilink Emergency Clinic</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <?php if (currentUserRole() === 'patient'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="patient_dashboard.php">Patient Dashboard</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="doctor_dashboard.php">Doctor Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="emergency_form.php">Create Call</a>
                    </li>
                    <?php if (currentUserRole() === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">Users</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
                <li class="nav-item">
                    <span class="nav-link text-warning small">
                        Signed in as <?= e($_SESSION['user_name'] ?? 'User'); ?> (<?= e(ucfirst(currentUserRole())); ?>)
                    </span>
                </li>
                <li class="nav-item">
                    <a class="btn btn-sm btn-outline-light ms-lg-2" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
