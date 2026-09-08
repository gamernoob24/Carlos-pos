<?php
/**
 * JSON API used by the POS screen (product search + checkout).
 * ------------------------------------------------------------
 *  GET  api.php?action=search&q=...
 *  POST api.php?action=checkout    (JSON body)
 */

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_out(['ok' => false, 'error' => 'Your session has expired. Please sign in again.'], 401);
}

$action = (string) get('action', '');

switch ($action) {

    /* -------------------------------------------------------------- */
    case 'search':
        $q = (string) get('q', '');
        if (trim($q) === '') {
            json_out(['ok' => true, 'products' => []]);
        }

        $like = '%' . $q . '%';
        $rows = db_all(
            'SELECT p.id, p.sku, p.barcode, p.name, p.selling_price, p.stock_qty,
                    p.unit, p.tax_exempt, p.category_id, c.name AS category_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.active = 1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
              ORDER BY (p.barcode = ? OR p.sku = ?) DESC, p.name
              LIMIT 20',
            [$like, $like, $like, $q, $q]
        );

        json_out(['ok' => true, 'products' => array_map('api_product_shape', $rows)]);

    /* -------------------------------------------------------------- */
    case 'product':
        $id = (int) get('id', 0);
        $row = db_one('SELECT p.*, c.name AS category_name
                         FROM products p LEFT JOIN categories c ON c.id = p.category_id
                        WHERE p.id = ? AND p.active = 1', [$id]);
        json_out($row
            ? ['ok' => true, 'product' => api_product_shape($row)]
            : ['ok' => false, 'error' => 'Product not found.']);

    /* -------------------------------------------------------------- */
    case 'checkout':
        if (!is_post()) {
            json_out(['ok' => false, 'error' => 'Use POST for checkout.'], 405);
        }

        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals(csrf_token(), (string) $sent)) {
            json_out(['ok' => false, 'error' => 'Security token mismatch. Reload the page and retry.'], 400);
        }

        try {
            json_out(api_checkout(json_body()));
        } catch (Throwable $e) {
            json_out(['ok' => false, 'error' => $e->getMessage()], 422);
        }

    /* -------------------------------------------------------------- */
    default:
        json_out(['ok' => false, 'error' => 'Unknown action.'], 404);
}

/* ================================================================== */
/* Helpers                                                             */
/* ================================================================== */

function api_product_shape(array $p)
{
    return [
        'id'           => (int) $p['id'],
        'sku'          => $p['sku'],
        'barcode'      => $p['barcode'],
        'name'         => $p['name'],
        'price'        => (float) $p['selling_price'],
        'stock'        => (float) $p['stock_qty'],
        'unit'         => $p['unit'] ?? 'pc',
        'tax_exempt'   => (int) ($p['tax_exempt'] ?? 0),
        'category_id'  => $p['category_id'] !== null ? (int) $p['category_id'] : null,
        'category_name'=> $p['category_name'] ?? null,
    ];
}

/**
 * Validate the cart, persist the sale and deduct stock.
 *
 * @return array
 * @throws RuntimeException|Throwable
 */
