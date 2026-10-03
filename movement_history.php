<?php
// ============================================================
// movement_history.php — SECURE VERSION (FIXED)
// ============================================================
// FIXED vs previous draft: was calling $conn->prepare() (mysqli),
// which does not exist in config.php -> fatal error. Now uses
// $pdo throughout, matching every other secure file.
// ============================================================

require_once 'auth.php';
require_once 'config.php';

requireLogin();

$search = trim($_GET['search'] ?? '');

$sql = "SELECT st.id, st.item_id, st.client_company, st.event_name, st.client_phone,
               st.trans_type, st.quantity_changed, st.created_at,
               ii.item_code, ii.item_name
        FROM stock_transactions st
        LEFT JOIN inventory_items ii ON ii.id = st.item_id";

$params = [];
if ($search !== '') {
    $sql .= " WHERE st.event_name LIKE ? OR st.client_company LIKE ?
              OR ii.item_name LIKE ? OR ii.item_code LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY st.created_at DESC, st.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---- Group rows back into movements ----
$movements = [];
foreach ($rows as $row) {
    $minute = $row['created_at'] ? date('Y-m-d H:i', strtotime($row['created_at'])) : 'unknown';
    $key = $row['client_company'] . '|' . $row['event_name'] . '|' . $row['client_phone'] . '|' . $row['trans_type'] . '|' . $minute;

    if (!isset($movements[$key])) {
        $movements[$key] = [
            'company'    => $row['client_company'],
            'event'      => $row['event_name'],
            'phone'      => $row['client_phone'],
            'type'       => $row['trans_type'],
            'created_at' => $row['created_at'],
            'items'      => [],
            'total_qty'  => 0,
        ];
    }
    $movements[$key]['items'][] = [
        'item_code' => $row['item_code'],
        'item_name' => $row['item_name'],
        'quantity'  => $row['quantity_changed'],
    ];
    $movements[$key]['total_qty'] += (int)$row['quantity_changed'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movement History — Jitu Unggul IMS</title>
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
            <a href="add_item.php" class="nav-item">
                <span class="nav-icon">➕</span> Add Item
            </a>
            <a href="supply_movement.php" class="nav-item">
                <span class="nav-icon">🚚</span> Supply Movement
            </a>
            <a href="movement_history.php" class="nav-item active">
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
                <div class="topbar-title">MOVEMENT HISTORY</div>
                <div class="topbar-meta">All supply movements, most recent first</div>
            </div>
            <span class="badge badge-cyan">✅ SECURE — Prepared Statements</span>
        </div>

        <div class="content">

            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">📜 LOGGED MOVEMENTS</span>
                    <a href="supply_movement.php" class="btn btn-primary btn-sm">+ New Movement</a>
                </div>
                <div class="panel-body">

                    <form method="GET" action="movement_history.php" class="search-bar">
                        <input type="text" name="search" class="form-control"
                               placeholder="Search by event, company, or item name/code..."
                               value="<?= e($search) ?>">
                        <button type="submit" class="btn btn-outline">🔍 Search</button>
                        <a href="movement_history.php" class="btn btn-outline">↺ Reset</a>
                    </form>

                    <?php if (empty($movements)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No movements found.</p>
                    </div>
                    <?php else: ?>

                    <?php foreach ($movements as $m): ?>
                    <div class="panel" style="margin-top:16px; border:1px solid var(--border-color, #2a2a3a)">
                        <div class="panel-header" style="padding:12px 16px">
                            <div>
                                <span class="badge <?= $m['type'] === 'OUT' ? 'badge-red' : 'badge-cyan' ?>">
                                    <?= $m['type'] === 'OUT' ? '⬇ OUT' : '⬆ IN' ?>
                                </span>
                                <strong style="margin-left:8px"><?= e($m['event'] ?: '(No event name)') ?></strong>
                                <?php if ($m['company']): ?>
                                <span class="text-muted"> — <?= e($m['company']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:12px">
                                <?= $m['created_at'] ? date('d M Y, H:i', strtotime($m['created_at'])) : '' ?>
                            </div>
                        </div>
                        <div class="panel-body" style="padding:0">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Item Name</th>
                                        <th>Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($m['items'] as $line): ?>
                                    <tr>
                                        <td><code style="color:var(--accent-cyan)"><?= e($line['item_code'] ?? '—') ?></code></td>
                                        <td><?= e($line['item_name'] ?? '(item deleted)') ?></td>
                                        <td style="font-weight:600"><?= (int)$line['quantity'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="padding:10px 16px; font-size:12px" class="text-muted">
                                <?php if ($m['phone']): ?>📞 <?= e($m['phone']) ?> &nbsp;·&nbsp; <?php endif; ?>
                                <?= count($m['items']) ?> item line(s) · Total qty moved: <?= $m['total_qty'] ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php endif; ?>

                </div>
            </div>

        </div><!-- /content -->
    </main><!-- /main -->
</div><!-- /layout -->
</body>
</html>
