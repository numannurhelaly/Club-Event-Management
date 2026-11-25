<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events_lib.php';
require_role(['admin', 'organizer']);

$user    = current_user();
$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$event   = get_manageable_event($pdo, $eventId, $user);
$self    = 'checkin.php?event_id=' . $eventId;

$today   = date('Y-m-d');
$open    = in_array($event['status'], ['published', 'completed'], true);
$started = $event['event_date'] <= $today;                                   // check-in opens on the event day
$ended   = $event['event_date'] < $today || $event['status'] === 'completed'; // absent can be marked after the event

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $regId  = (int)($_POST['registration_id'] ?? 0);

    if (!$open) {
        flash_set('error', 'Check-in works only for published or completed events.');

    } elseif ($action === 'checkin') {
        if (!$started) {
            flash_set('error', 'Check-in opens on the day of the event.');
        } else {
            $stmt = $pdo->prepare(
                "UPDATE registrations SET status = 'attended', checked_in_at = NOW()
                 WHERE id = ? AND event_id = ? AND status IN ('registered','absent')"
            );
            $stmt->execute([$regId, $eventId]);
            $ok = $stmt->rowCount() > 0;
            flash_set($ok ? 'success' : 'error', $ok ? 'Checked in.' : 'Could not check in this person.');
        }

    } elseif ($action === 'undo') {
        $stmt = $pdo->prepare(
            "UPDATE registrations SET status = 'registered', checked_in_at = NULL
             WHERE id = ? AND event_id = ? AND status = 'attended'"
        );
        $stmt->execute([$regId, $eventId]);
        $ok = $stmt->rowCount() > 0;
        flash_set($ok ? 'success' : 'error', $ok ? 'Check-in undone.' : 'Could not undo this check-in.');

    } elseif ($action === 'mark_absent') {
        if (!$ended) {
            flash_set('error', 'You can mark absentees only after the event has ended.');
        } else {
            $stmt = $pdo->prepare(
                "UPDATE registrations SET status = 'absent'
                 WHERE event_id = ? AND status = 'registered'"
            );
            $stmt->execute([$eventId]);
            flash_set('success', $stmt->rowCount() . ' registration(s) marked as absent.');
        }

    } else {
        flash_set('error', 'Invalid action.');
    }

    header('Location: ' . $self);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.id, r.status, r.checked_in_at, u.name, u.email, u.student_id
     FROM registrations r
     JOIN users u ON u.id = r.user_id
     WHERE r.event_id = ? AND r.status <> 'cancelled'
     ORDER BY u.name"
);
$stmt->execute([$eventId]);
$rows = $stmt->fetchAll();

$count = ['registered' => 0, 'attended' => 0, 'absent' => 0];
foreach ($rows as $r) { $count[$r['status']]++; }

$pageTitle = 'Check-in';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar">
  <h1>Check-in: <?= e($event['title']) ?></h1>
  <a class="btn" href="events_manage.php">Back to events</a>
</div>
<p class="muted"><?= e(date('D, d M Y', strtotime($event['event_date']))) ?>, <?= e(substr($event['start_time'], 0, 5)) ?> - <?= e(substr($event['end_time'], 0, 5)) ?> at <?= e($event['venue']) ?></p>

<?php if (!$open): ?>
  <p class="error">This event is <?= e($event['status']) ?>. Check-in is available only for published or completed events.</p>
<?php elseif (!$started): ?>
  <p class="muted">Check-in will open on the day of the event.</p>
<?php endif; ?>

<div class="stats" id="checkin-stats">
  <div class="stat"><small>Registered</small><b id="count-registered"><?= (int)$count['registered'] ?></b></div>
  <div class="stat"><small>Attended</small><b id="count-attended"><?= (int)$count['attended'] ?></b></div>
  <div class="stat"><small>Absent</small><b id="count-absent"><?= (int)$count['absent'] ?></b></div>
  <div class="stat"><small>Capacity</small><b><?= (int)$event['capacity'] ?></b></div>
</div>

<?php if (!$rows): ?>
  <p class="muted">Nobody has registered for this event yet.</p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="checkin-table">
  <thead><tr><th>Name</th><th>Email</th><th>Student ID</th><th>Status</th><th>Checked in</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['email']) ?></td>
      <td><?= e($r['student_id']) ?></td>
      <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
      <td><?= $r['checked_in_at'] ? e(date('d M, H:i', strtotime($r['checked_in_at']))) : '' ?></td>
      <td class="actions">
        <?php if ($open): ?>
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="event_id" value="<?= (int)$eventId ?>">
          <input type="hidden" name="registration_id" value="<?= (int)$r['id'] ?>">
          <?php if ($r['status'] === 'attended'): ?>
            <input type="hidden" name="action" value="undo">
            <button type="submit" class="btn">Undo</button>
          <?php else: ?>
            <input type="hidden" name="action" value="checkin">
            <button type="submit" class="btn btn-primary" <?= $started ? '' : 'disabled' ?>>Check in</button>
          <?php endif; ?>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($open && $ended && $count['registered'] > 0): ?>
<form method="post" class="form-actions" onsubmit="return confirm('Mark everyone who did not check in as absent?');">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="event_id" value="<?= (int)$eventId ?>">
  <input type="hidden" name="action" value="mark_absent">
  <button type="submit" class="btn" id="mark-absent-btn">Mark remaining as absent</button>
</form>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
