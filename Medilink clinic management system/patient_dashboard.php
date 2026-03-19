<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireRole('patient');

$linkedPatientId = isset($_SESSION['patient_id']) && is_numeric($_SESSION['patient_id'])
    ? (int) $_SESSION['patient_id']
    : null;
$accountName = (string) ($_SESSION['user_name'] ?? 'Patient');
$accountEmail = (string) ($_SESSION['user_email'] ?? '');
$dashboardNotice = null;
$patient = null;
$appointments = [];
$calls = [];
$summary = [
    'total_calls' => 0,
    'pending_calls' => 0,
    'completed_calls' => 0,
    'latest_call' => null,
];
$appointmentSummary = [
    'total_appointments' => 0,
    'upcoming_appointments' => 0,
];

try {
    if ($linkedPatientId !== null) {
        $patientStatement = $pdo->prepare(
            'SELECT id, name, phone, date_of_birth, medical_record_number
             FROM patients
             WHERE id = :id
             LIMIT 1'
        );
        $patientStatement->execute(['id' => $linkedPatientId]);
        $patient = $patientStatement->fetch();

        if ($patient) {
            $appointmentsStatement = $pdo->prepare(
                'SELECT
                    a.appointment_type,
                    a.scheduled_for,
                    a.status,
                    a.notes,
                    COALESCE(d.name, "Clinic Team") AS doctor_name
                 FROM appointments a
                 LEFT JOIN users d ON d.id = a.doctor_id
                 WHERE a.patient_id = :patient_id
                 ORDER BY a.scheduled_for DESC'
            );
            $appointmentsStatement->execute(['patient_id' => (int) $patient['id']]);
            $appointments = $appointmentsStatement->fetchAll();

            $callsStatement = $pdo->prepare(
                'SELECT location, priority, status, timestamp, notes
                 FROM emergency_calls
                 WHERE patient_id = :patient_id
                 ORDER BY timestamp DESC'
            );
            $callsStatement->execute(['patient_id' => (int) $patient['id']]);
            $calls = $callsStatement->fetchAll();

            $summaryStatement = $pdo->prepare(
                'SELECT
                    COUNT(*) AS total_calls,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_calls,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed_calls,
                    MAX(timestamp) AS latest_call
                 FROM emergency_calls
                 WHERE patient_id = :patient_id'
            );
            $summaryStatement->execute(['patient_id' => (int) $patient['id']]);
            $summary = $summaryStatement->fetch() ?: $summary;

            $appointmentSummaryStatement = $pdo->prepare(
                'SELECT
                    COUNT(*) AS total_appointments,
                    SUM(CASE WHEN status = "scheduled" AND scheduled_for >= NOW() THEN 1 ELSE 0 END) AS upcoming_appointments
                 FROM appointments
                 WHERE patient_id = :patient_id'
            );
            $appointmentSummaryStatement->execute(['patient_id' => (int) $patient['id']]);
            $appointmentSummary = $appointmentSummaryStatement->fetch() ?: $appointmentSummary;
        } else {
            $dashboardNotice = 'Your account is active, but no linked patient record could be found yet.';
        }
    } else {
        $dashboardNotice = 'Your patient account is active, but no clinic record has been linked yet.';
    }
} catch (PDOException $exception) {
    setFlash('flash_error', 'Unable to load patient dashboard data right now.');
}

