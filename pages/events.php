<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events_lib.php';
require_once __DIR__ . '/../includes/ticket.php';
require_login();

$user       = current_user();
$q          = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$events     = fetch_public_events($pdo, $user['id'], $q, $categoryId);
$csrf       = csrf_token();

$pageTitle    = 'Events';
$extraScripts = ['../assets/js/events.js'];   // live search (AJAX)
require __DIR__ . '/../includes/header.php';
?>
<h1>Upcoming events</h1>
<p class="page-sub">Pick a show and reserve your seat. Your tickets turn rose once you are in.</p>

<form method="get" class="filter-bar" id="filter-form">
  <input type="text" name="q" id="search-input" placeholder="Search by title or venue" value="<?= e($q) ?>" autocomplete="off" aria-label="Search events">
  <select name="category" id="category-filter" aria-label="Category">
    <option value="0">All categories</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Search</button>
</form>

<div id="events-list" data-csrf="<?= e($csrf) ?>">
<?php if (!$events): ?>
  <p class="muted" id="no-events">No shows match your search. Try another word or category.</p>
<?php else: ?>
  <div class="ticket-grid">
    <?php foreach ($events as $ev) { render_ticket($ev, $csrf); } ?>
  </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
