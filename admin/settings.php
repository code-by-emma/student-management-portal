<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Settings';

// Simple settings stored in a flat file for this demo. For production, move to a `settings` DB table.
$settingsFile = __DIR__ . '/../config/app_settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [
    'school_name' => 'Springfield High School',
    'academic_year' => '2026/2027',
    'contact_email' => 'admin@smp.local',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $settings['school_name'] = input('school_name');
    $settings['academic_year'] = input('academic_year');
    $settings['contact_email'] = input('contact_email');
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    audit_log($user['id'], 'settings.update');
    flash('success', 'Settings saved.');
    redirect('/admin/settings.php');
}

$roleCounts = $pdo->query("SELECT r.name, COUNT(u.id) c FROM roles r LEFT JOIN users u ON u.role_id=r.id GROUP BY r.id")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 card p-6">
    <h3 class="font-semibold mb-4">School Information</h3>
    <form method="POST" class="space-y-3">
      <?= csrf_field() ?>
      <div><label class="text-sm font-medium">School name</label><input name="school_name" value="<?= e($settings['school_name']) ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Current academic year</label><input name="academic_year" value="<?= e($settings['academic_year']) ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Contact email</label><input type="email" name="contact_email" value="<?= e($settings['contact_email']) ?>" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <button class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2">Save settings</button>
    </form>
  </div>

  <div class="card p-6 h-fit">
    <h3 class="font-semibold mb-4">Accounts by role</h3>
    <ul class="space-y-2 text-sm">
      <?php foreach ($roleCounts as $r): ?>
        <li class="flex justify-between"><span class="capitalize text-slate-500"><?= e($r['name']) ?>s</span><span class="font-medium"><?= (int)$r['c'] ?></span></li>
      <?php endforeach; ?>
    </ul>
    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700 text-xs text-slate-400">
      Password policy: bcrypt hashing, min. 8 characters. Session timeout: <?= SESSION_TIMEOUT_SECONDS / 60 ?> minutes idle.
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