function api_checkout(array $payload)
{
    $items    = $payload['items']    ?? [];
    $discount = $payload['discount'] ?? ['type' => 'fixed', 'value' => 0];
    $payment  = $payload['payment']  ?? ['method' => 'cash', 'cash' => 0];
    $customer = trim((string) ($payload['customer'] ?? ''));
    $note     = trim((string) ($payload['note'] ?? ''));

    if (!is_array($items) || count($items) === 0) {
        throw new RuntimeException('The cart is empty.');
    }

    $taxRate     = (float) setting('tax_rate', '0');
    $taxMode     = setting('tax_mode', 'exclusive');
    $allowNeg    = (int) setting('allow_negative_stock', '0');
    $decimals    = (int) setting('decimal_places', '2');

    $method = in_array(($payment['method'] ?? 'cash'), ['cash', 'card', 'ewallet'], true)
        ? $payment['method'] : 'cash';

    $user = current_user();

    /* ---------- 1. Build & validate lines ---------- */
    $lines       = [];
    $subtotal    = 0.0;
    $taxableNet  = 0.0;

    // Collect product ids first so we can lock the rows once
    $requested = [];
    foreach ($items as $raw) {
        $pid = (int) ($raw['id'] ?? 0);
        $qty = (float) ($raw['qty'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $requested[$pid] = ($requested[$pid] ?? 0) + $qty;
        }
    }
    if (!$requested) {
        throw new RuntimeException('No valid items in the cart.');
    }

    db()->beginTransaction();

    try {
        $placeholders = implode(',', array_fill(0, count($requested), '?'));
        $products = db_all(
            "SELECT id, sku, name, selling_price, stock_qty, unit, tax_exempt
               FROM products
              WHERE id IN ($placeholders) AND active = 1
              FOR UPDATE",
            array_keys($requested)
        );

        $found = [];
        foreach ($products as $p) {
            $found[(int) $p['id']] = $p;
        }

        foreach ($items as $raw) {
            $pid = (int) ($raw['id'] ?? 0);
            $qty = (float) ($raw['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($found[$pid])) {
                throw new RuntimeException('One of the products is no longer available.');
            }

            $p   = $found[$pid];
            $unitPrice = (float) $p['selling_price'];
            $gross     = round($unitPrice * $qty, 2);
            $lineDisc  = min(max(0, (float) ($raw['discount'] ?? 0)), $gross);
            $net       = round($gross - $lineDisc, 2);

            if (!$allowNeg && (float) $p['stock_qty'] + 0.0001 < $qty) {
                throw new RuntimeException(
                    'Not enough stock for "' . $p['name'] . '" (available: ' . fmt_qty($p['stock_qty']) . ').'
                );
            }

            $lines[] = [
                'product_id' => $pid,
                'sku'        => $p['sku'],
                'name'       => $p['name'],
                'qty'        => $qty,
                'unit'       => $p['unit'],
                'unit_price' => $unitPrice,
                'discount'   => $lineDisc,
                'net'        => $net,
                'tax_exempt' => (int) $p['tax_exempt'] === 1,
                'stock'      => (float) $p['stock_qty'],
            ];

            $subtotal += $net;
            if ((int) $p['tax_exempt'] !== 1) {
                $taxableNet += $net;
            }
        }

        $subtotal = round($subtotal, 2);

        /* ---------- 2. Cart-level discount ---------- */
        $dType  = ($discount['type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $dValue = max(0, (float) ($discount['value'] ?? 0));

        $cartDiscount = $dType === 'percent'
            ? round($subtotal * min(100, $dValue) / 100, 2)
            : round($dValue, 2);
        $cartDiscount = min($cartDiscount, $subtotal);

        /* ---------- 3. Tax ---------- */
        $share = $subtotal > 0 ? ($taxableNet / $subtotal) : 0;
        $taxableBase = round($taxableNet - ($cartDiscount * $share), 2);

        if ($taxRate > 0 && $taxableBase > 0) {
            $tax = $taxMode === 'inclusive'
                ? round($taxableBase - ($taxableBase / (1 + $taxRate / 100)), 2)
                : round($taxableBase * $taxRate / 100, 2);
        } else {
            $tax = 0.0;
        }

        $netSales = round($subtotal - $cartDiscount, 2);
        $total    = $taxMode === 'inclusive'
            ? round($netSales, 2)
            : round($netSales + $tax, 2);

        /* ---------- 4. Payment ---------- */
        $cashIn = $method === 'cash' ? (float) ($payment['cash'] ?? 0) : $total;
        if ($method === 'cash' && $cashIn + 0.005 < $total) {
            throw new RuntimeException('Cash received is less than the amount due.');
        }
        $change = $method === 'cash' ? round($cashIn - $total, 2) : 0.0;

        /* ---------- 5. Persist ---------- */
        $saleNo = next_sale_no();

        $saleId = db_insert('sales', [
            'sale_no'        => $saleNo,
            'user_id'        => $user['id'],
            'user_name'      => $user['name'],
            'customer_name'  => $customer !== '' ? $customer : null,
            'subtotal'       => $subtotal,
            'discount_total' => $cartDiscount,
            'tax_total'      => $tax,
            'total'          => $total,
            'paid_amount'    => $cashIn,
            'change_amount'  => $change,
            'payment_method' => $method,
            'status'         => 'completed',
            'note'           => $note !== '' ? $note : null,
        ]);

        foreach ($lines as $line) {
            // Spread the cart discount / tax across lines proportionally (for reporting)
            $lineShare = $subtotal > 0 ? ($line['net'] / $subtotal) : 0;
            $lineTax   = $line['tax_exempt'] || $taxableNet <= 0
                ? 0.0
                : round($tax * ($line['net'] / $taxableNet), 2);

            db_insert('sale_items', [
                'sale_id'         => $saleId,
                'product_id'      => $line['product_id'],
                'product_name'    => $line['name'],
                'sku'             => $line['sku'],
                'qty'             => $line['qty'],
                'unit_price'      => $line['unit_price'],
                'discount_amount' => round($line['discount'] + ($cartDiscount * $lineShare), 2),
                'tax_rate'        => $line['tax_exempt'] ? 0 : $taxRate,
                'tax_amount'      => $lineTax,
                'line_total'      => $taxMode === 'inclusive'
                    ? round($line['net'] - ($cartDiscount * $lineShare), 2)
                    : round($line['net'] - ($cartDiscount * $lineShare) + $lineTax, 2),
            ]);

            db()->prepare(
                'UPDATE products SET stock_qty = stock_qty - ?, updated_at = NOW() WHERE id = ?'
            )->execute([$line['qty'], $line['product_id']]);

            stock_move($line['product_id'], -$line['qty'], 'Sale ' . $saleNo,
                       round($line['stock'] - $line['qty'], 3));
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return [
        'ok'          => true,
        'sale_id'     => (int) $saleId,
        'sale_no'     => $saleNo,
        'subtotal'    => round($subtotal, $decimals),
        'discount'    => $cartDiscount,
        'tax'         => $tax,
        'total'       => $total,
        'paid'        => $cashIn,
        'change'      => $change,
        'method'      => $method,
        'receipt_url' => base_url('index.php?page=sale&id=' . $saleId . '&print=1'),
        'view_url'    => base_url('index.php?page=sale&id=' . $saleId),
    ];
}
