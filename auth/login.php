
<?php
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect('/' . $_SESSION['role'] . '/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $identifier = trim(input('identifier'));
    $password   = input('password');
    $remember   = isset($_POST['remember']);

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your email/username and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? OR u.username = ?
             LIMIT 1'
        );

        $stmt->execute([$identifier, $identifier]);
        $userRow = $stmt->fetch();

        if (
            !$userRow ||
            !password_verify($password, $userRow['password_hash'])
        ) {
            $error = 'Invalid credentials. Please try again.';

            audit_log(
                null,
                'auth.login_failed',
                'users',
                null,
                "identifier={$identifier}"
            );
        } elseif ($userRow['status'] !== 'active') {
            $error = 'This account is ' .
                $userRow['status'] .
                '. Contact the administrator.';
        } else {
            $linkedRow = null;

            if ($userRow['role_name'] === 'student') {
                $s = $pdo->prepare(
                    'SELECT id FROM students WHERE user_id = ?'
                );
                $s->execute([$userRow['id']]);
                $linkedRow = $s->fetch();
            } elseif ($userRow['role_name'] === 'teacher') {
                $s = $pdo->prepare(
                    'SELECT id FROM teachers WHERE user_id = ?'
                );
                $s->execute([$userRow['id']]);
                $linkedRow = $s->fetch();
            }

            // Establish the authenticated session.
            login_user($userRow, $linkedRow);

            // Optional persistent login.
            if ($remember) {
                $token = bin2hex(random_bytes(32));

                $pdo->prepare(
                    'UPDATE users SET remember_token = ? WHERE id = ?'
                )->execute([$token, $userRow['id']]);

                $isHttps = (
                    !empty($_SERVER['HTTPS']) &&
                    $_SERVER['HTTPS'] !== 'off'
                );

                setcookie('smp_remember', $token, [
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            $pdo->prepare(
                'UPDATE users SET last_login = NOW() WHERE id = ?'
            )->execute([$userRow['id']]);

            audit_log(
                $userRow['id'],
                'auth.login',
                'users',
                $userRow['id']
            );

            redirect('/' . $userRow['role_name'] . '/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | <?= e(APP_NAME) ?></title>


<link rel="stylesheet" href="<?= e(BASE_URL) ?>/output.css">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- Main project stylesheet -->
    <link
        rel="stylesheet"
        href="<?= e(BASE_URL) ?>/assets/css/style.css"
    >
</head>

<body class="login-page min-h-screen flex items-center justify-center px-4 py-10">

    <main class="login-wrapper">

        <!-- Page heading -->
        <header class="text-center mb-7">
            <h1 class="login-heading mt-4 text-2xl sm:text-3xl font-extrabold">
                Student Management Portal
            </h1>

            <p class="login-subtitle mt-2 text-sm sm:text-base">
                Welcome back! Sign in to access your account.
            </p>
        </header>

        <!-- Login card -->
        <section class="login-card">

            <div class="mb-6">
                <h2 class="text-xl font-bold text-slate-900">
                    Sign in
                </h2>

                <p class="text-sm text-slate-500 mt-1">
                    Enter your login details below.
                </p>
            </div>

            <?php if (isset($_GET['timeout'])): ?>
                <div
                    class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3"
                    role="status"
                >
                    You were signed out due to inactivity. Please sign in again.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset'])): ?>
                <div
                    class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3"
                    role="status"
                >
                    Your password was reset successfully. Please sign in.
                </div>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <div
                    class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3"
                    role="alert"
                >
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <?= csrf_field() ?>

                <!-- Email or username -->
                <div>
                    <label
                        for="identifier"
                        class="block text-sm font-semibold text-slate-700 mb-2"
                    >
                        Email or Username
                    </label>

                    <input
                        type="text"
                        name="identifier"
                        id="identifier"
                        required
                        autofocus
                        autocomplete="username"
                        value="<?= e($identifier ?? '') ?>"
                        class="login-input"
                        placeholder="you@school.edu"
                    >
                </div>

                <!-- Password -->
                <div>
                    <label
                        for="password"
                        class="block text-sm font-semibold text-slate-700 mb-2"
                    >
                        Password
                    </label>

                    <div class="relative password-field">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            required
                            autocomplete="current-password"
                            class="login-input pr-12"
                            placeholder="Enter your password"
                        >

                        <?= password_toggle_button() ?>
                    </div>
                </div>

                <!-- Remember me and password recovery -->
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">

                    <label class="flex items-center gap-2 text-slate-600 cursor-pointer">
                        <input
                            type="checkbox"
                            name="remember"
                            class="rounded border-slate-300 text-primary focus:ring-primary"
                        >
                        Remember me
                    </label>

                    <a
                        href="<?= e(BASE_URL) ?>/auth/forgot-password.php"
                        class="login-link"
                    >
                        Forgot password?
                    </a>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    class="login-button w-full bg-primary hover:bg-secondary text-white font-semibold rounded-lg py-3 text-sm transition"
                >
                    Sign in
                </button>
            </form>

            <div class="login-divider">
                <span>Secure access to your school portal</span>
            </div>

        </section>

        <!-- Registration and account information -->
        <footer class="login-footer mt-6 text-sm">
            <p>
                New student?
                <a
                    href="<?= e(BASE_URL) ?>/auth/register.php"
                    class="login-footer-link"
                >
                    Create an account
                </a>
            </p>

            <p class="mt-2 text-xs">
                Teacher and administrator accounts are managed by the school.
            </p>

            <p class="mt-4 text-xs login-copyright">
                &copy; <?= date('Y') ?>
                <?= e(APP_NAME) ?>. All rights reserved.
            </p>
        </footer>

    </main>

    <script src="<?= e(BASE_URL) ?>/assets/js/main.js"></script>
</body>
</html>
