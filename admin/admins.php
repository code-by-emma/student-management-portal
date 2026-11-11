<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Administrators';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        if ($action === 'create') {
            $firstName = input('first_name'); $lastName = input('last_name');
            $email = input('email'); $username = input('username'); $phone = input('phone');
            if (!$firstName || !$lastName || !$email || !$username) {
                throw new RuntimeException('Please fill in all required fields.');
            }
            $tempPassword = bin2hex(random_bytes(4));
            $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, username, password_hash, phone)
                                    VALUES ((SELECT id FROM roles WHERE name="admin"), ?,?,?,?,?,?)');
            $stmt->execute([$firstName, $lastName, $email, $username, $hash, $phone]);
            $newUserId = $pdo->lastInsertId();
            notify($newUserId, 'Welcome to SMP', "Your administrator account was created. Username: {$username}, temporary password: {$tempPassword}", 'account');
            audit_log($user['id'], 'admin.create', 'users', $newUserId);
            flash('success', "Administrator added. Temporary password: {$tempPassword} (share this with them securely — it won't be shown again).");
        } elseif ($action === 'deactivate') {
            $targetId = (int)input('user_id');
            if ($targetId === $user['id']) throw new RuntimeException('You cannot deactivate your own account.');
            $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$targetId]);
            audit_log($user['id'], 'admin.deactivate', 'users', $targetId);
            flash('success', 'Administrator deactivated.');
        } elseif ($action === 'activate') {
            $targetId = (int)input('user_id');
            $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$targetId]);
            audit_log($user['id'], 'admin.activate', 'users', $targetId);
            flash('success', 'Administrator reactivated.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'That email or username is already in use.' : 'Database error.');
    }
    redirect('/admin/admins.php');
}

$admins = $pdo->query("SELECT u.* FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='admin' ORDER BY u.created_at DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="flex justify-between items-center mb-5">
  <p class="text-sm text-slate-500">Administrators have full access to the portal. Add new admin accounts sparingly.</p>
  <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2 shrink-0">+ Add Administrator</button>
</div>

<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($admins as $a): ?>
        <tr>
          <td class="flex items-center gap-3 font-medium">
            <img src="<?= $a['profile_picture'] ? BASE_URL.'/uploads/'.e($a['profile_picture']) : 'https://ui-avatars.com/api/?background=2563EB&color=fff&name='.urlencode($a['first_name'].' '.$a['last_name']) ?>" class="h-9 w-9 rounded-full object-cover">
            <?= e($a['first_name'].' '.$a['last_name']) ?>
            <?= $a['id'] == $user['id'] ? '<span class="badge bg-blue-50 text-primary">You</span>' : '' ?>
          </td>
          <td><?= e($a['email']) ?></td>
          <td><?= e($a['username']) ?></td>
          <td><span class="badge <?= $a['status']==='active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= e($a['status']) ?></span></td>
          <td><?= e(date('M j, Y', strtotime($a['created_at']))) ?></td>
          <td class="text-right whitespace-nowrap">
            <?php if ($a['id'] != $user['id']): ?>
              <form method="POST" class="inline" data-confirm="<?= $a['status']==='active' ? 'Deactivate this administrator?' : 'Reactivate this administrator?' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $a['status']==='active' ? 'deactivate' : 'activate' ?>">
                <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
                <button class="text-xs font-medium <?= $a['status']==='active' ? 'text-rose-500' : 'text-emerald-600' ?> hover:underline"><?= $a['status']==='active' ? 'Deactivate' : 'Reactivate' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div id="modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <h3 class="text-lg font-semibold">Add Administrator</h3>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">First name</label><input required name="first_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Last name</label><input required name="last_name" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-medium">Email</label><input required type="email" name="email" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Username</label><input required name="username" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      </div>
      <div><label class="text-sm font-medium">Phone</label><input name="phone" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <p class="text-xs text-slate-400">A random temporary password will be generated and shown once after creation. The new admin should change it on first login.</p>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Create</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
