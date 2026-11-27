<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/events_lib.php';
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
    if ($action === 'register') {
        $message = register_user_for_event($pdo, $eventId, (int)$user['id']);
    } elseif ($action === 'cancel') {
        $message = cancel_user_registration($pdo, $eventId, (int)$user['id']);
    } else {
        throw new RuntimeException('Invalid action.', 400);
    }
    flash_set('success', $message);
} catch (RuntimeException $ex) {
    flash_set('error', $ex->getMessage());
} catch (PDOException $ex) {
    flash_set('error', 'Something went wrong. Please try again.');
}

header('Location: ' . $back);
exit;
