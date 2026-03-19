<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireDoctorAccess();

$errors = [];
$formData = [
    'patient_id' => '',
    'location' => '',
    'priority' => 'medium',
    'notes' => '',
];

try {
    $patientStatement = $pdo->prepare('SELECT id, name, medical_record_number FROM patients ORDER BY name ASC');
    $patientStatement->execute();
    $patients = $patientStatement->fetchAll();
} catch (PDOException $exception) {
    $patients = [];
    $errors[] = 'Unable to load patients. Please check your database connection.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['patient_id'] = trim((string) ($_POST['patient_id'] ?? ''));
    $formData['location'] = trim((string) ($_POST['location'] ?? ''));
    $formData['priority'] = trim((string) ($_POST['priority'] ?? 'medium'));
    $formData['notes'] = trim((string) ($_POST['notes'] ?? ''));

    $allowedPriorities = ['high', 'medium', 'low'];

    if ($formData['patient_id'] === '' || !ctype_digit($formData['patient_id'])) {
        $errors[] = 'Please select a valid patient.';
    }

    if ($formData['location'] === '') {
        $errors[] = 'Location is required for emergency dispatch.';
    }

    if (!in_array($formData['priority'], $allowedPriorities, true)) {
        $errors[] = 'Please choose a valid priority level.';
    }

    if (!$errors) {
        try {
            $patientCheckStatement = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM patients WHERE id = :id)');
            $patientCheckStatement->execute(['id' => (int) $formData['patient_id']]);

            if (!(bool) $patientCheckStatement->fetchColumn()) {
                $errors[] = 'The selected patient record no longer exists.';
            }
        } catch (PDOException $exception) {
            $errors[] = 'Unable to validate the selected patient.';
        }
    }

    if (!$errors) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO emergency_calls (patient_id, location, priority, status, notes)
                 VALUES (:patient_id, :location, :priority, :status, :notes)'
            );
            $statement->execute([
                'patient_id' => (int) $formData['patient_id'],
                'location' => $formData['location'],
                'priority' => $formData['priority'],
                'status' => 'pending',
                'notes' => $formData['notes'] !== '' ? $formData['notes'] : null,
            ]);

            setFlash('flash_success', 'Emergency call created successfully.');
            header('Location: doctor_dashboard.php');
            exit;
        } catch (PDOException $exception) {
            $errors[] = 'Unable to save the emergency call. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Emergency Call | Medilink Emergency Clinic</title>
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
                <span class="eyebrow">Emergency Intake</span>
                <h1 class="mb-1">Create Emergency Call</h1>
                <p class="text-muted mb-0">Capture triage details and route the request into the live operations board.</p>
            </div>
        </div>

        <div class="card shadow-soft border-0">
            <div class="card-body p-4">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="row g-3" novalidate>
                    <div class="col-md-6">
                        <label class="form-label" for="patient_id">Patient</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Select patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= (int) $patient['id']; ?>" <?= $formData['patient_id'] === (string) $patient['id'] ? 'selected' : ''; ?>>
                                    <?= e($patient['name'] . ' - ' . $patient['medical_record_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="high" <?= $formData['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?= $formData['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?= $formData['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="location">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?= e($formData['location']); ?>" placeholder="Westlands, Nairobi" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">Clinical or dispatch notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="5" placeholder="Provide any known symptoms, urgency notes, or landmark directions."><?= e($formData['notes']); ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-3">
                        <button type="submit" class="btn btn-primary">Create Call</button>
                        <a href="doctor_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
