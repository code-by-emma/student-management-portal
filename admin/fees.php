<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Fees';

$gradeLevels = $pdo->query('SELECT id, name FROM grade_levels ORDER BY display_order, name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        if ($action === 'create_structure' || $action === 'update_structure') {
            $gradeLevelId = input('grade_level_id') !== '' ? (int)input('grade_level_id') : null;
            $feeType = input('fee_type'); $amount = input('amount'); $term = input('term');
            $year = input('academic_year'); $dueDate = input('due_date') ?: null;
            if (!$feeType || !$term || !$year) throw new RuntimeException('Fee type, term and academic year are required.');
            if (!is_numeric($amount) || (float)$amount <= 0) throw new RuntimeException('Amount must be a positive number.');

            if ($action === 'create_structure') {
                $pdo->prepare('INSERT INTO fee_structures (grade_level_id, fee_type, amount, term, academic_year, due_date, created_by) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$gradeLevelId, $feeType, $amount, $term, $year, $dueDate, $user['id']]);
                audit_log($user['id'], 'fee_structure.create', 'fee_structures', $pdo->lastInsertId());
                flash('success', 'Fee structure created. Use "Generate Invoices" to bill students.');
            } else {
                $id = (int)input('fee_structure_id');
                $pdo->prepare('UPDATE fee_structures SET grade_level_id=?, fee_type=?, amount=?, term=?, academic_year=?, due_date=? WHERE id=?')
                    ->execute([$gradeLevelId, $feeType, $amount, $term, $year, $dueDate, $id]);
                audit_log($user['id'], 'fee_structure.update', 'fee_structures', $id);
                flash('success', 'Fee structure updated. Note: this does not change amounts already invoiced to students.');
            }
        } elseif ($action === 'delete_structure') {
            $id = (int)input('fee_structure_id');
            $paid = $pdo->prepare("SELECT COUNT(*) FROM fee_payments fp
                                    JOIN student_fees sf ON sf.id = fp.student_fee_id
                                    WHERE sf.fee_structure_id = ?");
            $paid->execute([$id]);
            if ((int)$paid->fetchColumn() > 0) {
                throw new RuntimeException('Cannot delete — payments have already been recorded against this fee. Financial records are kept intact.');
            }
            $pdo->prepare('DELETE FROM fee_structures WHERE id=?')->execute([$id]);
            audit_log($user['id'], 'fee_structure.delete', 'fee_structures', $id);
            flash('success', 'Fee structure deleted.');
        } elseif ($action === 'generate_invoices') {
            $structureId = (int)input('fee_structure_id');
            $stmt = $pdo->prepare('SELECT * FROM fee_structures WHERE id=?');
            $stmt->execute([$structureId]);
            $structure = $stmt->fetch();
            if (!$structure) throw new RuntimeException('Fee structure not found.');

            if ($structure['grade_level_id']) {
                $students = $pdo->prepare("SELECT s.id, s.user_id FROM students s
                                            JOIN classes c ON c.id = s.class_id
                                            WHERE c.grade_level_id = ?");
                $students->execute([$structure['grade_level_id']]);
            } else {
                $students = $pdo->query('SELECT id, user_id FROM students');
            }
            $students = $students->fetchAll();

            $insert = $pdo->prepare('INSERT IGNORE INTO student_fees (student_id, fee_structure_id, amount_due) VALUES (?,?,?)');
            $created = 0;
            foreach ($students as $s) {
                $insert->execute([$s['id'], $structureId, $structure['amount']]);
                if ($insert->rowCount() > 0) {
                    $created++;
                    notify($s['user_id'], 'New fee invoice', "{$structure['fee_type']} ({$structure['term']}, {$structure['academic_year']}): " . money((float)$structure['amount']) . ' due' . ($structure['due_date'] ? ' by ' . date('M j, Y', strtotime($structure['due_date'])) : '') . '.', 'fee', BASE_URL . '/student/fees.php');
                }
            }
            audit_log($user['id'], 'fee_structure.generate_invoices', 'fee_structures', $structureId, "created={$created}");
            flash('success', $created > 0 ? "Invoiced {$created} student(s)." : 'No new invoices — everyone in scope already has one.');
        } elseif ($action === 'record_payment') {
            $studentFeeId = (int)input('student_fee_id');
            $amount = input('amount'); $method = input('method'); $reference = input('reference') ?: null;
            $paidAt = input('paid_at') ?: date('Y-m-d');
            if (!is_numeric($amount) || (float)$amount <= 0) throw new RuntimeException('Amount must be a positive number.');
            if (!in_array($method, ['cash', 'bank_transfer', 'other'], true)) throw new RuntimeException('Invalid payment method.');

            $stmt = $pdo->prepare('SELECT sf.*, s.user_id FROM student_fees sf JOIN students s ON s.id = sf.student_id WHERE sf.id=?');
            $stmt->execute([$studentFeeId]);
            $fee = $stmt->fetch();
            if (!$fee) throw new RuntimeException('That fee invoice was not found.');

            $pdo->prepare("INSERT INTO fee_payments (student_fee_id, amount, method, reference, status, paid_at, recorded_by)
                            VALUES (?,?,?,?, 'success', ?, ?)")
                ->execute([$studentFeeId, $amount, $method, $reference, $paidAt . ' ' . date('H:i:s'), $user['id']]);
            audit_log($user['id'], 'fee_payment.record', 'student_fees', $studentFeeId, "amount={$amount}");
            notify($fee['user_id'], 'Payment received', 'We recorded a payment of ' . money((float)$amount) . '. Thank you.', 'fee', BASE_URL . '/student/fees.php');
            flash('success', 'Payment recorded.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        flash('error', 'Database error — please check the values and try again.');
    }
    redirect('/admin/fees.php?tab=' . (str_contains($action ?? '', 'payment') ? 'payments' : 'structures'));
}

$tab = ($_GET['tab'] ?? 'structures') === 'payments' ? 'payments' : 'structures';

$structures = $pdo->query("SELECT f.*, g.name AS grade_level_name,
    (SELECT COUNT(*) FROM student_fees sf WHERE sf.fee_structure_id = f.id) AS invoiced_count,
    (SELECT COALESCE(SUM(fp.amount),0) FROM fee_payments fp JOIN student_fees sf ON sf.id=fp.student_fee_id WHERE sf.fee_structure_id = f.id AND fp.status='success') AS collected
    FROM fee_structures f LEFT JOIN grade_levels g ON g.id = f.grade_level_id
    ORDER BY f.academic_year DESC, f.term, f.fee_type")->fetchAll();

// CSV export of the payments ledger.
if ($tab === 'payments' && isset($_GET['export'])) {
    $rows = $pdo->query("SELECT fp.paid_at, u.first_name, u.last_name, s.admission_no, fs.fee_type, fp.amount, fp.method, fp.reference, fp.status
                          FROM fee_payments fp
                          JOIN student_fees sf ON sf.id = fp.student_fee_id
                          JOIN students s ON s.id = sf.student_id
                          JOIN users u ON u.id = s.user_id
                          JOIN fee_structures fs ON fs.id = sf.fee_structure_id
                          ORDER BY fp.paid_at DESC")->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fee_payments_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'First Name', 'Last Name', 'Admission No.', 'Fee Type', 'Amount', 'Method', 'Reference', 'Status']);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    audit_log($user['id'], 'fee_payments.export');
    exit;
}

$payments = [];
$outstandingFees = [];
$totals = ['collected' => 0, 'outstanding' => 0];
if ($tab === 'payments') {
    $payments = $pdo->query("SELECT fp.*, u.first_name, u.last_name, s.admission_no, fs.fee_type
                              FROM fee_payments fp
                              JOIN student_fees sf ON sf.id = fp.student_fee_id
                              JOIN students s ON s.id = sf.student_id
                              JOIN users u ON u.id = s.user_id
                              JOIN fee_structures fs ON fs.id = sf.fee_structure_id
                              ORDER BY fp.paid_at DESC LIMIT 100")->fetchAll();

    $outstandingFees = $pdo->query("SELECT sf.id, u.first_name, u.last_name, s.admission_no, fs.fee_type,
                                    sf.amount_due, COALESCE(SUM(fp.amount),0) AS paid,
                                    (sf.amount_due - COALESCE(SUM(fp.amount),0)) AS balance
                                    FROM student_fees sf
                                    JOIN students s ON s.id = sf.student_id
                                    JOIN users u ON u.id = s.user_id
                                    JOIN fee_structures fs ON fs.id = sf.fee_structure_id
                                    LEFT JOIN fee_payments fp ON fp.student_fee_id = sf.id AND fp.status='success'
                                    GROUP BY sf.id
                                    HAVING balance > 0.001
                                    ORDER BY u.first_name")->fetchAll();

    $totals['collected'] = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE status='success'")->fetchColumn();
    $totals['outstanding'] = (float)$pdo->query("SELECT COALESCE(SUM(sf.amount_due),0) - COALESCE((SELECT SUM(amount) FROM fee_payments WHERE status='success'),0) FROM student_fees sf")->fetchColumn();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="flex gap-2 mb-5 border-b border-slate-200 dark:border-slate-700">
  <a href="?tab=structures" class="px-4 py-2 text-sm font-medium border-b-2 <?= $tab==='structures' ? 'border-primary text-primary' : 'border-transparent text-slate-500' ?>">Fee Structures</a>
  <a href="?tab=payments" class="px-4 py-2 text-sm font-medium border-b-2 <?= $tab==='payments' ? 'border-primary text-primary' : 'border-transparent text-slate-500' ?>">Payments Ledger</a>
</div>

<?php if ($tab === 'structures'): ?>
  <div class="flex justify-end mb-5">
    <button onclick="openStructureModal()" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ Add Fee Structure</button>
  </div>
  <div class="card overflow-x-auto">
    <table class="data-table w-full">
      <thead><tr><th>Fee Type</th><th>Grade Level</th><th>Term</th><th>Year</th><th>Amount</th><th>Due</th><th>Invoiced</th><th>Collected</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($structures as $s): ?>
          <tr>
            <td class="font-medium"><?= e($s['fee_type']) ?></td>
            <td><?= e($s['grade_level_name'] ?: 'All grade levels') ?></td>
            <td><?= e($s['term']) ?></td>
            <td><?= e($s['academic_year']) ?></td>
            <td><?= money((float)$s['amount']) ?></td>
            <td><?= $s['due_date'] ? e(date('M j, Y', strtotime($s['due_date']))) : '—' ?></td>
            <td><?= (int)$s['invoiced_count'] ?></td>
            <td><?= money((float)$s['collected']) ?></td>
            <td class="text-right whitespace-nowrap text-xs">
              <form method="POST" class="inline" data-confirm="Generate invoices for every student in scope? Students already invoiced for this fee are skipped automatically.">
                <?= csrf_field() ?><input type="hidden" name="action" value="generate_invoices"><input type="hidden" name="fee_structure_id" value="<?= $s['id'] ?>">
                <button class="text-emerald-600 hover:underline font-medium">Generate Invoices</button>
              </form>
              <button onclick='openStructureModal(<?= json_encode($s, JSON_HEX_APOS) ?>)' class="text-primary hover:underline font-medium ml-3">Edit</button>
              <form method="POST" class="inline" data-confirm="Delete this fee structure?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_structure"><input type="hidden" name="fee_structure_id" value="<?= $s['id'] ?>">
                <button class="text-rose-500 hover:underline font-medium ml-3">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$structures): ?><tr><td colspan="9" class="text-center text-slate-400 py-8">No fee structures yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div id="structureModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md">
      <form method="POST" class="p-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="sAction" value="create_structure">
        <input type="hidden" name="fee_structure_id" id="sId">
        <h3 class="text-lg font-semibold" id="sModalTitle">Add Fee Structure</h3>
        <div><label class="text-sm font-medium">Fee type</label><input required name="fee_type" id="s_fee_type" placeholder="e.g. Tuition Fee" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Grade level</label>
          <select name="grade_level_id" id="s_grade_level_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
            <option value="">All grade levels</option>
            <?php foreach ($gradeLevels as $gl): ?><option value="<?= $gl['id'] ?>"><?= e($gl['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="text-sm font-medium">Amount (<?= APP_CURRENCY_SYMBOL ?>)</label><input required type="number" step="0.01" min="0.01" name="amount" id="s_amount" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium">Term</label><input required name="term" id="s_term" placeholder="Term 1" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
          <div><label class="text-sm font-medium">Academic year</label><input required name="academic_year" id="s_academic_year" placeholder="2026/2027" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        </div>
        <div><label class="text-sm font-medium">Due date</label><input type="date" name="due_date" id="s_due_date" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" onclick="closeStructureModal()" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
          <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openStructureModal(data) {
    const fields = ['fee_type','grade_level_id','amount','term','academic_year','due_date'];
    fields.forEach(f => { const el = document.getElementById('s_'+f); if (el) el.value = ''; });
    if (data) {
      document.getElementById('sModalTitle').textContent = 'Edit Fee Structure';
      document.getElementById('sAction').value = 'update_structure';
      document.getElementById('sId').value = data.id;
      fields.forEach(f => { const el = document.getElementById('s_'+f); if (el) el.value = data[f] || ''; });
    } else {
      document.getElementById('sModalTitle').textContent = 'Add Fee Structure';
      document.getElementById('sAction').value = 'create_structure';
      document.getElementById('sId').value = '';
    }
    document.getElementById('structureModal').classList.remove('hidden');
  }
  function closeStructureModal() { document.getElementById('structureModal').classList.add('hidden'); }
  </script>

<?php else: ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="card p-5"><p class="text-xs font-medium text-slate-500">Total Collected</p><p class="text-2xl font-bold mt-1 text-emerald-600"><?= money($totals['collected']) ?></p></div>
    <div class="card p-5"><p class="text-xs font-medium text-slate-500">Total Outstanding</p><p class="text-2xl font-bold mt-1 text-rose-500"><?= money(max(0, $totals['outstanding'])) ?></p></div>
  </div>

  <div class="flex justify-end gap-2 mb-5">
    <a href="?tab=payments&export=csv" class="bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-sm font-medium rounded-lg px-4 py-2">⬇ Export CSV</a>
    <button onclick="openPaymentModal()" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ Record Payment</button>
  </div>

  <div class="card overflow-x-auto">
    <table class="data-table w-full">
      <thead><tr><th>Date</th><th>Student</th><th>Fee</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime($p['paid_at']))) ?></td>
            <td class="font-medium"><?= e($p['first_name'].' '.$p['last_name']) ?> <span class="text-slate-400 text-xs">(<?= e($p['admission_no']) ?>)</span></td>
            <td><?= e($p['fee_type']) ?></td>
            <td><?= money((float)$p['amount']) ?></td>
            <td><span class="badge bg-blue-50 text-primary"><?= e(ucwords(str_replace('_',' ',$p['method']))) ?></span></td>
            <td class="text-slate-400 text-xs"><?= e($p['reference'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No payments recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div id="paymentModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md">
      <form method="POST" class="p-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="record_payment">
        <h3 class="text-lg font-semibold">Record Payment</h3>
        <?php if (!$outstandingFees): ?>
          <p class="text-sm text-slate-400">No students currently have an outstanding balance. Generate invoices from the Fee Structures tab first.</p>
        <?php else: ?>
          <div>
            <label class="text-sm font-medium">Student invoice</label>
            <input type="text" id="feeFilter" placeholder="Type a name to filter…" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1 mb-2">
            <select required name="student_fee_id" id="feeSelect" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
              <?php foreach ($outstandingFees as $of): ?>
                <option value="<?= $of['id'] ?>" data-name="<?= e(strtolower($of['first_name'].' '.$of['last_name'].' '.$of['admission_no'])) ?>">
                  <?= e($of['first_name'].' '.$of['last_name']) ?> (<?= e($of['admission_no']) ?>) — <?= e($of['fee_type']) ?> — Balance: <?= money((float)$of['balance']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label class="text-sm font-medium">Amount (<?= APP_CURRENCY_SYMBOL ?>)</label><input required type="number" step="0.01" min="0.01" name="amount" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="text-sm font-medium">Method</label>
              <select required name="method" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div><label class="text-sm font-medium">Date</label><input required type="date" name="paid_at" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
          </div>
          <div><label class="text-sm font-medium">Reference (optional)</label><input name="reference" placeholder="e.g. receipt or bank ref no." class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <?php endif; ?>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" onclick="closePaymentModal()" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
          <?php if ($outstandingFees): ?><button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openPaymentModal() { document.getElementById('paymentModal').classList.remove('hidden'); }
  function closePaymentModal() { document.getElementById('paymentModal').classList.add('hidden'); }
  const feeFilter = document.getElementById('feeFilter');
  if (feeFilter) {
    feeFilter.addEventListener('input', () => {
      const q = feeFilter.value.toLowerCase();
      document.querySelectorAll('#feeSelect option').forEach(opt => {
        opt.hidden = !opt.dataset.name.includes(q);
      });
    });
  }
  </script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
