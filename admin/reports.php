<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Reports';

$report = $_GET['report'] ?? 'attendance';
$classId = $_GET['class_id'] ?? '';
$classes = $pdo->query('SELECT id, name, section FROM classes ORDER BY name')->fetchAll();

// --- CSV export -------------------------------------------------------
if (isset($_GET['export'])) {
    $rows = []; $headers = [];
    if ($report === 'attendance') {
        $headers = ['Student', 'Admission No.', 'Class', 'Present', 'Absent', 'Late', 'Excused', 'Attendance %'];
        $sql = "SELECT u.first_name, u.last_name, s.admission_no, c.name AS class_name, c.section,
                SUM(a.status='present') AS present, SUM(a.status='absent') AS absent,
                SUM(a.status='late') AS late, SUM(a.status='excused') AS excused, COUNT(a.id) AS total
                FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN classes c ON c.id=s.class_id
                LEFT JOIN attendance a ON a.student_id = s.id
                " . ($classId ? "WHERE s.class_id = " . (int)$classId : "") . "
                GROUP BY s.id ORDER BY u.first_name";
        foreach ($pdo->query($sql) as $r) {
            $pct = $r['total'] ? round(($r['present'] / $r['total']) * 100, 1) : 0;
            $rows[] = [$r['first_name'].' '.$r['last_name'], $r['admission_no'], trim(($r['class_name']??'').' '.($r['section']??'')), $r['present'], $r['absent'], $r['late'], $r['excused'], $pct.'%'];
        }
    } elseif ($report === 'grades') {
        $headers = ['Student', 'Admission No.', 'Course', 'Exam', 'Score', 'Max Score', 'Grade'];
        $sql = "SELECT u.first_name, u.last_name, s.admission_no, sub.name AS subject_name, ex.name AS exam_name, g.score, ex.max_score, g.grade_letter
                FROM grades g JOIN students s ON s.id=g.student_id JOIN users u ON u.id=s.user_id
                JOIN exams ex ON ex.id=g.exam_id JOIN courses co ON co.id=ex.course_id JOIN subjects sub ON sub.id=co.subject_id
                " . ($classId ? "WHERE co.class_id = " . (int)$classId : "") . "
                ORDER BY u.first_name";
        foreach ($pdo->query($sql) as $r) {
            $rows[] = [$r['first_name'].' '.$r['last_name'], $r['admission_no'], $r['subject_name'], $r['exam_name'], $r['score'], $r['max_score'], $r['grade_letter']];
        }
    } elseif ($report === 'enrollment') {
        $headers = ['Class', 'Academic Year', 'Student Count'];
        foreach ($pdo->query("SELECT c.name, c.section, c.academic_year, COUNT(s.id) cnt FROM classes c LEFT JOIN students s ON s.class_id=c.id GROUP BY c.id") as $r) {
            $rows[] = [$r['name'].' '.$r['section'], $r['academic_year'], $r['cnt']];
        }
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report . '_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    audit_log($user['id'], 'report.export', null, null, $report);
    exit;
}

// --- On-screen preview --------------------------------------------------
$data = [];
if ($report === 'attendance') {
    $sql = "SELECT u.first_name, u.last_name, s.admission_no, c.name AS class_name, c.section,
            SUM(a.status='present') AS present, SUM(a.status='absent') AS absent,
            SUM(a.status='late') AS late, COUNT(a.id) AS total
            FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN classes c ON c.id=s.class_id
            LEFT JOIN attendance a ON a.student_id = s.id
            " . ($classId ? "WHERE s.class_id = " . (int)$classId : "") . "
            GROUP BY s.id ORDER BY u.first_name LIMIT 50";
    $data = $pdo->query($sql)->fetchAll();
} elseif ($report === 'grades') {
    $sql = "SELECT u.first_name, u.last_name, s.admission_no, sub.name AS subject_name, ex.name AS exam_name, g.score, ex.max_score, g.grade_letter
            FROM grades g JOIN students s ON s.id=g.student_id JOIN users u ON u.id=s.user_id
            JOIN exams ex ON ex.id=g.exam_id JOIN courses co ON co.id=ex.course_id JOIN subjects sub ON sub.id=co.subject_id
            " . ($classId ? "WHERE co.class_id = " . (int)$classId : "") . "
            ORDER BY u.first_name LIMIT 50";
    $data = $pdo->query($sql)->fetchAll();
} elseif ($report === 'enrollment') {
    $data = $pdo->query("SELECT c.name, c.section, c.academic_year, COUNT(s.id) cnt FROM classes c LEFT JOIN students s ON s.class_id=c.id GROUP BY c.id")->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="flex flex-wrap items-center gap-2 mb-5">
  <div class="flex rounded-lg border border-slate-200 dark:border-slate-600 overflow-hidden text-sm">
    <?php foreach (['attendance'=>'Attendance','grades'=>'Grades','enrollment'=>'Enrollment'] as $key=>$label): ?>
      <a href="?report=<?= $key ?>&class_id=<?= e($classId) ?>" class="px-4 py-2 <?= $report===$key ? 'bg-primary text-white' : 'bg-white dark:bg-slate-700' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <form method="GET" class="flex gap-2">
    <input type="hidden" name="report" value="<?= e($report) ?>">
    <select name="class_id" onchange="this.form.submit()" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
      <option value="">All Classes</option>
      <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classId==$c['id']?'selected':'' ?>><?= e($c['name'].' '.$c['section']) ?></option><?php endforeach; ?>
    </select>
  </form>
  <a href="?report=<?= $report ?>&class_id=<?= e($classId) ?>&export=csv" class="ml-auto bg-accent hover:opacity-90 text-white text-sm font-medium rounded-lg px-4 py-2">⬇ Export CSV</a>
</div>

<div class="card overflow-x-auto">
  <?php if ($report === 'attendance'): ?>
    <table class="data-table w-full">
      <thead><tr><th>Student</th><th>Admission No.</th><th>Class</th><th>Present</th><th>Absent</th><th>Late</th><th>Attendance %</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): $pct = $r['total'] ? round(($r['present']/$r['total'])*100,1) : 0; ?>
          <tr>
            <td><?= e($r['first_name'].' '.$r['last_name']) ?></td><td><?= e($r['admission_no']) ?></td>
            <td><?= e(trim(($r['class_name']??'').' '.($r['section']??''))) ?></td>
            <td><?= (int)$r['present'] ?></td><td><?= (int)$r['absent'] ?></td><td><?= (int)$r['late'] ?></td>
            <td><span class="badge <?= $pct>=75?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' ?>"><?= $pct ?>%</span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="7" class="text-center text-slate-400 py-8">No data yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php elseif ($report === 'grades'): ?>
    <table class="data-table w-full">
      <thead><tr><th>Student</th><th>Admission No.</th><th>Subject</th><th>Exam</th><th>Score</th><th>Grade</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): ?>
          <tr>
            <td><?= e($r['first_name'].' '.$r['last_name']) ?></td><td><?= e($r['admission_no']) ?></td>
            <td><?= e($r['subject_name']) ?></td><td><?= e($r['exam_name']) ?></td>
            <td><?= $r['score'] !== null ? e($r['score'].' / '.$r['max_score']) : '—' ?></td>
            <td><span class="badge bg-blue-50 text-primary"><?= e($r['grade_letter'] ?: '—') ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No data yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table class="data-table w-full">
      <thead><tr><th>Class</th><th>Academic Year</th><th>Student Count</th></tr></thead>
      <tbody>
        <?php foreach ($data as $r): ?>
          <tr><td><?= e($r['name'].' '.$r['section']) ?></td><td><?= e($r['academic_year']) ?></td><td><?= (int)$r['cnt'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<p class="text-xs text-slate-400 mt-3">Showing up to 50 rows on-screen. Use "Export CSV" for the complete report (open it in Excel or Google Sheets — CSV is universally compatible and avoids adding a heavy PDF/Excel-writing library to this codebase).</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
