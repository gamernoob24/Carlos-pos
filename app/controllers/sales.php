<?php
/**
 * Sales history, receipt view and voiding.
 */

function sales_controller()
{
    if (get('action') === 'export') {
        sales_export();
    }
    if (is_post() && get('action') === 'void') {
        sales_void();
    }

    $from  = (string) get('from', date('Y-m-01'));
    $to    = (string) get('to', date('Y-m-d'));
    $q     = (string) get('q', '');
    $pay   = (string) get('pay', 'all');
    $status= (string) get('status', 'all');
    $pageN = (int) get('p', 1);

    $where  = ['DATE(s.created_at) BETWEEN ? AND ?'];
    $params = [$from, $to];

    if ($q !== '') {
        $where[]  = '(s.sale_no LIKE ? OR s.customer_name LIKE ? OR s.user_name LIKE ?)';
        $like     = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (in_array($pay, ['cash', 'card', 'ewallet'], true)) {
        $where[]  = 's.payment_method = ?';
        $params[] = $pay;
    }
    if (in_array($status, ['completed', 'voided'], true)) {
        $where[]  = 's.status = ?';
        $params[] = $status;
    }

    $whereSql = implode(' AND ', $where);

    $totals = db_one(
        "SELECT COUNT(*) AS cnt,
                COALESCE(SUM(CASE WHEN s.status='completed' THEN s.total ELSE 0 END),0) AS gross,
                COALESCE(SUM(CASE WHEN s.status='completed' THEN s.discount_total ELSE 0 END),0) AS discounts,
                COALESCE(SUM(CASE WHEN s.status='completed' THEN s.tax_total ELSE 0 END),0) AS tax
           FROM sales s WHERE $whereSql",
        $params
    );

    $pager = paginate((int) $totals['cnt'], 25, $pageN);

    $rows = db_all(
        "SELECT s.*, (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
           FROM sales s
          WHERE $whereSql
          ORDER BY s.id DESC
          LIMIT {$pager['perPage']} OFFSET {$pager['offset']}",
        $params
    );

    return [
        'title'  => 'Sales History',
        'rows'   => $rows,
        'pager'  => $pager,
        'totals' => $totals,
        'from'   => $from,
        'to'     => $to,
        'q'      => $q,
        'pay'    => $pay,
        'status' => $status,
    ];
}

function sales_void()
{
    require_admin();

    $id = (int) post('id', 0);
    $sale = db_one('SELECT * FROM sales WHERE id = ?', [$id]);

    if (!$sale) {
        flash('Sale not found.', 'danger');
        redirect('index.php?page=sales');
    }
    if ($sale['status'] === 'voided') {
        flash('That sale is already voided.', 'warning');
        redirect('index.php?page=sale&id=' . $id);
    }

    $user = current_user();

    try {
        db()->beginTransaction();

        // Return every line back into stock
        $items = db_all('SELECT * FROM sale_items WHERE sale_id = ?', [$id]);
        foreach ($items as $item) {
            if ($item['product_id']) {
                db()->prepare(
                    'UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?'
                )->execute([$item['qty'], $item['product_id']]);

                $balance = (float) db_val('SELECT stock_qty FROM products WHERE id = ?', [$item['product_id']]);
                db_insert('stock_movements', [
                    'product_id'    => $item['product_id'],
                    'qty_change'    => $item['qty'],
                    'balance_after' => $balance,
                    'reason'        => 'Void ' . $sale['sale_no'],
                    'user_id'       => $user['id'],
                    'user_name'     => $user['name'],
                ]);
            }
        }

        db_update('sales', [
            'status'    => 'voided',
            'voided_at' => date('Y-m-d H:i:s'),
            'voided_by' => $user['name'],
            'note'      => post('reason', 'Voided'),
        ], 'id = ?', [$id]);

        db()->commit();
        flash('Sale ' . e($sale['sale_no']) . ' voided and stock returned.', 'success');
    } catch (Throwable $e) {
        db()->rollBack();
        flash('Could not void the sale: ' . e($e->getMessage()), 'danger');
    }

    redirect('index.php?page=sale&id=' . $id);
}

function sales_export()
{
    $from = (string) get('from', date('Y-m-01'));
    $to   = (string) get('to', date('Y-m-d'));

    $rows = db_all(
        "SELECT s.sale_no, s.created_at, s.user_name, s.customer_name, s.subtotal,
                s.discount_total, s.tax_total, s.total, s.paid_amount, s.change_amount,
                s.payment_method, s.status,
                (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items
           FROM sales s
          WHERE DATE(s.created_at) BETWEEN ? AND ?
          ORDER BY s.id",
        [$from, $to]
    );

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            $r['sale_no'], $r['created_at'], $r['user_name'], $r['customer_name'],
            $r['items'], $r['subtotal'], $r['discount_total'], $r['tax_total'],
            $r['total'], $r['paid_amount'], $r['change_amount'],
            ucfirst($r['payment_method']), ucfirst($r['status']),
        ];
    }

    csv_download('sales-' . $from . '-to-' . $to . '.csv',
        ['Sale No', 'Date', 'Cashier', 'Customer', 'Items', 'Subtotal', 'Discount', 'Tax',
         'Total', 'Paid', 'Change', 'Payment', 'Status'],
        $out);
}

/* ------------------------------------------------------------------ */
/* Single sale (receipt)                                               */
/* ------------------------------------------------------------------ */

function sale_view_controller()
{
    $id = (int) get('id', 0);
    $sale = db_one('SELECT * FROM sales WHERE id = ?', [$id]);

    if (!$sale) {
        flash('Sale not found.', 'danger');
        redirect('index.php?page=sales');
    }

    $items = db_all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id]);

    return [
        'title'     => 'Sale ' . $sale['sale_no'],
        'sale'      => $sale,
        'items'     => $items,
        'autoprint' => (int) get('print', 0) === 1,
    ];
}
