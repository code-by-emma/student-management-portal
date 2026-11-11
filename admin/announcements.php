<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    if ($action === 'create') {
        $title = input('title'); $body = input('body'); $audience = input('audience');
        if (!$title || !$body) { flash('error', 'Title and message are required.'); redirect('/admin/announcements.php'); }
        $stmt = $pdo->prepare('INSERT INTO announcements (title, body, audience, created_by) VALUES (?,?,?,?)');
        $stmt->execute([$title, $body, $audience, $user['id']]);
        $annId = $pdo->lastInsertId();

        // Fan out an in-app notification to the target audience.
        $roleFilter = $audience === 'all' ? "1=1" : "r.name = " . $pdo->quote($audience);
        $recipients = $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE $roleFilter AND u.id != {$user['id']}")->fetchAll();
        foreach ($recipients as $rcpt) {
            notify($rcpt['id'], $title, mb_strimwidth($body, 0, 120, '…'), 'announcement', BASE_URL . '/admin/announcements.php');
        }
        audit_log($user['id'], 'announcement.create', 'announcements', $annId);
        flash('success', 'Announcement posted to ' . count($recipients) . ' user(s).');
    } elseif ($action === 'delete') {
        $id = (int)input('announcement_id');
        $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([$id]);
        audit_log($user['id'], 'announcement.delete', 'announcements', $id);
        flash('success', 'Announcement deleted.');
    }
    redirect('/admin/announcements.php');
}

$announcements = $pdo->query("SELECT a.*, u.first_name, u.last_name FROM announcements a
                               JOIN users u ON u.id = a.created_by ORDER BY a.created_at DESC LIMIT 30")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-1 card p-5 h-fit">
    <h2 class="font-semibold mb-4">New Announcement</h2>
    <form method="POST" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div><label class="text-sm font-medium">Title</label><input required name="title" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Message</label><textarea required name="body" rows="4" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></textarea></div>
      <div><label class="text-sm font-medium">Audience</label>
        <select name="audience" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
          <option value="all">Everyone</option><option value="teacher">Teachers</option><option value="student">Students</option>
        </select>
      </div>
      <button class="w-full bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg py-2.5">Post announcement</button>
    </form>
  </div>

  <div class="lg:col-span-2 space-y-3">
    <?php foreach ($announcements as $a): ?>
      <div class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="font-semibold"><?= e($a['title']) ?></h3>
            <p class="text-xs text-slate-400 mt-0.5">By <?= e($a['first_name'].' '.$a['last_name']) ?> · <?= time_ago($a['created_at']) ?> · <span class="badge bg-blue-50 text-primary"><?= e($a['audience']) ?></span></p>
          </div>
          <form method="POST" data-confirm="Delete this announcement?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
            <button class="text-rose-500 hover:underline text-xs">Delete</button>
          </form>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-300 mt-2"><?= nl2br(e($a['body'])) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if (!$announcements): ?><p class="text-slate-400 text-sm">No announcements yet.</p><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
