<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireDoctorAccess();

$priorityFilter = trim((string) ($_GET['priority'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$searchFilter = trim((string) ($_GET['search'] ?? ''));

$allowedPriorities = ['high', 'medium', 'low'];
$allowedStatuses = ['pending', 'assigned', 'completed'];
$allowedAppointmentTypes = ['scheduled', 'emergency'];
$minimumAppointmentDateTime = date('Y-m-d\TH:i', strtotime('+1 minute'));

$where = [];
$params = [];
$errors = [];
$appointmentForm = [
    'patient_id' => '',
    'doctor_user_id' => currentUserRole() === 'doctor' ? (string) ($_SESSION['user_id'] ?? '') : '',
    'appointment_type' => 'scheduled',
    'scheduled_for' => '',
    'notes' => '',
];

if (in_array($priorityFilter, $allowedPriorities, true)) {
    $where[] = 'ec.priority = :priority';
    $params['priority'] = $priorityFilter;
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = 'ec.status = :status';
    $params['status'] = $statusFilter;
}

if ($searchFilter !== '') {
    $where[] = '(p.name LIKE :search OR p.medical_record_number LIKE :search)';
    $params['search'] = '%' . $searchFilter . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $patientsStatement = $pdo->prepare('SELECT id, name, medical_record_number FROM patients ORDER BY name ASC');
    $patientsStatement->execute();
    $patients = $patientsStatement->fetchAll();

    $doctorsStatement = $pdo->prepare(
        'SELECT u.id, u.name, d.specialty
         FROM users u
         LEFT JOIN doctors d ON d.user_id = u.id
         WHERE u.role = :role
         ORDER BY u.name ASC'
    );
    $doctorsStatement->execute(['role' => 'doctor']);
    $doctorUsers = $doctorsStatement->fetchAll();
} catch (PDOException $exception) {
    $patients = [];
    $doctorUsers = [];
    $errors[] = 'Unable to load the appointment form right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentForm['patient_id'] = trim((string) ($_POST['patient_id'] ?? ''));
    $appointmentForm['doctor_user_id'] = trim((string) ($_POST['doctor_user_id'] ?? $appointmentForm['doctor_user_id']));
    $appointmentForm['appointment_type'] = trim((string) ($_POST['appointment_type'] ?? 'scheduled'));
    $appointmentForm['scheduled_for'] = trim((string) ($_POST['scheduled_for'] ?? ''));
    $appointmentForm['notes'] = trim((string) ($_POST['notes'] ?? ''));

    if ($appointmentForm['patient_id'] === '' || !ctype_digit($appointmentForm['patient_id'])) {
        $errors[] = 'Please choose a valid patient for the appointment.';
    }

    if (!in_array($appointmentForm['appointment_type'], $allowedAppointmentTypes, true)) {
        $errors[] = 'Please choose a valid appointment type.';
    }

    if ($appointmentForm['scheduled_for'] === '') {
        $errors[] = 'Please choose the appointment date and time.';
    }

    $scheduledAt = strtotime($appointmentForm['scheduled_for']);
    if ($appointmentForm['scheduled_for'] !== '' && $scheduledAt === false) {
        $errors[] = 'The appointment date and time are invalid.';
    } elseif ($appointmentForm['scheduled_for'] !== '' && $scheduledAt <= time()) {
        $errors[] = 'Appointments can only be created for future dates and times.';
    }

    if (currentUserRole() === 'admin') {
        if ($appointmentForm['doctor_user_id'] === '' || !ctype_digit($appointmentForm['doctor_user_id'])) {
            $errors[] = 'Please assign the appointment to a doctor.';
        }
    }

    if (!$errors) {
        try {
            $patientCheckStatement = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM patients WHERE id = :id)');
            $patientCheckStatement->execute(['id' => (int) $appointmentForm['patient_id']]);

            if (!(bool) $patientCheckStatement->fetchColumn()) {
                $errors[] = 'The selected patient record no longer exists.';
            }
        } catch (PDOException $exception) {
            $errors[] = 'Unable to validate the selected patient record.';
        }
    }

    $assignedDoctorId = currentUserRole() === 'doctor'
        ? (int) ($_SESSION['user_id'] ?? 0)
        : (int) $appointmentForm['doctor_user_id'];

    if (!$errors) {
        try {
            $doctorCheckStatement = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM users WHERE id = :id AND role = :role)');
            $doctorCheckStatement->execute([
                'id' => $assignedDoctorId,
                'role' => 'doctor',
            ]);

            if (!(bool) $doctorCheckStatement->fetchColumn()) {
                $errors[] = 'The selected doctor account is not available.';
            }
        } catch (PDOException $exception) {
            $errors[] = 'Unable to validate the selected doctor account.';
        }
    }

    if (!$errors) {
        try {
            $appointmentInsertStatement = $pdo->prepare(
                'INSERT INTO appointments (patient_id, doctor_id, appointment_type, scheduled_for, status, notes)
                 VALUES (:patient_id, :doctor_id, :appointment_type, :scheduled_for, :status, :notes)'
            );
            $appointmentInsertStatement->execute([
                'patient_id' => (int) $appointmentForm['patient_id'],
                'doctor_id' => $assignedDoctorId,
                'appointment_type' => $appointmentForm['appointment_type'],
                'scheduled_for' => date('Y-m-d H:i:s', $scheduledAt),
                'status' => 'scheduled',
                'notes' => $appointmentForm['notes'] !== '' ? $appointmentForm['notes'] : null,
            ]);

            setFlash('flash_success', 'Appointment created successfully.');
            header('Location: doctor_dashboard.php');
            exit;
        } catch (PDOException $exception) {
            $errors[] = 'Unable to create the appointment right now.';
        }
    }
}

try {
    $callsStatement = $pdo->prepare(
        "SELECT
            ec.id,
            ec.location,
            ec.priority,
            ec.status,
            ec.timestamp,
            ec.notes,
            p.name AS patient_name,
            p.phone AS patient_phone,
            p.date_of_birth,
            p.medical_record_number
         FROM emergency_calls ec
         INNER JOIN patients p ON p.id = ec.patient_id
         {$whereSql}
         ORDER BY
            CASE ec.status
                WHEN 'assigned' THEN 0
                WHEN 'pending' THEN 1
                ELSE 2
            END,
            ec.timestamp DESC"
    );
    $callsStatement->execute($params);
    $calls = $callsStatement->fetchAll();

    $metricQueries = [
        'assigned_calls' => "SELECT COUNT(*) FROM emergency_calls WHERE status = 'assigned'",
        'pending_calls' => "SELECT COUNT(*) FROM emergency_calls WHERE status = 'pending'",
        'upcoming_appointments' => currentUserRole() === 'doctor'
            ? "SELECT COUNT(*) FROM appointments WHERE status = 'scheduled' AND scheduled_for >= NOW() AND doctor_id = " . (int) ($_SESSION['user_id'] ?? 0)
            : "SELECT COUNT(*) FROM appointments WHERE status = 'scheduled' AND scheduled_for >= NOW()",
        'total_patients' => 'SELECT COUNT(*) FROM patients',
    ];
    $metrics = [];

    foreach ($metricQueries as $metricKey => $metricSql) {
        $metricStatement = $pdo->prepare($metricSql);
        $metricStatement->execute();
        $metrics[$metricKey] = (int) $metricStatement->fetchColumn();
    }

    $appointmentsWhere = ['a.status = :scheduled_status', 'a.scheduled_for >= NOW()'];
    $appointmentsParams = ['scheduled_status' => 'scheduled'];

    if (currentUserRole() === 'doctor') {
        $appointmentsWhere[] = 'a.doctor_id = :doctor_id';
        $appointmentsParams['doctor_id'] = (int) ($_SESSION['user_id'] ?? 0);
    }

    $appointmentsStatement = $pdo->prepare(
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
         WHERE ' . implode(' AND ', $appointmentsWhere) . '
         ORDER BY a.scheduled_for ASC'
    );
    $appointmentsStatement->execute($appointmentsParams);
    $upcomingAppointments = $appointmentsStatement->fetchAll();

    $recordsWhere = [];
    $recordsParams = [];

    if (currentUserRole() === 'doctor') {
        $recordsWhere[] = 'a.doctor_id = :doctor_id';
        $recordsParams['doctor_id'] = (int) ($_SESSION['user_id'] ?? 0);
    }

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
         ' . ($recordsWhere ? 'WHERE ' . implode(' AND ', $recordsWhere) : '') . '
         ORDER BY a.scheduled_for DESC'
    );
    $appointmentRecordsStatement->execute($recordsParams);
    $appointmentRecords = $appointmentRecordsStatement->fetchAll();
} catch (PDOException $exception) {
    $calls = [];
    $upcomingAppointments = [];
    $appointmentRecords = [];
    $metrics = [
        'assigned_calls' => 0,
        'pending_calls' => 0,
        'upcoming_appointments' => 0,
        'total_patients' => 0,
    ];
    if (!$errors) {
        $errors[] = 'Unable to load the doctor dashboard right now.';
    }
}

