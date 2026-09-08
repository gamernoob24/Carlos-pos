<?php /** @var array $data */ ?>
<div class="card">
  <div class="card-head">
    <h3>Categories</h3>
  </div>

  <form method="post" action="<?= e(base_url('index.php?page=categories&action=save')) ?>" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($data['edit']['id'] ?? 0) ?>">
    <input class="input" type="text" name="name" required placeholder="Category name"
           value="<?= e($data['edit']['name'] ?? '') ?>">
    <button class="btn btn-primary btn-sm" type="submit"><?= $data['edit'] ? 'Update' : 'Add category' ?></button>
    <?php if ($data['edit']): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(base_url('index.php?page=categories')) ?>">Cancel</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th class="right">Products</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php if (!$data['rows']): ?>
        <tr><td colspan="3" class="empty">No categories yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($data['rows'] as $r): ?>
        <tr>
          <td><strong><?= e($r['name']) ?></strong></td>
          <td class="right"><?= e($r['product_count']) ?></td>
          <td class="right">
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=categories&action=edit&id=' . $r['id'])) ?>">Edit</a>
            <form method="post" action="<?= e(base_url('index.php?page=categories&action=delete')) ?>" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= e($r['id']) ?>">
              <button class="btn btn-sm btn-danger" type="submit"
                      data-confirm="Delete &quot;<?= e($r['name']) ?>&quot;? Its products become Uncategorized.">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
