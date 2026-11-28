<?php
// Draws one event as a ticket. The same markup is built in assets/js/events.js for live search.
const ICON_CLOCK = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
const ICON_PIN   = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';

function render_ticket(array $ev, string $csrf): void {
    $id           = (int)$ev['id'];
    $left         = max(0, (int)$ev['capacity'] - (int)$ev['taken']);
    $isRegistered = in_array($ev['my_status'], ['registered', 'attended'], true);
    $ts           = strtotime($ev['event_date']);
    $classes      = 'ticket' . ($isRegistered ? ' is-registered' : '') . (!$isRegistered && $left === 0 ? ' is-full' : '');

    if ($left === 0)      { $seats = 'Full'; }
    elseif ($left <= 5)   { $seats = 'Only ' . $left . ' left'; }
    else                  { $seats = $left . ' seats left'; }
    ?>
    <article class="<?= e($classes) ?>" data-event-id="<?= $id ?>">
      <div class="ticket-main">
        <div class="ticket-top">
          <?php if ($ev['category']): ?><span class="badge"><?= e($ev['category']) ?></span><?php else: ?><span></span><?php endif; ?>
          <?php if ($isRegistered): ?><span class="stamp">You're in</span><?php endif; ?>
        </div>
        <h3 class="ticket-title"><a href="event_view.php?id=<?= $id ?>"><?= e($ev['title']) ?></a></h3>
        <p class="ticket-meta"><span class="icon"><?= ICON_CLOCK ?></span><span><?= e(substr($ev['start_time'], 0, 5)) ?> - <?= e(substr($ev['end_time'], 0, 5)) ?></span></p>
        <p class="ticket-meta"><span class="icon"><?= ICON_PIN ?></span><span><?= e($ev['venue']) ?></span></p>
        <?php if ($ev['description']): ?><p class="ticket-desc"><?= e($ev['description']) ?></p><?php endif; ?>
        <div class="ticket-actions">
          <?php if ($isRegistered): ?>
            <form method="post" action="event_register.php" class="inline">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="event_id" value="<?= $id ?>">
              <input type="hidden" name="action" value="cancel">
              <button type="submit" class="btn btn-danger">Cancel</button>
            </form>
          <?php elseif ($left > 0): ?>
            <form method="post" action="event_register.php" class="inline">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="event_id" value="<?= $id ?>">
              <input type="hidden" name="action" value="register">
              <button type="submit" class="btn btn-primary">Register</button>
            </form>
          <?php else: ?>
            <button type="button" class="btn" disabled>Full</button>
          <?php endif; ?>
          <a class="link-quiet" href="event_view.php?id=<?= $id ?>">Details</a>
        </div>
      </div>
      <div class="ticket-stub">
        <span class="stub-dow"><?= e(date('D', $ts)) ?></span>
        <span class="stub-day"><?= e(date('j', $ts)) ?></span>
        <span class="stub-month"><?= e(date('M Y', $ts)) ?></span>
        <span class="stub-seats"><?= e($seats) ?></span>
      </div>
    </article>
    <?php
}
