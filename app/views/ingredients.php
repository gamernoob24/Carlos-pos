<?php
/** @var array $data */
$rows   = $data['rows'];
$pager  = $data['pager'];
$counts = $data['counts'];
$edit   = $data['edit'];
$action = get('action', 'list');
$showForm = ($action === 'add' || $edit !== null);

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
    <input type="hidden" name="page" value="inventory">
    <input type="text" name="q" class="input" placeholder="Search ingredient…" value="<?= e($data['q']) ?>">
    <select name="status" class="input">
      <?php foreach ($tabs as $k => $label): ?>
        <option value="<?= e($k) ?>" <?= $data['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost" type="submit">Filter</button>
  </form>
  <div class="inline-form">
    <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=recipes')) ?>">Recipes</a>
    <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=modifiers')) ?>">Modifiers</a>
    <a class="btn btn-primary" href="<?= e(base_url('index.php?page=inventory&action=add')) ?>">+ Add ingredient</a>
  </div>
</div>

<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="tab<?= $data['status'] === $k ? ' active' : '' ?>"
       href="<?= e(base_url('index.php?page=inventory&status=' . $k . '&q=' . urlencode($data['q']))) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($showForm): ?>
<div class="card form-card">
  <div class="card-head"><h3><?= $edit ? 'Edit ingredient' : 'New ingredient' ?></h3></div>
  <form method="post" action="<?= e(base_url('index.php?page=inventory&action=save&status=' . urlencode($data['status']))) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">

    <label class="field span-2"><span>Ingredient name *</span>
      <input class="input" type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Beef patty">
    </label>
    <label class="field"><span>Unit</span>
      <input class="input" type="text" name="unit" value="<?= e($edit['unit'] ?? 'g') ?>" placeholder="g, ml, pc, slice">
    </label>
    <label class="field"><span>Reorder level</span>
      <input class="input" type="number" step="0.001" min="0" name="reorder_level" value="<?= e($edit['reorder_level'] ?? '0') ?>">
    </label>
    <label class="field"><span>Stock on hand</span>
      <input class="input" type="number" step="0.001" name="stock_qty" value="<?= e($edit['stock_qty'] ?? '0') ?>">
    </label>
    <label class="field"><span>Cost per unit</span>
      <input class="input" type="number" step="0.0001" min="0" name="cost_per_unit" value="<?= e($edit['cost_per_unit'] ?? '0') ?>">
    </label>
    <div class="field span-2 checks">
      <label class="check"><input type="checkbox" name="active" value="1" <?= !isset($edit['active']) || $edit['active'] ? 'checked' : '' ?>> <span>Active</span></label>
    </div>

    <div class="form-actions span-2">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create ingredient' ?></button>
      <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=inventory')) ?>">Cancel</a>
      <?php if ($edit): ?>
        <button class="btn btn-danger" type="submit" form="deleteIngredient" data-confirm="Delete this ingredient?">Delete</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($edit): ?>
    <hr class="sep">
    <div class="adjust-grid">
      <div>
        <h4>Receive / adjust stock</h4>
        <form method="post" action="<?= e(base_url('index.php?page=inventory&action=adjust')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e($edit['id']) ?>">
          <input class="input input-sm" type="number" step="0.001" name="qty_change" placeholder="+5000 or -250" required>
          <input class="input input-sm" type="text" name="reason" placeholder="Reason (delivery, waste, spoiled)">
          <button class="btn btn-ghost btn-sm" type="submit">Apply</button>
        </form>
        <p class="muted small">Current stock: <strong><?= e(fmt_qty($edit['stock_qty'])) ?> <?= e($edit['unit']) ?></strong></p>
      </div>
      <div>
        <h4>Recent movements</h4>
        <?php if (!$data['movements']): ?>
          <p class="muted small">No movements recorded yet.</p>
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
  <form method="post" id="deleteIngredient" action="<?= e(base_url('index.php?page=inventory&action=delete')) ?>" class="hidden">
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
          <th>Ingredient</th><th>Unit</th>
          <th class="right">Stock</th><th class="right">Reorder at</th>
          <th class="right">Cost / unit</th><th class="right">Stock value</th>
          <th>Used in</th><th>Status</th><th class="right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="empty">No ingredients yet — add your raw stock to start tracking it.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <?php
          $stock = (float) $r['stock_qty'];
          $state = $stock <= 0 ? 'out' : ($stock <= (float) $r['reorder_level'] ? 'low' : 'ok');
        ?>
        <tr>
          <td><strong><?= e($r['name']) ?></strong></td>
          <td><?= e($r['unit']) ?></td>
          <td class="right"><?= e(fmt_qty($stock)) ?></td>
          <td class="right muted"><?= e(fmt_qty($r['reorder_level'])) ?></td>
          <td class="right"><?= e(money($r['cost_per_unit'])) ?></td>
          <td class="right"><?= e(money($stock * (float) $r['cost_per_unit'])) ?></td>
          <td><?= e($r['used_in']) ?> recipe(s)</td>
          <td>
            <?php if (!$r['active']): ?>
              <span class="pill pill-muted">Hidden</span>
            <?php else: ?>
              <span class="pill pill-<?= e($state) ?>"><?= $state === 'out' ? 'Out of stock' : ($state === 'low' ? 'Low: ' . fmt_qty($stock) : 'In stock') ?></span>
            <?php endif; ?>
          </td>
          <td class="right">
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=inventory&action=edit&id=' . $r['id'])) ?>">Edit</a>
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
             href="<?= e(base_url('index.php?page=inventory&p=' . $i . '&q=' . urlencode($data['q']) . '&status=' . urlencode($data['status']))) ?>"><?= e($i) ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
