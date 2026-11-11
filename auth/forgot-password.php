<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';

$mailerFile = __DIR__ . '/../includes/mailer.php';
// Email can only work if credentials are filled in AND PHPMailer is installed.
$mailReady = MAIL_ENABLED && is_file($mailerFile) && is_file(__DIR__ . '/../vendor/autoload.php');

$sent = false;    // request processed -> show a confirmation
$failed = false;  // a reset email was attempted for a real account and delivery FAILED

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$mailReady) {
        // Nothing can be sent, so don't create a token or pretend otherwise.
        // The page shows an honest notice to EVERY visitor (known email or not).
        error_log('Password reset requested but email is not configured (config/mail.php / vendor/ missing).');
        $sent = true;
    } else {
        $email = input('email');
        $stmt = $pdo->prepare('SELECT id, first_name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        // Unknown emails get the same neutral confirmation as known ones, so this
        // form can't be used to discover which addresses are registered.
        if ($u) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?')
                ->execute([$token, $expires, $u['id']]);

            $resetLink = absolute_url('/auth/reset-password.php?token=' . $token);
            try {
                require_once $mailerFile;
                $ok = send_reset_email($email, $u['first_name'], $resetLink);
                $why = $ok ? '' : 'SMTP delivery failed (details in the PHP error log under "PHPMailer error")';
            } catch (Throwable $t) {
                $ok = false;
                $why = get_class($t) . ': ' . $t->getMessage();
            }

            if ($ok) {
                audit_log($u['id'], 'auth.password_reset_requested');
            } else {
                // Don't leave a live token behind for a link nobody received,
                // and don't tell the user it was sent.
                $pdo->prepare('UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE id = ?')->execute([$u['id']]);
                error_log('Password reset email FAILED for user #' . $u['id'] . ': ' . $why);
                audit_log($u['id'], 'auth.password_reset_email_failed', null, null, $why);
                $failed = true;
            }
        }
        $sent = !$failed;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot password · <?= e(APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { colors: { primary:'#2563EB', secondary:'#1E40AF', bgapp:'#F8FAFC' } } } }</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="min-h-screen bg-bgapp flex items-center justify-center px-4">
  <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
    <h1 class="text-lg font-bold text-slate-900 mb-1">Reset your password</h1>
    <p class="text-sm text-slate-500 mb-5">Enter your account email and we'll send you a reset link.</p>

    <?php if ($failed): ?>
      <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3">
        We couldn't send the reset email right now. Please try again in a few minutes, or contact your school administrator.
      </div>
    <?php endif; ?>
    <?php if ($sent && !$mailReady): ?>
      <?php /* Shown for EVERY request in this state (not just real accounts), so it leaks nothing. */ ?>
      <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
        Email delivery isn't set up on this server, so no email was sent. Please contact your school administrator to reset your password.
      </div>
    <?php elseif ($sent): ?>
      <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3">
        If that email is registered, a password reset link has been sent.
      </div>
    <?php else: ?>
      <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
          <input type="email" name="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-medium rounded-lg py-2.5 text-sm transition">Send reset link</button>
      </form>
    <?php endif; ?>
    <p class="text-center text-xs text-slate-400 mt-6"><a href="<?= BASE_URL ?>/auth/login.php" class="text-primary hover:underline">Back to sign in</a></p>
  </div>
</body>
</html>