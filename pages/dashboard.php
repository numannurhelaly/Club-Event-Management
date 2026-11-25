<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= e($user['name']) ?></h1>
<p>Role: <strong><?= e($user['role']) ?></strong></p>

<?php if ($user['role'] === 'admin'): ?>
  <p><a class="btn btn-primary" href="events_manage.php">Manage all events</a></p>
  <p class="muted">User management and reports will be added in a later step.</p>
<?php elseif ($user['role'] === 'organizer'): ?>
  <p><a class="btn btn-primary" href="events_manage.php">Manage my events</a></p>
<?php else: ?>
  <p class="muted">Browsing and registering for events will be added in the next step.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
