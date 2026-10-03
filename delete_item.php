<?php
// ============================================================
// delete_item.php — INSECURE VERSION
// ============================================================
// ⚠️ VULNERABILITIES:
//   1. No authentication check — anyone can delete
//   2. SQL Injection via id parameter
//   3. No CSRF token — attackers can craft malicious GET links
//   4. GET-based deletion — can be triggered by loading an image
// ============================================================

session_start();
require_once 'config.php';

// ⚠️ No auth check — anyone can delete items

$id = isset($_GET['id']) ? $_GET['id'] : '';

if (!empty($id)) {
    // ⚠️ VULNERABILITY: SQL Injection via id
    // Payload: ?id=1 OR 1=1
    // This could delete ALL rows!
    $sql = "DELETE FROM inventory_items WHERE id=$id";
    mysqli_query($conn, $sql);
}

header("Location: dashboard.php?msg=deleted");
exit;
