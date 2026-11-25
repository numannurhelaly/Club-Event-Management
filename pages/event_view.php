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
<div class="toolbar">
  <h1><?= e($event['title']) ?></h1>
  <a class="btn" href="events.php">Back to events</a>
</div>

<div class="panel" id="event-details">
  <?php if ($event['category']): ?><span class="badge"><?= e($event['category']) ?></span><?php endif; ?>
  <?php if ($event['status'] !== 'published'): ?><span class="badge badge-<?= e($event['status']) ?>"><?= e($event['status']) ?></span><?php endif; ?>
  <p><strong><?= e(date('l, d M Y', strtotime($event['event_date']))) ?></strong>,
     <?= e(substr($event['start_time'], 0, 5)) ?> - <?= e(substr($event['end_time'], 0, 5)) ?></p>
  <p class="muted">Venue: <?= e($event['venue']) ?> | Organizer: <?= e($event['organizer']) ?></p>
  <?php if ($event['description']): ?><p><?= nl2br(e($event['description'])) ?></p><?php endif; ?>

  <div class="event-foot">
    <span class="seats"><?= $left > 0 ? $left . ' of ' . (int)$event['capacity'] . ' seats left' : 'Full' ?></span>
    <?php if ($isOpen && $isRegistered): ?>
      <form method="post" action="event_register.php" class="inline">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= (int)$id ?>">
        <input type="hidden" name="action" value="cancel">
        <span class="badge badge-published">Registered</span>
        <button type="submit" class="btn btn-danger">Cancel</button>
      </form>
    <?php elseif ($isOpen && $left > 0): ?>
      <form method="post" action="event_register.php" class="inline">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="event_id" value="<?= (int)$id ?>">
        <input type="hidden" name="action" value="register">
        <button type="submit" class="btn btn-primary">Register</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<h2 class="section-title">Schedule</h2>
<?php if (!$performances): ?>
  <p class="muted">The schedule has not been announced yet.</p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="schedule-table">
  <thead><tr><th>Time</th><th>Performance</th><th>Performer</th><th>Notes</th></tr></thead>
  <tbody>
  <?php foreach ($performances as $p): ?>
    <tr>
      <td><?= e(substr($p['start_time'], 0, 5)) ?> - <?= e(substr($p['end_time'], 0, 5)) ?></td>
      <td><?= e($p['title']) ?></td>
      <td><?= e($p['member_name'] ?? $p['performer_name']) ?></td>
      <td><?= e($p['notes']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
