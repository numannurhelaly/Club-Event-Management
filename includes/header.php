<?php
// Shared page header. Set $pageTitle before including this file.
$navUser = current_user();
$current = basename($_SERVER['SCRIPT_NAME']);

if (!function_exists('nav_link')) {
    // $files: pages for which this link should look "active".
    function nav_link(array $files, string $label, string $current): void {
        $href   = $files[0];
        $active = in_array($current, $files, true) ? ' class="is-active" aria-current="page"' : '';
        echo '<a href="' . e($href) . '"' . $active . '>' . e($label) . '</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1D1646">
  <title><?= e($pageTitle ?? 'Club Event System') ?> | AIUB Arts Club</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Young+Serif&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="app">
  <nav class="nav" aria-label="Main">
    <a class="brand" href="dashboard.php">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#FFB627" d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a3 3 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a3 3 0 0 0 0-6z"/></svg>
      AIUB Arts Club
    </a>
    <?php if ($navUser): ?>
      <?php nav_link(['dashboard.php'], 'Home', $current); ?>
      <?php nav_link(['events.php', 'event_view.php'], 'Events', $current); ?>
      <?php nav_link(['my_registrations.php'], 'My tickets', $current); ?>
      <?php if (in_array($navUser['role'], ['admin', 'organizer'], true)): ?>
        <?php nav_link(['events_manage.php', 'event_form.php', 'performances.php', 'checkin.php'], 'Manage events', $current); ?>
      <?php endif; ?>
      <?php if ($navUser['role'] === 'admin'): ?>
        <?php nav_link(['admin_users.php'], 'Users', $current); ?>
        <?php nav_link(['admin_categories.php'], 'Categories', $current); ?>
        <?php nav_link(['admin_reports.php'], 'Reports', $current); ?>
      <?php endif; ?>
    <?php endif; ?>
    <span class="spacer"></span>
    <?php if ($navUser): ?>
      <span class="who">
        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($navUser['name'], 0, 1))) ?></span>
        <span class="who-text"><?= e($navUser['name']) ?><small><?= e($navUser['role']) ?></small></span>
      </span>
      <a class="logout" href="logout.php" id="logout-link">Log out</a>
    <?php endif; ?>
  </nav>
  <main class="container">
    <?php flash_show(); ?>
