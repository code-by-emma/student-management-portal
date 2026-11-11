<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Courses';

$subjects = $pdo->query('SELECT id, name, code FROM subjects ORDER BY name')->fetchAll();
$classes  = $pdo->query('SELECT id, name, section FROM classes ORDER BY name')->fetchAll();
$teachers = $pdo->query("SELECT t.id, u.first_name, u.last_name FROM teachers t JOIN users u ON u.id=t.user_id ORDER BY u.first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        if ($action === 'add_subject') {
            $name = input('subject_name'); $code = input('subject_code');
            if (!$name || !$code) throw new RuntimeException('Subject name and code are required.');
            $pdo->prepare('INSERT INTO subjects (name, code) VALUES (?,?)')->execute([$name, $code]);
            audit_log($user['id'], 'subject.create', 'subjects', $pdo->lastInsertId());
            flash('success', 'Subject added.');
        } else {
            $subjectId = input('subject_id'); $classId = input('class_id');
            $teacherId = input('teacher_id') ?: null; $term = input('term');
            $year = input('academic_year'); $desc = input('description');

            if ($action === 'create') {
                if (!$subjectId || !$classId || !$year) throw new RuntimeException('Subject, class, and academic year are required.');
                $pdo->prepare('INSERT INTO courses (subject_id, class_id, teacher_id, term, academic_year, description) VALUES (?,?,?,?,?,?)')
                    ->execute([$subjectId, $classId, $teacherId, $term, $year, $desc]);
                $courseId = $pdo->lastInsertId();

                // Auto-enroll every student currently in that class.
                $pdo->prepare('INSERT INTO enrollments (student_id, course_id)
                                SELECT id, ? FROM students WHERE class_id = ?')
                    ->execute([$courseId, $classId]);

                audit_log($user['id'], 'course.create', 'courses', $courseId);
                flash('success', 'Course created and students auto-enrolled.');
            } elseif ($action === 'update') {
                $courseId = (int)input('course_id');
                $pdo->prepare('UPDATE courses SET subject_id=?, class_id=?, teacher_id=?, term=?, academic_year=?, description=? WHERE id=?')
                    ->execute([$subjectId, $classId, $teacherId, $term, $year, $desc, $courseId]);
                audit_log($user['id'], 'course.update', 'courses', $courseId);
                flash('success', 'Course updated.');
            } elseif ($action === 'delete') {
                $courseId = (int)input('course_id');
                $pdo->prepare('DELETE FROM courses WHERE id=?')->execute([$courseId]);
                audit_log($user['id'], 'course.delete', 'courses', $courseId);
                flash('success', 'Course deleted.');
            }
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . (str_contains($e->getMessage(),'Duplicate') ? 'that subject code already exists.' : 'please try again.'));
    }
    redirect('/admin/courses.php');
}

$courses = $pdo->query("SELECT co.*, s.name AS subject_name, s.code, c.name AS class_name, c.section,
                        u.first_name AS t_first, u.last_name AS t_last,
                        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = co.id) AS enrolled_count
                        FROM courses co
                        JOIN subjects s ON s.id = co.subject_id
                        JOIN classes c ON c.id = co.class_id
                        LEFT JOIN teachers t ON t.id = co.teacher_id
                        LEFT JOIN users u ON u.id = t.user_id
                        ORDER BY co.academic_year DESC, s.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="flex justify-end gap-2 mb-5">
  <button onclick="document.getElementById('subjectModal').classList.remove('hidden')" class="bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg px-4 py-2">+ Add Subject</button>
  <button onclick="openModal()" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ Add Course</button>
</div>

<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Subject</th><th>Class</th><th>Teacher</th><th>Term / Year</th><th>Enrolled</th><th></th></tr></thead>
    <tbody>
      <?php if (!$courses): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No courses yet.</td></tr><?php endif; ?>
      <?php foreach ($courses as $c): ?>
        <tr>
          <td><span class="font-medium"><?= e($c['subject_name']) ?></span><div class="text-xs text-slate-400"><?= e($c['code']) ?></div></td>
          <td><?= e($c['class_name'].' '.$c['section']) ?></td>
          <td><?= $c['t_first'] ? e($c['t_first'].' '.$c['t_last']) : '<span class="text-slate-400">Unassigned</span>' ?></td>
          <td><?= e(($c['term'] ?: '—').' · '.$c['academic_year']) ?></td>
          <td><?= (int)$c['enrolled_count'] ?></td>
          <td class="text-right whitespace-nowrap">
            <button onclick='openModal(<?= json_encode($c, JSON_HEX_APOS) ?>)' class="text-primary hover:underline text-xs font-medium">Edit</button>
            <form method="POST" class="inline" data-confirm="Delete this course?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= $c['id'] ?>">
              <button class="text-rose-500 hover:underline text-xs font-medium ml-2">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Course Modal -->
<div id="modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="course_id" id="courseId">
      <h3 class="text-lg font-semibold" id="modalTitle">Add Course</h3>
      <div><label class="text-sm font-medium">Subject</label>
        <select required name="subject_id" id="f_subject_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name'].' ('.$s['code'].')') ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="text-sm font-medium">Class</label>
        <select required name="class_id" id="f_class_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name'].' '.$c['section']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="text-sm font-medium">Teacher</label>
        <select name="teacher_id" id="f_teacher_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <option value="">Unassigned</option>
          <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['first_name'].' '.$t['last_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Term</label><input name="term" id="f_term" placeholder="Term 1" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Academic year</label><input required name="academic_year" id="f_academic_year" placeholder="2026/2027" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div><label class="text-sm font-medium">Description</label><textarea name="description" id="f_description" rows="2" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></textarea></div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Subject Modal -->
<div id="subjectModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_subject">
      <h3 class="text-lg font-semibold">Add Subject</h3>
      <div><label class="text-sm font-medium">Name</label><input required name="subject_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1" placeholder="e.g. Mathematics"></div>
      <div><label class="text-sm font-medium">Code</label><input required name="subject_code" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1" placeholder="e.g. MATH101"></div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('subjectModal').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(data) {
  const fields = ['subject_id','class_id','teacher_id','term','academic_year','description'];
  fields.forEach(f => { const el = document.getElementById('f_'+f); if (el) el.value = ''; });
  if (data) {
    document.getElementById('modalTitle').textContent = 'Edit Course';
    document.getElementById('formAction').value = 'update';
    document.getElementById('courseId').value = data.id;
    document.getElementById('f_subject_id').value = data.subject_id;
    document.getElementById('f_class_id').value = data.class_id;
    document.getElementById('f_teacher_id').value = data.teacher_id || '';
    document.getElementById('f_term').value = data.term || '';
    document.getElementById('f_academic_year').value = data.academic_year || '';
    document.getElementById('f_description').value = data.description || '';
  } else {
    document.getElementById('modalTitle').textContent = 'Add Course';
    document.getElementById('formAction').value = 'create';
    document.getElementById('courseId').value = '';
  }
  document.getElementById('modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('modal').classList.add('hidden'); }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
