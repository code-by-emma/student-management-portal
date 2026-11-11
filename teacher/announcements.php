<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('teacher');
$pageTitle = 'Announcements';
$teacherId = $user['linked_id'];

$courses = $pdo->prepare("SELECT co.id, sub.name AS subject_name, c.id AS class_id, c.name AS class_name, c.section
                          FROM courses co JOIN subjects sub ON sub.id=co.subject_id JOIN classes c ON c.id=co.class_id
                          WHERE co.teacher_id=? ORDER BY sub.name");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();
$courseIds = array_column($courses, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $courseId = (int)input('course_id');
        if (!in_array($courseId, $courseIds, true)) throw new RuntimeException('You do not have access to that course.');
        $title = input('title'); $body = input('body');
        if (!$title || !$body) throw new RuntimeException('Title and message are required.');

        $classId = null;
        foreach ($courses as $c) if ($c['id'] == $courseId) $classId = $c['class_id'];

        $stmt = $pdo->prepare('INSERT INTO announcements (title, body, audience, class_id, created_by) VALUES (?,?,"student",?,?)');
        $stmt->execute([$title, $body, $classId, $user['id']]);
        $annId = $pdo->lastInsertId();

        $students = $pdo->prepare('SELECT s.user_id FROM enrollments e JOIN students s ON s.id=e.student_id WHERE e.course_id=?');
        $students->execute([$courseId]);
        foreach ($students->fetchAll() as $s) {
            notify($s['user_id'], $title, mb_strimwidth($body, 0, 120, '…'), 'announcement', BASE_URL.'/student/dashboard.php');
        }
        audit_log($user['id'], 'announcement.create', 'announcements', $annId);
        flash('success', 'Announcement sent.');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/teacher/announcements.php');
}

$announcements = [];
if ($courseIds) {
    $announcements = $pdo->prepare("SELECT a.* FROM announcements a WHERE a.created_by = ? ORDER BY a.created_at DESC LIMIT 20");
    $announcements->execute([$user['id']]);
    $announcements = $announcements->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-1 card p-5 h-fit">
    <h2 class="font-semibold mb-4">New Announcement</h2>
    <?php if (!$courses): ?>
      <p class="text-slate-400 text-sm">No courses assigned to you yet.</p>
    <?php else: ?>
      <form method="POST" class="space-y-3">
        <?= csrf_field() ?>
        <div><label class="text-sm font-medium">Course / class</label>
          <select required name="course_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
            <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['subject_name'].' — '.$c['class_name'].' '.$c['section']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label class="text-sm font-medium">Title</label><input required name="title" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
        <div><label class="text-sm font-medium">Message</label><textarea required name="body" rows="4" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></textarea></div>
        <button class="w-full bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg py-2.5">Send to enrolled students</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="lg:col-span-2 space-y-3">
    <?php foreach ($announcements as $a): ?>
      <div class="card p-5">
        <h3 class="font-semibold"><?= e($a['title']) ?></h3>
        <p class="text-xs text-slate-400 mt-0.5"><?= time_ago($a['created_at']) ?></p>
        <p class="text-sm text-slate-600 dark:text-slate-300 mt-2"><?= nl2br(e($a['body'])) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if (!$announcements): ?><p class="text-slate-400 text-sm">You haven't sent any announcements yet.</p><?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
