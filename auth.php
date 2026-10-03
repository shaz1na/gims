<?php
// ============================================================
// auth.php — SECURE VERSION
// ============================================================
// Reusable authentication + security helper functions.
// Include this at the TOP of every protected page with:
//   require_once 'auth.php';
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Session timeout — 30 minutes of inactivity logs the user out
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php?msg=timeout");
    exit;
}
$_SESSION['last_activity'] = time();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: dashboard.php?error=unauthorized");
        exit;
    }
}

function getCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die("CSRF validation failed. Request blocked.");
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Output escaping helper — always wrap user-supplied data with e()
// before printing it into HTML.
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
