<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.profile_picture, u.status,
                        c.name AS class_name, c.section
                        FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN classes c ON c.id=s.class_id
                        WHERE s.id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { flash('error', 'Student not found.'); redirect('/admin/students.php'); }

$pageTitle = $student['first_name'] . ' ' . $student['last_name'];

$courses = $pdo->prepare("SELECT sub.name, co.term, co.academic_year FROM enrollments e
                           JOIN courses co ON co.id=e.course_id JOIN subjects sub ON sub.id=co.subject_id
                           WHERE e.student_id = ?");
$courses->execute([$id]);
$courses = $courses->fetchAll();

$attSummary = $pdo->prepare("SELECT SUM(status='present') present, COUNT(*) total FROM attendance WHERE student_id=?");
$attSummary->execute([$id]);
$attSummary = $attSummary->fetch();
$attPct = $attSummary['total'] ? round(($attSummary['present'] / $attSummary['total']) * 100) : 0;

$grades = $pdo->prepare("SELECT sub.name AS subject_name, ex.name AS exam_name, g.score, ex.max_score, g.grade_letter FROM grades g
                          JOIN exams ex ON ex.id=g.exam_id JOIN courses co ON co.id=ex.course_id JOIN subjects sub ON sub.id=co.subject_id
                          WHERE g.student_id=? ORDER BY g.recorded_at DESC LIMIT 10");
$grades->execute([$id]);
$grades = $grades->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<a href="students.php" class="text-sm text-primary hover:underline">← Back to Students</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">
  <div class="card p-6 text-center h-fit">
    <img src="<?= $student['profile_picture'] ? BASE_URL.'/uploads/'.e($student['profile_picture']) : 'https://ui-avatars.com/api/?background=2563EB&color=fff&size=128&name='.urlencode($student['first_name'].' '.$student['last_name']) ?>" class="h-24 w-24 rounded-full object-cover mx-auto">
    <h2 class="font-semibold mt-3"><?= e($student['first_name'].' '.$student['last_name']) ?></h2>
    <p class="text-sm text-slate-400"><?= e($student['admission_no']) ?></p>
    <div class="text-left mt-4 space-y-1 text-sm">
      <p><span class="text-slate-400">Class:</span> <?= $student['class_name'] ? e($student['class_name'].' '.$student['section']) : '—' ?></p>
      <p><span class="text-slate-400">Email:</span> <?= e($student['email']) ?></p>
      <p><span class="text-slate-400">Phone:</span> <?= e($student['phone'] ?: '—') ?></p>
      <p><span class="text-slate-400">Guardian:</span> <?= e($student['guardian_name'] ?: '—') ?></p>
      <p><span class="text-slate-400">Guardian phone:</span> <?= e($student['guardian_phone'] ?: '—') ?></p>
    </div>
  </div>

  <div class="lg:col-span-2 space-y-6">
    <div class="card p-6">
      <h3 class="font-semibold mb-3">Attendance Summary</h3>
      <div class="flex items-center gap-4">
        <div class="text-3xl font-bold"><?= $attPct ?>%</div>
        <div class="text-sm text-slate-500"><?= (int)$attSummary['present'] ?> present out of <?= (int)$attSummary['total'] ?> recorded sessions</div>
      </div>
    </div>

    <div class="card p-6">
      <h3 class="font-semibold mb-3">Enrolled Courses</h3>
      <?php if (!$courses): ?><p class="text-sm text-slate-400">Not enrolled in any courses yet.</p><?php endif; ?>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($courses as $c): ?>
          <span class="badge bg-blue-50 text-primary"><?= e($c['name']) ?> — <?= e($c['term'] ?: $c['academic_year']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card p-6">
      <h3 class="font-semibold mb-3">Recent Grades</h3>
      <table class="data-table w-full">
        <thead><tr><th>Subject</th><th>Exam</th><th>Score</th><th>Grade</th></tr></thead>
        <tbody>
          <?php foreach ($grades as $g): ?>
            <tr><td><?= e($g['subject_name']) ?></td><td><?= e($g['exam_name']) ?></td><td><?= $g['score'] !== null ? e($g['score'].'/'.$g['max_score']) : '—' ?></td><td><?= e($g['grade_letter'] ?: '—') ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$grades): ?><tr><td colspan="4" class="text-center text-slate-400 py-6">No grades recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
