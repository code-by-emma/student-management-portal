<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('teacher');
$pageTitle = 'My Courses';
$teacherId = $user['linked_id'];

$courses = $pdo->prepare("SELECT co.*, sub.name AS subject_name, sub.code, c.name AS class_name, c.section,
                          (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=co.id) AS student_count
                          FROM courses co JOIN subjects sub ON sub.id=co.subject_id JOIN classes c ON c.id=co.class_id
                          WHERE co.teacher_id=? ORDER BY co.academic_year DESC");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($courses as $c): ?>
    <div class="card p-5">
      <h3 class="font-semibold"><?= e($c['subject_name']) ?></h3>
      <p class="text-xs text-slate-400"><?= e($c['code']) ?></p>
      <p class="text-sm text-slate-500 mt-2"><?= e($c['class_name'].' '.$c['section']) ?> · <?= e($c['term'] ?: $c['academic_year']) ?></p>
      <p class="text-sm text-slate-500"><?= (int)$c['student_count'] ?> students enrolled</p>
      <?php if ($c['description']): ?><p class="text-xs text-slate-400 mt-2"><?= e($c['description']) ?></p><?php endif; ?>
      <div class="flex gap-3 mt-4 text-xs">
        <a href="attendance.php?course_id=<?= $c['id'] ?>" class="text-primary hover:underline font-medium">Attendance</a>
        <a href="assignments.php?course_id=<?= $c['id'] ?>" class="text-primary hover:underline font-medium">Assignments</a>
        <a href="grades.php?course_id=<?= $c['id'] ?>" class="text-primary hover:underline font-medium">Grades</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$courses): ?><p class="text-slate-400 text-sm col-span-full text-center py-8">No courses assigned. Contact an administrator.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
