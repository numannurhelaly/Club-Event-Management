<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');

    try {
        if ($action === 'add' || $action === 'rename') {
            if ($name === '' || strlen($name) > 80) {
                flash_set('error', 'Category name must be 1 to 80 characters.');
            } else {
                // Compared case-insensitively because of the table collation.
                $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id <> ?');
                $stmt->execute([$name, $action === 'rename' ? $id : 0]);
                if ($stmt->fetch()) {
                    flash_set('error', 'A category with that name already exists.');
                } elseif ($action === 'add') {
                    $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
                    flash_set('success', 'Category added.');
                } else {
                    $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
                    flash_set('success', 'Category renamed.');
                }
            }
        } elseif ($action === 'delete') {
            // Events keep existing; their category just becomes empty (ON DELETE SET NULL).
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash_set('success', 'Category deleted.');
        } else {
            flash_set('error', 'Invalid action.');
        }
    } catch (PDOException $ex) {
        flash_set('error', 'Something went wrong. Please try again.');
    }
    header('Location: admin_categories.php');
    exit;
}

$categories = $pdo->query(
    'SELECT c.id, c.name, COUNT(e.id) AS event_count
     FROM categories c
     LEFT JOIN events e ON e.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY c.name'
)->fetchAll();

$pageTitle = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar"><h1>Categories</h1></div>

<form method="post" class="filter-bar" id="add-category-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add">
  <input type="text" name="name" id="new-category" placeholder="New category name" maxlength="80" required>
  <button type="submit" class="btn btn-primary" id="add-category-btn">Add category</button>
</form>

<?php if (!$categories): ?>
  <p class="muted">No categories yet.</p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="categories-table">
  <thead><tr><th>Name</th><th>Events</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($categories as $c): ?>
    <tr>
      <td>
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="rename">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="80" required>
          <button type="submit" class="btn">Rename</button>
        </form>
      </td>
      <td><?= (int)$c['event_count'] ?></td>
      <td class="actions">
        <form method="post" class="inline"
              onsubmit="return confirm('Delete this category? Its events will stay, but without a category.');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
