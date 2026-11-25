<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();

$stmt = $pdo->prepare(
    "SELECT r.status AS reg_status, r.registered_at,
            e.id AS event_id, e.title, e.venue, e.event_date, e.start_time, e.end_time,
            e.status AS event_status
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ?
     ORDER BY e.event_date DESC, e.start_time DESC"
);
$stmt->execute([$user['id']]);
$rows  = $stmt->fetchAll();
$today = date('Y-m-d');

$pageTitle = 'My registrations';
require __DIR__ . '/../includes/header.php';
?>
<h1>My registrations</h1>

<?php if (!$rows): ?>
  <p class="muted">You haven't registered for any event yet. <a href="events.php">Browse events</a></p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="my-registrations-table">
  <thead>
    <tr><th>Event</th><th>Date</th><th>Time</th><th>Venue</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r):
      $canCancel = $r['reg_status'] === 'registered'
                && $r['event_status'] === 'published'
                && $r['event_date'] >= $today;
  ?>
    <tr>
      <td><?= e($r['title']) ?></td>
      <td><?= e(date('d M Y', strtotime($r['event_date']))) ?></td>
      <td><?= e(substr($r['start_time'], 0, 5)) ?> - <?= e(substr($r['end_time'], 0, 5)) ?></td>
      <td><?= e($r['venue']) ?></td>
      <td><span class="badge badge-<?= e($r['reg_status']) ?>"><?= e($r['reg_status']) ?></span></td>
      <td class="actions">
        <?php if ($canCancel): ?>
        <form method="post" action="event_register.php" class="inline"
              onsubmit="return confirm('Cancel your registration?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="event_id" value="<?= (int)$r['event_id'] ?>">
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="back" value="my">
          <button type="submit" class="btn btn-danger">Cancel</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
