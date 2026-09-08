<?php
/**
 * Reports: sales summary, payment mix, top sellers, category & cashier breakdown.
 */

function reports_controller()
{
    if (get('action') === 'export') {
        reports_export();
    }

    $from = (string) get('from', date('Y-m-d'));
    $to   = (string) get('to', date('Y-m-d'));
    $p    = [$from, $to];

    $done = "s.status = 'completed' AND DATE(s.created_at) BETWEEN ? AND ?";

    /* ---------- headline numbers ---------- */
    $summary = db_one(
        "SELECT COUNT(*) AS transactions,
                COALESCE(SUM(s.total),0)          AS gross,
                COALESCE(SUM(s.discount_total),0) AS discounts,
                COALESCE(SUM(s.tax_total),0)      AS tax,
                COALESCE(SUM(s.subtotal),0)       AS subtotal
           FROM sales s WHERE $done",
        $p
    );

    $summary['transactions'] = (int) $summary['transactions'];
    $summary['avg'] = $summary['transactions'] > 0
        ? (float) $summary['gross'] / $summary['transactions']
        : 0.0;

    /* ---------- gross profit (revenue - cost of goods sold) ---------- */
    $profit = db_one(
        "SELECT COALESCE(SUM(si.qty * (si.unit_price - si.discount_amount)),0) AS revenue,
                COALESCE(SUM(si.qty * COALESCE(p.cost_price,0)),0)             AS cogs
           FROM sale_items si
           JOIN sales s   ON s.id = si.sale_id
           LEFT JOIN products p ON p.id = si.product_id
          WHERE $done",
        $p
    );
    $summary['profit'] = (float) $profit['revenue'] - (float) $profit['cogs'];

    /* ---------- payment mix ---------- */
    $payments = db_all(
        "SELECT s.payment_method, COUNT(*) AS cnt, COALESCE(SUM(s.total),0) AS total
           FROM sales s WHERE $done
          GROUP BY s.payment_method
          ORDER BY total DESC",
        $p
    );

    /* ---------- top sellers ---------- */
    $topProducts = db_all(
        "SELECT si.product_name, si.sku,
                SUM(si.qty) AS qty_sold,
                COALESCE(SUM(si.qty * (si.unit_price - si.discount_amount)),0) AS revenue
           FROM sale_items si
           JOIN sales s ON s.id = si.sale_id
          WHERE $done
          GROUP BY si.product_id, si.product_name, si.sku
          ORDER BY revenue DESC
          LIMIT 10",
        $p
    );

    /* ---------- category mix ---------- */
    $byCategory = db_all(
        "SELECT COALESCE(c.name,'Uncategorized') AS name,
                SUM(si.qty) AS qty_sold,
                COALESCE(SUM(si.qty * (si.unit_price - si.discount_amount)),0) AS revenue
           FROM sale_items si
           JOIN sales s        ON s.id = si.sale_id
           LEFT JOIN products p ON p.id = si.product_id
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE $done
          GROUP BY COALESCE(c.name,'Uncategorized')
          ORDER BY revenue DESC",
        $p
    );

    /* ---------- cashier performance ---------- */
    $byCashier = db_all(
        "SELECT s.user_name, COUNT(*) AS cnt, COALESCE(SUM(s.total),0) AS total
           FROM sales s WHERE $done
          GROUP BY s.user_name
          ORDER BY total DESC",
        $p
    );

    /* ---------- sales over time (daily, or monthly for long ranges) ---------- */
    $days = (int) round((strtotime($to) - strtotime($from)) / 86400);
    if ($days > 92) {
        $groupExpr = "DATE_FORMAT(s.created_at, '%Y-%m')";
    } else {
        $groupExpr = "DATE(s.created_at)";
    }

    $timeline = db_all(
        "SELECT $groupExpr AS period, COUNT(*) AS cnt, COALESCE(SUM(s.total),0) AS total
           FROM sales s WHERE $done
          GROUP BY period
          ORDER BY period ASC",
        $p
    );

    /* ---------- inventory snapshot ---------- */
    $stock = [
        'products' => (int) db_val('SELECT COUNT(*) FROM products WHERE active = 1'),
        'value'    => (float) db_val('SELECT COALESCE(SUM(cost_price*stock_qty),0) FROM products WHERE active = 1'),
        'retail'   => (float) db_val('SELECT COALESCE(SUM(selling_price*stock_qty),0) FROM products WHERE active = 1'),
        'low'      => (int) db_val('SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty <= reorder_level'),
    ];

    return [
        'title'       => 'Reports',
        'from'        => $from,
        'to'          => $to,
        'summary'     => $summary,
        'payments'    => $payments,
        'topProducts' => $topProducts,
        'byCategory'  => $byCategory,
        'byCashier'   => $byCashier,
        'timeline'    => $timeline,
        'stock'       => $stock,
    ];
}

function reports_export()
{
    $from = (string) get('from', date('Y-m-d'));
    $to   = (string) get('to', date('Y-m-d'));
    $p    = [$from, $to];
    $done = "s.status = 'completed' AND DATE(s.created_at) BETWEEN ? AND ?";

    $rows = db_all(
        "SELECT si.product_name, si.sku, SUM(si.qty) AS qty_sold,
                COALESCE(SUM(si.qty * (si.unit_price - si.discount_amount)),0) AS revenue
           FROM sale_items si
           JOIN sales s ON s.id = si.sale_id
          WHERE $done
          GROUP BY si.product_id, si.product_name, si.sku
          ORDER BY revenue DESC",
        $p
    );

    $out = [];
    foreach ($rows as $r) {
        $out[] = [$r['sku'], $r['product_name'], $r['qty_sold'], $r['revenue']];
    }

    csv_download('product-sales-' . $from . '-to-' . $to . '.csv',
        ['SKU', 'Product', 'Qty Sold', 'Revenue'], $out);
}
