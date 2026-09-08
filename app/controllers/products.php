<?php
/**
 * Products & categories controller (CRUD, stock control, CSV export).
 */

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

/** Duplicate-key (23000) safe message. */
function duplicate_message(Throwable $e)
{
    $msg = $e->getMessage();
    if (strpos($msg, 'uq_products_sku') !== false) {
        return 'That SKU is already used by another product.';
    }
    if (strpos($msg, 'uq_products_barcode') !== false) {
        return 'That barcode is already used by another product.';
    }
    return 'Database error: ' . $msg;
}

/* ------------------------------------------------------------------ */
/* Products                                                            */
/* ------------------------------------------------------------------ */

function products_controller()
{
    $action = get('action', 'list');

    if ($action === 'export') {
        products_export();
    }
    if (is_post()) {
        if ($action === 'save')   { products_save(); }
        if ($action === 'delete') { products_delete(); }
        if ($action === 'adjust') { products_adjust(); }
    }

    /* ---- filters ---- */
    $q       = (string) get('q', '');
    $cat     = (int) get('cat', 0);
    $status  = (string) get('status', 'all'); // all | active | low | out | inactive
    $pageNum = (int) get('p', 1);

    $where  = ['1=1'];
    $params = [];

    if ($q !== '') {
        $where[]  = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
        $like     = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($cat > 0) {
        $where[]  = 'p.category_id = ?';
        $params[] = $cat;
    }
    if ($status === 'active')   { $where[] = 'p.active = 1'; }
    if ($status === 'inactive') { $where[] = 'p.active = 0'; }
    if ($status === 'low')      { $where[] = 'p.active = 1 AND p.stock_qty > 0 AND p.stock_qty <= p.reorder_level'; }
    if ($status === 'out')      { $where[] = 'p.active = 1 AND p.stock_qty <= 0'; }

    $whereSql = implode(' AND ', $where);

    $total = (int) db_val(
        "SELECT COUNT(*) FROM products p WHERE $whereSql",
        $params
    );

    $pager = paginate($total, 24, $pageNum);

    $rows = db_all(
        "SELECT p.*, c.name AS category_name
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE $whereSql
          ORDER BY p.name
          LIMIT {$pager['perPage']} OFFSET {$pager['offset']}",
        $params
    );

    $counts = [
        'all'      => (int) db_val('SELECT COUNT(*) FROM products'),
        'active'   => (int) db_val('SELECT COUNT(*) FROM products WHERE active = 1'),
        'low'      => (int) db_val('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty > 0 AND stock_qty <= reorder_level'),
        'out'      => (int) db_val('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty <= 0'),
        'inactive' => (int) db_val('SELECT COUNT(*) FROM products WHERE active = 0'),
    ];

    // Product being edited (if any)
    $edit = null;
    $editId = (int) get('id', 0);
    if ($action === 'edit' && $editId > 0) {
        $edit = db_one('SELECT * FROM products WHERE id = ?', [$editId]);
        if (!$edit) {
            flash('Product not found.', 'danger');
            redirect('index.php?page=products');
        }
    }

    return [
        'title'      => 'Products',
        'rows'       => $rows,
        'pager'      => $pager,
        'counts'     => $counts,
        'categories' => db_all('SELECT id, name FROM categories ORDER BY name'),
        'q'          => $q,
        'cat'        => $cat,
        'status'     => $status,
        'edit'       => $edit,
        'movements'  => $edit ? db_all(
            'SELECT * FROM stock_movements WHERE product_id = ? ORDER BY id DESC LIMIT 10',
            [$edit['id']]
        ) : [],
    ];
}

function products_save()
{
    $id   = (int) post('id', 0);
    $name = (string) post('name');
    $sku  = (string) post('sku');

    if ($name === '') {
        flash('Product name is required.', 'danger');
        redirect('index.php?page=products');
    }
    if ($sku === '') {
        $sku = 'SKU-' . strtoupper(substr(md5($name . microtime(true)), 0, 8));
    }

    $data = [
        'sku'           => $sku,
        'barcode'       => post('barcode') !== '' ? post('barcode') : null,
        'name'          => $name,
        'category_id'   => ((int) post('category_id', 0)) ?: null,
        'cost_price'    => post_num('cost_price'),
        'selling_price' => post_num('selling_price'),
        'stock_qty'     => post_num('stock_qty'),
        'reorder_level' => post_num('reorder_level'),
        'unit'          => post('unit', 'pc') ?: 'pc',
        'tax_exempt'    => isset($_POST['tax_exempt']) ? 1 : 0,
        'active'        => isset($_POST['active']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $before = db_one('SELECT stock_qty FROM products WHERE id = ?', [$id]);
            db_update('products', $data, 'id = ?', [$id]);
            if ($before && abs((float) $before['stock_qty'] - (float) $data['stock_qty']) > 0.0001) {
                stock_move($id, (float) $data['stock_qty'] - (float) $before['stock_qty'], 'Manual edit');
            }
            flash('Product "' . e($name) . '" updated.', 'success');
        } else {
            $newId = db_insert('products', $data);
            if ((float) $data['stock_qty'] != 0.0) {
                stock_move($newId, (float) $data['stock_qty'], 'Opening stock');
            }
            flash('Product "' . e($name) . '" created.', 'success');
        }
    } catch (Throwable $e) {
        flash(duplicate_message($e), 'danger');
    }

    redirect('index.php?page=products&status=' . urlencode(get('status', 'all')));
}

function products_delete()
{
    $id = (int) post('id', 0);
    if ($id > 0) {
        $used = (int) db_val('SELECT COUNT(*) FROM sale_items WHERE product_id = ?', [$id]);
        if ($used > 0) {
            // Keep history intact: just hide it from the sales floor.
            db_update('products', ['active' => 0], 'id = ?', [$id]);
            flash('Product is referenced by past sales, so it was deactivated instead of deleted.', 'warning');
        } else {
            db_delete('products', 'id = ?', [$id]);
            flash('Product deleted.', 'success');
        }
    }
    redirect('index.php?page=products');
}

function products_adjust()
{
    $id      = (int) post('id', 0);
    $delta   = post_num('qty_change');
    $reason  = (string) post('reason', 'Adjustment');

    if ($id <= 0 || $delta == 0.0) {
        flash('Enter a quantity to add or remove.', 'danger');
        redirect('index.php?page=products');
    }

    $p = db_one('SELECT id, name, stock_qty FROM products WHERE id = ?', [$id]);
    if (!$p) {
        flash('Product not found.', 'danger');
        redirect('index.php?page=products');
    }

    $newQty = (float) $p['stock_qty'] + $delta;
    if ($newQty < 0 && !(int) setting('allow_negative_stock', '0')) {
        flash('Stock cannot go below zero (negative stock is disabled in Settings).', 'danger');
        redirect('index.php?page=products&action=edit&id=' . $id);
    }

    db_update('products', ['stock_qty' => $newQty], 'id = ?', [$id]);
    stock_move($id, $delta, $reason !== '' ? $reason : 'Adjustment', $newQty);

    flash('Stock for "' . e($p['name']) . '" is now ' . fmt_qty($newQty) . '.', 'success');
    redirect('index.php?page=products&action=edit&id=' . $id);
}

function products_export()
{
    $rows = db_all(
        'SELECT p.sku, p.barcode, p.name, c.name AS category, p.cost_price, p.selling_price,
                p.stock_qty, p.reorder_level, p.unit, p.tax_exempt, p.active
           FROM products p LEFT JOIN categories c ON c.id = p.category_id
          ORDER BY p.name'
    );

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            $r['sku'], $r['barcode'], $r['name'], $r['category'],
            $r['cost_price'], $r['selling_price'], $r['stock_qty'],
            $r['reorder_level'], $r['unit'], $r['tax_exempt'] ? 'Yes' : 'No',
            $r['active'] ? 'Yes' : 'No',
        ];
    }

    csv_download('products-' . date('Y-m-d') . '.csv',
        ['SKU', 'Barcode', 'Name', 'Category', 'Cost', 'Price', 'Stock', 'Reorder', 'Unit', 'Tax Exempt', 'Active'],
        $out);
}

