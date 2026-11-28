<?php
// GET /api/me.php  -> the logged-in user and a CSRF token for write requests.
require_once __DIR__ . '/../includes/api.php';

allow_methods(['GET']);
$user = api_require_login();
json_out(200, ['user' => $user, 'csrf_token' => csrf_token()]);
