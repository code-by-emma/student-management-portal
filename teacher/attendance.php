<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('teacher');
$pageTitle = 'Attendance';
$teacherId = $user['linked_id'];

$courses = $pdo->prepare("SELECT co.id, sub.name AS subject_name, c.name AS class_name, c.section
                          FROM courses co JOIN subjects sub ON sub.id=co.subject_id JOIN classes c ON c.id=co.class_id
                          WHERE co.teacher_id=? ORDER BY sub.name");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();

$courseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));
$date = $_GET['date'] ?? date('Y-m-d');

// Ownership check: this teacher must actually own the selected course.
$owns = false;
foreach ($courses as $c) if ($c['id'] == $courseId) $owns = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$owns) { flash('error', 'You do not have access to that course.'); redirect('/teacher/attendance.php'); }
    $postDate = input('date');
    $statuses = $_POST['status'] ?? [];
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, course_id, date, status, marked_by) VALUES (?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)");
    foreach ($statuses as $studentId => $status) {
        $stmt->execute([(int)$studentId, $courseId, $postDate, $status, $user['id']]);
    }
    $pdo->commit();
    audit_log($user['id'], 'attendance.mark', 'courses', $courseId, "date={$postDate}, count=".count($statuses));
    flash('success', 'Attendance saved for ' . date('M j, Y', strtotime($postDate)) . '.');
    redirect('/teacher/attendance.php?course_id=' . $courseId . '&date=' . $postDate);
}

$roster = [];
$existing = [];
if ($owns && $courseId) {
    $roster = $pdo->prepare("SELECT s.id, u.first_name, u.last_name, u.profile_picture, s.admission_no
                              FROM enrollments e JOIN students s ON s.id=e.student_id JOIN users u ON u.id=s.user_id
                              WHERE e.course_id=? ORDER BY u.first_name");
    $roster->execute([$courseId]);
    $roster = $roster->fetchAll();

    $ex = $pdo->prepare('SELECT student_id, status FROM attendance WHERE course_id=? AND date=?');
    $ex->execute([$courseId, $date]);
    foreach ($ex->fetchAll() as $row) $existing[$row['student_id']] = $row['status'];
}

include __DIR__ . '/../includes/header.php';
?>
<form method="GET" class="flex flex-wrap gap-2 mb-5">
  <select name="course_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
    <?php foreach ($courses as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':'' ?>><?= e($c['subject_name'].' — '.$c['class_name'].' '.$c['section']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
</form>

<?php if (!$courses): ?>
  <p class="text-slate-400 text-sm">No courses assigned to you yet.</p>
<?php else: ?>
<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="date" value="<?= e($date) ?>">
  <div class="card overflow-x-auto">
    <table class="data-table w-full">
      <thead><tr><th>Student</th><th>Admission No.</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($roster as $s): $cur = $existing[$s['id']] ?? 'present'; ?>
          <tr>
            <td class="flex items-center gap-3">
              <img src="<?= $s['profile_picture'] ? BASE_URL.'/uploads/'.e($s['profile_picture']) : 'https://ui-avatars.com/api/?background=2563EB&color=fff&name='.urlencode($s['first_name'].' '.$s['last_name']) ?>" class="h-8 w-8 rounded-full object-cover">
              <?= e($s['first_name'].' '.$s['last_name']) ?>
            </td>
            <td><?= e($s['admission_no']) ?></td>
            <td>
              <div class="flex gap-3 text-sm">
                <?php foreach (['present'=>'Present','absent'=>'Absent','late'=>'Late','excused'=>'Excused'] as $val=>$label): ?>
                  <label class="flex items-center gap-1">
                    <input type="radio" name="status[<?= $s['id'] ?>]" value="<?= $val ?>" <?= $cur===$val?'checked':'' ?> class="text-primary focus:ring-primary">
                    <?= $label ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$roster): ?><tr><td colspan="3" class="text-center text-slate-400 py-8">No students enrolled in this course.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($roster): ?>
    <button class="mt-4 bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-5 py-2.5">Save attendance</button>
  <?php endif; ?>
</form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
