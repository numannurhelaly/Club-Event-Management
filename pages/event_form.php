<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'organizer']);

$user    = current_user();
$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$statuses   = ['draft', 'published', 'cancelled', 'completed'];

$data = [
    'title' => '', 'description' => '', 'category_id' => '', 'venue' => '',
    'event_date' => '', 'start_time' => '', 'end_time' => '',
    'capacity' => 50, 'status' => 'draft',
];

if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        exit('Event not found.');
    }
    // An organizer can only edit their own events.
    if ($user['role'] !== 'admin' && (int)$existing['created_by'] !== $user['id']) {
        http_response_code(403);
        exit('403 Forbidden: this is not your event.');
    }
    $data = array_merge($data, $existing);
    $data['start_time'] = substr($existing['start_time'], 0, 5);
    $data['end_time']   = substr($existing['end_time'], 0, 5);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $data['title']       = trim($_POST['title'] ?? '');
    $data['description'] = trim($_POST['description'] ?? '');
    $data['category_id'] = $_POST['category_id'] ?? '';
    $data['venue']       = trim($_POST['venue'] ?? '');
    $data['event_date']  = $_POST['event_date'] ?? '';
    $data['start_time']  = $_POST['start_time'] ?? '';
    $data['end_time']    = $_POST['end_time'] ?? '';
    $data['capacity']    = $_POST['capacity'] ?? '';
    $data['status']      = $_POST['status'] ?? 'draft';

    if ($data['title'] === '' || strlen($data['title']) > 150) {
        $errors[] = 'Enter a title (up to 150 characters).';
    }
    if ($data['venue'] === '' || strlen($data['venue']) > 150) {
        $errors[] = 'Enter a venue (up to 150 characters).';
    }

    $d = DateTime::createFromFormat('Y-m-d', $data['event_date']);
    if (!$d || $d->format('Y-m-d') !== $data['event_date']) {
        $errors[] = 'Choose a valid event date.';
    } elseif (!$editing && $data['event_date'] < date('Y-m-d')) {
        $errors[] = 'Event date can\'t be in the past.';
    }

    $s = DateTime::createFromFormat('H:i', $data['start_time']);
    $t = DateTime::createFromFormat('H:i', $data['end_time']);
    if (!$s || !$t) {
        $errors[] = 'Choose a valid start and end time.';
    } elseif ($data['end_time'] <= $data['start_time']) {
        $errors[] = 'End time must be after start time.';
    }

    $capacity = filter_var($data['capacity'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5000],
    ]);
    if ($capacity === false) {
        $errors[] = 'Capacity must be a number between 1 and 5000.';
    } elseif ($editing) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registrations
             WHERE event_id = ? AND status IN ('registered','attended')"
        );
        $stmt->execute([$id]);
        $taken = (int)$stmt->fetchColumn();
        if ($capacity < $taken) {
            $errors[] = "Capacity can't be lower than the $taken seats already taken.";
        }
    }

    if (!in_array($data['status'], $statuses, true)) {
        $errors[] = 'Choose a valid status.';
    }

    $categoryId = null;
    if ($data['category_id'] !== '') {
        $validIds = array_column($categories, 'id');
        if (!in_array($data['category_id'], array_map('strval', $validIds), true)) {
            $errors[] = 'Choose a valid category.';
        } else {
            $categoryId = (int)$data['category_id'];
        }
    }

    if (!$errors) {
        if ($editing) {
            $stmt = $pdo->prepare(
                'UPDATE events SET title = ?, description = ?, category_id = ?, venue = ?,
                        event_date = ?, start_time = ?, end_time = ?, capacity = ?, status = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['title'], $data['description'] ?: null, $categoryId, $data['venue'],
                $data['event_date'], $data['start_time'], $data['end_time'], $capacity,
                $data['status'], $id,
            ]);
            flash_set('success', 'Event updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO events (title, description, category_id, venue, event_date,
                                     start_time, end_time, capacity, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['title'], $data['description'] ?: null, $categoryId, $data['venue'],
                $data['event_date'], $data['start_time'], $data['end_time'], $capacity,
                $data['status'], $user['id'],
            ]);
            flash_set('success', 'Event created.');
        }
        header('Location: events_manage.php');
        exit;
    }
}

$pageTitle = $editing ? 'Edit event' : 'Create event';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= $editing ? 'Edit event' : 'Create event' ?></h1>

<?php foreach ($errors as $err): ?>
  <p class="error"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" id="event-form" class="panel">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <div class="form-grid">
    <label class="full">Title
      <input type="text" name="title" id="title" value="<?= e($data['title']) ?>" required>
    </label>
    <label class="full">Description (optional)
      <textarea name="description" id="description" rows="3"><?= e($data['description']) ?></textarea>
    </label>
    <label>Category
      <select name="category_id" id="category_id">
        <option value="">None</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= (string)$data['category_id'] === (string)$cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Venue
      <input type="text" name="venue" id="venue" value="<?= e($data['venue']) ?>" required>
    </label>
    <label>Date
      <input type="date" name="event_date" id="event_date" value="<?= e($data['event_date']) ?>" required>
    </label>
    <label>Capacity
      <input type="number" name="capacity" id="capacity" min="1" max="5000" value="<?= e($data['capacity']) ?>" required>
    </label>
    <label>Start time
      <input type="time" name="start_time" id="start_time" value="<?= e($data['start_time']) ?>" required>
    </label>
    <label>End time
      <input type="time" name="end_time" id="end_time" value="<?= e($data['end_time']) ?>" required>
    </label>
    <label>Status
      <select name="status" id="status">
        <?php foreach ($statuses as $st): ?>
          <option value="<?= e($st) ?>" <?= $data['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <p class="muted">Draft events are hidden from members. Choose "Published" to open registration.</p>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary" id="save-event-btn">Save event</button>
    <a class="btn" href="events_manage.php">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
