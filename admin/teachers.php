<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Teachers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        if ($action === 'create' || $action === 'update') {
            $firstName = input('first_name'); $lastName = input('last_name');
            $email = input('email'); $username = input('username');
            $staffNo = input('staff_no'); $department = input('department');
            $qualification = input('qualification'); $phone = input('phone');

            if (!$firstName || !$lastName || !$email || !$username || !$staffNo) {
                throw new RuntimeException('Please fill in all required fields.');
            }

            if ($action === 'create') {
                $tempPassword = bin2hex(random_bytes(4));
                $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, username, password_hash, phone)
                                        VALUES ((SELECT id FROM roles WHERE name="teacher"), ?,?,?,?,?,?)');
                $stmt->execute([$firstName, $lastName, $email, $username, $hash, $phone]);
                $userId = $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO teachers (user_id, staff_no, department, qualification, hire_date) VALUES (?,?,?,?,CURDATE())')
                    ->execute([$userId, $staffNo, $department, $qualification]);
                $pdo->commit();
                notify($userId, 'Welcome to SMP', "Your account was created. Username: {$username}, temporary password: {$tempPassword}", 'account');
                audit_log($user['id'], 'teacher.create', 'teachers', $pdo->lastInsertId());
                flash('success', "Teacher added. Temporary password: {$tempPassword} (share this with the teacher).");
            } else {
                $teacherId = (int)input('teacher_id');
                $stmt = $pdo->prepare('SELECT user_id FROM teachers WHERE id = ?');
                $stmt->execute([$teacherId]);
                $row = $stmt->fetch();
                if (!$row) throw new RuntimeException('Teacher not found.');
                $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, username=?, phone=? WHERE id=?')
                    ->execute([$firstName, $lastName, $email, $username, $phone, $row['user_id']]);
                $pdo->prepare('UPDATE teachers SET staff_no=?, department=?, qualification=? WHERE id=?')
                    ->execute([$staffNo, $department, $qualification, $teacherId]);
                audit_log($user['id'], 'teacher.update', 'teachers', $teacherId);
                flash('success', 'Teacher updated.');
            }
        } elseif ($action === 'delete') {
            $teacherId = (int)input('teacher_id');
            $stmt = $pdo->prepare('SELECT user_id FROM teachers WHERE id = ?');
            $stmt->execute([$teacherId]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$row['user_id']]);
                audit_log($user['id'], 'teacher.delete', 'teachers', $teacherId);
                flash('success', 'Teacher removed.');
            }
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', str_contains($e->getMessage(),'Duplicate') ? 'That email, username, or staff number is already in use.' : 'Database error.');
    }
    redirect('/admin/teachers.php');
}

$search = trim($_GET['q'] ?? '');
[$perPage, $offset, $page] = paginate(10);
$where = ''; $params = [];
if ($search !== '') {
    $where = 'WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.staff_no LIKE ?';
    $params = ["%$search%","%$search%","%$search%","%$search%"];
}
$total = (function() use ($pdo, $where, $params) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM teachers t JOIN users u ON u.id=t.user_id $where");
    $s->execute($params); return (int)$s->fetchColumn();
})();
$totalPages = (int)ceil($total / $perPage);
$stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.email, u.username, u.phone, u.profile_picture, u.status,
                        (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = t.id) AS course_count
                        FROM teachers t JOIN users u ON u.id = t.user_id
                        $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$teachers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
  <form method="GET" class="flex gap-2">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, staff no…" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-primary">
    <button class="rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 px-3 py-2 text-sm">Search</button>
  </form>
  <button onclick="openModal()" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">+ Add Teacher</button>
</div>

<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Teacher</th><th>Staff No.</th><th>Department</th><th>Courses</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$teachers): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No teachers found.</td></tr><?php endif; ?>
      <?php foreach ($teachers as $t): ?>
        <tr>
          <td class="flex items-center gap-3">
            <img src="<?= $t['profile_picture'] ? BASE_URL.'/uploads/'.e($t['profile_picture']) : 'https://ui-avatars.com/api/?background=1E40AF&color=fff&name='.urlencode($t['first_name'].' '.$t['last_name']) ?>" class="h-9 w-9 rounded-full object-cover">
            <div><span class="font-medium"><?= e($t['first_name'].' '.$t['last_name']) ?></span><div class="text-xs text-slate-400"><?= e($t['email']) ?></div></div>
          </td>
          <td><?= e($t['staff_no']) ?></td>
          <td><?= e($t['department'] ?: '—') ?></td>
          <td><?= (int)$t['course_count'] ?></td>
          <td><span class="badge <?= $t['status']==='active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= e($t['status']) ?></span></td>
          <td class="text-right whitespace-nowrap">
            <button onclick='openModal(<?= json_encode($t, JSON_HEX_APOS) ?>)' class="text-primary hover:underline text-xs font-medium">Edit</button>
            <form method="POST" class="inline" data-confirm="Remove this teacher? This cannot be undone.">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
              <button class="text-rose-500 hover:underline text-xs font-medium ml-2">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pagination_links($page, $totalPages, http_build_query(['q'=>$search])) ?>

<div id="modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="teacher_id" id="teacherId">
      <h3 class="text-lg font-semibold" id="modalTitle">Add Teacher</h3>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">First name</label><input required name="first_name" id="f_first_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Last name</label><input required name="last_name" id="f_last_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Email</label><input required type="email" name="email" id="f_email" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Username</label><input required name="username" id="f_username" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Staff No.</label><input required name="staff_no" id="f_staff_no" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Department</label><input name="department" id="f_department" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div><label class="text-sm font-medium">Qualification</label><input name="qualification" id="f_qualification" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Phone</label><input name="phone" id="f_phone" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(data) {
  const fields = ['first_name','last_name','email','username','staff_no','department','qualification','phone'];
  fields.forEach(f => { const el = document.getElementById('f_'+f); if (el) el.value = ''; });
  if (data) {
    document.getElementById('modalTitle').textContent = 'Edit Teacher';
    document.getElementById('formAction').value = 'update';
    document.getElementById('teacherId').value = data.id;
    fields.forEach(f => { const el = document.getElementById('f_'+f); if (el) el.value = data[f] || ''; });
  } else {
    document.getElementById('modalTitle').textContent = 'Add Teacher';
    document.getElementById('formAction').value = 'create';
    document.getElementById('teacherId').value = '';
  }
  document.getElementById('modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('modal').classList.add('hidden'); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
