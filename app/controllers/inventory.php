<?php
/**
 * Inventory controller: ingredients, recipes (product → ingredient map)
 * and order modifiers. Manager-only (enforced by the route).
 */

/* ------------------------------------------------------------------ */
/* Ingredients (raw stock)                                             */
/* ------------------------------------------------------------------ */

function ingredients_controller()
{
    $action = get('action', 'list');

    if (is_post()) {
        if ($action === 'save')   { ingredients_save(); }
        if ($action === 'delete') { ingredients_delete(); }
        if ($action === 'adjust') { ingredients_adjust(); }
    }

    $q       = (string) get('q', '');
    $status  = (string) get('status', 'all');
    $pageNum = (int) get('p', 1);

    $where  = ['1=1'];
    $params = [];
    if ($q !== '') {
        $where[]  = 'i.name LIKE ?';
        $params[] = '%' . $q . '%';
    }
    if ($status === 'active')   { $where[] = 'i.active = 1'; }
    if ($status === 'inactive') { $where[] = 'i.active = 0'; }
    if ($status === 'low')      { $where[] = 'i.active = 1 AND i.stock_qty > 0 AND i.stock_qty <= i.reorder_level'; }
    if ($status === 'out')      { $where[] = 'i.active = 1 AND i.stock_qty <= 0'; }
    $whereSql = implode(' AND ', $where);

    $total = (int) db_val("SELECT COUNT(*) FROM ingredients i WHERE $whereSql", $params);
    $pager = paginate($total, 25, $pageNum);

    $rows = db_all(
        "SELECT i.*,
                (SELECT COUNT(*) FROM product_ingredients pi WHERE pi.ingredient_id = i.id) AS used_in
           FROM ingredients i
          WHERE $whereSql
          ORDER BY i.name
          LIMIT {$pager['perPage']} OFFSET {$pager['offset']}",
        $params
    );

    $counts = [
        'all'      => (int) db_val('SELECT COUNT(*) FROM ingredients'),
        'active'   => (int) db_val('SELECT COUNT(*) FROM ingredients WHERE active = 1'),
        'low'      => (int) db_val('SELECT COUNT(*) FROM ingredients WHERE active = 1 AND stock_qty > 0 AND stock_qty <= reorder_level'),
        'out'      => (int) db_val('SELECT COUNT(*) FROM ingredients WHERE active = 1 AND stock_qty <= 0'),
        'inactive' => (int) db_val('SELECT COUNT(*) FROM ingredients WHERE active = 0'),
    ];

    $edit = null;
    $editId = (int) get('id', 0);
    if ($action === 'edit' && $editId > 0) {
        $edit = db_one('SELECT * FROM ingredients WHERE id = ?', [$editId]);
        if (!$edit) {
            flash('Ingredient not found.', 'danger');
            redirect('index.php?page=inventory');
        }
    }

    return [
        'title'      => 'Ingredients',
        'rows'       => $rows,
        'pager'      => $pager,
        'counts'     => $counts,
        'q'          => $q,
        'status'     => $status,
        'edit'       => $edit,
        'lowCount'   => $counts['low'] + $counts['out'],
        'movements'  => $edit ? db_all(
            'SELECT * FROM stock_movements WHERE ingredient_id = ? ORDER BY id DESC LIMIT 10',
            [$edit['id']]
        ) : [],
    ];
}

function ingredients_save()
{
    $id   = (int) post('id', 0);
    $name = (string) post('name');

    if ($name === '') {
        flash('Ingredient name is required.', 'danger');
        redirect('index.php?page=inventory');
    }

    $data = [
        'name'          => $name,
        'unit'          => post('unit', 'g') ?: 'g',
        'stock_qty'     => post_num('stock_qty'),
        'reorder_level' => post_num('reorder_level'),
        'cost_per_unit' => post_num('cost_per_unit'),
        'active'        => isset($_POST['active']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $before = db_one('SELECT stock_qty FROM ingredients WHERE id = ?', [$id]);
            db_update('ingredients', $data, 'id = ?', [$id]);
            if ($before && abs((float) $before['stock_qty'] - (float) $data['stock_qty']) > 0.0001) {
                stock_move_ingredient($id, (float) $data['stock_qty'] - (float) $before['stock_qty'], 'Manual count');
            }
            flash('Ingredient "' . e($name) . '" updated.', 'success');
        } else {
            $newId = db_insert('ingredients', $data);
            if ((float) $data['stock_qty'] != 0.0) {
                stock_move_ingredient($newId, (float) $data['stock_qty'], 'Opening stock');
            }
            flash('Ingredient "' . e($name) . '" added.', 'success');
        }
    } catch (Throwable $e) {
        flash('That ingredient already exists.', 'danger');
    }

    redirect('index.php?page=inventory&status=' . urlencode(get('status', 'all')));
}

