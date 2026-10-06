<?php
/**
 * POS / checkout screen controller.
 * The order itself is submitted with AJAX to api.php?action=checkout.
 */

function pos_controller()
{
    $categories = db_all('SELECT id, name FROM categories ORDER BY name');

    $products = db_all(
        'SELECT p.id, p.sku, p.barcode, p.name, p.selling_price AS price, p.stock_qty AS stock, p.unit,
                p.tax_exempt, p.category_id, c.name AS category_name
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.active = 1
          ORDER BY c.name, p.name
          LIMIT 500'
    );

    foreach ($products as $i => $p) {
        $products[$i]['price'] = (float) $p['price'];
        $products[$i]['stock'] = (float) $p['stock'];
        $products[$i]['tax_exempt'] = (int) $p['tax_exempt'];
    }

    $modifiers = db_all('SELECT id, name, price_delta FROM modifiers WHERE active = 1 ORDER BY sort_order, name');
    foreach ($modifiers as $i => $m) {
        $modifiers[$i]['price_delta'] = (float) $m['price_delta'];
    }

    $recent = db_all(
        'SELECT id, sale_no, total, payment_method, status, created_at
           FROM sales ORDER BY id DESC LIMIT 8'
    );

    $shift = current_shift();

    return [
        'title' => 'Point of Sale',
        'categories' => $categories,
        'products' => $products,
        'modifiers' => $modifiers,
        'recent' => $recent,
        'shift' => $shift,
        'openOrders' => (int) db_val("SELECT COUNT(*) FROM sales WHERE status IN ('queued','preparing')"),
        'jsSettings' => [
            'currencySymbol' => setting('currency_symbol', '₱'),
            'currencyPosition' => setting('currency_position', 'before'),
            'decimals' => (int) setting('decimal_places', '2'),
            'taxRate' => (float) setting('tax_rate', '0'),
            'taxName' => setting('tax_name', 'Tax'),
            'taxMode' => setting('tax_mode', 'inclusive'),
            'allowNegative' => (int) setting('allow_negative_stock', '0'),
            'checkoutUrl' => base_url('api.php?action=checkout'),
            'searchUrl' => base_url('api.php?action=search'),
            'liveUrl' => base_url('api.php?action=pos_live_data'), // <-- ADD THIS
            'csrfToken' => csrf_token(),
            'hasShift' => $shift ? 1 : 0,
        ],
    ];
}
