<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}
csrf_check();

$user    = current_user();
$eventId = (int)($_POST['event_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$back    = (($_POST['back'] ?? '') === 'my') ? 'my_registrations.php' : 'events.php';

try {
    $pdo->beginTransaction();

    // Lock the event row so two people can't take the last seat at the same time.
    $stmt = $pdo->prepare(
        "SELECT id, capacity FROM events
         WHERE id = ? AND status = 'published' AND event_date >= ? FOR UPDATE"
    );
    $stmt->execute([$eventId, date('Y-m-d')]);
    $event = $stmt->fetch();
    if (!$event) {
        throw new RuntimeException('This event is not open for registration.');
    }

    $stmt = $pdo->prepare('SELECT id, status FROM registrations WHERE event_id = ? AND user_id = ?');
    $stmt->execute([$eventId, $user['id']]);
    $existing = $stmt->fetch();

    if ($action === 'register') {
        if ($existing && in_array($existing['status'], ['registered', 'attended'], true)) {
            throw new RuntimeException('You are already registered for this event.');
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registrations
             WHERE event_id = ? AND status IN ('registered','attended')"
        );
        $stmt->execute([$eventId]);
        if ((int)$stmt->fetchColumn() >= (int)$event['capacity']) {
            throw new RuntimeException('Sorry, this event is full.');
        }

        if ($existing) {
            // Re-registering after a cancellation.
            $pdo->prepare(
                "UPDATE registrations SET status = 'registered', registered_at = NOW(), checked_in_at = NULL
                 WHERE id = ?"
            )->execute([$existing['id']]);
        } else {
            $pdo->prepare('INSERT INTO registrations (event_id, user_id) VALUES (?, ?)')
                ->execute([$eventId, $user['id']]);
        }
        $message = 'You are registered for this event.';

    } elseif ($action === 'cancel') {
        if (!$existing || $existing['status'] !== 'registered') {
            throw new RuntimeException('You have no active registration to cancel.');
        }
        $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE id = ?")
            ->execute([$existing['id']]);
        $message = 'Registration cancelled.';

    } else {
        throw new RuntimeException('Invalid action.');
    }

    $pdo->commit();
    flash_set('success', $message);

} catch (RuntimeException $ex) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    flash_set('error', $ex->getMessage());
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    flash_set('error', 'Something went wrong. Please try again.');
}

header('Location: ' . $back);
exit;
