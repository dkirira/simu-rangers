<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireRole('admin');

$errors = [];
$form = [
    'name' => '',
    'email' => '',
    'role' => 'doctor',
    'patient_id' => '',
    'phone' => '',
    'date_of_birth' => '',
    'medical_record_number' => '',
];

try {
    $patientsStatement = $pdo->prepare('SELECT id, name, medical_record_number FROM patients ORDER BY name ASC');
    $patientsStatement->execute();
    $patients = $patientsStatement->fetchAll();
} catch (PDOException $exception) {
    $patients = [];
    $errors[] = 'Unable to load patients for account linking.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $form['role'] = trim((string) ($_POST['role'] ?? 'doctor'));
    $form['patient_id'] = trim((string) ($_POST['patient_id'] ?? ''));
    $form['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $form['date_of_birth'] = trim((string) ($_POST['date_of_birth'] ?? ''));
    $form['medical_record_number'] = trim((string) ($_POST['medical_record_number'] ?? ''));

    if ($form['name'] === '') {
        $errors[] = 'Name is required.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (!in_array($form['role'], ['admin', 'doctor', 'patient'], true)) {
        $errors[] = 'Please choose a valid user role.';
    }

    if ($form['role'] === 'patient') {
        if ($form['patient_id'] !== '') {
            if (!ctype_digit($form['patient_id'])) {
                $errors[] = 'Please choose a valid linked patient record.';
            }
        }

        if ($form['patient_id'] === '') {
            if ($form['phone'] === '') {
                $errors[] = 'A phone number is required when creating a new patient record.';
            }

            if ($form['medical_record_number'] === '') {
                $errors[] = 'A medical record number is required when creating a new patient record.';
            }
        }

        if ($form['date_of_birth'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['date_of_birth'])) {
            $errors[] = 'Date of birth must use the YYYY-MM-DD format.';
        }

        if ($form['patient_id'] !== '' && ctype_digit($form['patient_id'])) {
            try {
                $patientCheckStatement = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM patients WHERE id = :id)');
                $patientCheckStatement->execute(['id' => (int) $form['patient_id']]);

                if (!(bool) $patientCheckStatement->fetchColumn()) {
                    $errors[] = 'The selected patient record does not exist.';
                }
            } catch (PDOException $exception) {
                $errors[] = 'Unable to validate the linked patient record.';
            }
        }

        if ($form['patient_id'] === '' && $form['medical_record_number'] !== '') {
            try {
                $recordNumberCheckStatement = $pdo->prepare(
                    'SELECT EXISTS(SELECT 1 FROM patients WHERE medical_record_number = :medical_record_number)'
                );
                $recordNumberCheckStatement->execute(['medical_record_number' => $form['medical_record_number']]);

                if ((bool) $recordNumberCheckStatement->fetchColumn()) {
                    $errors[] = 'That medical record number already exists. Link the existing patient record instead.';
                }
            } catch (PDOException $exception) {
                $errors[] = 'Unable to validate the medical record number right now.';
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $linkedPatientId = null;

            if ($form['role'] === 'patient') {
                if ($form['patient_id'] !== '') {
                    $linkedPatientId = (int) $form['patient_id'];
                } else {
                    $patientInsertStatement = $pdo->prepare(
                        'INSERT INTO patients (name, phone, date_of_birth, medical_record_number)
                         VALUES (:name, :phone, :date_of_birth, :medical_record_number)'
                    );
                    $patientInsertStatement->execute([
                        'name' => $form['name'],
                        'phone' => $form['phone'],
                        'date_of_birth' => $form['date_of_birth'] !== '' ? $form['date_of_birth'] : null,
                        'medical_record_number' => $form['medical_record_number'],
                    ]);
                    $linkedPatientId = (int) $pdo->lastInsertId();
                }
            }

            $statement = $pdo->prepare(
                'INSERT INTO users (name, email, password, patient_id, role)
                 VALUES (:name, :email, :password, :patient_id, :role)'
            );
            $statement->execute([
                'name' => $form['name'],
                'email' => $form['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'patient_id' => $linkedPatientId,
                'role' => $form['role'],
            ]);

            $userId = (int) $pdo->lastInsertId();

            if ($form['role'] === 'doctor') {
                $doctorProfileStatement = $pdo->prepare('INSERT INTO doctors (user_id) VALUES (:user_id)');
                $doctorProfileStatement->execute(['user_id' => $userId]);
            }

            if ($form['role'] === 'admin') {
                $adminProfileStatement = $pdo->prepare('INSERT INTO admins (user_id) VALUES (:user_id)');
                $adminProfileStatement->execute(['user_id' => $userId]);
            }

            $pdo->commit();

            setFlash('flash_success', 'User account created successfully.');
            header('Location: users.php');
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to create the user account. The email address may already exist.';
        }
    }
}

try {
    $usersStatement = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.role, u.created_at, p.medical_record_number
         FROM users u
         LEFT JOIN patients p ON p.id = u.patient_id
         ORDER BY u.created_at DESC'
    );
    $usersStatement->execute();
    $users = $usersStatement->fetchAll();

    $appointmentRecordsStatement = $pdo->prepare(
        'SELECT
            a.id,
            a.appointment_type,
            a.scheduled_for,
            a.status,
            a.notes,
            p.name AS patient_name,
            p.medical_record_number,
            u.name AS doctor_name
         FROM appointments a
         INNER JOIN patients p ON p.id = a.patient_id
         LEFT JOIN users u ON u.id = a.doctor_id
         ORDER BY a.scheduled_for DESC'
    );
    $appointmentRecordsStatement->execute();
    $appointmentRecords = $appointmentRecordsStatement->fetchAll();
} catch (PDOException $exception) {
    $users = [];
    $appointmentRecords = [];
    $errors[] = 'Unable to load users right now.';
}

$flashSuccess = getFlash('flash_success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | Medilink Emergency Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="app-shell">
    <?php include __DIR__ . '/partials_nav.php'; ?>

    <main class="container py-4">
        <div class="page-header">
            <div>
                <span class="eyebrow">Administrator Controls</span>
                <h1 class="mb-1">User Management</h1>
                <p class="text-muted mb-0">Admins can provision doctors, patients, and fellow administrators from one place.</p>
            </div>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= e($flashSuccess); ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-soft border-0">
                    <div class="card-body p-4">
                        <h2 class="h4 mb-3">Create New User</h2>
                        <form method="post" class="row g-3" novalidate>
                            <div class="col-12">
                                <label class="form-label" for="name">Full name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= e($form['name']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="email">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($form['email']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="password">Temporary password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="role">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="doctor" <?= $form['role'] === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                    <option value="patient" <?= $form['role'] === 'patient' ? 'selected' : ''; ?>>Patient</option>
                                    <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="patient_id">Linked patient record</label>
                                <select class="form-select" id="patient_id" name="patient_id">
                                    <option value="">Create a new patient record</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?= (int) $patient['id']; ?>" <?= $form['patient_id'] === (string) $patient['id'] ? 'selected' : ''; ?>>
                                            <?= e($patient['name'] . ' - ' . $patient['medical_record_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <p class="small text-muted mb-0">
                                    For patient accounts, either link an existing patient record or leave the selector empty and fill in the patient details below.
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Patient phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($form['phone']); ?>" placeholder="+254700000000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="medical_record_number">Medical record number</label>
                                <input type="text" class="form-control" id="medical_record_number" name="medical_record_number" value="<?= e($form['medical_record_number']); ?>" placeholder="MEC-1001">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="date_of_birth">Date of birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($form['date_of_birth']); ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Create User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-soft border-0">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h4 mb-0">Existing Users</h2>
                            <span class="small text-muted"><?= count($users); ?> account(s)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Linked Record</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$users): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= e($user['name']); ?></td>
                                            <td><?= e($user['email']); ?></td>
                                            <td><span class="badge rounded-pill bg-dark-subtle text-dark"><?= ucfirst(e($user['role'])); ?></span></td>
                                            <td><?= e($user['medical_record_number'] ?? 'Not linked'); ?></td>
                                            <td><?= e($user['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="card shadow-soft border-0 mt-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0">Appointment Records</h2>
                    <span class="small text-muted"><?= count($appointmentRecords); ?> record(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Type</th>
                                <th>When</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$appointmentRecords): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No appointment records found.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($appointmentRecords as $appointmentRecord): ?>
                                <?php $recordStatusClass = $appointmentRecord['status'] === 'scheduled' ? 'status-assigned' : ($appointmentRecord['status'] === 'cancelled' ? 'status-cancelled' : 'status-completed'); ?>
                                <tr>
                                    <td>
                                        <strong><?= e($appointmentRecord['patient_name']); ?></strong>
                                        <div class="small text-muted"><?= e($appointmentRecord['medical_record_number']); ?></div>
                                    </td>
                                    <td><?= e($appointmentRecord['doctor_name'] ?? 'Unassigned'); ?></td>
                                    <td><?= e(ucfirst($appointmentRecord['appointment_type'])); ?></td>
                                    <td><?= e($appointmentRecord['scheduled_for']); ?></td>
                                    <td><span class="badge rounded-pill status-badge <?= e($recordStatusClass); ?>"><?= e(ucfirst($appointmentRecord['status'])); ?></span></td>
                                    <td><?= e($appointmentRecord['notes'] ?? 'No notes provided.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
