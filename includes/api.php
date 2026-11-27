<?php
// Helpers shared by all JSON API endpoints in /api.
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function json_out(int $status, ?array $payload = null): void {
    http_response_code($status);
    if ($payload !== null && $status !== 204) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Error body: {"error": "...", "errors": {field: message}}
function api_error(int $status, string $message, array $errors = []): void {
    $body = ['error' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    json_out($status, $body);
}

function allow_methods(array $methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        api_error(405, 'Method not allowed.');
    }
}

function api_require_login(): array {
    if (!is_logged_in()) {
        api_error(401, 'Please log in first.');
    }
    return current_user();
}

function api_require_role(array $roles): array {
    $user = api_require_login();
    if (!in_array($user['role'], $roles, true)) {
        api_error(403, 'You do not have permission to do this.');
    }
    return $user;
}

// Requests that change data must send the token from /api/login.php or /api/me.php
// in the X-CSRF-Token header.
function api_csrf_check(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf']) || !is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        api_error(403, 'Missing or invalid CSRF token. Send the X-CSRF-Token header.');
    }
}

function api_json_body(): array {
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== 0) {
        api_error(415, 'Content-Type must be application/json.');
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        api_error(400, 'Request body must be valid JSON.');
    }
    return $data;
}

// Positive integer from a value, or null when it isn't one.
function api_int($value): ?int {
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
        return (int)$value;
    }
    return null;
}
