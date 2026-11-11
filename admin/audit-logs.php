<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Audit Logs';

[$perPage, $offset, $page] = paginate(25);
$total = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT al.*, u.first_name, u.last_name, u.email FROM audit_logs al
                        LEFT JOIN users u ON u.id = al.user_id
                        ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$logs = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th>Details</th></tr></thead>
    <tbody>
      <?php if (!$logs): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No audit records.</td></tr><?php endif; ?>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="whitespace-nowrap"><?= e(date('M j, Y g:i a', strtotime($l['created_at']))) ?></td>
          <td><?= $l['first_name'] ? e($l['first_name'].' '.$l['last_name']) : '<span class="text-slate-400">System</span>' ?></td>
          <td><span class="badge bg-blue-50 text-primary"><?= e($l['action']) ?></span></td>
          <td><?= e(($l['entity_type'] ?: '—') . ($l['entity_id'] ? ' #'.$l['entity_id'] : '')) ?></td>
          <td class="text-xs text-slate-400"><?= e($l['ip_address'] ?: '—') ?></td>
          <td class="text-xs text-slate-500 max-w-xs truncate"><?= e($l['details'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pagination_links($page, $totalPages) ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
