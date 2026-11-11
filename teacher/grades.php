<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('teacher');
$pageTitle = 'Grades';
$teacherId = $user['linked_id'];

$courses = $pdo->prepare("SELECT co.id, sub.name AS subject_name, c.name AS class_name, c.section
                          FROM courses co JOIN subjects sub ON sub.id=co.subject_id JOIN classes c ON c.id=co.class_id
                          WHERE co.teacher_id=? ORDER BY sub.name");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();
$courseIds = array_column($courses, 'id');

function teacher_owns_course(array $courseIds, int $courseId): bool {
    return in_array($courseId, $courseIds, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        if ($action === 'create_exam') {
            $courseId = (int)input('course_id');
            if (!teacher_owns_course($courseIds, $courseId)) throw new RuntimeException('You do not have access to that course.');
            $name = input('name'); $examDate = input('exam_date') ?: null;
            $maxScore = input('max_score', 100); $weight = input('weight_percent', 100);
            if (!$name) throw new RuntimeException('Exam name is required.');
            $stmt = $pdo->prepare('INSERT INTO exams (course_id, name, exam_date, max_score, weight_percent) VALUES (?,?,?,?,?)');
            $stmt->execute([$courseId, $name, $examDate, $maxScore, $weight]);
            audit_log($user['id'], 'exam.create', 'exams', $pdo->lastInsertId());
            flash('success', 'Exam created.');
            redirect('/teacher/grades.php?course_id=' . $courseId . '&exam_id=' . $pdo->lastInsertId());
        } elseif ($action === 'save_grades') {
            $examId = (int)input('exam_id');
            $stmt = $pdo->prepare('SELECT co.teacher_id, co.id AS course_id, ex.max_score FROM exams ex JOIN courses co ON co.id=ex.course_id WHERE ex.id=?');
            $stmt->execute([$examId]);
            $exam = $stmt->fetch();
            if (!$exam || $exam['teacher_id'] != $teacherId) throw new RuntimeException('You do not have access to that exam.');

            $scores = $_POST['score'] ?? [];
            $remarks = $_POST['remarks'] ?? [];
            $stmt = $pdo->prepare("INSERT INTO grades (exam_id, student_id, score, grade_letter, remarks, recorded_by)
                                    VALUES (?,?,?,?,?,?)
                                    ON DUPLICATE KEY UPDATE score=VALUES(score), grade_letter=VALUES(grade_letter),
                                                            remarks=VALUES(remarks), recorded_by=VALUES(recorded_by)");
            $notifyStmt = $pdo->prepare('SELECT user_id FROM students WHERE id = ?');
            foreach ($scores as $studentId => $score) {
                if ($score === '') continue;
                $letter = letter_grade((float)$score, (float)$exam['max_score']);
                $stmt->execute([$examId, (int)$studentId, $score, $letter, $remarks[$studentId] ?? null, $user['id']]);
                $notifyStmt->execute([(int)$studentId]);
                if ($row = $notifyStmt->fetch()) {
                    notify($row['user_id'], 'Grade published', "Score: {$score}/{$exam['max_score']}", 'grade', BASE_URL.'/student/grades.php');
                }
            }
            audit_log($user['id'], 'grades.save', 'exams', $examId, 'count=' . count($scores));
            flash('success', 'Grades saved.');
            redirect('/teacher/grades.php?course_id=' . $exam['course_id'] . '&exam_id=' . $examId);
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('/teacher/grades.php');
    }
}

$courseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));
$exams = [];
if (teacher_owns_course($courseIds, $courseId)) {
    $stmt = $pdo->prepare('SELECT * FROM exams WHERE course_id = ? ORDER BY exam_date DESC, id DESC');
    $stmt->execute([$courseId]);
    $exams = $stmt->fetchAll();
}
$examId = (int)($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$exam = null;
foreach ($exams as $ex) if ($ex['id'] == $examId) $exam = $ex;

$roster = [];
if ($exam) {
    $stmt = $pdo->prepare("SELECT s.id AS student_id, u.first_name, u.last_name, s.admission_no, g.score, g.remarks
                            FROM enrollments e JOIN students s ON s.id=e.student_id JOIN users u ON u.id=s.user_id
                            LEFT JOIN grades g ON g.exam_id=? AND g.student_id=s.id
                            WHERE e.course_id=? ORDER BY u.first_name");
    $stmt->execute([$examId, $courseId]);
    $roster = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="flex flex-wrap items-center gap-2 mb-5">
  <form method="GET" class="flex gap-2">
    <select name="course_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
      <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':'' ?>><?= e($c['subject_name'].' — '.$c['class_name'].' '.$c['section']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php foreach ($exams as $ex): ?>
    <a href="?course_id=<?= $courseId ?>&exam_id=<?= $ex['id'] ?>" class="px-3 py-2 rounded-lg text-sm border <?= $examId==$ex['id'] ? 'bg-primary text-white border-primary' : 'border-slate-300 dark:border-slate-600' ?>"><?= e($ex['name']) ?></a>
  <?php endforeach; ?>
  <button onclick="document.getElementById('examModal').classList.remove('hidden')" class="ml-auto bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ New Exam</button>
</div>

<?php if (!$courses): ?>
  <p class="text-slate-400 text-sm">No courses assigned to you yet.</p>
<?php elseif (!$exam): ?>
  <p class="text-slate-400 text-sm">No exams created for this course yet. Click "New Exam" to add one.</p>
<?php else: ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_grades">
    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
    <div class="card overflow-x-auto">
      <table class="data-table w-full">
        <thead><tr><th>Student</th><th>Admission No.</th><th>Score (/ <?= e($exam['max_score']) ?>)</th><th>Remarks</th></tr></thead>
        <tbody>
          <?php foreach ($roster as $r): ?>
            <tr>
              <td class="font-medium"><?= e($r['first_name'].' '.$r['last_name']) ?></td>
              <td><?= e($r['admission_no']) ?></td>
              <td><input type="number" step="0.01" max="<?= e($exam['max_score']) ?>" name="score[<?= $r['student_id'] ?>]" value="<?= e($r['score'] ?? '') ?>" class="w-24 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-2 py-1 text-sm"></td>
              <td><input name="remarks[<?= $r['student_id'] ?>]" value="<?= e($r['remarks'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-2 py-1 text-sm"></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$roster): ?><tr><td colspan="4" class="text-center text-slate-400 py-8">No students enrolled in this course.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($roster): ?><button class="mt-4 bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-5 py-2.5">Save grades</button><?php endif; ?>
  </form>
<?php endif; ?>

<div id="examModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_exam">
      <h3 class="text-lg font-semibold">New Exam</h3>
      <div><label class="text-sm font-medium">Course</label>
        <select required name="course_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= $courseId==$c['id']?'selected':'' ?>><?= e($c['subject_name'].' — '.$c['class_name'].' '.$c['section']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="text-sm font-medium">Exam name</label><input required name="name" placeholder="e.g. Midterm" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-sm font-medium">Date</label><input type="date" name="exam_date" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Max score</label><input type="number" name="max_score" value="100" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Weight %</label><input type="number" name="weight_percent" value="100" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('examModal').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Create</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
