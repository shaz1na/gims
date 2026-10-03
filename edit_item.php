<?php
// ============================================================
// edit_item.php — SECURE VERSION (NEW — was missing before)
// ============================================================
// Fixes vs insecure edit_item.php:
//   1. requireLogin() — must be logged in to edit anything
//   2. SELECT and UPDATE both use PDO prepared statements
//      (no SQL Injection via ?id= or any form field)
//   3. CSRF token required on the POST submit
//   4. Server-side validation on required fields
//   5. All output escaped with e() — no Stored XSS
//   6. Generic error messages — no raw SQL / query leaked
// ============================================================

require_once 'auth.php';
require_once 'config.php';

requireLogin();
$csrf = getCsrfToken();

$error   = '';
$success = '';

// id must be a real integer — casting with (int) neutralises
// any injection attempt like "1 OR 1=1"
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    die("Item not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } else {
        $item_code = trim($_POST['item_code'] ?? '');
        $item_name = trim($_POST['item_name'] ?? '');
        $category  = trim($_POST['category'] ?? '');
        $quantity  = (int)($_POST['quantity'] ?? -1);
        $unit      = trim($_POST['unit'] ?? '');
        $location  = trim($_POST['location'] ?? '');
        $remarks   = trim($_POST['remarks'] ?? '');

        if ($item_code === '' || $item_name === '' || $quantity < 0) {
            $error = "Please fill in Item Code, Item Name, and a Quantity of 0 or more.";
        } else {
            try {
                $update = $pdo->prepare(
                    "UPDATE inventory_items SET
                        item_code = ?, item_name = ?, category = ?, quantity = ?,
                        unit = ?, location = ?, remarks = ?
                     WHERE id = ?"
                );
                $update->execute([
                    $item_code, $item_name, $category, $quantity, $unit, $location, $remarks, $id
                ]);
                $success = "Item updated successfully!";

                // Refresh item data so the form shows the saved values
                $stmt = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
            } catch (PDOException $e) {
                error_log("edit_item update failed: " . $e->getMessage());
                $error = "Could not update the item. The item code may already be in use.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Item — Jitu Unggul IMS (Secure)</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-title">🏢 JITU UNGGUL</div>
            <div class="logo-sub">Inventory System</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span> Dashboard</a>
            <a href="add_item.php" class="nav-item"><span class="nav-icon">➕</span> Add Item</a>
            <a href="supply_movement.php" class="nav-item"><span class="nav-icon">🚚</span> Supply Movement</a>
            <a href="movement_history.php" class="nav-item"><span class="nav-icon">📜</span> Movement History</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= e(strtoupper(substr($_SESSION['username'] ?? '?', 0, 1))) ?></div>
                <div>
                    <div class="user-name"><?= e($_SESSION['username'] ?? 'Guest') ?></div>
                    <div class="user-role"><?= e($_SESSION['role'] ?? 'unknown') ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn-logout">⏻ Logout</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <div class="topbar-title">EDIT INVENTORY ITEM</div>
                <div class="topbar-meta">Item ID: <?= (int)$id ?></div>
            </div>
            <span class="badge badge-cyan">✅ SECURE VERSION</span>
        </div>
        <div class="content">

            <?php if ($error):   ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?= e($success) ?></div><?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">✏️ EDIT ITEM — <?= e($item['item_code']) ?></span>
                    <a href="dashboard.php" class="btn btn-outline btn-sm">← Back</a>
                </div>
                <div class="panel-body">
                    <form method="POST" action="edit_item.php?id=<?= (int)$id ?>">
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Item Code *</label>
                                <input type="text" name="item_code" class="form-control"
                                    value="<?= e($item['item_code']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Item Name *</label>
                                <input type="text" name="item_name" class="form-control"
                                    value="<?= e($item['item_name']) ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-control">
                                    <?php foreach (['Electronics','Furniture','Stationery','Other'] as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= $item['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quantity *</label>
                                <input type="number" name="quantity" class="form-control" min="0"
                                    value="<?= (int)$item['quantity'] ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Unit</label>
                                <input type="text" name="unit" class="form-control"
                                    value="<?= e($item['unit']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control"
                                    value="<?= e($item['location']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control"><?= e($item['remarks']) ?></textarea>
                        </div>

                        <div class="flex gap-2 justify-between items-center mt-2">
                            <a href="dashboard.php" class="btn btn-outline">← Cancel</a>
                            <button type="submit" class="btn btn-primary">💾 Update Item</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>
