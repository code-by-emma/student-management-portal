<?php
/**
 * Verifies a Paystack transaction server-side before crediting a payment.
 * NEVER trust the client-side "success" callback alone — Paystack's JS
 * callback firing just means the popup closed with a reference; a browser
 * could be tampered with to fake that. The only trustworthy confirmation is
 * this server calling Paystack's own verify endpoint with our secret key.
 */
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json');

$user = require_role('student'); // still redirects to login if not authenticated; fine for an AJAX call too
$studentId = $user['linked_id'];

function respond(bool $success, string $message = ''): void {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.');

$token = $_POST['csrf_token'] ?? '';
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(419);
    respond(false, 'Session expired — please refresh the page and try again.');
}

if (!ONLINE_PAYMENTS_ENABLED) respond(false, 'Online payments are not configured.');

$reference = trim($_POST['reference'] ?? '');
$studentFeeId = (int)($_POST['student_fee_id'] ?? 0);
if (!$reference || !$studentFeeId) respond(false, 'Missing payment reference.');

// Ownership check: this invoice must belong to the logged-in student.
$stmt = $pdo->prepare('SELECT * FROM student_fees WHERE id = ? AND student_id = ?');
$stmt->execute([$studentFeeId, $studentId]);
$fee = $stmt->fetch();
if (!$fee) respond(false, 'Invoice not found.');

// Already recorded? (page refresh, double callback, etc.) Treat as success,
// not an error — the UNIQUE constraint on `reference` is the real guard.
$stmt = $pdo->prepare('SELECT id FROM fee_payments WHERE reference = ?');
$stmt->execute([$reference]);
if ($stmt->fetch()) respond(true);

// Verify with Paystack.
$ch = curl_init("https://api.paystack.co/transaction/verify/" . rawurlencode($reference));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    error_log("Paystack verify failed for {$reference}: HTTP {$httpCode} {$curlError}");
    respond(false, 'Could not reach the payment gateway. Please try again in a moment.');
}

$data = json_decode($response, true);
$txn = $data['data'] ?? null;
if (!$txn || ($data['status'] ?? false) !== true || ($txn['status'] ?? '') !== 'success') {
    respond(false, 'Payment was not successful.');
}
if (($txn['currency'] ?? '') !== CURRENCY_CODE) {
    respond(false, 'Currency mismatch.');
}

// The amount actually charged (kobo -> naira) — this is what we credit,
// never a client-supplied amount, so a tampered request can't overstate
// what was really paid.
$amountPaid = ((float)$txn['amount']) / 100;
if ($amountPaid <= 0 || $amountPaid > ((float)$fee['amount_due'] + 0.01)) {
    // Still record it (the money really was charged), but flag for review
    // rather than silently accepting an amount that doesn't make sense.
    error_log("Paystack verify: unusual amount {$amountPaid} for student_fee {$studentFeeId}, reference {$reference}");
}

try {
    $pdo->prepare("INSERT INTO fee_payments (student_fee_id, amount, method, reference, status, paid_at, recorded_by)
                    VALUES (?,?, 'online', ?, 'success', ?, NULL)")
        ->execute([$studentFeeId, $amountPaid, $reference, date('Y-m-d H:i:s', strtotime($txn['paid_at'] ?? 'now'))]);
    audit_log($user['id'], 'fee_payment.online', 'student_fees', $studentFeeId, "amount={$amountPaid};reference={$reference}");
    notify($user['id'], 'Payment received', 'We received your online payment of ' . money($amountPaid) . '. Thank you.', 'fee', BASE_URL . '/student/fees.php');
    respond(true);
} catch (PDOException $e) {
    // Duplicate reference landing here in a race is fine — it means another
    // concurrent request already recorded it successfully.
    if (str_contains($e->getMessage(), 'Duplicate')) respond(true);
    error_log('fee-pay-verify insert failed: ' . $e->getMessage());
    respond(false, 'Payment verified but could not be saved — please contact the school office with your reference: ' . $reference);
}
