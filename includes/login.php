<?php
// POST /api/login.php   body: {"email": "...", "password": "..."}
require_once __DIR__ . '/../includes/api.php';

allow_methods(['POST']);
$body = api_json_body();

$email    = (isset($body['email']) && is_string($body['email'])) ? trim($body['email']) : '';
$password = (isset($body['password']) && is_string($body['password'])) ? $body['password'] : '';
if ($email === '' || $password === '') {
    api_error(400, 'email and password are required.');
}

try {
    $stmt = $pdo->prepare('SELECT id, name, password_hash, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (PDOException $ex) {
    api_error(500, 'Something went wrong. Please try again.');
}

// Same message for a wrong email, a wrong password and a deactivated account.
if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
    api_error(401, 'Incorrect email or password.');
}

session_regenerate_id(true);
$_SESSION['user'] = ['id' => (int)$user['id'], 'name' => $user['name'], 'role' => $user['role']];

json_out(200, [
    'user'       => $_SESSION['user'],
    'csrf_token' => csrf_token(),
]);
