<?php
/**
 * Kitchen display: every paid order that is still queued or being prepared,
 * showing the per-item modifiers ("no onions", "extra cheese" …).
 */

function kitchen_controller()
{
    if (is_post() && get('action') === 'advance') {
        kitchen_advance();
    }

    $orders = kitchen_orders();

    $served = db_all(
        "SELECT id, sale_no, total, created_at, ready_at, user_name
           FROM sales
          WHERE status = 'completed' AND DATE(created_at) = CURDATE()
          ORDER BY ready_at DESC LIMIT 12"
    );

    return [
        'title'  => 'Kitchen Display',
        'orders' => $orders,
        'served' => $served,
        'counts' => [
            'queued'    => count(array_filter($orders, function ($o) { return $o['status'] === 'queued'; })),
            'preparing' => count(array_filter($orders, function ($o) { return $o['status'] === 'preparing'; })),
        ],
    ];
}

/** Open orders with their lines and modifiers, oldest first. */
function kitchen_orders()
{
    $orders = db_all(
        "SELECT s.*, TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) AS age_minutes
           FROM sales s
          WHERE s.status IN ('queued','preparing')
          ORDER BY FIELD(s.status, 'preparing', 'queued'), s.id ASC
          LIMIT 40"
    );

    if (!$orders) {
        return [];
    }

    $ids  = implode(',', array_map('intval', array_column($orders, 'id')));
    $items = db_all("SELECT * FROM sale_items WHERE sale_id IN ($ids) ORDER BY id");
    $itemIds = array_column($items, 'id');

    $mods = [];
    if ($itemIds) {
        $modRows = db_all(
            'SELECT * FROM sale_item_modifiers
              WHERE sale_item_id IN (' . implode(',', array_map('intval', $itemIds)) . ')'
        );
        foreach ($modRows as $m) {
            $mods[$m['sale_item_id']][] = $m;
        }
    }

    foreach ($items as $i => $item) {
        $items[$i]['modifiers'] = $mods[$item['id']] ?? [];
    }

    foreach ($orders as $o => $order) {
        $orders[$o]['items'] = array_values(array_filter($items, function ($it) use ($order) {
            return (int) $it['sale_id'] === (int) $order['id'];
        }));
    }

    return $orders;
}

/** Move an order along: queued → preparing → completed (served). */
function kitchen_advance()
{
    $id = (int) post('id', 0);
    $to = (string) post('to', '');

    $allowed = ['queued' => 'preparing', 'preparing' => 'completed'];
    $sale = db_one('SELECT id, status FROM sales WHERE id = ?', [$id]);

    if (!$sale || !isset($allowed[$sale['status']]) || $allowed[$sale['status']] !== $to) {
        flash('That order was already updated.', 'warning');
        redirect('index.php?page=kitchen');
    }

    $data = ['status' => $to];
    if ($to === 'completed') {
        $data['ready_at'] = date('Y-m-d H:i:s');
    }

    db_update('sales', $data, 'id = ?', [$id]);
    redirect('index.php?page=kitchen');
}
