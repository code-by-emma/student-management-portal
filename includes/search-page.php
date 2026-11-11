<?php
/** Shared topbar search results, included by each role's search.php */
$pageTitle = 'Search results';
$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $like = "%$q%";
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT s.id, u.first_name, u.last_name, 'Student' AS kind, 'student-view.php?id=' AS href
                                FROM students s JOIN users u ON u.id=s.user_id
                                WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR s.admission_no LIKE ? LIMIT 10");
        $stmt->execute([$like, $like, $like]);
        foreach ($stmt->fetchAll() as $r) $results[] = ['label' => $r['first_name'].' '.$r['last_name'].' (Student)', 'href' => 'student-view.php?id='.$r['id']];

        $stmt = $pdo->prepare("SELECT t.id, u.first_name, u.last_name FROM teachers t JOIN users u ON u.id=t.user_id
                                WHERE u.first_name LIKE ? OR u.last_name LIKE ? LIMIT 10");
        $stmt->execute([$like, $like]);
        foreach ($stmt->fetchAll() as $r) $results[] = ['label' => $r['first_name'].' '.$r['last_name'].' (Teacher)', 'href' => 'teachers.php?q='.urlencode($r['first_name'])];

        $stmt = $pdo->prepare("SELECT co.id, s.name FROM courses co JOIN subjects s ON s.id=co.subject_id WHERE s.name LIKE ? LIMIT 10");
        $stmt->execute([$like]);
        foreach ($stmt->fetchAll() as $r) $results[] = ['label' => $r['name'].' (Course)', 'href' => 'courses.php'];
    } elseif ($user['role'] === 'teacher') {
        $stmt = $pdo->prepare("SELECT s.id, u.first_name, u.last_name FROM students s JOIN users u ON u.id=s.user_id
                                JOIN enrollments e ON e.student_id=s.id JOIN courses c ON c.id=e.course_id
                                WHERE c.teacher_id = ? AND (u.first_name LIKE ? OR u.last_name LIKE ?) GROUP BY s.id LIMIT 15");
        $stmt->execute([$user['linked_id'], $like, $like]);
        foreach ($stmt->fetchAll() as $r) $results[] = ['label' => $r['first_name'].' '.$r['last_name'].' (Student)', 'href' => 'grades.php'];
    } else { // student
        $stmt = $pdo->prepare("SELECT co.id, sub.name FROM courses co JOIN subjects sub ON sub.id=co.subject_id
                                JOIN enrollments e ON e.course_id=co.id WHERE e.student_id=? AND sub.name LIKE ? LIMIT 15");
        $stmt->execute([$user['linked_id'], $like]);
        foreach ($stmt->fetchAll() as $r) $results[] = ['label' => $r['name'].' (Course)', 'href' => 'courses.php'];
    }
}

include __DIR__ . '/header.php';
?>
<p class="text-sm text-slate-500 mb-4">Results for "<?= e($q) ?>"</p>
<div class="card divide-y divide-slate-100 dark:divide-slate-700">
  <?php if (!$results): ?><p class="text-slate-400 text-sm p-6 text-center">No matches found.</p><?php endif; ?>
  <?php foreach ($results as $r): ?>
    <a href="<?= e($r['href']) ?>" class="block p-4 text-sm hover:bg-slate-50 dark:hover:bg-slate-700"><?= e($r['label']) ?></a>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
