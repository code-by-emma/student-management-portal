<?php
/**
 * Shared "My Profile" page, included by admin/profile.php, teacher/profile.php,
 * student/profile.php. Expects $user (from require_role) to already be set.
 */
$pageTitle = 'My Profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');

    if ($action === 'update_profile') {
        $firstName = input('first_name'); $lastName = input('last_name');
        $email = input('email'); $phone = input('phone');
        try {
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?')
                ->execute([$firstName, $lastName, $email, $phone, $user['id']]);

            $picPath = handle_upload('profile_picture', 'profile_pics', ALLOWED_IMAGE_EXT);
            if ($picPath) {
                $pdo->prepare('UPDATE users SET profile_picture=? WHERE id=?')->execute([$picPath, $user['id']]);
                $_SESSION['profile_picture'] = $picPath;
            }
            $_SESSION['name'] = "$firstName $lastName";
            $_SESSION['email'] = $email;
            audit_log($user['id'], 'profile.update');
            flash('success', 'Profile updated.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (PDOException $e) {
            flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'That email is already in use.' : 'Could not update profile.');
        }
    } elseif ($action === 'change_password') {
        $current = input('current_password'); $new = input('new_password'); $confirm = input('confirm_password');
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
            audit_log($user['id'], 'profile.password_changed');
            flash('success', 'Password changed successfully.');
        }
    }
    redirect('/' . $user['role'] . '/profile.php');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

include __DIR__ . '/header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="card p-6 text-center h-fit">
    <img src="<?= $profile['profile_picture'] ? BASE_URL.'/uploads/'.e($profile['profile_picture']) : 'https://ui-avatars.com/api/?background=2563EB&color=fff&size=128&name='.urlencode($profile['first_name'].' '.$profile['last_name']) ?>" class="h-24 w-24 rounded-full object-cover mx-auto">
    <h2 class="font-semibold mt-3"><?= e($profile['first_name'].' '.$profile['last_name']) ?></h2>
    <p class="text-sm text-slate-400 capitalize"><?= e($user['role']) ?></p>
    <p class="text-xs text-slate-400 mt-2">Member since <?= e(date('M Y', strtotime($profile['created_at']))) ?></p>
  </div>

  <div class="lg:col-span-2 space-y-6">
    <div class="card p-6">
      <h3 class="font-semibold mb-4">Profile details</h3>
      <form method="POST" enctype="multipart/form-data" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium">First name</label><input required name="first_name" value="<?= e($profile['first_name']) ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
          <div><label class="text-sm font-medium">Last name</label><input required name="last_name" value="<?= e($profile['last_name']) ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        </div>
        <div><label class="text-sm font-medium">Email</label><input required type="email" name="email" value="<?= e($profile['email']) ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Phone</label><input name="phone" value="<?= e($profile['phone']) ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Profile picture</label><input type="file" name="profile_picture" accept="image/*" class="w-full text-sm mt-1"></div>
        <button class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">Save changes</button>
      </form>
    </div>

    <div class="card p-6">
      <h3 class="font-semibold mb-4">Change password</h3>
      <form method="POST" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div><label class="text-sm font-medium">Current password</label>
          <div class="relative password-field">
            <input required type="password" name="current_password" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 pr-10 text-sm mt-1">
            <?= password_toggle_button() ?>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium">New password</label>
            <div class="relative password-field">
              <input required type="password" minlength="8" name="new_password" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 pr-10 text-sm mt-1">
              <?= password_toggle_button() ?>
            </div>
          </div>
          <div><label class="text-sm font-medium">Confirm new password</label>
            <div class="relative password-field">
              <input required type="password" minlength="8" name="confirm_password" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 pr-10 text-sm mt-1">
              <?= password_toggle_button() ?>
            </div>
          </div>
        </div>
        <button class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">Update password</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
