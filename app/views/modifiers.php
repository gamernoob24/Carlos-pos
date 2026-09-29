<?php /** @var array $data */ ?>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3><?= $data['edit'] ? 'Edit modifier' : 'Add modifier' ?></h3></div>
    <form method="post" action="<?= e(base_url('index.php?page=modifiers&action=save')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= e($data['edit']['id'] ?? 0) ?>">
      <label class="field"><span>Name</span>
        <input class="input" type="text" name="name" required placeholder="e.g. Extra cheese, No onions"
               value="<?= e($data['edit']['name'] ?? '') ?>">
      </label>
      <label class="field"><span>Price change</span>
        <input class="input" type="number" step="0.01" name="price_delta"
               value="<?= e($data['edit']['price_delta'] ?? '0.00') ?>">
        <small class="muted">Use 0 for omissions (“no onions”), a positive amount for paid extras.</small>
      </label>
      <label class="field"><span>Sort order</span>
        <input class="input" type="number" name="sort_order" value="<?= e($data['edit']['sort_order'] ?? '0') ?>">
      </label>
      <label class="check">
        <input type="checkbox" name="active" value="1" <?= !isset($data['edit']['active']) || $data['edit']['active'] ? 'checked' : '' ?>>
        <span>Shown on the checkout screen</span>
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $data['edit'] ? 'Save modifier' : 'Add modifier' ?></button>
        <?php if ($data['edit']): ?>
          <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=modifiers')) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Order modifiers</h3>
      <a class="link" href="<?= e(base_url('index.php?page=recipes')) ?>">Recipes →</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Modifier</th><th class="right">Price change</th><th>Status</th><th class="right">Actions</th></tr></thead>
        <tbody>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="4" class="empty">No modifiers yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($data['rows'] as $m): ?>
          <tr>
            <td><strong><?= e($m['name']) ?></strong></td>
            <td class="right">
              <?php if ((float) $m['price_delta'] == 0.0): ?>
                <span class="muted">no charge</span>
              <?php else: ?>
                +<?= e(money($m['price_delta'])) ?>
              <?php endif; ?>
            </td>
            <td><?= $m['active'] ? '<span class="pill pill-ok">Active</span>' : '<span class="pill pill-muted">Hidden</span>' ?></td>
            <td class="right">
              <a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=modifiers&action=edit&id=' . $m['id'])) ?>">Edit</a>
              <form method="post" action="<?= e(base_url('index.php?page=modifiers&action=delete')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($m['id']) ?>">
                <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this modifier?">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted small">Modifiers appear on the checkout screen under each item’s <strong>Modify</strong> button and print on the kitchen ticket.</p>
  </div>
</div>
