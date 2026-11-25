<?php
// Shared page header. Set $pageTitle before including this file.
$navUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Club Event System') ?> | Club Event System</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="app">
  <nav class="nav">
    <a class="brand" href="dashboard.php">Club Events</a>
    <a href="dashboard.php">Dashboard</a>
    <?php if ($navUser && in_array($navUser['role'], ['admin', 'organizer'], true)): ?>
      <a href="events_manage.php">Manage events</a>
    <?php endif; ?>
    <span class="spacer"></span>
    <?php if ($navUser): ?>
      <span class="who"><?= e($navUser['name']) ?> (<?= e($navUser['role']) ?>)</span>
      <a href="logout.php" id="logout-link">Log out</a>
    <?php endif; ?>
  </nav>
  <main class="container">
    <?php flash_show(); ?>
