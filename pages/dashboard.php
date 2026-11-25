<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | Club Event System</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <main class="card">
    <h1>Welcome, <?= e($user['name']) ?></h1>
    <p>Role: <strong><?= e($user['role']) ?></strong></p>

    <?php if ($user['role'] === 'admin'): ?>
      <p>Admin tools will appear here (users, categories, reports).</p>
    <?php elseif ($user['role'] === 'organizer'): ?>
      <p>Organizer tools will appear here (create events, check-in).</p>
    <?php else: ?>
      <p>Member tools will appear here (browse and register for events).</p>
    <?php endif; ?>

    <p><a href="logout.php">Log out</a></p>
  </main>
</body>
</html>
