<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user    = current_user();
$isAdmin = $user['role'] === 'admin';

if ($isAdmin) {
    $today = date('Y-m-d');

    $users = $pdo->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(role = 'member'), 0)    AS members,
                COALESCE(SUM(role = 'organizer'), 0) AS organizers,
                COALESCE(SUM(role = 'admin'), 0)     AS admins
         FROM users"
    )->fetch();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'published'), 0) AS published,
                COALESCE(SUM(status = 'published' AND event_date >= ?), 0) AS upcoming
         FROM events"
    );
    $stmt->execute([$today]);
    $events = $stmt->fetch();

    $activeRegs = (int)$pdo->query(
        "SELECT COUNT(*) FROM registrations WHERE status IN ('registered','attended')"
    )->fetchColumn();

    // Attendance rate for events that are over: attended / signed up.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(r.status = 'attended'), 0) AS attended,
                COALESCE(SUM(r.status IN ('registered','attended','absent')), 0) AS signed_up
         FROM registrations r
         JOIN events e ON e.id = r.event_id
         WHERE e.status IN ('published','completed')
           AND (e.event_date < ? OR e.status = 'completed')"
    );
    $stmt->execute([$today]);
    $att  = $stmt->fetch();
    $rate = (int)$att['signed_up'] > 0
        ? round((int)$att['attended'] * 100 / (int)$att['signed_up']) . '%'
        : '-';

    $recent = $pdo->query(
        "SELECT u.name, e.title, r.status, r.registered_at
         FROM registrations r
         JOIN users u  ON u.id = r.user_id
         JOIN events e ON e.id = r.event_id
         ORDER BY r.registered_at DESC, r.id DESC
         LIMIT 8"
    )->fetchAll();
}

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= e($user['name']) ?></h1>
<p>Role: <strong><?= e($user['role']) ?></strong></p>

<p>
  <a class="btn btn-primary" href="events.php">Browse events</a>
  <a class="btn" href="my_registrations.php">My registrations</a>
  <?php if (in_array($user['role'], ['admin', 'organizer'], true)): ?>
    <a class="btn" href="events_manage.php"><?= $isAdmin ? 'Manage all events' : 'Manage my events' ?></a>
  <?php endif; ?>
</p>

<?php if ($isAdmin): ?>
  <h2 class="section-title">Overview</h2>
  <div class="stats" id="admin-stats">
    <div class="stat"><small>Users</small><b id="stat-users"><?= (int)$users['total'] ?></b>
      <small class="sub"><?= (int)$users['members'] ?> members, <?= (int)$users['organizers'] ?> organizers, <?= (int)$users['admins'] ?> admins</small></div>
    <div class="stat"><small>Events</small><b id="stat-events"><?= (int)$events['total'] ?></b>
      <small class="sub"><?= (int)$events['published'] ?> published, <?= (int)$events['upcoming'] ?> upcoming</small></div>
    <div class="stat"><small>Active registrations</small><b id="stat-regs"><?= $activeRegs ?></b></div>
    <div class="stat"><small>Attendance rate</small><b id="stat-rate"><?= e($rate) ?></b>
      <small class="sub">of past events</small></div>
  </div>

  <h2 class="section-title">Recent registrations</h2>
  <?php if (!$recent): ?>
    <p class="muted">No registrations yet.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="tbl" id="recent-table">
    <thead><tr><th>Member</th><th>Event</th><th>Status</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['title']) ?></td>
        <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><?= e(date('d M Y, H:i', strtotime($r['registered_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
