<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../utils/verify_jwt.php';
require_once __DIR__ . '/../../utils/sol_spl.php';

// --- JWT ---
$h = getallheaders();
if (!isset($h['Authorization'])) {
    echo json_encode(['ok' => false, 'error' => 'no auth']);
    exit;
}
if (!preg_match('/Bearer\s(\S+)/', $h['Authorization'], $m)) {
    echo json_encode(['ok' => false, 'error' => 'bad auth']);
    exit;
}
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$decoded = verify_jwt($m[1], $jwt_secret);
if (!$decoded) {
    echo json_encode(['ok' => false, 'error' => 'invalid token']);
    exit;
}

$action = $_GET['action'] ?? 'meta';

try {
    if ($action === 'meta') {
        echo json_encode(['ok' => true, 'data' => spl_meta()]);
        exit;
    }
    if ($action === 'balance') {
        $owner = $_GET['owner'] ?? '';
        if (!$owner) throw new InvalidArgumentException('owner required');
        echo json_encode(['ok' => true, 'data' => spl_balance($owner)]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'unknown action']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
