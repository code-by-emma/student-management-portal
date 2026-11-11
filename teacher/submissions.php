<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('teacher');
$teacherId = $user['linked_id'];

$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$stmt = $pdo->prepare("SELECT a.*, co.teacher_id, sub.name AS subject_name FROM assignments a
                        JOIN courses co ON co.id=a.course_id JOIN subjects sub ON sub.id=co.subject_id WHERE a.id=?");
$stmt->execute([$assignmentId]);
$assignment = $stmt->fetch();
if (!$assignment || $assignment['teacher_id'] != $teacherId) { flash('error', 'Assignment not found.'); redirect('/teacher/assignments.php'); }

$pageTitle = 'Grade: ' . $assignment['title'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submissionId = (int)input('submission_id');
    $score = input('score');
    $feedback = input('feedback');
    $stmt = $pdo->prepare('UPDATE assignment_submissions SET score=?, feedback=?, graded_by=?, graded_at=NOW()
                            WHERE id=? AND assignment_id=?');
    $stmt->execute([$score !== '' ? $score : null, $feedback, $user['id'], $submissionId, $assignmentId]);

    $s = $pdo->prepare('SELECT st.user_id FROM assignment_submissions asu JOIN students st ON st.id=asu.student_id WHERE asu.id=?');
    $s->execute([$submissionId]);
    if ($row = $s->fetch()) {
        notify($row['user_id'], 'Assignment graded', $assignment['title'] . ': ' . $score . '/' . $assignment['max_score'], 'grade', BASE_URL.'/student/assignments.php');
    }
    audit_log($user['id'], 'assignment.grade', 'assignment_submissions', $submissionId);
    flash('success', 'Grade saved.');
    redirect('/teacher/submissions.php?assignment_id=' . $assignmentId);
}

$roster = $pdo->prepare("SELECT s.id AS student_id, u.first_name, u.last_name, s.admission_no,
                          asu.id AS submission_id, asu.file_path, asu.submission_text, asu.submitted_at, asu.score, asu.feedback
                          FROM enrollments e JOIN students s ON s.id=e.student_id JOIN users u ON u.id=s.user_id
                          LEFT JOIN assignment_submissions asu ON asu.student_id=s.id AND asu.assignment_id=?
                          WHERE e.course_id=? ORDER BY u.first_name");
$roster->execute([$assignmentId, $assignment['course_id']]);
$roster = $roster->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<a href="assignments.php" class="text-sm text-primary hover:underline">← Back to Assignments</a>
<div class="card mt-4 overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Student</th><th>Submission</th><th>Submitted</th><th>Score</th><th>Feedback</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($roster as $r): ?>
        <tr>
          <td><?= e($r['first_name'].' '.$r['last_name']) ?><div class="text-xs text-slate-400"><?= e($r['admission_no']) ?></div></td>
          <td>
            <?php if ($r['file_path']): ?><a href="<?= BASE_URL ?>/uploads/<?= e($r['file_path']) ?>" target="_blank" class="text-primary hover:underline text-xs">📎 View file</a><?php endif; ?>
            <?php if ($r['submission_text']): ?><p class="text-xs text-slate-500 mt-1 max-w-xs truncate"><?= e($r['submission_text']) ?></p><?php endif; ?>
            <?php if (!$r['submission_id']): ?><span class="text-xs text-slate-400">Not submitted</span><?php endif; ?>
          </td>
          <td class="text-xs"><?= $r['submitted_at'] ? e(date('M j, g:i a', strtotime($r['submitted_at']))) : '—' ?></td>
          <?php if ($r['submission_id']): ?>
            <form method="POST">
              <?= csrf_field() ?><input type="hidden" name="submission_id" value="<?= $r['submission_id'] ?>">
              <td><input type="number" step="0.01" name="score" value="<?= e($r['score'] ?? '') ?>" max="<?= $assignment['max_score'] ?>" class="w-20 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-2 py-1 text-sm"> / <?= $assignment['max_score'] ?></td>
              <td><input name="feedback" value="<?= e($r['feedback'] ?? '') ?>" class="w-40 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-2 py-1 text-sm"></td>
              <td><button class="text-primary hover:underline text-xs font-medium">Save</button></td>
            </form>
          <?php else: ?>
            <td class="text-slate-400 text-xs">—</td><td class="text-slate-400 text-xs">—</td><td></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
