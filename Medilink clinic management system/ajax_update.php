<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireDoctorAccess();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$callId = trim((string) ($_POST['call_id'] ?? ''));

if ($callId === '' || !ctype_digit($callId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid call selected.']);
    exit;
}

try {
    $fetchStatement = $pdo->prepare('SELECT status FROM emergency_calls WHERE id = :id LIMIT 1');
    $fetchStatement->execute(['id' => (int) $callId]);
    $call = $fetchStatement->fetch();

    if (!$call) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Emergency call not found.']);
        exit;
    }

    $nextStatusMap = [
        'pending' => 'assigned',
        'assigned' => 'completed',
        'completed' => 'completed',
    ];

    $currentStatus = $call['status'];
    $nextStatus = $nextStatusMap[$currentStatus] ?? 'pending';

    if ($currentStatus === 'completed') {
        echo json_encode([
            'success' => true,
            'message' => 'This emergency call is already completed.',
            'status' => $currentStatus,
        ]);
        exit;
    }

    $updateStatement = $pdo->prepare('UPDATE emergency_calls SET status = :status WHERE id = :id');
    $updateStatement->execute([
        'status' => $nextStatus,
        'id' => (int) $callId,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Emergency call status updated successfully.',
        'status' => $nextStatus,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update the emergency call status right now.',
    ]);
}
