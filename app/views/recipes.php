<?php
/** @var array $data */
$products    = $data['products'];
$product     = $data['product'];
$recipe      = $data['recipe'];
$ingredients = $data['ingredients'];
$capacity    = $data['capacity'];
?>
<div class="grid-2">
  <!-- ============ Choose product ============ -->
  <div class="card">
    <div class="card-head"><h3>Menu items</h3>
      <span class="muted small">pick one to edit its recipe</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Item</th><th>Recipe lines</th><th class="right">Price</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
          <tr class="<?= (int) $data['productId'] === (int) $p['id'] ? 'row-active' : '' ?>">
            <td>
              <strong><?= e($p['name']) ?></strong>
              <div class="muted small"><?= e($p['category_name'] ?? 'Uncategorized') ?></div>
            </td>
            <td>
              <?php if ((int) $p['recipe_count'] === 0): ?>
                <span class="pill pill-low">no recipe</span>
              <?php else: ?>
                <span class="pill pill-ok"><?= e($p['recipe_count']) ?> ingredient(s)</span>
              <?php endif; ?>
            </td>
            <td class="right"><?= e(money($p['selling_price'])) ?></td>
            <td class="right"><a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=recipes&id=' . $p['id'])) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ============ Recipe for selected product ============ -->
  <div class="card">
    <div class="card-head">
      <h3><?= $product ? 'Recipe: ' . e($product['name']) : 'Recipe' ?></h3>
      <?php if ($capacity !== null): ?>
        <span class="pill <?= $capacity <= 0 ? 'pill-out' : ($capacity < 10 ? 'pill-low' : 'pill-ok') ?>">
          Can make <?= e($capacity) ?> more
        </span>
      <?php endif; ?>
    </div>

    <?php if (!$product): ?>
      <p class="muted">Add a menu item first (Products → Add product).</p>
    <?php else: ?>
      <?php if (!$recipe): ?>
        <p class="muted">No ingredients mapped yet. Selling this item will not consume any raw stock.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Ingredient</th><th class="right">Qty per serving</th><th class="right">In stock</th><th class="right"></th></tr></thead>
            <tbody>
            <?php foreach ($recipe as $r): ?>
              <tr>
                <td><strong><?= e($r['ingredient_name']) ?></strong></td>
                <td class="right">
                  <form method="post" action="<?= e(base_url('index.php?page=recipes&action=update')) ?>" class="inline-form" style="justify-content:flex-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                    <input type="hidden" name="ingredient_id" value="<?= e($r['ingredient_id']) ?>">
                    <input class="input input-sm" style="width:90px;text-align:right" type="number" step="0.001" min="0"
                           name="qty_per_unit" value="<?= e(fmt_qty($r['qty_per_unit'])) ?>">
                    <span class="muted small"><?= e($r['unit']) ?></span>
                    <button class="btn btn-sm btn-ghost" type="submit">Save</button>
                  </form>
                </td>
                <td class="right"><?= e(fmt_qty($r['stock_qty'])) ?></td>
                <td class="right">
                  <form method="post" action="<?= e(base_url('index.php?page=recipes&action=remove')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                    <input type="hidden" name="ingredient_id" value="<?= e($r['ingredient_id']) ?>">
                    <button class="btn btn-sm btn-danger" type="submit" data-confirm="Remove this ingredient from the recipe?">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <hr class="sep">
      <h4>Add ingredient to this recipe</h4>
      <form method="post" action="<?= e(base_url('index.php?page=recipes&action=add')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
        <select class="input" name="ingredient_id" required>
          <option value="">Choose ingredient…</option>
          <?php foreach ($ingredients as $ing): ?>
            <option value="<?= e($ing['id']) ?>"><?= e($ing['name']) ?> (<?= e($ing['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <input class="input input-sm" style="width:110px" type="number" step="0.001" min="0" name="qty_per_unit"
               placeholder="qty per serving" required>
        <button class="btn btn-primary btn-sm" type="submit">Add</button>
      </form>
      <?php if (!$ingredients): ?>
        <p class="muted small">No ingredients yet — add them under <a href="<?= e(base_url('index.php?page=inventory&action=add')) ?>">Inventory → Add ingredient</a>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
