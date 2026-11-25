<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= e($user['name']) ?></h1>
<p>Role: <strong><?= e($user['role']) ?></strong></p>

<p>
  <a class="btn btn-primary" href="events.php">Browse events</a>
  <a class="btn" href="my_registrations.php">My registrations</a>
  <?php if (in_array($user['role'], ['admin', 'organizer'], true)): ?>
    <a class="btn" href="events_manage.php"><?= $user['role'] === 'admin' ? 'Manage all events' : 'Manage my events' ?></a>
  <?php endif; ?>
</p>
<?php if ($user['role'] === 'admin'): ?>
  <p class="muted">User management and reports will be added in a later step.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
