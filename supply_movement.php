<?php
// ============================================================
// supply_movement.php — SECURE VERSION (FIXED)
// ============================================================
// Logs stock IN/OUT movements, one or more item lines per
// event, all inside a transaction (all lines save or none do).
// FIXED vs previous draft: was calling $conn->prepare() and
// $conn->begin_transaction() (mysqli), which does not exist in
// config.php -> fatal error. Now uses PDO transactions.
// ============================================================

require_once 'auth.php';
require_once 'config.php';

requireLogin();
$csrf = getCsrfToken();

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
        $error = "Invalid session token. Please refresh the page and try again.";
    } else {
        $company = trim($_POST['company_name'] ?? '');
        $event   = trim($_POST['event_name'] ?? '');
        $phone   = trim($_POST['client_phone'] ?? '');
        $type    = ($_POST['trans_type'] ?? 'IN') === 'OUT' ? 'OUT' : 'IN';

        $item_ids   = $_POST['item_id']  ?? [];
        $quantities = $_POST['quantity'] ?? [];

        $lines = [];
        foreach ($item_ids as $i => $iid) {
            $iid = (int)$iid;
            $qty = (int)($quantities[$i] ?? 0);
            if ($iid > 0 && $qty > 0) {
                $lines[] = [$iid, $qty];
            }
        }

        if (empty($lines)) {
            $error = "Please add at least one item (selected from the list) with a quantity greater than 0.";
        } else {
            try {
                $pdo->beginTransaction();

                $insert = $pdo->prepare(
                    "INSERT INTO stock_transactions
                        (item_id, client_company, event_name, client_phone, trans_type, quantity_changed)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $update = $pdo->prepare(
                    "UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?"
                );

                foreach ($lines as [$iid, $qty]) {
                    $insert->execute([$iid, $company, $event, $phone, $type, $qty]);

                    $adjustment = ($type === 'OUT') ? -$qty : $qty;
                    $update->execute([$adjustment, $iid]);
                }

                $pdo->commit();
                $msg = count($lines) . " item line(s) recorded successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("supply_movement failed: " . $e->getMessage());
                $error = "Something went wrong while saving the movement. Nothing was changed — please try again.";
            }
        }
    }
}

// Pull every item once — used to build the searchable datalist
$items_list = $pdo->query("SELECT id, item_code, item_name FROM inventory_items ORDER BY item_name")->fetchAll();

