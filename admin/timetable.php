<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Timetable';

$classes = $pdo->query('SELECT id, name, section FROM classes ORDER BY name')->fetchAll();
$classId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));

$courses = $pdo->prepare("SELECT co.id, sub.name AS subject_name FROM courses co JOIN subjects sub ON sub.id=co.subject_id WHERE co.class_id = ? ORDER BY sub.name");
$courses->execute([$classId]);
$courses = $courses->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        if ($action === 'create') {
            $courseId = (int)input('course_id');
            $day = input('day_of_week');
            $start = input('start_time'); $end = input('end_time'); $room = input('room');
            if (!$courseId || !$day || !$start || !$end) throw new RuntimeException('All fields except room are required.');
            $stmt = $pdo->prepare('INSERT INTO timetables (class_id, course_id, day_of_week, start_time, end_time, room) VALUES (?,?,?,?,?,?)');
            $stmt->execute([(int)input('class_id'), $courseId, $day, $start, $end, $room]);
            audit_log($user['id'], 'timetable.create', 'timetables', $pdo->lastInsertId());
            flash('success', 'Timetable slot added.');
        } elseif ($action === 'delete') {
            $id = (int)input('timetable_id');
            $pdo->prepare('DELETE FROM timetables WHERE id=?')->execute([$id]);
            audit_log($user['id'], 'timetable.delete', 'timetables', $id);
            flash('success', 'Slot removed.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/timetable.php?class_id=' . (int)input('class_id', $classId));
}

$slots = $pdo->prepare("SELECT tt.*, sub.name AS subject_name FROM timetables tt
                        JOIN courses co ON co.id = tt.course_id JOIN subjects sub ON sub.id = co.subject_id
                        WHERE tt.class_id = ? ORDER BY tt.day_of_week, tt.start_time");
$slots->execute([$classId]);
$slots = $slots->fetchAll();

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

include __DIR__ . '/../includes/header.php';
?>
<div class="flex flex-wrap items-center gap-2 mb-5">
  <form method="GET" class="flex gap-2">
    <select name="class_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
      <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= e($c['name'].' '.$c['section']) ?></option><?php endforeach; ?>
    </select>
  </form>
  <?php if ($courses): ?>
    <button onclick="document.getElementById('modal').classList.remove('hidden')" class="ml-auto bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ Add Slot</button>
  <?php endif; ?>
</div>

<?php if (!$courses): ?>
  <p class="text-slate-400 text-sm">This class has no courses yet — create a course for it first.</p>
<?php else: ?>
<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Room</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($slots as $s): ?>
        <tr>
          <td><?= e($s['day_of_week']) ?></td>
          <td><?= e(substr($s['start_time'],0,5).' – '.substr($s['end_time'],0,5)) ?></td>
          <td class="font-medium"><?= e($s['subject_name']) ?></td>
          <td><?= e($s['room'] ?: '—') ?></td>
          <td>
            <form method="POST" data-confirm="Remove this slot?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete">
              <input type="hidden" name="class_id" value="<?= $classId ?>">
              <input type="hidden" name="timetable_id" value="<?= $s['id'] ?>">
              <button class="text-rose-500 hover:underline text-xs">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$slots): ?><tr><td colspan="5" class="text-center text-slate-400 py-8">No timetable slots for this class yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div id="modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <h3 class="text-lg font-semibold">Add Timetable Slot</h3>
      <div><label class="text-sm font-medium">Course</label>
        <select required name="course_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['subject_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="text-sm font-medium">Day</label>
        <select required name="day_of_week" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <?php foreach ($days as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Start</label><input type="time" required name="start_time" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">End</label><input type="time" required name="end_time" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div><label class="text-sm font-medium">Room</label><input name="room" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Add</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
