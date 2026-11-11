<?php
/** Shared notifications list, included by each role's notifications.php */
$pageTitle = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (input('action') === 'mark_all_read') {
        $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]);
    }
    redirect('/' . $user['role'] . '/notifications.php');
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();
$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$user['id']]); // viewing marks as read

include __DIR__ . '/header.php';
$icons = ['assignment'=>'📘','grade'=>'📝','attendance'=>'📅','announcement'=>'📣','account'=>'🔑','general'=>'🔔'];
?>
<div class="flex justify-between items-center mb-5">
  <h2 class="font-semibold">All Notifications</h2>
  <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_read">
    <button class="text-xs text-primary hover:underline">Mark all as read</button>
  </form>
</div>
<div class="card divide-y divide-slate-100 dark:divide-slate-700">
  <?php if (!$notifications): ?><p class="text-slate-400 text-sm p-6 text-center">No notifications yet.</p><?php endif; ?>
  <?php foreach ($notifications as $n): ?>
    <div class="p-4 flex items-start gap-3 <?= !$n['is_read'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' ?>">
      <span class="text-xl"><?= $icons[$n['type']] ?? '🔔' ?></span>
      <div class="flex-1">
        <?php if ($n['link']): ?><a href="<?= e($n['link']) ?>" class="font-medium text-sm hover:text-primary"><?= e($n['title']) ?></a>
        <?php else: ?><p class="font-medium text-sm"><?= e($n['title']) ?></p><?php endif; ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($n['message']) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= time_ago($n['created_at']) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
