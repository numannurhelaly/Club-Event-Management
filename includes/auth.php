<?php
// Session, role check, CSRF, flash messages and helper functions.
date_default_timezone_set('Asia/Dhaka');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Usage: require_role(['admin', 'organizer']);
function require_role(array $roles) {
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        exit('403 Forbidden: you do not have access to this page.');
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check() {
    if (
        !isset($_POST['csrf'], $_SESSION['csrf']) ||
        !hash_equals($_SESSION['csrf'], $_POST['csrf'])
    ) {
        http_response_code(400);
        exit('Invalid request.');
    }
}

// One-time message shown after a redirect. type: "success" or "error".
function flash_set($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

function flash_show() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<p class="' . e($f['type']) . '" id="flash">' . e($f['msg']) . '</p>';
    }
}