/* ------------------------------------------------------------------ */
/* Categories                                                          */
/* ------------------------------------------------------------------ */

function categories_controller()
{
    if (is_post()) {
        if (get('action') === 'save')   { categories_save(); }
        if (get('action') === 'delete') { categories_delete(); }
    }

    $rows = db_all(
        'SELECT c.*, COUNT(p.id) AS product_count
           FROM categories c
           LEFT JOIN products p ON p.category_id = c.id
          GROUP BY c.id
          ORDER BY c.name'
    );

    return [
        'title' => 'Categories',
        'rows'  => $rows,
        'edit'  => ((int) get('id', 0)) ? db_one('SELECT * FROM categories WHERE id = ?', [(int) get('id')]) : null,
    ];
}

function categories_save()
{
    $id   = (int) post('id', 0);
    $name = (string) post('name');

    if ($name === '') {
        flash('Category name is required.', 'danger');
        redirect('index.php?page=categories');
    }

    try {
        if ($id > 0) {
            db_update('categories', ['name' => $name], 'id = ?', [$id]);
            flash('Category updated.', 'success');
        } else {
            db_insert('categories', ['name' => $name]);
            flash('Category added.', 'success');
        }
    } catch (Throwable $e) {
        flash('That category already exists.', 'danger');
    }

    redirect('index.php?page=categories');
}

function categories_delete()
{
    $id = (int) post('id', 0);
    if ($id > 0) {
        db_delete('categories', 'id = ?', [$id]); // products fall back to "Uncategorized"
        flash('Category deleted. Its products moved to Uncategorized.', 'success');
    }
    redirect('index.php?page=categories');
}
