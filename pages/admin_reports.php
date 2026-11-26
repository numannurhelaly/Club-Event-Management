<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$statuses = ['draft', 'published', 'cancelled', 'completed'];
$errors   = [];

function valid_date($d) {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

$from   = isset($_GET['from'])   && is_string($_GET['from'])   ? trim($_GET['from'])   : '';
$to     = isset($_GET['to'])     && is_string($_GET['to'])     ? trim($_GET['to'])     : '';
$status = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';

if ($from !== '' && !valid_date($from)) { $errors[] = 'The "from" date is not valid.'; $from = ''; }
if ($to   !== '' && !valid_date($to))   { $errors[] = 'The "to" date is not valid.';   $to   = ''; }
if ($from !== '' && $to !== '' && $from > $to) { $errors[] = '"From" must not be after "to".'; $from = $to = ''; }
if ($status !== '' && !in_array($status, $statuses, true)) { $errors[] = 'Unknown status.'; $status = ''; }

$where  = [];
$params = [];
if ($from   !== '') { $where[] = 'e.event_date >= ?'; $params[] = $from; }
if ($to     !== '') { $where[] = 'e.event_date <= ?'; $params[] = $to; }
if ($status !== '') { $where[] = 'e.status = ?';      $params[] = $status; }

$sql = "SELECT e.id, e.title, e.event_date, e.status, e.capacity, u.name AS organizer,
               COALESCE(SUM(r.status IN ('registered','attended','absent')), 0) AS signed_up,
               COALESCE(SUM(r.status = 'attended'), 0)   AS attended,
               COALESCE(SUM(r.status = 'absent'), 0)     AS absent,
               COALESCE(SUM(r.status = 'registered'), 0) AS pending,
               COALESCE(SUM(r.status = 'cancelled'), 0)  AS cancelled
        FROM events e
        JOIN users u ON u.id = e.created_by
        LEFT JOIN registrations r ON r.event_id = e.id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' GROUP BY e.id, e.title, e.event_date, e.status, e.capacity, u.name
        ORDER BY e.event_date DESC, e.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function rate_text($attended, $signedUp) {
    return $signedUp > 0 ? round($attended * 100 / $signedUp) . '%' : '-';
}

$tot = ['signed_up' => 0, 'attended' => 0, 'absent' => 0, 'pending' => 0, 'cancelled' => 0];
foreach ($rows as $r) {
    foreach ($tot as $k => $_) { $tot[$k] += (int)$r[$k]; }
}

// CSV download (same filters as the page).
if (($_GET['export'] ?? '') === 'csv') {
    // Cells starting with = + - @ could run as formulas in Excel, so we neutralise them.
    $safe = function ($v) {
        $v = (string)$v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
    fputcsv($out, ['Event', 'Date', 'Status', 'Organizer', 'Capacity', 'Signed up', 'Attended', 'Absent', 'Not yet marked', 'Cancelled', 'Attendance rate']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $safe($r['title']), $r['event_date'], $r['status'], $safe($r['organizer']), $r['capacity'],
            $r['signed_up'], $r['attended'], $r['absent'], $r['pending'], $r['cancelled'],
            rate_text((int)$r['attended'], (int)$r['signed_up']),
        ]);
    }
    fclose($out);
    exit;
}

$exportQuery = http_build_query(array_filter(
    ['from' => $from, 'to' => $to, 'status' => $status, 'export' => 'csv'],
    function ($v) { return $v !== ''; }
));

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="toolbar">
  <h1>Attendance report</h1>
  <a class="btn" id="export-csv" href="admin_reports.php?<?= e($exportQuery) ?>">Download CSV</a>
</div>

<?php foreach ($errors as $err): ?>
  <p class="error"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="get" class="filter-bar" id="report-filter">
  <label class="inline-label">From <input type="date" name="from" id="from" value="<?= e($from) ?>"></label>
  <label class="inline-label">To <input type="date" name="to" id="to" value="<?= e($to) ?>"></label>
  <select name="status" id="status-filter">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $s): ?>
      <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary">Apply</button>
  <a class="btn" href="admin_reports.php">Reset</a>
</form>

<?php if (!$rows): ?>
  <p class="muted">No events match these filters.</p>
<?php else: ?>
<div class="table-wrap">
<table class="tbl" id="report-table">
  <thead>
    <tr><th>Event</th><th>Date</th><th>Status</th><th>Organizer</th><th>Capacity</th>
        <th>Signed up</th><th>Attended</th><th>Absent</th><th>Not yet marked</th><th>Cancelled</th><th>Rate</th></tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['title']) ?></td>
      <td class="nowrap"><?= e(date('d M Y', strtotime($r['event_date']))) ?></td>
      <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
      <td><?= e($r['organizer']) ?></td>
      <td><?= (int)$r['capacity'] ?></td>
      <td><?= (int)$r['signed_up'] ?></td>
      <td><?= (int)$r['attended'] ?></td>
      <td><?= (int)$r['absent'] ?></td>
      <td><?= (int)$r['pending'] ?></td>
      <td><?= (int)$r['cancelled'] ?></td>
      <td><?= e(rate_text((int)$r['attended'], (int)$r['signed_up'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="5">Total (<?= count($rows) ?> events)</td>
      <td><?= $tot['signed_up'] ?></td><td><?= $tot['attended'] ?></td><td><?= $tot['absent'] ?></td>
      <td><?= $tot['pending'] ?></td><td><?= $tot['cancelled'] ?></td>
      <td><?= e(rate_text($tot['attended'], $tot['signed_up'])) ?></td>
    </tr>
  </tfoot>
</table>
</div>
<p class="muted">Rate = attended / signed up (registered, attended and absent). Upcoming events show a low rate until check-in is done.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
