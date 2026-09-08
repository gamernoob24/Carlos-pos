<?php
/** @var array $data */
$rows   = $data['rows'];
$pager  = $data['pager'];
$counts = $data['counts'];
$cats   = $data['categories'];
$edit   = $data['edit'];
$action = get('action', 'list');
$showForm = ($action === 'add' || $edit !== null);

$query = [
    'q'      => $data['q'],
    'cat'    => $data['cat'],
    'status' => $data['status'],
];
$tabs = [
    'all'      => 'All (' . $counts['all'] . ')',
    'active'   => 'Active (' . $counts['active'] . ')',
    'low'      => 'Low stock (' . $counts['low'] . ')',
    'out'      => 'Out of stock (' . $counts['out'] . ')',
    'inactive' => 'Hidden (' . $counts['inactive'] . ')',
];
?>

<div class="page-actions">
  <form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
    <input type="hidden" name="page" value="products">
    <input type="text" name="q" class="input" placeholder="Search name, SKU, barcode…" value="<?= e($data['q']) ?>">
    <select name="cat" class="input">
      <option value="0">All categories</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= e($c['id']) ?>" <?= $data['cat'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="input">
      <?php foreach ($tabs as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $data['status'] === $k ? 'selected' : '' ?>><?= e($tabs[$k]) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost" type="submit">Filter</button>
    <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=products&action=export')) ?>">Export CSV</a>
  </form>
  <a class="btn btn-primary" href="<?= e(base_url('index.php?page=products&action=add')) ?>">+ Add product</a>
</div>

<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="tab<?= $data['status'] === $k ? ' active' : '' ?>"
       href="<?= e(base_url('index.php?page=products&status=' . $k . '&q=' . urlencode($data['q']) . '&cat=' . $data['cat'])) ?>">
      <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($showForm): ?>
<div class="card form-card">
  <div class="card-head"><h3><?= $edit ? 'Edit product' : 'New product' ?></h3></div>
  <form method="post" action="<?= e(base_url('index.php?page=products&action=save&status=' . urlencode($data['status']))) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">

    <label class="field span-2"><span>Product name *</span>
      <input class="input" type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>">
    </label>
    <label class="field"><span>SKU</span>
      <input class="input" type="text" name="sku" value="<?= e($edit['sku'] ?? '') ?>" placeholder="auto-generated if blank">
    </label>
    <label class="field"><span>Barcode</span>
      <input class="input" type="text" name="barcode" value="<?= e($edit['barcode'] ?? '') ?>">
    </label>
    <label class="field"><span>Category</span>
      <select class="input" name="category_id">
        <option value="0">Uncategorized</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= e($c['id']) ?>" <?= (int) ($edit['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field"><span>Unit</span>
      <input class="input" type="text" name="unit" value="<?= e($edit['unit'] ?? 'pc') ?>" placeholder="pc, pack, kg…">
    </label>
    <label class="field"><span>Cost price</span>
      <input class="input" type="number" step="0.01" min="0" name="cost_price" value="<?= e($edit['cost_price'] ?? '0.00') ?>">
    </label>
    <label class="field"><span>Selling price *</span>
      <input class="input" type="number" step="0.01" min="0" name="selling_price" required value="<?= e($edit['selling_price'] ?? '0.00') ?>">
    </label>
    <label class="field"><span>Stock on hand</span>
      <input class="input" type="number" step="0.001" name="stock_qty" value="<?= e($edit['stock_qty'] ?? '0') ?>">
    </label>
    <label class="field"><span>Reorder level</span>
      <input class="input" type="number" step="0.001" min="0" name="reorder_level" value="<?= e($edit['reorder_level'] ?? setting('low_stock_threshold', '5')) ?>">
    </label>
    <div class="field span-2 checks">
      <label class="check"><input type="checkbox" name="tax_exempt" value="1" <?= !empty($edit['tax_exempt']) ? 'checked' : '' ?>> <span>Tax exempt (no <?= e(strtolower(setting('tax_name', 'tax'))) ?>)</span></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= !isset($edit['active']) || $edit['active'] ? 'checked' : '' ?>> <span>Available for sale</span></label>
    </div>

    <div class="form-actions span-2">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create product' ?></button>
      <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=products')) ?>">Cancel</a>
      <?php if ($edit): ?>
        <button class="btn btn-danger" type="submit" form="deleteProduct" data-confirm="Delete this product?">Delete</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($edit): ?>
    <hr class="sep">
    <div class="adjust-grid">
      <div>
        <h4>Adjust stock</h4>
        <form method="post" action="<?= e(base_url('index.php?page=products&action=adjust')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e($edit['id']) ?>">
          <input class="input input-sm" type="number" step="0.001" name="qty_change" placeholder="+10 or -3" required>
          <input class="input input-sm" type="text" name="reason" placeholder="Reason (e.g. delivery, damage)">
          <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
        </form>
        <p class="muted small">Current stock: <strong><?= e(fmt_qty($edit['stock_qty'])) ?></strong></p>
      </div>
      <div>
        <h4>Recent movements</h4>
        <?php if (!$data['movements']): ?>
          <p class="muted small">No stock movements recorded yet.</p>
        <?php else: ?>
          <table class="table table-sm">
            <thead><tr><th>When</th><th>Reason</th><th class="right">Change</th><th class="right">Balance</th></tr></thead>
            <tbody>
            <?php foreach ($data['movements'] as $m): ?>
              <tr>
                <td><?= e(fmt_date($m['created_at'])) ?></td>
                <td><?= e($m['reason']) ?> <span class="muted">· <?= e($m['user_name']) ?></span></td>
                <td class="right <?= (float) $m['qty_change'] >= 0 ? 'text-ok' : 'text-bad' ?>">
                  <?= (float) $m['qty_change'] >= 0 ? '+' : '' ?><?= e(fmt_qty($m['qty_change'])) ?>
                </td>
                <td class="right"><?= e(fmt_qty($m['balance_after'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($edit): ?>
  <form method="post" id="deleteProduct" action="<?= e(base_url('index.php?page=products&action=delete')) ?>" class="hidden">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($edit['id']) ?>">
  </form>
<?php endif; ?>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>SKU</th><th>Product</th><th>Category</th>
          <th class="right">Cost</th><th class="right">Price</th>
          <th class="right">Stock</th><th>Status</th><th class="right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="empty">No products found.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <?php
          $stock = (float) $r['stock_qty'];
          $state = $stock <= 0 ? 'out' : ($stock <= (float) $r['reorder_level'] ? 'low' : 'ok');
        ?>
        <tr>
          <td><code><?= e($r['sku']) ?></code></td>
          <td>
            <strong><?= e($r['name']) ?></strong>
            <?php if ($r['barcode']): ?><div class="muted small"><?= e($r['barcode']) ?></div><?php endif; ?>
          </td>
          <td><?= e($r['category_name'] ?? 'Uncategorized') ?></td>
          <td class="right"><?= e(money($r['cost_price'])) ?></td>
          <td class="right"><strong><?= e(money($r['selling_price'])) ?></strong></td>
          <td class="right"><?= e(fmt_qty($stock)) ?> <span class="muted"><?= e($r['unit']) ?></span></td>
          <td>
            <?php if (!$r['active']): ?>
              <span class="pill pill-muted">Hidden</span>
            <?php else: ?>
              <span class="pill pill-<?= e($state) ?>"><?= $state === 'out' ? 'Out of stock' : ($state === 'low' ? 'Low: ' . fmt_qty($stock) : 'In stock') ?></span>
            <?php endif; ?>
          </td>
          <td class="right">
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=products&action=edit&id=' . $r['id'])) ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pager['pages'] > 1): ?>
    <div class="pager">
      <span class="muted">Showing <?= e($pager['from']) ?>–<?= e($pager['to']) ?> of <?= e($pager['total']) ?></span>
      <div class="pager-links">
        <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
          <a class="pager-link<?= $i === $pager['page'] ? ' active' : '' ?>"
             href="<?= e(base_url('index.php?page=products&p=' . $i . '&q=' . urlencode($data['q']) . '&cat=' . $data['cat'] . '&status=' . urlencode($data['status']))) ?>"><?= e($i) ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
