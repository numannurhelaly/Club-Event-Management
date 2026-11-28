<?php
// POST /api/logout.php   header: X-CSRF-Token
require_once __DIR__ . '/../includes/api.php';

allow_methods(['POST']);
api_require_login();
api_csrf_check();

$_SESSION = [];
session_destroy();
json_out(200, ['message' => 'Logged out.']);
