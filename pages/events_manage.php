<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'organizer']);

$user    = current_user();
$isAdmin = $user['role'] === 'admin';

$sql = "SELECT e.id, e.title, e.event_date, e.start_time, e.end_time, e.venue,
               e.capacity, e.status, c.name AS category, u.name AS organizer,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.id AND r.status IN ('registered','attended')) AS reg_count
        FROM events e
        LEFT JOIN categories c ON c.id = e.category_id
        JOIN users u ON u.id = e.created_by";
$params = [];
if (!$isAdmin) {
    $sql .= ' WHERE e.created_by = ?';
    $params[] = $user['id'];
}
$sql .= ' ORDER BY e.event_date DESC, e.start_time DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$pageTitle = 'Manage events';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar">
  <h1><?= $isAdmin ? 'All events' : 'My events' ?></h1>
  <a class="btn btn-primary" id="create-event-btn" href="event_form.php">Create event</a>
</div>

<?php if (!$events): ?>
  <p class="muted">No events yet. Create your first event.</p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="events-table">
  <thead>
    <tr>
      <th>Title</th><th>Date</th><th>Time</th><th>Venue</th><th>Seats</th><th>Status</th>
      <?php if ($isAdmin): ?><th>Organizer</th><?php endif; ?>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($events as $ev): ?>
    <tr>
      <td><?= e($ev['title']) ?><?php if ($ev['category']): ?><br><small class="muted"><?= e($ev['category']) ?></small><?php endif; ?></td>
      <td><?= e(date('d M Y', strtotime($ev['event_date']))) ?></td>
      <td><?= e(substr($ev['start_time'], 0, 5)) ?> - <?= e(substr($ev['end_time'], 0, 5)) ?></td>
      <td><?= e($ev['venue']) ?></td>
      <td><?= (int)$ev['reg_count'] ?> / <?= (int)$ev['capacity'] ?></td>
      <td><span class="badge badge-<?= e($ev['status']) ?>"><?= e($ev['status']) ?></span></td>
      <?php if ($isAdmin): ?><td><?= e($ev['organizer']) ?></td><?php endif; ?>
      <td class="actions">
        <a class="btn" href="performances.php?event_id=<?= (int)$ev['id'] ?>">Schedule</a>
        <a class="btn" href="checkin.php?event_id=<?= (int)$ev['id'] ?>">Check-in</a>
        <a class="btn" href="event_form.php?id=<?= (int)$ev['id'] ?>">Edit</a>
        <form method="post" action="event_delete.php" class="inline"
              onsubmit="return confirm('Delete this event and all its registrations?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
