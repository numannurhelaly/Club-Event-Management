<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$me    = current_user();
$roles = ['member', 'organizer', 'admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $targetId = (int)($_POST['user_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    $q        = trim($_POST['q'] ?? '');
    $back     = 'admin_users.php' . ($q !== '' ? '?q=' . urlencode($q) : '');

    if ($targetId === (int)$me['id']) {
        // Stops an admin from locking themselves out.
        flash_set('error', "You can't change your own role or status.");
    } else {
        $stmt = $pdo->prepare('SELECT id, name, role, is_active FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if (!$target) {
            flash_set('error', 'User not found.');
        } elseif ($action === 'update_role') {
            $newRole = $_POST['role'] ?? '';
            if (!in_array($newRole, $roles, true)) {
                flash_set('error', 'Choose a valid role.');
            } else {
                $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
                flash_set('success', 'Role of ' . $target['name'] . ' is now ' . $newRole . '.');
            }
        } elseif ($action === 'toggle_active') {
            $newState = $target['is_active'] ? 0 : 1;
            $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newState, $targetId]);
            flash_set('success', $target['name'] . ($newState ? ' is active again.' : ' has been deactivated.'));
        } else {
            flash_set('error', 'Invalid action.');
        }
    }
    header('Location: ' . $back);
    exit;
}

$q      = trim($_GET['q'] ?? '');
$sql    = 'SELECT id, name, email, student_id, role, is_active, created_at FROM users';
$params = [];
if ($q !== '') {
    $like   = '%' . addcslashes($q, '%_\\') . '%';
    $sql   .= ' WHERE name LIKE ? OR email LIKE ?';
    $params = [$like, $like];
}
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar">
  <h1>Users</h1>
</div>

<form method="get" class="filter-bar" id="user-search-form">
  <input type="text" name="q" id="user-search" placeholder="Search by name or email" value="<?= e($q) ?>" autocomplete="off">
  <button type="submit" class="btn btn-primary">Search</button>
</form>

<?php if (!$list): ?>
  <p class="muted">No users found.</p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="users-table">
  <thead>
    <tr><th>Name</th><th>Email</th><th>Student ID</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($list as $u):
      $isSelf = (int)$u['id'] === (int)$me['id'];
  ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['student_id']) ?></td>
      <td>
        <?php if ($isSelf): ?>
          <span class="badge"><?= e($u['role']) ?></span> <small class="muted">(you)</small>
        <?php else: ?>
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <input type="hidden" name="action" value="update_role">
            <select name="role" id="role-<?= (int)$u['id'] ?>">
              <?php foreach ($roles as $r): ?>
                <option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Save</button>
          </form>
        <?php endif; ?>
      </td>
      <td><span class="badge <?= $u['is_active'] ? 'badge-published' : 'badge-cancelled' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
      <td class="nowrap"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
      <td class="actions">
        <?php if (!$isSelf): ?>
        <form method="post" class="inline"
              onsubmit="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this account?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
          <input type="hidden" name="q" value="<?= e($q) ?>">
          <input type="hidden" name="action" value="toggle_active">
          <button type="submit" class="btn <?= $u['is_active'] ? 'btn-danger' : '' ?>"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="muted">Showing up to 200 users. A deactivated user can't log in, and an active session ends on their next click.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