$items_for_js = array_map(function ($it) {
    return [
        'id'    => (int)$it['id'],
        'label' => $it['item_name'] . ' (' . $it['item_code'] . ')',
    ];
}, $items_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supply Movement — Jitu Unggul IMS</title>
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
            <a href="supply_movement.php" class="nav-item active">
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
                <div class="topbar-title">SUPPLY MOVEMENT</div>
                <div class="topbar-meta">Log stock IN / OUT for one or more items per event</div>
            </div>
            <span class="badge badge-cyan">✅ SECURE — Prepared Statements</span>
        </div>

        <div class="content">

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($msg): ?>
            <div class="alert alert-success">
                ✅ <?= e($msg) ?>
                &nbsp;<a href="movement_history.php" style="color:var(--accent-cyan)">View Movement History →</a>
            </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">🚚 LOG SUPPLY MOVEMENT</span>
                    <a href="dashboard.php" class="btn btn-outline btn-sm">← Back</a>
                </div>
                <div class="panel-body">

                    <datalist id="itemDatalist">
                        <?php foreach ($items_for_js as $it): ?>
                        <option value="<?= e($it['label']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <template id="itemRowTemplate">
                        <div class="form-row item-row" style="align-items:flex-start">
                            <div class="form-group" style="flex:2">
                                <label class="form-label">Item *</label>
                                <input type="text" class="form-control item-search"
                                       list="itemDatalist" autocomplete="off"
                                       placeholder="Type to search e.g. stand, tv...">
                                <input type="hidden" class="item-id-hidden" name="item_id[]" value="0">
                                <div class="item-search-error" style="display:none; color:var(--accent-red); font-size:12px; margin-top:4px;">
                                    ⚠️ Pick an item from the suggestion list.
                                </div>
                            </div>
                            <div class="form-group" style="flex:1">
                                <label class="form-label">Quantity *</label>
                                <input type="number" class="form-control" name="quantity[]" min="1" required>
                            </div>
                            <div class="form-group" style="flex:0 0 auto; padding-top:28px">
                                <button type="button" class="btn btn-outline btn-sm remove-row-btn">✕ Remove</button>
                            </div>
                        </div>
                    </template>

                    <form method="POST" action="supply_movement.php" id="movementForm">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Transaction Type *</label>
                                <select name="trans_type" class="form-control">
                                    <option value="IN">IN (Stock Added)</option>
                                    <option value="OUT">OUT (Stock Removed)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Event Name</label>
                                <input type="text" name="event_name" class="form-control"
                                    placeholder="e.g. Annual Dinner 2026">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control"
                                    placeholder="e.g. ABC Supplies Sdn Bhd">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Client Phone</label>
                                <input type="text" name="client_phone" class="form-control"
                                    placeholder="e.g. 012-3456789">
                            </div>
                        </div>

                        <label class="form-label" style="display:block; margin-top:18px;">Items in this movement *</label>
                        <div id="itemRowsContainer"></div>

                        <button type="button" id="addItemRowBtn" class="btn btn-outline btn-sm mt-2">
                            + Add Another Item
                        </button>

                        <div class="flex gap-2 justify-between items-center mt-2" style="margin-top:20px">
                            <a href="dashboard.php" class="btn btn-outline">← Cancel</a>
                            <button type="submit" class="btn btn-primary">💾 Submit Movement</button>
                        </div>

                    </form>
                </div>
            </div>

        </div><!-- /content -->
    </main><!-- /main -->
</div><!-- /layout -->

<script>
    const ITEMS = <?= json_encode($items_for_js, JSON_HEX_TAG) ?>;
    const ITEMS_BY_LABEL = {};
    ITEMS.forEach(it => { ITEMS_BY_LABEL[it.label] = it.id; });

    const rowsContainer = document.getElementById('itemRowsContainer');
    const rowTemplate    = document.getElementById('itemRowTemplate');

    function addItemRow() {
        const fragment = rowTemplate.content.cloneNode(true);
        const row         = fragment.querySelector('.item-row');
        const searchInput = row.querySelector('.item-search');
        const hiddenInput = row.querySelector('.item-id-hidden');
        const errorEl     = row.querySelector('.item-search-error');
        const removeBtn   = row.querySelector('.remove-row-btn');

        searchInput.addEventListener('input', () => {
            const matchedId = ITEMS_BY_LABEL[searchInput.value];
            if (matchedId) {
                hiddenInput.value = matchedId;
                errorEl.style.display = 'none';
            } else {
                hiddenInput.value = '0';
            }
        });

        searchInput.addEventListener('blur', () => {
            errorEl.style.display = (searchInput.value && hiddenInput.value === '0') ? 'block' : 'none';
        });

        removeBtn.addEventListener('click', () => {
            if (rowsContainer.querySelectorAll('.item-row').length > 1) {
                row.remove();
            }
        });

        rowsContainer.appendChild(row);
    }

    document.getElementById('addItemRowBtn').addEventListener('click', addItemRow);
    addItemRow();

    document.getElementById('movementForm').addEventListener('submit', function (e) {
        const hiddenInputs = rowsContainer.querySelectorAll('.item-id-hidden');
        let hasInvalidRow = false;
        hiddenInputs.forEach(h => { if (h.value === '0') hasInvalidRow = true; });

        if (hasInvalidRow) {
            e.preventDefault();
            alert('Please pick a valid item (from the suggestion list) for every row before submitting.');
        }
    });
</script>

</body>
</html>
