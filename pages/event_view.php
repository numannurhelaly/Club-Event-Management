<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$id   = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT e.*, c.name AS category, u.name AS organizer
     FROM events e
     LEFT JOIN categories c ON c.id = e.category_id
     JOIN users u ON u.id = e.created_by
     WHERE e.id = ?'
);
$stmt->execute([$id]);
$event = $stmt->fetch();

$isOwnerOrAdmin = $event && ($user['role'] === 'admin' || (int)$event['created_by'] === (int)$user['id']);
// Unpublished events are hidden from everyone except the owner and admins.
if (!$event || ($event['status'] !== 'published' && !$isOwnerOrAdmin)) {
    http_response_code(404);
    exit('Event not found.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('registered','attended')");
$stmt->execute([$id]);
$taken = (int)$stmt->fetchColumn();
$left  = max(0, (int)$event['capacity'] - $taken);

$stmt = $pdo->prepare('SELECT status FROM registrations WHERE event_id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$myStatus     = $stmt->fetchColumn();
$isRegistered = in_array($myStatus, ['registered', 'attended'], true);
$isOpen       = $event['status'] === 'published' && $event['event_date'] >= date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT p.title, p.start_time, p.end_time, p.notes, p.performer_name, u.name AS member_name
     FROM performances p
     LEFT JOIN users u ON u.id = p.performer_id
     WHERE p.event_id = ?
     ORDER BY p.start_time'
);
$stmt->execute([$id]);
$performances = $stmt->fetchAll();

$pageTitle = $event['title'];
require __DIR__ . '/../includes/header.php';
?>
<section class="poster" id="event-details">
  <div class="poster-body">
    <div>
      <?php if ($event['category']): ?><span class="badge"><?= e($event['category']) ?></span><?php endif; ?>
      <?php if ($event['status'] !== 'published'): ?><span class="badge"><?= e($event['status']) ?></span><?php endif; ?>
    </div>
    <h1><?= e($event['title']) ?></h1>
    <p class="poster-when"><?= e(date('l, j F Y', strtotime($event['event_date']))) ?></p>
    <p class="poster-where"><?= e(substr($event['start_time'], 0, 5)) ?> to <?= e(substr($event['end_time'], 0, 5)) ?> at <?= e($event['venue']) ?></p>
    <p class="poster-by">Organized by <?= e($event['organizer']) ?></p>
  </div>

  <aside class="poster-side">
    <div>
      <div class="seat-count"><?= $left ?></div>
      <p class="seat-label"><?= $left > 0 ? 'of ' . (int)$event['capacity'] . ' seats left' : 'Sold out' ?></p>
    </div>
    <?php if ($isOpen && $isRegistered): ?>
      <span class="stamp" style="align-self:flex-start">You're in</span>
      <form method="post" action="event_register.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= (int)$id ?>">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-danger">Cancel my seat</button>
      </form>
    <?php elseif ($isOpen && $left > 0): ?>
      <form method="post" action="event_register.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= (int)$id ?>">
        <input type="hidden" name="action" value="register">
        <button type="submit" class="btn btn-primary">Reserve my seat</button>
      </form>
    <?php endif; ?>
    <a class="btn" href="events.php">Back to events</a>
  </aside>
</section>

<?php if ($event['description']): ?>
  <div class="about">
    <h2>About this event</h2>
    <p><?= nl2br(e($event['description'])) ?></p>
  </div>
<?php endif; ?>

<h2 class="section-title">Schedule</h2>
<?php if (!$performances): ?>
  <p class="muted">The schedule has not been announced yet.</p>
<?php else: ?>
<ol class="timeline" id="schedule-list">
  <?php foreach ($performances as $p): ?>
    <li>
      <div class="tl-time"><?= e(substr($p['start_time'], 0, 5)) ?><small>until <?= e(substr($p['end_time'], 0, 5)) ?></small></div>
      <div class="tl-body">
        <strong><?= e($p['title']) ?></strong>
        <span><?= e($p['member_name'] ?? $p['performer_name']) ?><?= $p['notes'] ? ': ' . e($p['notes']) : '' ?></span>
      </div>
    </li>
  <?php endforeach; ?>
</ol>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
