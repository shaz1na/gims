<?php
// ============================================================
// dashboard.php — SECURE VERSION (FIXED)
// ============================================================
// Fixes vs insecure dashboard.php:
//   1. requireLogin() — no access without a session
//   2. Search & category filter use PDO prepared statements
//   3. Delete uses a prepared statement + CSRF token check
//   4. All output escaped with e() (htmlspecialchars)
//   5. No raw SQL errors ever shown to the browser
// FIXED vs the previous "secure" draft:
//   - Was calling $conn (mysqli) which did not exist -> fatal
//     error. Now uses $pdo everywhere, matching config.php.
// ============================================================

require_once 'auth.php';
require_once 'config.php';

requireLogin();
$csrf = getCsrfToken();

// ---- Handle DELETE action (prepared statement + CSRF check) ----
if (isset($_GET['delete_id'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        die("Invalid or missing security token. Please go back and try again.");
    }
    $id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM inventory_items WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: dashboard.php?msg=deleted");
    exit;
}

// ---- Search / Filter (prepared statements — no SQL Injection) ----
$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        "SELECT * FROM inventory_items WHERE
            item_name LIKE ? OR item_code LIKE ? OR location LIKE ?
         ORDER BY updated_at DESC"
    );
    $stmt->execute([$like, $like, $like]);
} elseif ($category_filter !== '') {
    $stmt = $pdo->prepare("SELECT * FROM inventory_items WHERE category = ? ORDER BY updated_at DESC");
    $stmt->execute([$category_filter]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM inventory_items ORDER BY updated_at DESC");
    $stmt->execute();
}
$items = $stmt->fetchAll();

// ---- Stats ----
$total_items     = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn();
$total_locations = (int)$pdo->query("SELECT COUNT(DISTINCT location) FROM inventory_items")->fetchColumn();
$total_qty       = (int)$pdo->query("SELECT SUM(quantity) FROM inventory_items")->fetchColumn();

// ---- Categories for filter dropdown ----
$categories = $pdo->query("SELECT DISTINCT category FROM inventory_items ORDER BY category")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Jitu Unggul IMS (Secure)</title>
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
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="add_item.php" class="nav-item">
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
                <div class="topbar-title">INVENTORY DASHBOARD</div>
                <div class="topbar-meta">Jitu Unggul — <?= date('d M Y, H:i') ?></div>
            </div>
            <span class="badge badge-cyan">✅ SECURE VERSION</span>
        </div>

        <div class="content">

            <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success">✅ Action completed successfully.</div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Items</div>
                    <div class="stat-value"><?= $total_items ?></div>
                    <div class="stat-icon">📦</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Locations</div>
                    <div class="stat-value"><?= $total_locations ?></div>
                    <div class="stat-icon">📍</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Quantity</div>
                    <div class="stat-value"><?= $total_qty ?></div>
                    <div class="stat-icon">🔢</div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">📋 INVENTORY LIST</span>
                    <a href="add_item.php" class="btn btn-primary btn-sm">+ Add Item</a>
                </div>
                <div class="panel-body">

                    <form method="GET" action="dashboard.php" class="search-bar">
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search by name, code, or location..."
                            value="<?= e($search) ?>">
                        <select name="category" class="form-control" style="max-width:160px">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['category']) ?>"
                                <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                <?= e($cat['category']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline">🔍 Search</button>
                        <a href="dashboard.php" class="btn btn-outline">↺ Reset</a>
                    </form>

                    <?php if ($search !== ''): ?>
                    <p class="text-muted mb-2" style="font-size:13px">
                        Search results for: <strong><?= e($search) ?></strong>
                        (<?= count($items) ?> results)
                    </p>
                    <?php endif; ?>

                    <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No inventory items found.</p>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Location</th>
                                <th>Remarks</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><code style="color:var(--accent-cyan)"><?= e($item['item_code']) ?></code></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><span class="badge badge-cyan"><?= e($item['category']) ?></span></td>
                                <td style="font-weight:600"><?= (int)$item['quantity'] ?></td>
                                <td class="text-muted"><?= e($item['unit']) ?></td>
                                <td><?= e($item['location']) ?></td>
                                <td><?= e($item['remarks']) ?></td>
                                <td class="text-muted" style="font-size:12px"><?= e($item['updated_at']) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit_item.php?id=<?= (int)$item['id'] ?>" class="btn btn-outline btn-sm">✏️</a>
                                        <a href="dashboard.php?delete_id=<?= (int)$item['id'] ?>&csrf=<?= e($csrf) ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Delete this item?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>

                </div>
            </div><!-- /panel -->

        </div><!-- /content -->
    </main><!-- /main -->
</div><!-- /layout -->
</body>
</html>
