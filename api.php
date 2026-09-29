<?php
/**
 * JSON API used by the POS and kitchen screens.
 * ------------------------------------------------------------
 *  GET  api.php?action=search&q=...      menu item lookup
 *  POST api.php?action=checkout          (JSON body)
 *  GET  api.php?action=kitchen_orders    live queue for the kitchen display
 */

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/controllers/kitchen.php';

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
    case 'kitchen_orders':
        json_out([
            'ok'     => true,
            'orders' => array_map('api_order_shape', kitchen_orders()),
            'server' => date('H:i:s'),
        ]);

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
        'id'            => (int) $p['id'],
        'sku'           => $p['sku'],
        'barcode'       => $p['barcode'],
        'name'          => $p['name'],
        'price'         => (float) $p['selling_price'],
        'stock'         => (float) $p['stock_qty'],
        'unit'          => $p['unit'] ?? 'pc',
        'tax_exempt'    => (int) ($p['tax_exempt'] ?? 0),
        'category_id'   => $p['category_id'] !== null ? (int) $p['category_id'] : null,
        'category_name' => $p['category_name'] ?? null,
    ];
}

function api_order_shape(array $o)
{
    return [
        'id'          => (int) $o['id'],
        'sale_no'     => $o['sale_no'],
        'status'      => $o['status'],
        'age_minutes' => (int) ($o['age_minutes'] ?? 0),
        'created_at'  => $o['created_at'],
        'customer'    => $o['customer_name'],
        'cashier'     => $o['user_name'],
        'items'       => array_map(function ($it) {
            return [
                'name'      => $it['product_name'],
                'qty'       => (float) $it['qty'],
                'modifiers' => array_map(function ($m) {
                    return ['name' => $m['modifier_name'], 'delta' => (float) $m['price_delta']];
                }, $it['modifiers'] ?? []),
            ];
        }, $o['items'] ?? []),
    ];
}

