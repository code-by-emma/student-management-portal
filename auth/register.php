<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect('/' . $_SESSION['role'] . '/dashboard.php');
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = [
        'first_name' => input('first_name'), 'last_name' => input('last_name'),
        'email' => input('email'), 'username' => input('username'), 'phone' => input('phone'),
        'date_of_birth' => input('date_of_birth'), 'gender' => input('gender'),
        'address' => input('address'), 'guardian_name' => input('guardian_name'),
        'guardian_phone' => input('guardian_phone'), 'class_id' => input('class_id'),
    ];
    $password = input('password');
    $confirm  = input('confirm_password');

    if (!$old['first_name'] || !$old['last_name']) $errors[] = 'First and last name are required.';
    if (!$old['email'] || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!$old['username'] || strlen($old['username']) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (role_id, first_name, last_name, email, username, password_hash, phone, status)
                                    VALUES ((SELECT id FROM roles WHERE name="student"), ?,?,?,?,?,?, "active")');
            $stmt->execute([$old['first_name'], $old['last_name'], $old['email'], $old['username'], $hash, $old['phone']]);
            $userId = $pdo->lastInsertId();

            $admissionNo = 'ADM-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare('INSERT INTO students (user_id, admission_no, class_id, date_of_birth, gender, address, guardian_name, guardian_phone, enrollment_date)
                                    VALUES (?,?,?,?,?,?,?,?, CURDATE())');
            $stmt->execute([
                $userId, $admissionNo, $old['class_id'] ?: null, $old['date_of_birth'] ?: null,
                $old['gender'] ?: null, $old['address'] ?: null, $old['guardian_name'] ?: null, $old['guardian_phone'] ?: null,
            ]);
            $studentId = $pdo->lastInsertId();
            $pdo->commit();

            audit_log($userId, 'auth.self_register', 'students', $studentId);

            // Auto sign the new student in.
            $userRow = ['id' => $userId, 'role_name' => 'student', 'first_name' => $old['first_name'],
                        'last_name' => $old['last_name'], 'email' => $old['email'], 'profile_picture' => null];
            login_user($userRow, ['id' => $studentId]);
            flash('success', "Welcome! Your admission number is {$admissionNo} — keep it for your records.");
            redirect('/student/dashboard.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'That email or username is already registered.' : 'Something went wrong. Please try again.';
        }
    }
}

$classes = $pdo->query('SELECT id, name, section FROM classes ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create your account · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/output.css">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="create-page min-h-screen bg-bgapp flex items-center justify-center px-4 py-10">
  <div class="w-full max-w-lg">
    <div class="flex flex-col items-center mb-6">
      <div class="h-10 w-80 rounded-xl bg-primary flex items-center justify-center text-black font-bold text-xl">Springfield High School</div>
      <h1 class="mt-3 text-xl font-bold text-slate-900">Create your student account</h1>
      <p class="text-sm text-slate-500">Teachers and administrators are added by the school — this form is for students only.</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
      <?php foreach ($errors as $err): ?>
        <div class="mb-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">First name</label>
            <input required name="first_name" value="<?= e($old['first_name'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Last name</label>
            <input required name="last_name" value="<?= e($old['last_name'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input required type="email" name="email" value="<?= e($old['email'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
            <input required name="username" value="<?= e($old['username'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <div class="relative password-field">
              <input required type="password" name="password" minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <?= password_toggle_button() ?>
            </div></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
            <div class="relative password-field">
              <input required type="password" name="confirm_password" minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <?= password_toggle_button() ?>
            </div></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
            <input name="phone" value="<?= e($old['phone'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Date of birth</label>
            <input type="date" name="date_of_birth" value="<?= e($old['date_of_birth'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
            <select name="gender" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="">— Select —</option>
              <option value="male" <?= ($old['gender'] ?? '')==='male'?'selected':'' ?>>Male</option>
              <option value="female" <?= ($old['gender'] ?? '')==='female'?'selected':'' ?>>Female</option>
              <option value="other" <?= ($old['gender'] ?? '')==='other'?'selected':'' ?>>Other</option>
            </select></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Class (if known)</label>
            <select name="class_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="">— Assigned later by admin —</option>
              <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>" <?= ($old['class_id'] ?? '')==$c['id']?'selected':'' ?>><?= e($c['name'].' '.$c['section']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Home address</label>
          <input name="address" value="<?= e($old['address'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Guardian name</label>
            <input name="guardian_name" value="<?= e($old['guardian_name'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
          <div><label class="block text-sm font-medium text-slate-700 mb-1">Guardian phone</label>
            <input name="guardian_phone" value="<?= e($old['guardian_phone'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></div>
        </div>
        <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-medium rounded-lg py-2.5 text-sm transition">Create account</button>
      </form>
    </div>
    <p class="text-center text-xs text-slate-400 mt-6">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php" class="text-primary hover:underline">Sign in</a></p>
  </div>
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
