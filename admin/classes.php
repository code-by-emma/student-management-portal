<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Classes';

$teachers = $pdo->query("SELECT t.id, u.first_name, u.last_name FROM teachers t JOIN users u ON u.id=t.user_id ORDER BY u.first_name")->fetchAll();
$gradeLevels = $pdo->query('SELECT id, name FROM grade_levels ORDER BY display_order, name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        $gradeLevelId = (int)input('grade_level_id') ?: null; $section = input('section');
        $year = input('academic_year'); $teacherId = input('class_teacher_id') ?: null;

        // The grade level is the source of truth for the class's display
        // name — look its text up server-side rather than trusting a name
        // the client might submit, and so every other page that still reads
        // classes.name directly (courses, timetable, reports, dashboards)
        // keeps working unchanged.
        $gradeLevelName = null;
        if ($gradeLevelId) {
            $stmt = $pdo->prepare('SELECT name FROM grade_levels WHERE id=?');
            $stmt->execute([$gradeLevelId]);
            $gradeLevelName = $stmt->fetchColumn() ?: null;
        }
        if (!$gradeLevelName) throw new RuntimeException('Please choose a valid grade level.');

        if ($action === 'create') {
            if (!$year) throw new RuntimeException('Academic year is required.');
            $pdo->prepare('INSERT INTO classes (name, grade_level_id, section, academic_year, class_teacher_id) VALUES (?,?,?,?,?)')
                ->execute([$gradeLevelName, $gradeLevelId, $section, $year, $teacherId]);
            audit_log($user['id'], 'class.create', 'classes', $pdo->lastInsertId());
            flash('success', 'Class created.');
        } elseif ($action === 'update') {
            $classId = (int)input('class_id');
            $pdo->prepare('UPDATE classes SET name=?, grade_level_id=?, section=?, academic_year=?, class_teacher_id=? WHERE id=?')
                ->execute([$gradeLevelName, $gradeLevelId, $section, $year, $teacherId, $classId]);
            audit_log($user['id'], 'class.update', 'classes', $classId);
            flash('success', 'Class updated.');
        } elseif ($action === 'delete') {
            $classId = (int)input('class_id');
            $pdo->prepare('DELETE FROM classes WHERE id=?')->execute([$classId]);
            audit_log($user['id'], 'class.delete', 'classes', $classId);
            flash('success', 'Class deleted.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        flash('error', 'Could not save class. It may still have students or courses linked to it.');
    }
    redirect('/admin/classes.php');
}

$classes = $pdo->query("SELECT c.*, u.first_name AS t_first, u.last_name AS t_last,
                        g.display_order AS grade_order,
                        (SELECT COUNT(*) FROM students s WHERE s.class_id=c.id) AS student_count,
                        (SELECT COUNT(*) FROM courses co WHERE co.class_id=c.id) AS course_count
                        FROM classes c
                        LEFT JOIN teachers t ON t.id = c.class_teacher_id
                        LEFT JOIN users u ON u.id = t.user_id
                        LEFT JOIN grade_levels g ON g.id = c.grade_level_id
                        ORDER BY c.academic_year DESC, g.display_order IS NULL, g.display_order, c.section")->fetchAll();

// Group sections under their grade level for the "Grade 1: 1A, 1B" style display.
$grouped = [];
foreach ($classes as $c) {
    $groupKey = $c['name']; // grade level name (or legacy free-text name if never linked)
    $grouped[$groupKey][] = $c;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="flex items-center justify-between mb-5">
  <p class="text-sm text-slate-500">Sections are grouped under their grade level — e.g. "Grade 1: 1A, 1B". Manage the grade level list itself on the <a href="grade-levels.php" class="text-primary hover:underline">Grade Levels</a> page.</p>
  <button onclick="openModal()" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2 shrink-0">+ Add Class</button>
</div>

<?php if (!$classes): ?>
  <p class="text-slate-400 text-sm text-center py-8">No classes yet.</p>
<?php endif; ?>

<?php foreach ($grouped as $gradeName => $sections): ?>
  <div class="mb-6">
    <h2 class="font-semibold text-slate-800 dark:text-white mb-3"><?= e($gradeName) ?></h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($sections as $c): ?>
        <div class="card p-5">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="font-semibold"><?= e($gradeName . ($c['section'] ? $c['section'] : '')) ?></h3>
              <p class="text-xs text-slate-400"><?= e($c['academic_year']) ?></p>
            </div>
            <span class="badge bg-blue-50 text-primary"><?= (int)$c['student_count'] ?> students</span>
          </div>
          <p class="text-sm text-slate-500 mt-3">Class teacher: <?= $c['t_first'] ? e($c['t_first'].' '.$c['t_last']) : '—' ?></p>
          <p class="text-sm text-slate-500">Courses: <?= (int)$c['course_count'] ?></p>
          <div class="flex justify-end gap-3 mt-4 text-xs">
            <a href="timetable.php?class_id=<?= $c['id'] ?>" class="text-primary hover:underline font-medium">Timetable</a>
            <button onclick='openModal(<?= json_encode($c, JSON_HEX_APOS) ?>)' class="text-primary hover:underline font-medium">Edit</button>
            <form method="POST" data-confirm="Delete this class?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="class_id" value="<?= $c['id'] ?>">
              <button class="text-rose-500 hover:underline font-medium">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<div id="modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="class_id" id="classId">
      <h3 class="text-lg font-semibold" id="modalTitle">Add Class</h3>
      <div><label class="text-sm font-medium">Grade level</label>
        <select required name="grade_level_id" id="f_grade_level_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <option value="">— Select —</option>
          <?php foreach ($gradeLevels as $gl): ?><option value="<?= $gl['id'] ?>"><?= e($gl['name']) ?></option><?php endforeach; ?>
        </select>
        <?php if (!$gradeLevels): ?><p class="text-xs text-rose-500 mt-1">No grade levels exist yet — <a href="grade-levels.php" class="underline">add one first</a>.</p><?php endif; ?>
      </div>
      <div><label class="text-sm font-medium">Section</label><input name="section" id="f_section" placeholder="e.g. A (so this becomes '1A')" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Academic year</label><input required name="academic_year" id="f_academic_year" placeholder="2026/2027" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Class teacher</label>
        <select name="class_teacher_id" id="f_class_teacher_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <option value="">Unassigned</option>
          <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['first_name'].' '.$t['last_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(data) {
  const fields = ['grade_level_id','section','academic_year','class_teacher_id'];
  fields.forEach(f => { const el = document.getElementById('f_'+f); if (el) el.value = ''; });
  if (data) {
    document.getElementById('modalTitle').textContent = 'Edit Class';
    document.getElementById('formAction').value = 'update';
    document.getElementById('classId').value = data.id;
    fields.forEach(f => { const el = document.getElementById('f_'+f); if (el) el.value = data[f] || ''; });
  } else {
    document.getElementById('modalTitle').textContent = 'Add Class';
    document.getElementById('formAction').value = 'create';
    document.getElementById('classId').value = '';
  }
  document.getElementById('modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('modal').classList.add('hidden'); }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
