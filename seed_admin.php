<?php
// Run ONCE in the browser: http://localhost/club-event-system/seed_admin.php
// Edit the three values below first. DELETE this file after use.
$adminName     = "Admin";
$adminEmail    = "admin@example.com";
$adminPassword = "ChangeMe123!";

require_once __DIR__ . '/config/db.php';

$exists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($exists > 0) {
    exit("An admin already exists. Delete this file.");
}

$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')"
);
$stmt->execute([$adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);

echo "Admin created: $adminEmail. Now delete seed_admin.php.";
