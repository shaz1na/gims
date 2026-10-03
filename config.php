<?php
// ============================================================
// config.php — SECURE VERSION (FIXED)
// ============================================================
// IMPORTANT: This whole secure app now speaks ONE database
// language only: PDO ($pdo). Every secure file below uses
// $pdo->prepare(...) / $pdo->query(...) — never $conn,
// never mysqli_query(). Mixing the two was the bug that made
// dashboard.php, add_item.php, supply_movement.php and
// movement_history.php crash with a fatal error.
//
// ✅ FIX: DB credentials kept in one place (would move to
//    environment variables in real production).
// ✅ FIX: display_errors OFF — users never see raw DB errors.
// ✅ FIX: PDO with ATTR_EMULATE_PREPARES = false, so every
//    "?" placeholder is a REAL prepared statement sent to
//    MySQL, not just PHP faking it.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jitu_unggul_secure');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("A system error occurred. Please contact the administrator.");
        }
    }
    return $pdo;
}

// Every file that does require_once('config.php') automatically
// gets a ready-to-use $pdo variable.
$pdo = getDB();
