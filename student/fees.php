<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('student');
$pageTitle = 'Fees';
$studentId = $user['linked_id'];

$invoices = $pdo->prepare("SELECT sf.id, sf.amount_due, fs.fee_type, fs.term, fs.academic_year, fs.due_date,
                            COALESCE(SUM(fp.amount),0) AS paid
                            FROM student_fees sf
                            JOIN fee_structures fs ON fs.id = sf.fee_structure_id
                            LEFT JOIN fee_payments fp ON fp.student_fee_id = sf.id AND fp.status='success'
                            WHERE sf.student_id = ?
                            GROUP BY sf.id
                            ORDER BY fs.academic_year DESC, sf.created_at DESC");
$invoices->execute([$studentId]);
$invoices = $invoices->fetchAll();

$totalDue = 0; $totalPaid = 0;
foreach ($invoices as $inv) { $totalDue += (float)$inv['amount_due']; $totalPaid += (float)$inv['paid']; }
$totalBalance = max(0, $totalDue - $totalPaid);

$history = $pdo->prepare("SELECT fp.*, fs.fee_type FROM fee_payments fp
                          JOIN student_fees sf ON sf.id = fp.student_fee_id
                          JOIN fee_structures fs ON fs.id = sf.fee_structure_id
                          WHERE sf.student_id = ? ORDER BY fp.paid_at DESC LIMIT 20");
$history->execute([$studentId]);
$history = $history->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Total Billed</p><p class="text-2xl font-bold mt-1"><?= money($totalDue) ?></p></div>
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Total Paid</p><p class="text-2xl font-bold mt-1 text-emerald-600"><?= money($totalPaid) ?></p></div>
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Balance</p><p class="text-2xl font-bold mt-1 <?= $totalBalance > 0 ? 'text-rose-500' : 'text-emerald-600' ?>"><?= money($totalBalance) ?></p></div>
</div>

<div class="space-y-4 mb-8">
  <?php foreach ($invoices as $inv): ?>
    <?php
      $balance = max(0, (float)$inv['amount_due'] - (float)$inv['paid']);
      $status = $balance <= 0.001 ? 'paid' : ((float)$inv['paid'] > 0 ? 'partial' : 'unpaid');
      $badge = ['paid' => 'bg-emerald-100 text-emerald-700', 'partial' => 'bg-amber-100 text-amber-700', 'unpaid' => 'bg-rose-100 text-rose-700'][$status];
    ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 class="font-semibold"><?= e($inv['fee_type']) ?></h3>
          <p class="text-xs text-slate-400"><?= e($inv['term'].' · '.$inv['academic_year']) ?><?= $inv['due_date'] ? ' · Due ' . e(date('M j, Y', strtotime($inv['due_date']))) : '' ?></p>
        </div>
        <span class="badge <?= $badge ?>"><?= ucfirst($status) ?></span>
      </div>
      <div class="grid grid-cols-3 gap-3 mt-3 text-sm">
        <div><p class="text-slate-400 text-xs">Amount Due</p><p class="font-medium"><?= money((float)$inv['amount_due']) ?></p></div>
        <div><p class="text-slate-400 text-xs">Paid</p><p class="font-medium"><?= money((float)$inv['paid']) ?></p></div>
        <div><p class="text-slate-400 text-xs">Balance</p><p class="font-medium"><?= money($balance) ?></p></div>
      </div>

      <?php if ($balance > 0.001 && ONLINE_PAYMENTS_ENABLED): ?>
        <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
          <input type="number" step="0.01" min="0.01" max="<?= $balance ?>" value="<?= $balance ?>" id="amount_<?= $inv['id'] ?>" class="w-40 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
          <button type="button" onclick="payOnline(<?= $inv['id'] ?>, <?= $balance ?>, '<?= e($inv['fee_type']) ?>')" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">Pay Online</button>
          <span id="payStatus_<?= $inv['id'] ?>" class="text-xs text-slate-400"></span>
        </div>
      <?php elseif ($balance > 0.001): ?>
        <p class="text-xs text-slate-400 mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">Online payment isn't set up yet — please pay at the school office and ask the admin to record it.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$invoices): ?><p class="text-slate-400 text-sm text-center py-8">No fees have been billed to you yet.</p><?php endif; ?>
</div>

<div class="card overflow-x-auto">
  <h2 class="font-semibold p-5 pb-0">Payment History</h2>
  <table class="data-table w-full">
    <thead><tr><th>Date</th><th>Fee</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
    <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td><?= e(date('M j, Y', strtotime($h['paid_at']))) ?></td>
          <td><?= e($h['fee_type']) ?></td>
          <td><?= money((float)$h['amount']) ?></td>
          <td><span class="badge bg-blue-50 text-primary"><?= e(ucwords(str_replace('_',' ',$h['method']))) ?></span></td>
          <td class="text-slate-400 text-xs"><?= e($h['reference'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="5" class="text-center text-slate-400 py-8">No payments yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (ONLINE_PAYMENTS_ENABLED): ?>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payOnline(studentFeeId, maxBalance, feeType) {
  const input = document.getElementById('amount_' + studentFeeId);
  const statusEl = document.getElementById('payStatus_' + studentFeeId);
  const amount = parseFloat(input.value);
  if (!amount || amount <= 0 || amount > maxBalance + 0.001) {
    statusEl.textContent = 'Enter an amount up to your balance.';
    statusEl.className = 'text-xs text-rose-500';
    return;
  }
  const reference = 'SMP-' + studentFeeId + '-' + Date.now();
  const handler = PaystackPop.setup({
    key: '<?= e(PAYSTACK_PUBLIC_KEY) ?>',
    email: '<?= e($user['email']) ?>',
    amount: Math.round(amount * 100), // kobo
    currency: '<?= CURRENCY_CODE ?>',
    ref: reference,
    metadata: { student_fee_id: studentFeeId, fee_type: feeType },
    callback: function (response) {
      statusEl.textContent = 'Verifying payment…';
      statusEl.className = 'text-xs text-slate-400';
      fetch('<?= BASE_URL ?>/student/fee-pay-verify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=<?= urlencode($_SESSION['csrf_token']) ?>&reference=' + encodeURIComponent(response.reference) + '&student_fee_id=' + studentFeeId
      }).then(r => r.json()).then(data => {
        if (data.success) {
          statusEl.textContent = 'Payment confirmed!';
          statusEl.className = 'text-xs text-emerald-600';
          setTimeout(() => window.location.reload(), 900);
        } else {
          statusEl.textContent = data.message || 'Could not verify payment — contact the school office with your reference: ' + response.reference;
          statusEl.className = 'text-xs text-rose-500';
        }
      }).catch(() => {
        statusEl.textContent = 'Network error verifying payment. Your reference: ' + response.reference + ' — contact the office if this persists.';
        statusEl.className = 'text-xs text-rose-500';
      });
    },
    onClose: function () {
      statusEl.textContent = 'Payment window closed.';
      statusEl.className = 'text-xs text-slate-400';
    },
  });
  handler.openIframe();
}
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
