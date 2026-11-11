<?php
require_once __DIR__ . '/../config/app.php';
$user = require_role('admin');
$pageTitle = 'Grade Levels';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input('action');
    try {
        $name = input('name');
        $order = (int)input('display_order', 0);

        if ($action === 'create') {
            if (!$name) throw new RuntimeException('Grade level name is required.');
            $pdo->prepare('INSERT INTO grade_levels (name, display_order) VALUES (?,?)')->execute([$name, $order]);
            audit_log($user['id'], 'grade_level.create', 'grade_levels', $pdo->lastInsertId());
            flash('success', 'Grade level added.');
        } elseif ($action === 'update') {
            $id = (int)input('grade_level_id');
            if (!$name) throw new RuntimeException('Grade level name is required.');
            // Keep every class's cached `name` column in sync with the rename,
            // since the rest of the app reads classes.name directly.
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE grade_levels SET name=?, display_order=? WHERE id=?')->execute([$name, $order, $id]);
            $pdo->prepare('UPDATE classes SET name=? WHERE grade_level_id=?')->execute([$name, $id]);
            $pdo->commit();
            audit_log($user['id'], 'grade_level.update', 'grade_levels', $id);
            flash('success', 'Grade level updated (and synced to its classes).');
        } elseif ($action === 'delete') {
            $id = (int)input('grade_level_id');
            $pdo->prepare('DELETE FROM grade_levels WHERE id=?')->execute([$id]);
            audit_log($user['id'], 'grade_level.delete', 'grade_levels', $id);
            flash('success', 'Grade level deleted. Any classes under it are kept, just unlinked — edit them to reassign.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'A grade level with that name already exists.' : 'Could not save grade level — it may still be used by a fee structure.');
    }
    redirect('/admin/grade-levels.php');
}

$gradeLevels = $pdo->query("SELECT g.*,
    (SELECT COUNT(*) FROM classes c WHERE c.grade_level_id = g.id) AS class_count,
    (SELECT COUNT(*) FROM fee_structures f WHERE f.grade_level_id = g.id) AS fee_count
    FROM grade_levels g ORDER BY g.display_order, g.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="flex items-center justify-between mb-5">
  <p class="text-sm text-slate-500 max-w-xl">Grade levels are the master list (e.g. "Grade 1") that classes are grouped under as sections (e.g. "1A", "1B") — set on the <a href="classes.php" class="text-primary hover:underline">Classes</a> page.</p>
  <button onclick="openModal()" class="bg-primary hover:bg-secondary text-white text-sm font-medium rounded-lg px-4 py-2 shrink-0">+ Add Grade Level</button>
</div>

<div class="card overflow-x-auto">
  <table class="data-table w-full">
    <thead><tr><th>Order</th><th>Name</th><th>Classes</th><th>Fee structures</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($gradeLevels as $g): ?>
        <tr>
          <td><?= (int)$g['display_order'] ?></td>
          <td class="font-medium"><?= e($g['name']) ?></td>
          <td><?= (int)$g['class_count'] ?></td>
          <td><?= (int)$g['fee_count'] ?></td>
          <td class="text-right whitespace-nowrap">
            <button onclick='openModal(<?= json_encode($g, JSON_HEX_APOS) ?>)' class="text-primary hover:underline font-medium text-xs">Edit</button>
            <form method="POST" class="inline" data-confirm="Delete '<?= e($g['name']) ?>'? Classes under it will be kept but unlinked.">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="grade_level_id" value="<?= $g['id'] ?>">
              <button class="text-rose-500 hover:underline font-medium text-xs ml-3">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$gradeLevels): ?><tr><td colspan="5" class="text-center text-slate-400 py-8">No grade levels yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div id="modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm">
    <form method="POST" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="grade_level_id" id="glId">
      <h3 class="text-lg font-semibold" id="modalTitle">Add Grade Level</h3>
      <div><label class="text-sm font-medium">Name</label><input required name="name" id="f_name" placeholder="e.g. Grade 1" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1"></div>
      <div><label class="text-sm font-medium">Sort order</label><input required type="number" name="display_order" id="f_display_order" value="0" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 px-3 py-2 text-sm mt-1">
        <p class="text-xs text-slate-400 mt-1">Controls list order (e.g. so "Grade 10" doesn't sort before "Grade 2").</p></div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm rounded-lg border border-slate-300">Cancel</button>
        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-primary text-white hover:bg-secondary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(data) {
  document.getElementById('f_name').value = '';
  document.getElementById('f_display_order').value = 0;
  if (data) {
    document.getElementById('modalTitle').textContent = 'Edit Grade Level';
    document.getElementById('formAction').value = 'update';
    document.getElementById('glId').value = data.id;
    document.getElementById('f_name').value = data.name || '';
    document.getElementById('f_display_order').value = data.display_order || 0;
  } else {
    document.getElementById('modalTitle').textContent = 'Add Grade Level';
    document.getElementById('formAction').value = 'create';
    document.getElementById('glId').value = '';
  }
  document.getElementById('modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('modal').classList.add('hidden'); }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
