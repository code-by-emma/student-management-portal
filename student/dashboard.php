<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('student');
$pageTitle = 'Student Dashboard';
$studentId = $user['linked_id'];

$courseCount = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
$courseCount->execute([$studentId]);
$courseCount = (int)$courseCount->fetchColumn();

$attendancePct = $pdo->prepare("SELECT
    SUM(status='present') AS present, COUNT(*) AS total
    FROM attendance WHERE student_id = ?");
$attendancePct->execute([$studentId]);
$att = $attendancePct->fetch();
$attPct = $att && $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100, 1) : null;

$pendingAssignments = $pdo->prepare("SELECT COUNT(*) FROM assignments a
    JOIN enrollments e ON e.course_id = a.course_id
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
    WHERE e.student_id = ? AND s.id IS NULL AND (a.due_date IS NULL OR a.due_date >= NOW())");
$pendingAssignments->execute([$studentId]);
$pendingAssignments = (int)$pendingAssignments->fetchColumn();

$upcoming = $pdo->prepare("SELECT a.id, a.title, a.due_date, sub.name AS subject_name
    FROM assignments a JOIN courses co ON co.id = a.course_id JOIN subjects sub ON sub.id = co.subject_id
    JOIN enrollments e ON e.course_id = a.course_id
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
    WHERE e.student_id = ? AND s.id IS NULL
    ORDER BY a.due_date IS NULL, a.due_date ASC LIMIT 5");
$upcoming->execute([$studentId]);
$upcoming = $upcoming->fetchAll();

$today = date('l');
$todaysClasses = $pdo->prepare("SELECT tt.start_time, tt.end_time, tt.room, sub.name AS subject_name
    FROM timetables tt JOIN courses co ON co.id = tt.course_id JOIN subjects sub ON sub.id = co.subject_id
    JOIN enrollments e ON e.course_id = tt.course_id
    WHERE e.student_id = ? AND tt.day_of_week = ? ORDER BY tt.start_time");
$todaysClasses->execute([$studentId, $today]);
$todaysClasses = $todaysClasses->fetchAll();

$recentAnnouncements = $pdo->query("SELECT title, body, created_at FROM announcements
    WHERE audience IN ('all','student') ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Two independent sums, not one joined query — a LEFT JOIN would multiply
// amount_due once per payment row on a fee with more than one payment.
$feeDue = $pdo->prepare("SELECT COALESCE(SUM(amount_due),0) FROM student_fees WHERE student_id = ?");
$feeDue->execute([$studentId]);
$feePaid = $pdo->prepare("SELECT COALESCE(SUM(fp.amount),0) FROM fee_payments fp
    JOIN student_fees sf ON sf.id = fp.student_fee_id
    WHERE sf.student_id = ? AND fp.status = 'success'");
$feePaid->execute([$studentId]);
$feeBalance = max(0, (float)$feeDue->fetchColumn() - (float)$feePaid->fetchColumn());

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Enrolled Courses</p><p class="text-2xl font-bold mt-1"><?= $courseCount ?></p></div>
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Attendance Rate</p><p class="text-2xl font-bold mt-1"><?= $attPct !== null ? $attPct.'%' : '—' ?></p></div>
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Pending Assignments</p><p class="text-2xl font-bold mt-1 text-amber-500"><?= $pendingAssignments ?></p><a href="assignments.php" class="text-xs text-primary hover:underline">View →</a></div>
  <div class="card p-5"><p class="text-xs font-medium text-slate-500">Fee Balance</p><p class="text-2xl font-bold mt-1 <?= $feeBalance > 0 ? 'text-rose-500' : 'text-emerald-600' ?>"><?= money($feeBalance) ?></p><a href="fees.php" class="text-xs text-primary hover:underline">View →</a></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-5">
    <h2 class="font-semibold mb-4">Upcoming Assignments</h2>
    <table class="data-table w-full">
      <thead><tr><th>Title</th><th>Subject</th><th>Due</th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $a): ?>
          <tr>
            <td class="font-medium"><a href="assignments.php" class="hover:underline"><?= e($a['title']) ?></a></td>
            <td><?= e($a['subject_name']) ?></td>
            <td><?= $a['due_date'] ? e(date('M j, Y g:i a', strtotime($a['due_date']))) : 'No due date' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$upcoming): ?><tr><td colspan="3" class="text-center text-slate-400 py-8">Nothing outstanding — nice work!</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="space-y-6">
    <div class="card p-5">
      <h2 class="font-semibold mb-4">Today's Schedule (<?= e($today) ?>)</h2>
      <ul class="space-y-3">
        <?php foreach ($todaysClasses as $t): ?>
          <li class="text-sm border-l-2 border-primary pl-3">
            <p class="font-medium"><?= e($t['subject_name']) ?></p>
            <p class="text-slate-400 text-xs"><?= e(substr($t['start_time'],0,5).' – '.substr($t['end_time'],0,5)) ?><?= $t['room'] ? ' · Room '.e($t['room']) : '' ?></p>
          </li>
        <?php endforeach; ?>
        <?php if (!$todaysClasses): ?><li class="text-sm text-slate-400">No classes scheduled today.</li><?php endif; ?>
      </ul>
    </div>

    <div class="card p-5">
      <h2 class="font-semibold mb-4">Announcements</h2>
      <ul class="space-y-3">
        <?php foreach ($recentAnnouncements as $an): ?>
          <li class="text-sm">
            <p class="font-medium"><?= e($an['title']) ?></p>
            <p class="text-slate-400 text-xs"><?= time_ago($an['created_at']) ?></p>
          </li>
        <?php endforeach; ?>
        <?php if (!$recentAnnouncements): ?><li class="text-sm text-slate-400">No announcements yet.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
