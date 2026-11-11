<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('student');
$pageTitle = 'Timetable';
$studentId = $user['linked_id'];

$rows = $pdo->prepare("SELECT tt.day_of_week, tt.start_time, tt.end_time, tt.room, sub.name AS subject_name
                        FROM timetables tt JOIN courses co ON co.id = tt.course_id JOIN subjects sub ON sub.id = co.subject_id
                        JOIN enrollments e ON e.course_id = tt.course_id
                        WHERE e.student_id = ? ORDER BY tt.start_time");
$rows->execute([$studentId]);
$rows = $rows->fetchAll();

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$byDay = array_fill_keys($days, []);
foreach ($rows as $r) $byDay[$r['day_of_week']][] = $r;

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
  <?php foreach ($days as $day): if (empty($byDay[$day]) && in_array($day, ['Saturday','Sunday'], true)) continue; ?>
    <div class="card p-4">
      <h3 class="font-semibold mb-3 text-sm"><?= e($day) ?></h3>
      <ul class="space-y-3">
        <?php foreach ($byDay[$day] as $t): ?>
          <li class="text-sm border-l-2 border-primary pl-3">
            <p class="font-medium"><?= e($t['subject_name']) ?></p>
            <p class="text-slate-400 text-xs"><?= e(substr($t['start_time'],0,5).' – '.substr($t['end_time'],0,5)) ?><?= $t['room'] ? ' · Room '.e($t['room']) : '' ?></p>
          </li>
        <?php endforeach; ?>
        <?php if (!$byDay[$day]): ?><li class="text-xs text-slate-400">No classes.</li><?php endif; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
