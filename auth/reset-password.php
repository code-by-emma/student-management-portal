
<?php
require_once __DIR__ . '/../config/app.php';

$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? input('token')
    : ($_GET['token'] ?? '');

$error = null;
$validToken = false;
$u = null;

if (!empty($token)) {
    $stmt = $pdo->prepare(
        'SELECT id, reset_token_expires FROM users WHERE reset_token = ?'
    );
    $stmt->execute([$token]);
    $u = $stmt->fetch();

    $validToken = $u
        && !empty($u['reset_token_expires'])
        && strtotime($u['reset_token_expires']) > time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$validToken) {
        $error = 'This reset link is invalid or has expired.';
    } else {
        $pw1 = input('password');
        $pw2 = input('password_confirm');

        if (strlen($pw1) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($pw1 !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($pw1, PASSWORD_BCRYPT);

            $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?,
                     reset_token = NULL,
                     reset_token_expires = NULL
                 WHERE id = ?'
            )->execute([$hash, $u['id']]);

            audit_log($u['id'], 'auth.password_reset_completed');

            redirect('/auth/login.php?reset=1');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset password · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/output.css">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="min-h-screen bg-bgapp flex items-center justify-center px-4">
  <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
    <h1 class="text-lg font-bold text-slate-900 mb-1">Choose a new password</h1>

    <?php if (!$validToken): ?>
      <div class="rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3 mt-4">
        This reset link is invalid or has expired. <a href="<?= BASE_URL ?>/auth/forgot-password.php" class="underline">Request a new one</a>.
      </div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="POST" class="space-y-4 mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">New password</label>
          <div class="relative password-field">
            <input type="password" name="password" required minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            <?= password_toggle_button() ?>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
          <div class="relative password-field">
            <input type="password" name="password_confirm" required minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
            <?= password_toggle_button() ?>
          </div>
        </div>
        <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-medium rounded-lg py-2.5 text-sm transition">Reset password</button>
      </form>
    <?php endif; ?>
  </div>
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
