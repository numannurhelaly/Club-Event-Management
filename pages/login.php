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

$pageTitle = 'Log in';
require __DIR__ . '/../includes/auth_open.php';
?>
    <h2>Log in</h2>
    <p class="lead">Welcome back. Your tickets are waiting.</p>

    <?php if (isset($_GET['registered'])): ?>
      <p class="success">Account created. You can log in now.</p>
    <?php endif; ?>
    <?php if ($error): ?>
      <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" id="login-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Email
        <input type="email" name="email" id="email" value="<?= e($email) ?>" autocomplete="email" required>
      </label>
      <label>Password
        <input type="password" name="password" id="password" autocomplete="current-password" required>
      </label>
      <button type="submit" class="btn btn-primary btn-block" id="login-btn">Log in</button>
    </form>
    <p class="auth-note">New to the club? <a href="register.php">Create an account</a></p>
<?php require __DIR__ . '/../includes/auth_close.php'; ?>