function ingredients_delete()
{
    $id = (int) post('id', 0);
    if ($id > 0) {
        $used = (int) db_val('SELECT COUNT(*) FROM product_ingredients WHERE ingredient_id = ?', [$id]);
        if ($used > 0) {
            db_update('ingredients', ['active' => 0], 'id = ?', [$id]);
            flash('Ingredient is used in a recipe, so it was deactivated instead of deleted.', 'warning');
        } else {
            db_delete('ingredients', 'id = ?', [$id]);
            flash('Ingredient deleted.', 'success');
        }
    }
    redirect('index.php?page=inventory');
}

function ingredients_adjust()
{
    $id     = (int) post('id', 0);
    $delta  = post_num('qty_change');
    $reason = (string) post('reason', 'Adjustment');

    if ($id <= 0 || $delta == 0.0) {
        flash('Enter a quantity to add or remove.', 'danger');
        redirect('index.php?page=inventory');
    }

    $ing = db_one('SELECT id, name, stock_qty FROM ingredients WHERE id = ?', [$id]);
    if (!$ing) {
        flash('Ingredient not found.', 'danger');
        redirect('index.php?page=inventory');
    }

    $newQty = (float) $ing['stock_qty'] + $delta;
    if ($newQty < 0 && !(int) setting('allow_negative_stock', '0')) {
        flash('Stock cannot go below zero (negative stock is disabled in Settings).', 'danger');
        redirect('index.php?page=inventory&action=edit&id=' . $id);
    }

    db_update('ingredients', ['stock_qty' => $newQty], 'id = ?', [$id]);
    stock_move_ingredient($id, $delta, $reason !== '' ? $reason : 'Adjustment', $newQty);

    flash('Stock for "' . e($ing['name']) . '" is now ' . fmt_qty($newQty) . '.', 'success');
    redirect('index.php?page=inventory&action=edit&id=' . $id);
}

/* ------------------------------------------------------------------ */
/* Recipes — map ingredients to menu products                          */
/* ------------------------------------------------------------------ */

function recipes_controller()
{
    if (is_post()) {
        if (get('action') === 'add')    { recipe_add(); }
        if (get('action') === 'remove') { recipe_remove(); }
        if (get('action') === 'update') { recipe_update(); }
    }

    $productId = (int) get('id', 0);
    if ($productId <= 0) {
        $first = db_one('SELECT id FROM products ORDER BY name LIMIT 1');
        $productId = $first ? (int) $first['id'] : 0;
    }

    $products = db_all(
        'SELECT p.id, p.name, p.selling_price, p.unit, c.name AS category_name,
                (SELECT COUNT(*) FROM product_ingredients pi WHERE pi.product_id = p.id) AS recipe_count,
                (SELECT COALESCE(SUM(pi.qty_per_unit * i.cost_per_unit),0)
                   FROM product_ingredients pi JOIN ingredients i ON i.id = pi.ingredient_id
                  WHERE pi.product_id = p.id) AS recipe_cost
           FROM products p LEFT JOIN categories c ON c.id = p.category_id
          ORDER BY c.name, p.name'
    );

    $product = null;
    $recipe  = [];
    if ($productId > 0) {
        $product = db_one('SELECT * FROM products WHERE id = ?', [$productId]);
        $recipe  = db_all(
            'SELECT pi.*, i.name AS ingredient_name, i.unit, i.stock_qty
               FROM product_ingredients pi
               JOIN ingredients i ON i.id = pi.ingredient_id
              WHERE pi.product_id = ?
              ORDER BY i.name',
            [$productId]
        );
    }

    return [
        'title'       => 'Recipes',
        'products'    => $products,
        'product'     => $product,
        'productId'   => $productId,
        'recipe'      => $recipe,
        'ingredients' => db_all('SELECT id, name, unit, stock_qty FROM ingredients WHERE active = 1 ORDER BY name'),
        'capacity'    => recipe_capacity($productId),
    ];
}