/**
 * Validate the cart, persist the order, deduct product stock AND every
 * recipe ingredient, then queue the order for the kitchen.
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

    $taxRate  = (float) setting('tax_rate', '0');
    $taxMode  = setting('tax_mode', 'inclusive');
    $allowNeg = (int) setting('allow_negative_stock', '0');
    $decimals = (int) setting('decimal_places', '2');

    $method = in_array(($payment['method'] ?? 'cash'), ['cash', 'card', 'ewallet'], true)
        ? $payment['method'] : 'cash';

    $user  = current_user();
    $shift = current_shift();

    /* ---------- available modifiers ---------- */
    $modifierMap = [];
    foreach (db_all('SELECT id, name, price_delta FROM modifiers WHERE active = 1') as $m) {
        $modifierMap[(int) $m['id']] = ['name' => $m['name'], 'delta' => (float) $m['price_delta']];
    }

    /* ---------- group requested quantities ---------- */
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

        $lines           = [];
        $subtotal        = 0.0;
        $taxableNet      = 0.0;
        $ingredientNeeds = [];
        $lineRecipes     = [];

        foreach ($items as $raw) {
            $pid = (int) ($raw['id'] ?? 0);
            $qty = (float) ($raw['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($found[$pid])) {
                throw new RuntimeException('One of the menu items is no longer available.');
            }

            $p = $found[$pid];

            /* --- chosen modifiers (extra cheese, no onions …) --- */
            $chosen   = [];
            $modTotal = 0.0;
            foreach ((array) ($raw['modifiers'] ?? []) as $mid) {
                $mid = (int) $mid;
                if (isset($modifierMap[$mid])) {
                    $chosen[] = ['id' => $mid, 'name' => $modifierMap[$mid]['name'], 'delta' => $modifierMap[$mid]['delta']];
                    $modTotal += $modifierMap[$mid]['delta'];
                }
            }
            $modTotal = round($modTotal, 2);

            $unitPrice = round((float) $p['selling_price'] + $modTotal, 2);
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
                'base_price' => (float) $p['selling_price'],
                'unit_price' => $unitPrice,
                'modifiers'  => $chosen,
                'mod_total'  => $modTotal,
                'discount'   => $lineDisc,
                'net'        => $net,
                'tax_exempt' => (int) $p['tax_exempt'] === 1,
                'stock'      => (float) $p['stock_qty'],
            ];

            $subtotal += $net;
            if ((int) $p['tax_exempt'] !== 1) {
                $taxableNet += $net;
            }

            /* --- recipe: ingredients this line consumes --- */
            $recipe = db_all(
                'SELECT pi.ingredient_id, pi.qty_per_unit, i.name, i.stock_qty, i.unit
                   FROM product_ingredients pi
                   JOIN ingredients i ON i.id = pi.ingredient_id
                  WHERE pi.product_id = ?',
                [$pid]
            );
            $lineRecipes[count($lines) - 1] = $recipe;

            foreach ($recipe as $r) {
                $need = (float) $r['qty_per_unit'] * $qty;
                $ingredientNeeds[(int) $r['ingredient_id']] = ($ingredientNeeds[(int) $r['ingredient_id']] ?? 0) + $need;
            }
        }

        $subtotal = round($subtotal, 2);

        /* ---------- cart-level discount ---------- */
        $dType  = ($discount['type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $dValue = max(0, (float) ($discount['value'] ?? 0));

        $cartDiscount = $dType === 'percent'
            ? round($subtotal * min(100, $dValue) / 100, 2)
            : round($dValue, 2);
        $cartDiscount = min($cartDiscount, $subtotal);

        /* ---------- tax ---------- */
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

        /* ---------- payment ---------- */
        $cashIn = $method === 'cash' ? (float) ($payment['cash'] ?? 0) : $total;
        if ($method === 'cash' && $cashIn + 0.005 < $total) {
            throw new RuntimeException('Cash received is less than the amount due.');
        }
        $change = $method === 'cash' ? round($cashIn - $total, 2) : 0.0;

        /* ---------- raw ingredient availability ---------- */
        if ($ingredientNeeds && !$allowNeg) {
            $ingIds = array_keys($ingredientNeeds);
            $ph     = implode(',', array_fill(0, count($ingIds), '?'));
            $rows   = db_all(
                "SELECT id, name, stock_qty, unit FROM ingredients WHERE id IN ($ph) FOR UPDATE",
                $ingIds
            );
            $stock = [];
            foreach ($rows as $r) {
                $stock[(int) $r['id']] = $r;
            }
            foreach ($ingredientNeeds as $ingId => $need) {
                $have = isset($stock[$ingId]) ? (float) $stock[$ingId]['stock_qty'] : 0.0;
                if ($have + 0.0001 < $need) {
                    throw new RuntimeException(
                        'Not enough ' . ($stock[$ingId]['name'] ?? 'ingredient') . ' in stock (need '
                        . fmt_qty($need) . ', have ' . fmt_qty($have) . ').'
                    );
                }
            }
        }

        /* ---------- persist ---------- */
        $saleNo = next_sale_no();

        $saleId = db_insert('sales', [
            'sale_no'        => $saleNo,
            'user_id'        => $user['id'],
            'user_name'      => $user['name'],
            'shift_id'       => $shift ? $shift['id'] : null,
            'customer_name'  => $customer !== '' ? $customer : null,
            'subtotal'       => $subtotal,
            'discount_total' => $cartDiscount,
            'tax_total'      => $tax,
            'total'          => $total,
            'paid_amount'    => $cashIn,
            'change_amount'  => $change,
            'payment_method' => $method,
            'status'         => 'queued',      // SRS: order is queued for kitchen prep
            'note'           => $note !== '' ? $note : null,
        ]);

        $stItem = db()->prepare(
            'INSERT INTO sale_items
                (sale_id, product_id, product_name, sku, qty, unit_price, modifiers_total,
                 discount_amount, tax_rate, tax_amount, line_total)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stMod = db()->prepare(
            'INSERT INTO sale_item_modifiers (sale_item_id, modifier_id, modifier_name, price_delta)
             VALUES (?,?,?,?)'
        );
        $stIng = db()->prepare(
            'INSERT INTO sale_item_ingredients (sale_item_id, ingredient_id, ingredient_name, qty_used)
             VALUES (?,?,?,?)'
        );
        $stDeductIng = db()->prepare(
            'UPDATE ingredients SET stock_qty = stock_qty - ?, updated_at = NOW() WHERE id = ?'
        );
        $stDeductProd = db()->prepare(
            'UPDATE products SET stock_qty = stock_qty - ?, updated_at = NOW() WHERE id = ?'
        );

        foreach ($lines as $idx => $line) {
            $lineShare = $subtotal > 0 ? ($line['net'] / $subtotal) : 0;
            $lineTax   = $line['tax_exempt'] || $taxableNet <= 0
                ? 0.0
                : round($tax * ($line['net'] / $taxableNet), 2);

            $lineTotal = $taxMode === 'inclusive'
                ? round($line['net'] - ($cartDiscount * $lineShare), 2)
                : round($line['net'] - ($cartDiscount * $lineShare) + $lineTax, 2);

            $stItem->execute([
                $saleId,
                $line['product_id'],
                $line['name'],
                $line['sku'],
                $line['qty'],
                $line['base_price'],
                $line['mod_total'],
                round($line['discount'] + ($cartDiscount * $lineShare), 2),
                $line['tax_exempt'] ? 0 : $taxRate,
                $lineTax,
                $lineTotal,
            ]);
            $saleItemId = (int) db()->lastInsertId();

            foreach ($line['modifiers'] as $mod) {
                $stMod->execute([$saleItemId, $mod['id'], $mod['name'], $mod['delta']]);
            }

            // finished-product stock
            $stDeductProd->execute([$line['qty'], $line['product_id']]);
            stock_move($line['product_id'], -$line['qty'], 'Order ' . $saleNo,
                       round($line['stock'] - $line['qty'], 3));

            // raw ingredients this recipe consumes
            foreach ($lineRecipes[$idx] ?? [] as $r) {
                $used = (float) $r['qty_per_unit'] * $line['qty'];
                $stDeductIng->execute([$used, $r['ingredient_id']]);
                $stIng->execute([$saleItemId, $r['ingredient_id'], $r['name'], $used]);

                $balance = (float) db_val('SELECT stock_qty FROM ingredients WHERE id = ?', [$r['ingredient_id']]);
                stock_move_ingredient($r['ingredient_id'], -$used, 'Order ' . $saleNo, $balance);
            }
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
        'status'      => 'queued',
        'kitchen_url' => base_url('index.php?page=kitchen'),
        'receipt_url' => base_url('index.php?page=sale&id=' . $saleId . '&print=1'),
        'view_url'    => base_url('index.php?page=sale&id=' . $saleId),
    ];
}
