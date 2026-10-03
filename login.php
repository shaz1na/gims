<?php
// ============================================================
// login.php — SECURE VERSION
// ============================================================
// ✅ Prepared statement — no SQL Injection possible
// ✅ password_verify() — bcrypt hash comparison
// ✅ session_regenerate_id() — prevent session fixation
// ✅ CSRF token — form protection
// ✅ Generic error messages — don't reveal whether user exists
// ============================================================

require_once 'auth.php';
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error   = '';
$success = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $error = "Your session expired. Please log in again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    validateCsrfToken($posted_token);

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}

$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Jitu Unggul IMS (SECURE)</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">

        <div class="secure-banner" style="margin-bottom:20px">
            🔒 SECURE VERSION
        </div>

        <div class="login-logo">
            <div class="logo-icon">🏢</div>
            <div class="logo-title">JITU UNGGUL</div>
            <div class="logo-sub">Inventory Management System</div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div class="form-group">
                <label class="form-label">Username</label>
                <input
                    type="text"
                    name="username"
                    class="form-control"
                    placeholder="Enter username"
                    autocomplete="username"
                    maxlength="50"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter password"
                    autocomplete="current-password"
                    required>
            </div>

            <button type="submit" class="btn btn-success" style="width:100%; justify-content:center; padding:12px">
                🔒 SECURE LOGIN
            </button>
        </form>

        <!-- Demo credentials & SQLi hint removed — keeps the page looking
             like a real login form instead of a teaching worksheet. -->

    </div>
</div>
</body>
</html>
