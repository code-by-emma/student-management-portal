<?php
$role = $user['role'] ?? '';
$current = basename($_SERVER['PHP_SELF']);

function nav_item(string $href, string $label, string $iconPath, string $current, string $file): string {
    $active = $current === $file;
    $classes = $active
        ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-blue-300 font-semibold'
        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700';
    return "<a href=\"{$href}\" class=\"flex items-center gap-3 px-3 py-2 rounded-lg text-sm {$classes}\">
        <svg class=\"h-5 w-5 shrink-0\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"{$iconPath}\"/></svg>
        <span>{$label}</span></a>";
}

$icons = [
    'grid'   => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
    'users'  => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-2a4 4 0 100-8 4 4 0 000 8zm6 4v-1a4 4 0 00-3-3.87',
    'book'   => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    'layers' => 'M12 2l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5',
    'check'  => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'clip'   => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    'award'  => 'M12 15a4 4 0 100-8 4 4 0 000 8zm-3.5 2.5L7 21l5-2 5 2-1.5-3.5',
    'bar'    => 'M9 19V6M15 19V10M3 19h18',
    'bell'   => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    'user'   => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    'cal'    => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    'megaphone' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
    'cog'    => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    'cash'   => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 9v1m9-5a9 9 0 11-18 0 9 9 0 0118 0z',
];
?>
<input type="checkbox" id="sidebarState" class="hidden peer">
<div class="fixed inset-0 bg-black/40 z-30 hidden peer-checked:block lg:hidden" onclick="document.getElementById('sidebarState').checked=false"></div>
<aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full peer-checked:translate-x-0 lg:translate-x-0 transition-transform bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex flex-col" id="sidebar">
  <div class="h-16 flex items-center gap-2 px-5 border-b border-slate-200 dark:border-slate-700">
    <div class=" items-center justify-center font-bold text-slate-900 dark:text-white">Springfield High School</div>
  </div>

  <nav class="flex-1 overflow-y-auto p-3 space-y-1">
    <?php if ($role === 'admin'): ?>
      <?= nav_item(BASE_URL.'/admin/dashboard.php', 'Dashboard', $icons['grid'], $current, 'dashboard.php') ?>
      <?= nav_item(BASE_URL.'/admin/admins.php', 'Administrators', $icons['user'], $current, 'admins.php') ?>
      <?= nav_item(BASE_URL.'/admin/students.php', 'Students', $icons['users'], $current, 'students.php') ?>
      <?= nav_item(BASE_URL.'/admin/teachers.php', 'Teachers', $icons['user'], $current, 'teachers.php') ?>
      <?= nav_item(BASE_URL.'/admin/courses.php', 'Courses', $icons['book'], $current, 'courses.php') ?>
      <?= nav_item(BASE_URL.'/admin/classes.php', 'Classes', $icons['layers'], $current, 'classes.php') ?>
      <?= nav_item(BASE_URL.'/admin/grade-levels.php', 'Grade Levels', $icons['award'], $current, 'grade-levels.php') ?>
      <?= nav_item(BASE_URL.'/admin/timetable.php', 'Timetable', $icons['cal'], $current, 'timetable.php') ?>
      <?= nav_item(BASE_URL.'/admin/reports.php', 'Reports', $icons['bar'], $current, 'reports.php') ?>
      <?= nav_item(BASE_URL.'/admin/fees.php', 'Fees', $icons['cash'], $current, 'fees.php') ?>
      <?= nav_item(BASE_URL.'/admin/announcements.php', 'Announcements', $icons['megaphone'], $current, 'announcements.php') ?>
      <?= nav_item(BASE_URL.'/admin/audit-logs.php', 'Audit Logs', $icons['clip'], $current, 'audit-logs.php') ?>
      <?= nav_item(BASE_URL.'/admin/settings.php', 'Settings', $icons['cog'], $current, 'settings.php') ?>
    <?php elseif ($role === 'teacher'): ?>
      <?= nav_item(BASE_URL.'/teacher/dashboard.php', 'Dashboard', $icons['grid'], $current, 'dashboard.php') ?>
      <?= nav_item(BASE_URL.'/teacher/courses.php', 'My Courses', $icons['book'], $current, 'courses.php') ?>
      <?= nav_item(BASE_URL.'/teacher/attendance.php', 'Attendance', $icons['check'], $current, 'attendance.php') ?>
      <?= nav_item(BASE_URL.'/teacher/assignments.php', 'Assignments', $icons['clip'], $current, 'assignments.php') ?>
      <?= nav_item(BASE_URL.'/teacher/grades.php', 'Grades', $icons['award'], $current, 'grades.php') ?>
      <?= nav_item(BASE_URL.'/teacher/timetable.php', 'Timetable', $icons['cal'], $current, 'timetable.php') ?>
      <?= nav_item(BASE_URL.'/teacher/announcements.php', 'Announcements', $icons['megaphone'], $current, 'announcements.php') ?>
    <?php elseif ($role === 'student'): ?>
      <?= nav_item(BASE_URL.'/student/dashboard.php', 'Dashboard', $icons['grid'], $current, 'dashboard.php') ?>
      <?= nav_item(BASE_URL.'/student/courses.php', 'My Courses', $icons['book'], $current, 'courses.php') ?>
      <?= nav_item(BASE_URL.'/student/attendance.php', 'Attendance', $icons['check'], $current, 'attendance.php') ?>
      <?= nav_item(BASE_URL.'/student/assignments.php', 'Assignments', $icons['clip'], $current, 'assignments.php') ?>
      <?= nav_item(BASE_URL.'/student/grades.php', 'Grades', $icons['award'], $current, 'grades.php') ?>
      <?= nav_item(BASE_URL.'/student/timetable.php', 'Timetable', $icons['cal'], $current, 'timetable.php') ?>
      <?= nav_item(BASE_URL.'/student/fees.php', 'Fees', $icons['cash'], $current, 'fees.php') ?>
    <?php endif; ?>

    <div class="pt-3 mt-3 border-t border-slate-200 dark:border-slate-700 space-y-1">
      <?= nav_item(BASE_URL."/{$role}/profile.php", 'My Profile', $icons['user'], $current, 'profile.php') ?>
      <?= nav_item(BASE_URL."/{$role}/notifications.php", 'Notifications', $icons['bell'], $current, 'notifications.php') ?>
    </div>
  </nav>

  <div class="p-3 border-t border-slate-200 dark:border-slate-700">
    <a href="<?= BASE_URL ?>/auth/logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30">
      <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</aside>
<label for="sidebarState" class="hidden"></label>
