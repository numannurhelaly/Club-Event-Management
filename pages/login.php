<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, password_hash, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'   => (int)$user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];
        header('Location: dashboard.php');
        exit;
    }
    // Same message for wrong email and wrong password.
    $error = 'Incorrect email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in | Club Event System</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <main class="card">
    <h1>Log in</h1>
    <?php if (isset($_GET['registered'])): ?>
      <p class="success">Account created. You can log in now.</p>
    <?php endif; ?>
    <?php if ($error): ?>
      <p class="error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" id="login-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Email
        <input type="email" name="email" id="email" value="<?= e($email) ?>" required>
      </label>
      <label>Password
        <input type="password" name="password" id="password" required>
      </label>
      <button type="submit" id="login-btn">Log in</button>
    </form>
    <p>New here? <a href="register.php">Create an account</a></p>
  </main>
</body>
</html>
