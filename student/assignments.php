<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('student');
$pageTitle = 'Assignments';
$studentId = $user['linked_id'];

// Ownership helper: is this student enrolled in the assignment's course?
function student_owns_assignment(PDO $pdo, int $studentId, int $assignmentId): ?array {
    $stmt = $pdo->prepare("SELECT a.* FROM assignments a
                            JOIN enrollments e ON e.course_id = a.course_id
                            WHERE a.id = ? AND e.student_id = ?");
    $stmt->execute([$assignmentId, $studentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $assignmentId = (int)input('assignment_id');
    $assignment = student_owns_assignment($pdo, $studentId, $assignmentId);
    try {
        if (!$assignment) throw new RuntimeException('You do not have access to that assignment.');
        $text = input('submission_text');
        $filePath = handle_upload('submission_file', 'assignments');
        if (!$text && !$filePath) throw new RuntimeException('Add a note or attach a file before submitting.');

        $stmt = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, submission_text)
                                VALUES (?,?,?,?)
                                ON DUPLICATE KEY UPDATE file_path = COALESCE(VALUES(file_path), file_path),
                                                        submission_text = VALUES(submission_text),
                                                        submitted_at = NOW()");
        $stmt->execute([$assignmentId, $studentId, $filePath, $text]);
        audit_log($user['id'], 'assignment.submit', 'assignments', $assignmentId);
        flash('success', 'Submission saved.');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/student/assignments.php');
}

$assignments = $pdo->prepare("SELECT a.id, a.title, a.description, a.file_path AS attachment, a.due_date, a.max_score,
                              sub.name AS subject_name,
                              s.id AS submission_id, s.file_path AS my_file, s.submission_text, s.submitted_at, s.score, s.feedback
                              FROM assignments a
                              JOIN courses co ON co.id = a.course_id
                              JOIN subjects sub ON sub.id = co.subject_id
                              JOIN enrollments e ON e.course_id = a.course_id
                              LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
                              WHERE e.student_id = ? ORDER BY a.due_date IS NULL, a.due_date ASC");
$assignments->execute([$studentId]);
$assignments = $assignments->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="space-y-4">
  <?php foreach ($assignments as $a): ?>
    <?php
      $overdue = $a['due_date'] && strtotime($a['due_date']) < time() && !$a['submission_id'];
      $graded = $a['score'] !== null;
    ?>
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 class="font-semibold"><?= e($a['title']) ?></h3>
          <p class="text-xs text-slate-400"><?= e($a['subject_name']) ?> · Due <?= $a['due_date'] ? e(date('M j, Y g:i a', strtotime($a['due_date']))) : 'no due date' ?></p>
        </div>
        <?php if ($graded): ?>
          <span class="badge bg-emerald-100 text-emerald-700"><?= e($a['score']) ?> / <?= e($a['max_score']) ?></span>
        <?php elseif ($a['submission_id']): ?>
          <span class="badge bg-blue-50 text-primary">Submitted — awaiting grade</span>
        <?php elseif ($overdue): ?>
          <span class="badge bg-rose-100 text-rose-700">Overdue</span>
        <?php else: ?>
          <span class="badge bg-amber-100 text-amber-700">Not submitted</span>
        <?php endif; ?>
      </div>
      <p class="text-sm text-slate-500 mt-2"><?= e($a['description']) ?></p>
      <?php if ($a['attachment']): ?>
        <a href="<?= BASE_URL ?>/uploads/<?= e($a['attachment']) ?>" target="_blank" class="text-xs text-primary hover:underline">📎 Assignment brief</a>
      <?php endif; ?>

      <?php if ($a['feedback']): ?>
        <div class="mt-3 text-sm bg-slate-50 dark:bg-slate-700/40 rounded-lg p-3">
          <p class="text-xs font-medium text-slate-500 mb-1">Teacher feedback</p>
          <?= nl2br(e($a['feedback'])) ?>
        </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="mt-4 border-t border-slate-100 dark:border-slate-700 pt-4 space-y-2">
        <?= csrf_field() ?>
        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
        <textarea name="submission_text" rows="2" placeholder="Add a note (optional)" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm"><?= e($a['submission_text'] ?? '') ?></textarea>
        <div class="flex items-center justify-between gap-3">
          <input type="file" name="submission_file" class="text-sm">
          <button class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2 shrink-0">
            <?= $a['submission_id'] ? 'Update submission' : 'Submit' ?>
          </button>
        </div>
        <?php if ($a['my_file']): ?>
          <a href="<?= BASE_URL ?>/uploads/<?= e($a['my_file']) ?>" target="_blank" class="text-xs text-primary hover:underline">📎 View your submitted file</a>
        <?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$assignments): ?><p class="text-slate-400 text-sm text-center py-8">No assignments posted yet.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
