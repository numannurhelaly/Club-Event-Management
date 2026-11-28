<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = $email = $student_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm'] ?? '';

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Enter your name (up to 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'That email is already registered.';
        }
    }

    if (!$errors) {
        // Public registration always creates a "member".
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, student_id)
             VALUES (?, ?, ?, "member", ?)'
        );
        $stmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $student_id !== '' ? $student_id : null,
        ]);
        header('Location: login.php?registered=1');
        exit;
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/../includes/auth_open.php';
?>
    <h2>Create account</h2>
    <p class="lead">Join to reserve seats at club events.</p>

    <?php foreach ($errors as $err): ?>
      <p class="error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post" id="register-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Full name
        <input type="text" name="name" id="name" value="<?= e($name) ?>" autocomplete="name" required>
      </label>
      <label>Email
        <input type="email" name="email" id="email" value="<?= e($email) ?>" autocomplete="email" required>
      </label>
      <label>Student ID (optional)
        <input type="text" name="student_id" id="student_id" value="<?= e($student_id) ?>">
      </label>
      <label>Password (at least 8 characters)
        <input type="password" name="password" id="password" autocomplete="new-password" required>
      </label>
      <label>Confirm password
        <input type="password" name="confirm" id="confirm" autocomplete="new-password" required>
      </label>
      <button type="submit" class="btn btn-primary btn-block" id="register-btn">Create account</button>
    </form>
    <p class="auth-note">Already have an account? <a href="login.php">Log in</a></p>
<?php require __DIR__ . '/../includes/auth_close.php'; ?>
