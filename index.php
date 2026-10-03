<?php
// ============================================================
// index.php — SECURE VERSION
// ============================================================
require_once 'auth.php';
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
