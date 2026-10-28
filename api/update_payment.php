<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Helper::verifyCsrf()) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF non valido.']);
    exit;
}

$paymentId = (int)($_POST['payment_id'] ?? 0);
$status = $_POST['status'] ?? 'non_pagato';
$amount = (float)($_POST['amount'] ?? 0);
$method = $_POST['method'] ?? 'contanti';

$validStatuses = ['non_pagato', 'pagato', 'parziale', 'esonerato'];
if (!in_array($status, $validStatuses, true) || $paymentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parametri non validi.']);
    exit;
}

$paidAt = ($status === 'pagato') ? date('Y-m-d') : null;

try {
    $stmt = $db->prepare("UPDATE rappresentor_payments SET status = :st, amount_paid = :am, payment_method = :pm, paid_at = :pa, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        'st' => $status,
        'am' => $amount,
        'pm' => $method,
        'pa' => $paidAt,
        'id' => $paymentId
    ]);

    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
