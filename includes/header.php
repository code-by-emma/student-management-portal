<?php
/**
 * Shared page header. Expects $pageTitle to be set by the including page.
 * Requires require_login()/require_role() to have already run.
 */
$user = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';

// Unread notification count for the topbar bell.
$unreadCount = 0;
if ($user) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    $unreadCount = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<!-- <script src="https://cdn.tailwindcss.com"></script> -->
 <link rel="stylesheet" href="<?= e(BASE_URL) ?>/output.css">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
<!-- <script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        colors: {
          primary: '#2563EB',
          secondary: '#1E40AF',
          accent: '#22C55E',
          bgapp: '#F8FAFC',
        },
        fontFamily: {
          sans: ['Inter', 'ui-sans-serif', 'system-ui'],
        },
      },
    },
  }
</script> -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"> -->
<script>
  // Apply saved theme before paint to avoid flash.
  if (localStorage.getItem('smp-theme') === 'dark') document.documentElement.classList.add('dark');
</script>
</head>
<body class="h-full bg-bgapp dark:bg-slate-900 font-sans text-slate-800 dark:text-slate-100 antialiased">
<div class="min-h-full flex">

  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0 lg:pl-64">
    <!-- Topbar -->
    <header class="sticky top-0 z-30 bg-white/90 dark:bg-slate-800/90 backdrop-blur border-b border-slate-200 dark:border-slate-700">
      <div class="flex items-center justify-between gap-4 px-4 sm:px-6 py-3">
        <div class="flex items-center gap-3">
          <button id="sidebarToggle" class="lg:hidden text-slate-500 hover:text-slate-700 dark:text-slate-300" aria-label="Toggle menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <h1 class="text-lg font-semibold text-slate-900 dark:text-white"><?= e($pageTitle) ?></h1>
        </div>

        <div class="flex items-center gap-3">
          <form action="<?= BASE_URL ?>/<?= e($user['role']) ?>/search.php" method="GET" class="hidden md:block">
            <div class="relative">
              <input type="text" name="q" placeholder="Search…" class="w-56 pl-9 pr-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 dark:bg-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <svg class="absolute left-2.5 top-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
            </div>
          </form>

          <button id="themeToggle" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-300" aria-label="Toggle dark mode">
            <svg id="iconSun" class="h-5 w-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <svg id="iconMoon" class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
          </button>

          <a href="<?= BASE_URL ?>/<?= e($user['role']) ?>/notifications.php" class="relative p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-300" aria-label="Notifications">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <?php if ($unreadCount > 0): ?>
              <span class="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
          </a>

          <div class="relative">
            <a href="<?= BASE_URL ?>/<?= e($user['role']) ?>/profile.php" class="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-600">
              <img src="<?= $user['profile_picture'] ? BASE_URL . '/uploads/' . e($user['profile_picture']) : 'https://ui-avatars.com/api/?background=2563EB&color=fff&name=' . urlencode($user['name']) ?>" class="h-8 w-8 rounded-full object-cover" alt="<?= e($user['name']) ?>">
              <span class="hidden sm:block text-sm font-medium"><?= e($user['name']) ?></span>
            </a>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-1 px-4 sm:px-6 py-6">
      <?= render_flashes() ?>
