<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('student');
$pageTitle = 'Grades';
$studentId = $user['linked_id'];

if (isset($_GET['export'])) {
    $stmt = $pdo->prepare("SELECT sub.name AS subject_name, ex.name AS exam_name, g.score, ex.max_score, g.grade_letter, g.remarks
                            FROM enrollments e JOIN courses co ON co.id = e.course_id JOIN subjects sub ON sub.id = co.subject_id
                            JOIN exams ex ON ex.course_id = co.id
                            LEFT JOIN grades g ON g.exam_id = ex.id AND g.student_id = e.student_id
                            WHERE e.student_id = ? ORDER BY sub.name, ex.exam_date");
    $stmt->execute([$studentId]);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_card_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Subject', 'Exam', 'Score', 'Max Score', 'Grade', 'Remarks']);
    foreach ($stmt as $r) {
        fputcsv($out, [$r['subject_name'], $r['exam_name'], $r['score'] ?? '—', $r['max_score'], $r['grade_letter'] ?? '—', $r['remarks'] ?? '']);
    }
    fclose($out);
    audit_log($user['id'], 'report_card.export', 'students', $studentId);
    exit;
}

$grades = $pdo->prepare("SELECT sub.name AS subject_name, ex.name AS exam_name, ex.exam_date, g.score, ex.max_score, g.grade_letter, g.remarks
                          FROM enrollments e JOIN courses co ON co.id = e.course_id JOIN subjects sub ON sub.id = co.subject_id
                          JOIN exams ex ON ex.course_id = co.id
                          LEFT JOIN grades g ON g.exam_id = ex.id AND g.student_id = e.student_id
                          WHERE e.student_id = ? ORDER BY sub.name, ex.exam_date");
$grades->execute([$studentId]);
$grades = $grades->fetchAll();

// Per-subject average (only counting graded exams).
$bySubject = [];
foreach ($grades as $g) {
    if ($g['score'] === null) continue;
    $bySubject[$g['subject_name']]['sum'] = ($bySubject[$g['subject_name']]['sum'] ?? 0) + ($g['score'] / $g['max_score'] * 100);
    $bySubject[$g['subject_name']]['count'] = ($bySubject[$g['subject_name']]['count'] ?? 0) + 1;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="flex justify-end mb-5">
  <a href="?export=csv" class="bg-accent hover:opacity-90 text-white text-sm font-medium rounded-lg px-4 py-2">⬇ Download report card (CSV)</a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <?php foreach ($bySubject as $name => $s): ?>
    <div class="card p-5">
      <p class="text-sm font-medium"><?= e($name) ?></p>
      <p class="text-2xl font-bold mt-1"><?= round($s['sum'] / $s['count'], 1) ?>%</p>
      <p class="text-xs text-slate-400 mt-1"><?= $s['count'] ?> exam(s) graded</p>
    </div>
  <?php endforeach; ?>
  <?php if (!$bySubject): ?><p class="text-slate-400 text-sm col-span-full text-center py-8">No grades published yet.</p><?php endif; ?>
</div>

<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Subject</th><th>Exam</th><th>Date</th><th>Score</th><th>Grade</th><th>Remarks</th></tr></thead>
    <tbody>
      <?php foreach ($grades as $g): ?>
        <tr>
          <td class="font-medium"><?= e($g['subject_name']) ?></td>
          <td><?= e($g['exam_name']) ?></td>
          <td><?= $g['exam_date'] ? e(date('M j, Y', strtotime($g['exam_date']))) : '—' ?></td>
          <td><?= $g['score'] !== null ? e($g['score'].' / '.$g['max_score']) : '—' ?></td>
          <td><span class="badge bg-blue-50 text-primary"><?= e($g['grade_letter'] ?: '—') ?></span></td>
          <td class="text-slate-500"><?= e($g['remarks'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$grades): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No exams recorded yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
