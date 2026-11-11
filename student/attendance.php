<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('student');
$pageTitle = 'Attendance';
$studentId = $user['linked_id'];

$courses = $pdo->prepare("SELECT co.id, sub.name AS subject_name
                          FROM enrollments e JOIN courses co ON co.id = e.course_id JOIN subjects sub ON sub.id = co.subject_id
                          WHERE e.student_id = ? ORDER BY sub.name");
$courses->execute([$studentId]);
$courses = $courses->fetchAll();

$courseId = (int)($_GET['course_id'] ?? 0);

$summary = $pdo->prepare("SELECT co.id, sub.name AS subject_name,
    SUM(a.status='present') AS present, SUM(a.status='absent') AS absent,
    SUM(a.status='late') AS late, SUM(a.status='excused') AS excused, COUNT(a.id) AS total
    FROM enrollments e JOIN courses co ON co.id = e.course_id JOIN subjects sub ON sub.id = co.subject_id
    LEFT JOIN attendance a ON a.course_id = co.id AND a.student_id = e.student_id
    WHERE e.student_id = ? GROUP BY co.id ORDER BY sub.name");
$summary->execute([$studentId]);
$summary = $summary->fetchAll();

$records = [];
if ($courseId) {
    $stmt = $pdo->prepare("SELECT date, status FROM attendance WHERE student_id = ? AND course_id = ? ORDER BY date DESC LIMIT 60");
    $stmt->execute([$studentId, $courseId]);
    $records = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach ($summary as $s): $pct = $s['total'] ? round(($s['present']/$s['total'])*100,1) : null; ?>
    <a href="?course_id=<?= $s['id'] ?>" class="card p-5 block <?= $courseId==$s['id']?'ring-2 ring-primary':'' ?>">
      <p class="text-sm font-medium"><?= e($s['subject_name']) ?></p>
      <p class="text-2xl font-bold mt-1"><?= $pct !== null ? $pct.'%' : '—' ?></p>
      <p class="text-xs text-slate-400 mt-1"><?= (int)$s['present'] ?> present · <?= (int)$s['absent'] ?> absent · <?= (int)$s['late'] ?> late</p>
    </a>
  <?php endforeach; ?>
  <?php if (!$summary): ?><p class="text-slate-400 text-sm col-span-full text-center py-8">No attendance data yet.</p><?php endif; ?>
</div>

<?php if ($courseId): ?>
<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Date</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($records as $r): ?>
        <?php $badge = ['present'=>'bg-emerald-100 text-emerald-700','absent'=>'bg-rose-100 text-rose-700','late'=>'bg-amber-100 text-amber-700','excused'=>'bg-blue-100 text-blue-700'][$r['status']]; ?>
        <tr>
          <td><?= e(date('M j, Y', strtotime($r['date']))) ?></td>
          <td><span class="badge <?= $badge ?>"><?= e(ucfirst($r['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$records): ?><tr><td colspan="2" class="text-center text-slate-400 py-8">No records for this course yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
