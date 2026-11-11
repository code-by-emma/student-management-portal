<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Admin Dashboard';

$totalStudents = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalTeachers = $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
$totalCourses  = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$totalClasses  = $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn();

$today = date('Y-m-d');
$attToday = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE date = ? GROUP BY status");
$attToday->execute([$today]);
$attendanceRows = $attToday->fetchAll();
$attendanceMap = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
foreach ($attendanceRows as $row) { $attendanceMap[$row['status']] = (int)$row['c']; }
$attTotal = array_sum($attendanceMap);
$presentPct = $attTotal ? round(($attendanceMap['present'] / $attTotal) * 100) : 0;

$feesCollected = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE status='success'")->fetchColumn();
$feesOutstanding = (float)$pdo->query("SELECT COALESCE(SUM(sf.amount_due),0) - COALESCE((SELECT SUM(amount) FROM fee_payments WHERE status='success'),0) FROM student_fees sf")->fetchColumn();

$recent = $pdo->query("SELECT al.*, u.first_name, u.last_name FROM audit_logs al
                        LEFT JOIN users u ON u.id = al.user_id
                        ORDER BY al.created_at DESC LIMIT 8")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="card p-5">
    <p class="text-xs font-medium text-slate-500">Total Students</p>
    <p class="text-2xl font-bold mt-1"><?= number_format($totalStudents) ?></p>
    <a href="students.php" class="text-xs text-primary hover:underline mt-2 inline-block">View all →</a>
  </div>
  <div class="card p-5">
    <p class="text-xs font-medium text-slate-500">Total Teachers</p>
    <p class="text-2xl font-bold mt-1"><?= number_format($totalTeachers) ?></p>
    <a href="teachers.php" class="text-xs text-primary hover:underline mt-2 inline-block">View all →</a>
  </div>
  <div class="card p-5">
    <p class="text-xs font-medium text-slate-500">Total Courses</p>
    <p class="text-2xl font-bold mt-1"><?= number_format($totalCourses) ?></p>
    <a href="courses.php" class="text-xs text-primary hover:underline mt-2 inline-block">View all →</a>
  </div>
  <div class="card p-5">
    <p class="text-xs font-medium text-slate-500">Total Classes</p>
    <p class="text-2xl font-bold mt-1"><?= number_format($totalClasses) ?></p>
    <a href="classes.php" class="text-xs text-primary hover:underline mt-2 inline-block">View all →</a>
  </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
  <div class="card p-5">
    <p class="text-xs font-medium text-slate-500">Fees Collected</p>
    <p class="text-2xl font-bold mt-1 text-emerald-600"><?= money($feesCollected) ?></p>
    <a href="fees.php?tab=payments" class="text-xs text-primary hover:underline mt-2 inline-block">View ledger →</a>
  </div>
  <div class="card p-5">
    <p class="text-xs font-medium text-slate-500">Fees Outstanding</p>
    <p class="text-2xl font-bold mt-1 text-rose-500"><?= money(max(0, $feesOutstanding)) ?></p>
    <a href="fees.php" class="text-xs text-primary hover:underline mt-2 inline-block">Manage fees →</a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 card p-5">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-semibold text-slate-800 dark:text-white">Recent Activity</h2>
      <a href="audit-logs.php" class="text-xs text-primary hover:underline">View all logs →</a>
    </div>
    <ul class="space-y-3">
      <?php if (!$recent): ?>
        <li class="text-sm text-slate-400">No activity recorded yet.</li>
      <?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <li class="flex items-start gap-3 text-sm">
          <span class="mt-1 h-2 w-2 rounded-full bg-primary shrink-0"></span>
          <div>
            <span class="font-medium"><?= e(trim(($r['first_name'] ?? 'System') . ' ' . ($r['last_name'] ?? ''))) ?></span>
            <span class="text-slate-500"><?= e(str_replace('.', ' ', $r['action'])) ?></span>
            <span class="text-slate-400 block text-xs"><?= time_ago($r['created_at']) ?></span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card p-5">
    <h2 class="font-semibold text-slate-800 dark:text-white mb-4">Today's Attendance</h2>
    <div class="flex items-center justify-center">
      <div class="relative h-32 w-32">
        <svg viewBox="0 0 36 36" class="h-32 w-32 -rotate-90">
          <path class="text-slate-100 dark:text-slate-700" stroke="currentColor" stroke-width="3.5" fill="none" d="M18 2.0845a15.9155 15.9155 0 010 31.831 15.9155 15.9155 0 010-31.831"/>
          <path class="text-accent" stroke="currentColor" stroke-width="3.5" fill="none" stroke-dasharray="<?= $presentPct ?>, 100" stroke-linecap="round" d="M18 2.0845a15.9155 15.9155 0 010 31.831 15.9155 15.9155 0 010-31.831"/>
        </svg>
        <div class="absolute inset-0 flex items-center justify-center flex-col">
          <span class="text-xl font-bold"><?= $presentPct ?>%</span>
          <span class="text-[10px] text-slate-400">present</span>
        </div>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-2 mt-4 text-xs">
      <div class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-accent"></span> Present: <?= $attendanceMap['present'] ?></div>
      <div class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rose-400"></span> Absent: <?= $attendanceMap['absent'] ?></div>
      <div class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span> Late: <?= $attendanceMap['late'] ?></div>
      <div class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-sky-400"></span> Excused: <?= $attendanceMap['excused'] ?></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
