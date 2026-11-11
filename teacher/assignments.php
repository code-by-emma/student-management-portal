<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('teacher');
$pageTitle = 'Assignments';
$teacherId = $user['linked_id'];

$courses = $pdo->prepare("SELECT co.id, sub.name AS subject_name, c.name AS class_name, c.section
                          FROM courses co JOIN subjects sub ON sub.id=co.subject_id JOIN classes c ON c.id=co.class_id
                          WHERE co.teacher_id=? ORDER BY sub.name");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();
$courseIds = array_column($courses, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        if ($action === 'create') {
            $courseId = (int)input('course_id');
            if (!in_array($courseId, $courseIds)) throw new RuntimeException('You do not have access to that course.');
            $title = input('title'); $desc = input('description');
            $maxScore = input('max_score', 100); $dueDate = input('due_date') ?: null;
            if (!$title) throw new RuntimeException('Title is required.');

            $filePath = handle_upload('attachment', 'course_materials');
            $stmt = $pdo->prepare('INSERT INTO assignments (course_id, title, description, file_path, max_score, due_date, created_by) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$courseId, $title, $desc, $filePath, $maxScore, $dueDate, $user['id']]);
            $assignmentId = $pdo->lastInsertId();

            // Notify enrolled students.
            $students = $pdo->prepare('SELECT s.user_id FROM enrollments e JOIN students s ON s.id=e.student_id WHERE e.course_id=?');
            $students->execute([$courseId]);
            foreach ($students->fetchAll() as $s) {
                notify($s['user_id'], 'New assignment: ' . $title, 'Due ' . ($dueDate ? date('M j, Y', strtotime($dueDate)) : 'soon'), 'assignment', BASE_URL.'/student/assignments.php');
            }
            audit_log($user['id'], 'assignment.create', 'assignments', $assignmentId);
            flash('success', 'Assignment posted.');
        } elseif ($action === 'delete') {
            $id = (int)input('assignment_id');
            $stmt = $pdo->prepare('SELECT co.teacher_id FROM assignments a JOIN courses co ON co.id=a.course_id WHERE a.id=?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() != $teacherId) throw new RuntimeException('You do not have access to that assignment.');
            $pdo->prepare('DELETE FROM assignments WHERE id=?')->execute([$id]);
            audit_log($user['id'], 'assignment.delete', 'assignments', $id);
            flash('success', 'Assignment deleted.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/teacher/assignments.php');
}

$assignments = [];
if ($courseIds) {
    $in = implode(',', array_fill(0, count($courseIds), '?'));
    $stmt = $pdo->prepare("SELECT a.*, sub.name AS subject_name,
                           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id=a.id) AS submitted_count,
                           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=a.course_id) AS total_students
                           FROM assignments a JOIN courses co ON co.id=a.course_id JOIN subjects sub ON sub.id=co.subject_id
                           WHERE a.course_id IN ($in) ORDER BY a.created_at DESC");
    $stmt->execute($courseIds);
    $assignments = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="flex justify-end mb-5">
  <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ New Assignment</button>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
  <?php foreach ($assignments as $a): ?>
    <div class="card p-5">
      <div class="flex justify-between items-start">
        <div>
          <h3 class="font-semibold"><?= e($a['title']) ?></h3>
          <p class="text-xs text-slate-400"><?= e($a['subject_name']) ?> · Due <?= $a['due_date'] ? e(date('M j, Y', strtotime($a['due_date']))) : 'no due date' ?></p>
        </div>
        <form method="POST" data-confirm="Delete this assignment?">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
          <button class="text-rose-500 hover:underline text-xs">Delete</button>
        </form>
      </div>
      <p class="text-sm text-slate-500 mt-2"><?= e(mb_strimwidth($a['description'] ?? '', 0, 100, '…')) ?></p>
      <div class="flex items-center justify-between mt-3">
        <span class="badge bg-blue-50 text-primary"><?= (int)$a['submitted_count'] ?> / <?= (int)$a['total_students'] ?> submitted</span>
        <a href="submissions.php?assignment_id=<?= $a['id'] ?>" class="text-xs text-primary hover:underline font-medium">View & grade →</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$assignments): ?><p class="text-slate-400 text-sm col-span-full text-center py-8">No assignments posted yet.</p><?php endif; ?>
</div>

<div id="modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg">
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <h3 class="text-lg font-semibold">New Assignment</h3>
      <div><label class="text-sm font-medium">Course</label>
        <select required name="course_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['subject_name'].' — '.$c['class_name'].' '.$c['section']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="text-sm font-medium">Title</label><input required name="title" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Description</label><textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></textarea></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Max score</label><input type="number" name="max_score" value="100" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Due date</label><input type="datetime-local" name="due_date" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div><label class="text-sm font-medium">Attachment (optional)</label><input type="file" name="attachment" class="w-full text-sm mt-1"></div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Post assignment</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
