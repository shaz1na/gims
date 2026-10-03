<?php
// ============================================================
// add_item.php — SECURE VERSION (FIXED)
// ============================================================
// Fixes vs insecure add_item.php:
//   1. requireLogin() — must be logged in
//   2. INSERT uses a PDO prepared statement (no SQL Injection)
//   3. CSRF token required on submit
//   4. Server-side validation (item_code/name required, qty >= 0)
//   5. Output escaped with e()
// FIXED vs previous draft: was using $conn->prepare() (mysqli)
// which does not exist in config.php -> fatal error. Now uses
// $pdo consistently. Filename also matches the nav links
// (no more "_secure" suffix mismatch).
// ============================================================

require_once 'auth.php';
require_once 'config.php';

requireLogin();
$csrf = getCsrfToken();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } else {
        $item_code  = trim($_POST['item_code'] ?? '');
        $item_name  = trim($_POST['item_name'] ?? '');
        $category   = trim($_POST['category'] ?? '');
        $quantity   = (int)($_POST['quantity'] ?? -1);
        $unit       = trim($_POST['unit'] ?? '');
        $location   = trim($_POST['location'] ?? '');
        $remarks    = trim($_POST['remarks'] ?? '');
        $created_by = $_SESSION['user_id'];

        if ($item_code === '' || $item_name === '' || $quantity < 0) {
            $error = "Please fill in Item Code, Item Name, and a Quantity of 0 or more.";
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO inventory_items
                        (item_code, item_name, category, quantity, unit, location, remarks, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $item_code, $item_name, $category, $quantity, $unit, $location, $remarks, $created_by
                ]);
                $success = "Item added successfully!";
            } catch (PDOException $e) {
                error_log("add_item insert failed: " . $e->getMessage());
                $error = "Could not save the item. The item code may already be in use.";
            }
        }
    }
}

// Distinct values already used in the database, for the datalists below
$categories_list = $pdo->query(
    "SELECT DISTINCT category FROM inventory_items WHERE category IS NOT NULL AND category <> '' ORDER BY category"
)->fetchAll();

$units_list = $pdo->query(
    "SELECT DISTINCT unit FROM inventory_items WHERE unit IS NOT NULL AND unit <> '' ORDER BY unit"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Item — Jitu Unggul IMS (Secure)</title>
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
            <div class="nav-section-label">Main Menu</div>
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="add_item.php" class="nav-item active">
                <span class="nav-icon">➕</span> Add Item
            </a>
            <a href="supply_movement.php" class="nav-item">
                <span class="nav-icon">🚚</span> Supply Movement
            </a>
            <a href="movement_history.php" class="nav-item">
                <span class="nav-icon">📜</span> Movement History
            </a>
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
                <div class="topbar-title">ADD INVENTORY ITEM</div>
                <div class="topbar-meta">New item entry</div>
            </div>
            <span class="badge badge-cyan">✅ SECURE VERSION</span>
        </div>

        <div class="content">

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= e($success) ?></div>
            <?php endif; ?>

            <datalist id="categoryDatalist">
                <?php foreach ($categories_list as $c): ?>
                <option value="<?= e($c['category']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <datalist id="unitDatalist">
                <?php foreach ($units_list as $u): ?>
                <option value="<?= e($u['unit']) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">➕ NEW ITEM FORM</span>
                    <a href="dashboard.php" class="btn btn-outline btn-sm">← Back</a>
                </div>
                <div class="panel-body">
                    <form method="POST" action="add_item.php">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Item Code *</label>
                                <input type="text" name="item_code" class="form-control"
                                    placeholder="e.g. ITM-011" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Item Name *</label>
                                <input type="text" name="item_name" class="form-control"
                                    placeholder="e.g. Stand Base" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <input type="text" name="category" class="form-control"
                                    list="categoryDatalist" autocomplete="off"
                                    placeholder="Type to search or add new...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quantity *</label>
                                <input type="number" name="quantity" class="form-control"
                                    min="0" placeholder="e.g. 10" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Unit</label>
                                <input type="text" name="unit" class="form-control"
                                    list="unitDatalist" autocomplete="off"
                                    placeholder="Type to search or add new...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control"
                                    placeholder="e.g. Stor A-01">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control"
                                placeholder="Optional notes about this item"></textarea>
                        </div>

                        <div class="flex gap-2 justify-between items-center mt-2">
                            <a href="dashboard.php" class="btn btn-outline">← Cancel</a>
                            <button type="submit" class="btn btn-primary">💾 Save Item</button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>
