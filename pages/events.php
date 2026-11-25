<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events_lib.php';
require_login();

$user       = current_user();
$q          = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$events     = fetch_public_events($pdo, $user['id'], $q, $categoryId);

$pageTitle = 'Events';
require __DIR__ . '/../includes/header.php';
?>
<h1>Upcoming events</h1>

<form method="get" class="filter-bar" id="filter-form">
  <input type="text" name="q" id="search-input" placeholder="Search by title or venue" value="<?= e($q) ?>">
  <select name="category" id="category-filter">
    <option value="0">All categories</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Search</button>
</form>

<div id="events-list">
<?php if (!$events): ?>
  <p class="muted" id="no-events">No upcoming events found.</p>
<?php else: ?>
  <div class="event-grid">
  <?php foreach ($events as $ev):
      $left = max(0, (int)$ev['capacity'] - (int)$ev['taken']);
      $mine = $ev['my_status'];
      $isRegistered = in_array($mine, ['registered', 'attended'], true);
  ?>
    <div class="event-card" data-event-id="<?= (int)$ev['id'] ?>">
      <?php if ($ev['category']): ?><span class="badge"><?= e($ev['category']) ?></span><?php endif; ?>
      <h3><?= e($ev['title']) ?></h3>
      <p class="muted"><?= e(date('D, d M Y', strtotime($ev['event_date']))) ?>, <?= e(substr($ev['start_time'], 0, 5)) ?> - <?= e(substr($ev['end_time'], 0, 5)) ?></p>
      <p class="muted"><?= e($ev['venue']) ?></p>
      <?php if ($ev['description']): ?><p class="desc"><?= e($ev['description']) ?></p><?php endif; ?>
      <div class="event-foot">
        <span class="seats"><?= $left > 0 ? $left . ' seats left' : 'Full' ?></span>
        <?php if ($isRegistered): ?>
          <form method="post" action="event_register.php" class="inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
            <input type="hidden" name="action" value="cancel">
            <span class="badge badge-published">Registered</span>
            <button type="submit" class="btn btn-danger">Cancel</button>
          </form>
        <?php elseif ($left > 0): ?>
          <form method="post" action="event_register.php" class="inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
            <input type="hidden" name="action" value="register">
            <button type="submit" class="btn btn-primary">Register</button>
          </form>
        <?php else: ?>
          <button type="button" class="btn" disabled>Full</button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
