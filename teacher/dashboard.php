<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('teacher');
$pageTitle = 'Teacher Dashboard';
$teacherId = $user['linked_id'];

$myCourses = $pdo->prepare("SELECT co.id, sub.name AS subject_name, c.name AS class_name, c.section,
                            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=co.id) AS student_count
                            FROM courses co JOIN subjects sub ON sub.id=co.subject_id JOIN classes c ON c.id=co.class_id
                            WHERE co.teacher_id = ? ORDER BY co.academic_year DESC");
$myCourses->execute([$teacherId]);
$myCourses = $myCourses->fetchAll();

$totalStudents = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses co ON co.id=e.course_id WHERE co.teacher_id=?");
$totalStudents->execute([$teacherId]);
$totalStudents = (int)$totalStudents->fetchColumn();

$pendingGrading = $pdo->prepare("SELECT COUNT(*) FROM assignment_submissions asu
                                  JOIN assignments a ON a.id=asu.assignment_id JOIN courses co ON co.id=a.course_id
                                  WHERE co.teacher_id=? AND asu.score IS NULL");
$pendingGrading->execute([$teacherId]);
$pendingGrading = (int)$pendingGrading->fetchColumn();

$today = date('l'); // e.g. "Monday"
$todaysClasses = $pdo->prepare("SELECT tt.start_time, tt.end_time, tt.room, sub.name AS subject_name, c.name AS class_name, c.section
                                 FROM timetables tt JOIN courses co ON co.id=tt.course_id JOIN subjects sub ON sub.id=co.subject_id
                                 JOIN classes c ON c.id=tt.class_id
                                 WHERE co.teacher_id=? AND tt.day_of_week=? ORDER BY tt.start_time");
$todaysClasses->execute([$teacherId, $today]);
$todaysClasses = $todaysClasses->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">My Courses</p><p class="text-2xl font-bold mt-1"><?= count($myCourses) ?></p></div>
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Total Students</p><p class="text-2xl font-bold mt-1"><?= $totalStudents ?></p></div>
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Pending Grading</p><p class="text-2xl font-bold mt-1 text-amber-500"><?= $pendingGrading ?></p><a href="grades.php" class="text-xs text-primary hover:underline">Grade now →</a></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-5">
    <h2 class="font-semibold mb-4">My Courses</h2>
    <table class="data-table w-full">
      <thead><tr><th>Subject</th><th>Class</th><th>Students</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($myCourses as $c): ?>
          <tr>
            <td class="font-medium"><?= e($c['subject_name']) ?></td>
            <td><?= e($c['class_name'].' '.$c['section']) ?></td>
            <td><?= (int)$c['student_count'] ?></td>
            <td><a href="attendance.php?course_id=<?= $c['id'] ?>" class="text-primary hover:underline text-xs">Take attendance →</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$myCourses): ?><tr><td colspan="4" class="text-center text-slate-400 py-8">No courses assigned yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card p-5">
    <h2 class="font-semibold mb-4">Today's Schedule (<?= e($today) ?>)</h2>
    <ul class="space-y-3">
      <?php foreach ($todaysClasses as $t): ?>
        <li class="text-sm border-l-2 border-primary pl-3">
          <p class="font-medium"><?= e($t['subject_name']) ?></p>
          <p class="text-slate-400 text-xs"><?= e(substr($t['start_time'],0,5).' – '.substr($t['end_time'],0,5)) ?> · <?= e($t['class_name'].' '.$t['section']) ?> <?= $t['room'] ? '· Room '.e($t['room']) : '' ?></p>
        </li>
      <?php endforeach; ?>
      <?php if (!$todaysClasses): ?><li class="text-sm text-slate-400">No classes scheduled today.</li><?php endif; ?>
    </ul>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
