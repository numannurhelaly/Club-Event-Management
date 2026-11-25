<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events_lib.php';
require_role(['admin', 'organizer']);

$user    = current_user();
$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$event   = get_manageable_event($pdo, $eventId, $user);
$self    = 'performances.php?event_id=' . $eventId;

$members = $pdo->query('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name')->fetchAll();
$evStart = substr($event['start_time'], 0, 5);
$evEnd   = substr($event['end_time'], 0, 5);

$errors = [];
$form = ['title' => '', 'performer_id' => '', 'performer_name' => '',
         'start_time' => $evStart, 'end_time' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM performances WHERE id = ? AND event_id = ?')
            ->execute([(int)($_POST['performance_id'] ?? 0), $eventId]);
        flash_set('success', 'Performance removed.');
        header('Location: ' . $self);
        exit;
    }

    if ($action === 'add') {
        $form['title']          = trim($_POST['title'] ?? '');
        $form['performer_id']   = $_POST['performer_id'] ?? '';
        $form['performer_name'] = trim($_POST['performer_name'] ?? '');
        $form['start_time']     = $_POST['start_time'] ?? '';
        $form['end_time']       = $_POST['end_time'] ?? '';
        $form['notes']          = trim($_POST['notes'] ?? '');

        if ($form['title'] === '' || strlen($form['title']) > 150) {
            $errors[] = 'Enter a title (up to 150 characters).';
        }

        $performerId = null;
        if ($form['performer_id'] !== '') {
            $validIds = array_map('strval', array_column($members, 'id'));
            if (!in_array($form['performer_id'], $validIds, true)) {
                $errors[] = 'Choose a valid club member.';
            } else {
                $performerId = (int)$form['performer_id'];
            }
        }
        if ($performerId === null && $form['performer_name'] === '') {
            $errors[] = 'Choose a club member or type a guest performer name.';
        }
        if (strlen($form['performer_name']) > 100) {
            $errors[] = 'Guest name is too long (max 100 characters).';
        }
        if (strlen($form['notes']) > 255) {
            $errors[] = 'Notes are too long (max 255 characters).';
        }

        $s = DateTime::createFromFormat('H:i', $form['start_time']);
        $t = DateTime::createFromFormat('H:i', $form['end_time']);
        if (!$s || !$t) {
            $errors[] = 'Choose a valid start and end time.';
        } elseif ($form['end_time'] <= $form['start_time']) {
            $errors[] = 'End time must be after start time.';
        } elseif ($form['start_time'] < $evStart || $form['end_time'] > $evEnd) {
            $errors[] = "Performance must be inside the event time ($evStart - $evEnd).";
        }

        if (!$errors) {
            $pdo->prepare(
                'INSERT INTO performances (event_id, title, performer_id, performer_name, start_time, end_time, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $eventId,
                $form['title'],
                $performerId,
                $performerId === null ? $form['performer_name'] : null,
                $form['start_time'],
                $form['end_time'],
                $form['notes'] !== '' ? $form['notes'] : null,
            ]);
            flash_set('success', 'Performance added.');
            header('Location: ' . $self);
            exit;
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.title, p.start_time, p.end_time, p.notes, p.performer_name, u.name AS member_name
     FROM performances p
     LEFT JOIN users u ON u.id = p.performer_id
     WHERE p.event_id = ?
     ORDER BY p.start_time'
);
$stmt->execute([$eventId]);
$performances = $stmt->fetchAll();

$pageTitle = 'Schedule';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar">
  <h1>Schedule: <?= e($event['title']) ?></h1>
  <a class="btn" href="events_manage.php">Back to events</a>
</div>
<p class="muted"><?= e(date('D, d M Y', strtotime($event['event_date']))) ?>, <?= e($evStart) ?> - <?= e($evEnd) ?> at <?= e($event['venue']) ?></p>

<?php if (!$performances): ?>
  <p class="muted" id="no-performances">No performances added yet.</p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="performances-table">
  <thead><tr><th>Time</th><th>Performance</th><th>Performer</th><th>Notes</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($performances as $p): ?>
    <tr>
      <td><?= e(substr($p['start_time'], 0, 5)) ?> - <?= e(substr($p['end_time'], 0, 5)) ?></td>
      <td><?= e($p['title']) ?></td>
      <td><?= e($p['member_name'] ?? $p['performer_name']) ?></td>
      <td><?= e($p['notes']) ?></td>
      <td class="actions">
        <form method="post" class="inline" onsubmit="return confirm('Remove this performance?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="event_id" value="<?= (int)$eventId ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="performance_id" value="<?= (int)$p['id'] ?>">
          <button type="submit" class="btn btn-danger">Remove</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<h2 class="section-title">Add performance</h2>
<?php foreach ($errors as $err): ?>
  <p class="error"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" class="panel" id="performance-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="event_id" value="<?= (int)$eventId ?>">
  <input type="hidden" name="action" value="add">
  <div class="form-grid">
    <label class="full">Title
      <input type="text" name="title" id="perf-title" value="<?= e($form['title']) ?>" required>
    </label>
    <label>Club member
      <select name="performer_id" id="perf-member">
        <option value="">None (guest performer)</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= (string)$form['performer_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Guest performer name
      <input type="text" name="performer_name" id="perf-guest" value="<?= e($form['performer_name']) ?>">
    </label>
    <label>Start time
      <input type="time" name="start_time" id="perf-start" value="<?= e($form['start_time']) ?>" required>
    </label>
    <label>End time
      <input type="time" name="end_time" id="perf-end" value="<?= e($form['end_time']) ?>" required>
    </label>
    <label class="full">Notes (optional)
      <input type="text" name="notes" id="perf-notes" value="<?= e($form['notes']) ?>">
    </label>
  </div>
  <p class="muted">If you choose a club member, the guest name is ignored.</p>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary" id="add-performance-btn">Add performance</button>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
