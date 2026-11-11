<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Students';

$classes = $pdo->query('SELECT id, name, section FROM classes ORDER BY name')->fetchAll();

// ---------------------------------------------------------------
// Handle form submissions (create / update / delete)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');

    try {
        if ($action === 'create' || $action === 'update') {
            $firstName = input('first_name');
            $lastName  = input('last_name');
            $email     = input('email');
            $username  = input('username');
            $admissionNo = input('admission_no');
            $classId   = input('class_id') ?: null;
            $dob       = input('date_of_birth') ?: null;
            $gender    = input('gender') ?: null;
            $address   = input('address');
            $guardianName  = input('guardian_name');
            $guardianPhone = input('guardian_phone');
            $phone     = input('phone');

            if ($firstName === '' || $lastName === '' || $email === '' || $username === '' || $admissionNo === '') {
                throw new RuntimeException('Please fill in all required fields.');
            }

            if ($action === 'create') {
                $tempPassword = bin2hex(random_bytes(4)); // e.g. "a1b2c3d4"
                $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, username, password_hash, phone)
                                        VALUES ((SELECT id FROM roles WHERE name="student"), ?,?,?,?,?,?)');
                $stmt->execute([$firstName, $lastName, $email, $username, $hash, $phone]);
                $userId = $pdo->lastInsertId();

                $picPath = handle_upload('profile_picture', 'profile_pics', ALLOWED_IMAGE_EXT);
                if ($picPath) {
                    $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?')->execute([$picPath, $userId]);
                }

                $stmt = $pdo->prepare('INSERT INTO students (user_id, admission_no, class_id, date_of_birth, gender, address, guardian_name, guardian_phone, enrollment_date)
                                        VALUES (?,?,?,?,?,?,?,?, CURDATE())');
                $stmt->execute([$userId, $admissionNo, $classId, $dob, $gender, $address, $guardianName, $guardianPhone]);
                $pdo->commit();

                notify($userId, 'Welcome to SMP', "Your account was created. Username: {$username}, temporary password: {$tempPassword}", 'account');
                audit_log($user['id'], 'student.create', 'students', $pdo->lastInsertId(), $email);
                flash('success', "Student added. Temporary password: {$tempPassword} (share this with the student).");
            } else {
                $studentId = (int)input('student_id');
                $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = ?');
                $stmt->execute([$studentId]);
                $row = $stmt->fetch();
                if (!$row) throw new RuntimeException('Student not found.');

                $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, username=?, phone=? WHERE id=?')
                    ->execute([$firstName, $lastName, $email, $username, $phone, $row['user_id']]);

                $picPath = handle_upload('profile_picture', 'profile_pics', ALLOWED_IMAGE_EXT);
                if ($picPath) {
                    $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?')->execute([$picPath, $row['user_id']]);
                }

                $pdo->prepare('UPDATE students SET admission_no=?, class_id=?, date_of_birth=?, gender=?, address=?, guardian_name=?, guardian_phone=? WHERE id=?')
                    ->execute([$admissionNo, $classId, $dob, $gender, $address, $guardianName, $guardianPhone, $studentId]);

                audit_log($user['id'], 'student.update', 'students', $studentId);
                flash('success', 'Student updated successfully.');
            }
        } elseif ($action === 'delete') {
            $studentId = (int)input('student_id');
            $stmt = $pdo->prepare('SELECT user_id FROM students WHERE id = ?');
            $stmt->execute([$studentId]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$row['user_id']]); // cascades to students
                audit_log($user['id'], 'student.delete', 'students', $studentId);
                flash('success', 'Student removed.');
            }
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = str_contains($e->getMessage(), 'Duplicate') ? 'That email, username, or admission number is already in use.' : 'Database error. Please try again.';
        flash('error', $msg);
    }
    redirect('/admin/students.php' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
}