/** How many units of a product the current ingredient stock can still make. */
function recipe_capacity($productId)
{
    $rows = db_all(
        'SELECT pi.qty_per_unit, i.stock_qty
           FROM product_ingredients pi
           JOIN ingredients i ON i.id = pi.ingredient_id
          WHERE pi.product_id = ? AND pi.qty_per_unit > 0',
        [(int) $productId]
    );
    if (!$rows) {
        return null; // no recipe mapped yet
    }
    $min = null;
    foreach ($rows as $r) {
        $possible = floor((float) $r['stock_qty'] / (float) $r['qty_per_unit']);
        if ($min === null || $possible < $min) {
            $min = $possible;
        }
    }
    return max(0, (int) $min);
}

function recipe_add()
{
    $productId    = (int) post('product_id', 0);
    $ingredientId = (int) post('ingredient_id', 0);
    $qty          = post_num('qty_per_unit');

    if ($productId <= 0 || $ingredientId <= 0 || $qty <= 0) {
        flash('Choose an ingredient and a quantity greater than zero.', 'danger');
        redirect('index.php?page=recipes&id=' . $productId);
    }

    db()->prepare(
        'INSERT INTO product_ingredients (product_id, ingredient_id, qty_per_unit)
              VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE qty_per_unit = VALUES(qty_per_unit)'
    )->execute([$productId, $ingredientId, $qty]);

    flash('Recipe line saved.', 'success');
    redirect('index.php?page=recipes&id=' . $productId);
}

function recipe_update()
{
    $productId    = (int) post('product_id', 0);
    $ingredientId = (int) post('ingredient_id', 0);
    $qty          = post_num('qty_per_unit');

    if ($productId > 0 && $ingredientId > 0) {
        db()->prepare(
            'UPDATE product_ingredients SET qty_per_unit = ? WHERE product_id = ? AND ingredient_id = ?'
        )->execute([$qty, $productId, $ingredientId]);
    }
    redirect('index.php?page=recipes&id=' . $productId);
}

function recipe_remove()
{
    $productId    = (int) post('product_id', 0);
    $ingredientId = (int) post('ingredient_id', 0);

    if ($productId > 0 && $ingredientId > 0) {
        db_delete('product_ingredients', 'product_id = ? AND ingredient_id = ?', [$productId, $ingredientId]);
        flash('Removed from recipe.', 'success');
    }
    redirect('index.php?page=recipes&id=' . $productId);
}

/* ------------------------------------------------------------------ */
/* Order modifiers (no onions, extra cheese …)                         */
/* ------------------------------------------------------------------ */

function modifiers_controller()
{
    if (is_post()) {
        if (get('action') === 'save')   { modifier_save(); }
        if (get('action') === 'delete') { modifier_delete(); }
    }

    return [
        'title' => 'Modifiers',
        'rows'  => db_all('SELECT * FROM modifiers ORDER BY sort_order, name'),
        'edit'  => ((int) get('id', 0)) ? db_one('SELECT * FROM modifiers WHERE id = ?', [(int) get('id')]) : null,
    ];
}

function modifier_save()
{
    $id    = (int) post('id', 0);
    $name  = (string) post('name');
    $delta = post_num('price_delta');
    $sort  = (int) post('sort_order', 0);

    if ($name === '') {
        flash('Modifier name is required.', 'danger');
        redirect('index.php?page=modifiers');
    }

    $data = [
        'name'        => $name,
        'price_delta' => $delta,
        'sort_order'  => $sort,
        'active'      => isset($_POST['active']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            db_update('modifiers', $data, 'id = ?', [$id]);
            flash('Modifier updated.', 'success');
        } else {
            db_insert('modifiers', $data);
            flash('Modifier added.', 'success');
        }
    } catch (Throwable $e) {
        flash('That modifier already exists.', 'danger');
    }

    redirect('index.php?page=modifiers');
}

function modifier_delete()
{
    $id = (int) post('id', 0);
    if ($id > 0) {
        db_delete('modifiers', 'id = ?', [$id]); // past orders keep their snapshot
        flash('Modifier deleted.', 'success');
    }
    redirect('index.php?page=modifiers');
}
