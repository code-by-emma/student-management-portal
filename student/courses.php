<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('student');
$pageTitle = 'My Courses';
$studentId = $user['linked_id'];

$courses = $pdo->prepare("SELECT co.id, sub.name AS subject_name, sub.code, c.name AS class_name, c.section,
                          co.term, co.academic_year, co.description,
                          tu.first_name AS t_first, tu.last_name AS t_last,
                          (SELECT COUNT(*) FROM assignments a WHERE a.course_id = co.id) AS assignment_count
                          FROM enrollments e
                          JOIN courses co ON co.id = e.course_id
                          JOIN subjects sub ON sub.id = co.subject_id
                          JOIN classes c ON c.id = co.class_id
                          LEFT JOIN teachers t ON t.id = co.teacher_id
                          LEFT JOIN users tu ON tu.id = t.user_id
                          WHERE e.student_id = ? ORDER BY sub.name");
$courses->execute([$studentId]);
$courses = $courses->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
  <?php foreach ($courses as $c): ?>
    <div class="card p-5">
      <div class="flex items-start justify-between">
        <div>
          <h3 class="font-semibold"><?= e($c['subject_name']) ?></h3>
          <p class="text-xs text-slate-400"><?= e($c['code']) ?> · <?= e($c['class_name'].' '.$c['section']) ?> · <?= e($c['term'].' '.$c['academic_year']) ?></p>
        </div>
        <span class="badge bg-blue-50 text-primary"><?= (int)$c['assignment_count'] ?> assignments</span>
      </div>
      <p class="text-sm text-slate-500 mt-3"><?= e($c['description'] ?: 'No description provided.') ?></p>
      <p class="text-sm text-slate-500 mt-2">Teacher: <?= $c['t_first'] ? e($c['t_first'].' '.$c['t_last']) : 'Unassigned' ?></p>
    </div>
  <?php endforeach; ?>
  <?php if (!$courses): ?><p class="text-slate-400 text-sm col-span-full text-center py-8">You are not enrolled in any courses yet. Contact your administrator.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