// ---------------------------------------------------------------
// Build listing query (search + class filter + pagination)
// ---------------------------------------------------------------
$search  = trim($_GET['q'] ?? '');
$classFilter = $_GET['class_id'] ?? '';
[$perPage, $offset, $page] = paginate(10);

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.admission_no LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
if ($classFilter !== '') {
    $where[] = 's.class_id = ?';
    $params[] = $classFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

$sql = "SELECT s.*, u.first_name, u.last_name, u.email, u.username, u.phone, u.profile_picture, u.status,
               c.name AS class_name, c.section AS class_section
        FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN classes c ON c.id = s.class_id
        $whereSql
        ORDER BY u.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, admission no…" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-primary">
    <select name="class_id" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm">
      <option value="">All Classes</option>
      <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $classFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name'] . ' ' . $c['section']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 px-3 py-2 text-sm">Filter</button>
  </form>
  <button onclick="openStudentModal()" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ Add Student</button>
</div>

<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Student</th><th>Admission No.</th><th>Class</th><th>Contact</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$students): ?>
        <tr><td colspan="6" class="text-center text-slate-400 py-8">No students found.</td></tr>
      <?php endif; ?>
      <?php foreach ($students as $s): ?>
        <tr>
          <td class="flex items-center gap-3">
            <img src="<?= $s['profile_picture'] ? BASE_URL.'/uploads/'.e($s['profile_picture']) : 'https://ui-avatars.com/api/?background=2563EB&color=fff&name='.urlencode($s['first_name'].' '.$s['last_name']) ?>" class="h-9 w-9 rounded-full object-cover">
            <div>
              <a href="student-view.php?id=<?= $s['id'] ?>" class="font-medium hover:text-primary"><?= e($s['first_name'].' '.$s['last_name']) ?></a>
              <div class="text-xs text-slate-400"><?= e($s['email']) ?></div>
            </div>
          </td>
          <td><?= e($s['admission_no']) ?></td>
          <td><?= $s['class_name'] ? e($s['class_name'].' '.$s['class_section']) : '<span class="text-slate-400">Unassigned</span>' ?></td>
          <td><?= e($s['phone'] ?: '—') ?></td>
          <td><span class="badge <?= $s['status']==='active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= e($s['status']) ?></span></td>
          <td class="text-right whitespace-nowrap">
            <button onclick='openStudentModal(<?= json_encode($s, JSON_HEX_APOS) ?>)' class="text-primary hover:underline text-xs font-medium">Edit</button>
            <form method="POST" class="inline" data-confirm="Remove this student? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
              <button class="text-rose-500 hover:underline text-xs font-medium ml-2">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pagination_links($page, $totalPages, http_build_query(['q'=>$search,'class_id'=>$classFilter])) ?>

<!-- Add/Edit Modal -->
<div id="studentModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="student_id" id="studentId">
      <h3 class="text-lg font-semibold" id="modalTitle">Add Student</h3>

      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">First name</label><input required name="first_name" id="f_first_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Last name</label><input required name="last_name" id="f_last_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Email</label><input required type="email" name="email" id="f_email" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Username</label><input required name="username" id="f_username" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Admission No.</label><input required name="admission_no" id="f_admission_no" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Class</label>
          <select name="class_id" id="f_class_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
            <option value="">Unassigned</option>
            <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name'].' '.$c['section']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Date of birth</label><input type="date" name="date_of_birth" id="f_dob" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Gender</label>
          <select name="gender" id="f_gender" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
            <option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
          </select>
        </div>
      </div>
      <div><label class="text-sm font-medium">Phone</label><input name="phone" id="f_phone" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Address</label><input name="address" id="f_address" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Guardian name</label><input name="guardian_name" id="f_guardian_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Guardian phone</label><input name="guardian_phone" id="f_guardian_phone" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div><label class="text-sm font-medium">Profile picture</label><input type="file" name="profile_picture" accept="image/*" class="w-full text-sm mt-1"></div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeStudentModal()" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openStudentModal(data) {
  const modal = document.getElementById('studentModal');
  const fields = ['first_name','last_name','email','username','admission_no','class_id','dob','gender','phone','address','guardian_name','guardian_phone'];
  fields.forEach(f => { const el = document.getElementById('f_'+f); if (el) el.value = ''; });

  if (data) {
    document.getElementById('modalTitle').textContent = 'Edit Student';
    document.getElementById('formAction').value = 'update';
    document.getElementById('studentId').value = data.id;
    document.getElementById('f_first_name').value = data.first_name || '';
    document.getElementById('f_last_name').value = data.last_name || '';
    document.getElementById('f_email').value = data.email || '';
    document.getElementById('f_username').value = data.username || '';
    document.getElementById('f_admission_no').value = data.admission_no || '';
    document.getElementById('f_class_id').value = data.class_id || '';
    document.getElementById('f_dob').value = data.date_of_birth || '';
    document.getElementById('f_gender').value = data.gender || '';
    document.getElementById('f_phone').value = data.phone || '';
    document.getElementById('f_address').value = data.address || '';
    document.getElementById('f_guardian_name').value = data.guardian_name || '';
    document.getElementById('f_guardian_phone').value = data.guardian_phone || '';
  } else {
    document.getElementById('modalTitle').textContent = 'Add Student';
    document.getElementById('formAction').value = 'create';
    document.getElementById('studentId').value = '';
  }
  modal.classList.remove('hidden');
}
function closeStudentModal() { document.getElementById('studentModal').classList.add('hidden'); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
