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

if ($status === 'non_pagato' || $status === 'esonerato') {
    $amount = 0.00;
    $paidAt = null;
} elseif ($status === 'pagato') {
    if ($amount <= 0) {
        $stmtC = $db->prepare("SELECT c.quota_per_student FROM rappresentor_campaigns c JOIN rappresentor_payments p ON c.id = p.campaign_id WHERE p.id = :id");
        $stmtC->execute(['id' => $paymentId]);
        $amount = (float)($stmtC->fetchColumn() ?: 0);
    }
    $paidAt = date('Y-m-d');
} else {
    $paidAt = ($amount > 0) ? date('Y-m-d') : null;
}

try {
    $stmt = $db->prepare("UPDATE rappresentor_payments SET status = :st, amount_paid = :am, payment_method = :pm, paid_at = :pa, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        'st' => $status,
        'am' => $amount,
        'pm' => $method,
        'pa' => $paidAt,
        'id' => $paymentId
    ]);

    $stmtClass = $db->prepare("SELECT c.class_id FROM rappresentor_campaigns c JOIN rappresentor_payments p ON c.id = p.campaign_id WHERE p.id = :id");
    $stmtClass->execute(['id' => $paymentId]);
    $classId = (int)$stmtClass->fetchColumn();

    $summary = ($classId > 0) ? Helper::getClassCashSummary($db, $classId) : null;

    echo json_encode([
        'success' => true,
        'amount' => $amount,
        'status' => $status,
        'summary' => $summary
    ]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