$flashSuccess = getFlash('flash_success');
$flashError = getFlash('flash_error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Medilink Emergency Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body class="app-shell">
    <?php include __DIR__ . '/partials_nav.php'; ?>

    <main class="container py-4">
        <div class="page-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="eyebrow">Doctor Workspace</span>
                <h1 class="mb-1">Emergency Calls And Appointments</h1>
                <p class="text-muted mb-0">Review active cases, create patient appointments, and manage the clinical schedule from one place.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="emergency_form.php" class="btn btn-primary">Create Emergency Call</a>
                <?php if (currentUserRole() === 'admin'): ?>
                    <a href="users.php" class="btn btn-outline-secondary">Manage Users</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= e($flashSuccess); ?></div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-danger"><?= e($flashError); ?></div>
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

        <section class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card">
                    <span>Assigned Calls</span>
                    <strong><?= (int) $metrics['assigned_calls']; ?></strong>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card accent-warning">
                    <span>Pending Triage</span>
                    <strong><?= (int) $metrics['pending_calls']; ?></strong>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card accent-success">
                    <span>Upcoming Appointments</span>
                    <strong><?= (int) $metrics['upcoming_appointments']; ?></strong>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card accent-danger">
                    <span>Registered Patients</span>
                    <strong><?= (int) $metrics['total_patients']; ?></strong>
                </div>
            </div>
        </section>

        <section class="row g-4 mb-4">
            <div class="col-lg-5">
                <div class="card shadow-soft border-0 h-100">
                    <div class="card-body p-4">
                        <h2 class="h4 mb-3">Create Appointment</h2>
                        <form method="post" class="row g-3" novalidate>
                            <div class="col-12">
                                <label class="form-label" for="patient_id">Patient</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Select patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?= (int) $patient['id']; ?>" <?= $appointmentForm['patient_id'] === (string) $patient['id'] ? 'selected' : ''; ?>>
                                            <?= e($patient['name'] . ' - ' . $patient['medical_record_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (currentUserRole() === 'admin'): ?>
                                <div class="col-12">
                                    <label class="form-label" for="doctor_user_id">Assign doctor</label>
                                    <select class="form-select" id="doctor_user_id" name="doctor_user_id" required>
                                        <option value="">Select doctor</option>
                                        <?php foreach ($doctorUsers as $doctorUser): ?>
                                            <?php $doctorLabel = $doctorUser['specialty'] ? $doctorUser['name'] . ' - ' . $doctorUser['specialty'] : $doctorUser['name']; ?>
                                            <option value="<?= (int) $doctorUser['id']; ?>" <?= $appointmentForm['doctor_user_id'] === (string) $doctorUser['id'] ? 'selected' : ''; ?>>
                                                <?= e($doctorLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="form-label" for="appointment_type">Appointment type</label>
                                <select class="form-select" id="appointment_type" name="appointment_type" required>
                                    <option value="scheduled" <?= $appointmentForm['appointment_type'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="emergency" <?= $appointmentForm['appointment_type'] === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="scheduled_for">Date and time</label>
                                <input type="datetime-local" class="form-control" id="scheduled_for" name="scheduled_for" value="<?= e($appointmentForm['scheduled_for']); ?>" min="<?= e($minimumAppointmentDateTime); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Reason for the appointment, follow-up details, or preparation notes."><?= e($appointmentForm['notes']); ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">Save Appointment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-soft border-0 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h4 mb-0">Upcoming Appointments</h2>
                            <span class="small text-muted"><?= count($upcomingAppointments); ?> appointment(s)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle emergency-table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Type</th>
                                        <th>When</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$upcomingAppointments): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No upcoming appointments have been scheduled yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($upcomingAppointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($appointment['patient_name']); ?></strong>
                                                <div class="small text-muted"><?= e($appointment['medical_record_number']); ?></div>
                                            </td>
                                            <td><?= e($appointment['doctor_name'] ?? 'Unassigned'); ?></td>
                                            <td><?= e(ucfirst($appointment['appointment_type'])); ?></td>
                                            <td><?= e($appointment['scheduled_for']); ?></td>
                                            <td><?= e($appointment['notes'] ?? 'No notes provided.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card shadow-soft border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0">Appointment Records</h2>
                    <span class="small text-muted"><?= count($appointmentRecords); ?> record(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle emergency-table">
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
                                    <td colspan="6" class="text-center py-4 text-muted">No appointment records are available yet.</td>
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

        <section class="card shadow-soft border-0 mb-4">
            <div class="card-body p-4">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="search">Search patient</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= e($searchFilter); ?>" placeholder="Search by patient name or record number">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="">All priorities</option>
                            <option value="high" <?= $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?= $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All statuses</option>
                            <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-dark">Apply Filters</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-soft border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-1">Emergency Call Queue</h2>
                        <p class="text-muted mb-0">Each record includes the patient profile summary needed during emergency handling.</p>
                    </div>
                    <span class="small text-muted"><?= count($calls); ?> record(s) found</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle emergency-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Location</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                                <th>Notes</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$calls): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No emergency calls match the current filters.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($calls as $call): ?>
                                <tr id="call-row-<?= (int) $call['id']; ?>">
                                    <td>
                                        <strong><?= e($call['patient_name']); ?></strong>
                                        <div class="small text-muted"><?= e($call['medical_record_number']); ?> | <?= e($call['patient_phone']); ?></div>
                                        <div class="small text-muted">DOB: <?= e($call['date_of_birth'] ?? 'Not available'); ?></div>
                                    </td>
                                    <td><?= e($call['location']); ?></td>
                                    <td>
                                        <span class="badge rounded-pill priority-badge priority-<?= e($call['priority']); ?>">
                                            <?= ucfirst(e($call['priority'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill status-badge status-<?= e($call['status']); ?>" id="status-label-<?= (int) $call['id']; ?>">
                                            <?= ucfirst(e($call['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?= e($call['timestamp']); ?></td>
                                    <td><?= e($call['notes'] ?? 'No notes provided.'); ?></td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary update-status-btn"
                                            data-call-id="<?= (int) $call['id']; ?>"
                                            data-current-status="<?= e($call['status']); ?>"
                                            <?= $call['status'] === 'completed' ? 'disabled' : ''; ?>
                                        >
                                            <?= $call['status'] === 'completed' ? 'Completed' : 'Advance Status'; ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="charts.js"></script>
</body>
</html>