$flashSuccess = getFlash('flash_success');
$flashError = getFlash('flash_error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | Medilink Emergency Clinic</title>
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
                <span class="eyebrow">Patient Access</span>
                <h1 class="mb-1">My Care Dashboard</h1>
                <p class="text-muted mb-0">View your clinic profile, emergency updates, and scheduled appointments linked to your Medilink account.</p>
            </div>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success"><?= e($flashSuccess); ?></div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-danger"><?= e($flashError); ?></div>
        <?php endif; ?>

        <?php if ($patient): ?>
            <section class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card">
                        <span>Medical Record</span>
                        <strong><?= e($patient['medical_record_number']); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card accent-warning">
                        <span>Upcoming Appointments</span>
                        <strong><?= (int) ($appointmentSummary['upcoming_appointments'] ?? 0); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card accent-danger">
                        <span>Pending Emergencies</span>
                        <strong><?= (int) ($summary['pending_calls'] ?? 0); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="metric-card accent-success">
                        <span>Total Emergency Calls</span>
                        <strong><?= (int) ($summary['total_calls'] ?? 0); ?></strong>
                    </div>
                </div>
            </section>

            <section class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body p-4">
                            <h2 class="h4 mb-3">My Information</h2>
                            <dl class="detail-list mb-0">
                                <dt>Full name</dt>
                                <dd><?= e($patient['name']); ?></dd>
                                <dt>Phone number</dt>
                                <dd><?= e($patient['phone']); ?></dd>
                                <dt>Date of birth</dt>
                                <dd><?= e($patient['date_of_birth'] ?? 'Not available'); ?></dd>
                                <dt>Latest emergency update</dt>
                                <dd><?= e((string) ($summary['latest_call'] ?? 'No emergency call history yet')); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body p-4">
                            <h2 class="h4 mb-3">Appointments Overview</h2>
                            <p class="mb-2">This dashboard is read-only and shows only the records linked to your patient account.</p>
                            <p class="mb-2">Scheduled appointments and emergency follow-ups appear below with the assigned doctor when available.</p>
                            <p class="mb-0">If you need changes to your profile or a new booking, please contact the clinic directly.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card shadow-soft border-0 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h4 mb-0">Scheduled And Emergency Appointments</h2>
                        <span class="small text-muted"><?= count($appointments); ?> appointment(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle emergency-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Doctor</th>
                                    <th>When</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$appointments): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No appointments are linked to this patient record yet.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($appointments as $appointment): ?>
                                    <?php $appointmentStatusClass = $appointment['status'] === 'scheduled' ? 'status-assigned' : ($appointment['status'] === 'cancelled' ? 'status-cancelled' : 'status-completed'); ?>
                                    <tr>
                                        <td><?= e(ucfirst($appointment['appointment_type'])); ?></td>
                                        <td><?= e($appointment['doctor_name']); ?></td>
                                        <td><?= e($appointment['scheduled_for']); ?></td>
                                        <td>
                                            <span class="badge rounded-pill status-badge <?= e($appointmentStatusClass); ?>">
                                                <?= e(ucfirst($appointment['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?= e($appointment['notes'] ?? 'No notes provided.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="card shadow-soft border-0">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h4 mb-0">Emergency Call History</h2>
                        <span class="small text-muted"><?= count($calls); ?> record(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle emergency-table">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Timestamp</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$calls): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No emergency calls are linked to this patient record yet.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($calls as $call): ?>
                                    <tr>
                                        <td><?= e($call['location']); ?></td>
                                        <td><span class="badge rounded-pill priority-badge priority-<?= e($call['priority']); ?>"><?= e(ucfirst($call['priority'])); ?></span></td>
                                        <td><span class="badge rounded-pill status-badge status-<?= e($call['status']); ?>"><?= e(ucfirst($call['status'])); ?></span></td>
                                        <td><?= e($call['timestamp']); ?></td>
                                        <td><?= e($call['notes'] ?? 'No notes provided.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="card shadow-soft border-0">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3">Patient Account</h2>
                    <?php if ($dashboardNotice !== null): ?>
                        <div class="alert alert-warning"><?= e($dashboardNotice); ?></div>
                    <?php endif; ?>
                    <dl class="detail-list mb-4">
                        <dt>Account name</dt>
                        <dd><?= e($accountName); ?></dd>
                        <dt>Email address</dt>
                        <dd><?= e($accountEmail !== '' ? $accountEmail : 'Not available'); ?></dd>
                        <dt>Role</dt>
                        <dd>Patient</dd>
                    </dl>
                    <p class="mb-2">There are no appointments or emergency records to show yet.</p>
                    <p class="mb-0">Ask an administrator to link your account to a patient record, or create one from the Users page.</p>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
