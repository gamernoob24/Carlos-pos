<?php
/**
 * Cash-drawer shifts: open a shift, close it with a counted drawer,
 * and see per-shift cash vs digital totals.
 * Business rule (SRS): one open shift per cashier at a time.
 */

function shifts_controller()
{
    if (is_post()) {
        if (get('action') === 'open')  { shifts_open(); }
        if (get('action') === 'close') { shifts_close(); }
    }

    $user    = current_user();
    $myShift = current_shift();

    /* ---- shift detail ---- */
    $detailId = (int) get('id', 0);
    $detail   = null;
    $detailData = null;

    if ($detailId > 0) {
        $detail = db_one('SELECT * FROM shifts WHERE id = ?', [$detailId]);
        if (!$detail) {
            flash('Shift not found.', 'danger');
            redirect('index.php?page=shifts');
        }
        if (!is_admin() && (int) $detail['user_id'] !== (int) $user['id']) {
            flash('That shift belongs to another cashier.', 'danger');
            redirect('index.php?page=shifts');
        }
        $detailData = shift_summary($detail);
    } elseif ($myShift) {
        $detailId   = (int) $myShift['id'];
        $detail     = $myShift;
        $detailData = shift_summary($myShift);
    }

    /* ---- shift list ---- */
    $where  = '1=1';
    $params = [];
    if (!is_admin()) {
        $where    = 's.user_id = ?';
        $params[] = $user['id'];
    } elseif ((int) get('user_id', 0) > 0) {
        $where   .= ' AND s.user_id = ?';
        $params[] = (int) get('user_id');
    }

    $rows = db_all(
        "SELECT s.*,
                (SELECT COUNT(*) FROM sales sa WHERE sa.shift_id = s.id AND sa.status <> 'voided') AS order_count,
                (SELECT COALESCE(SUM(sa.total),0) FROM sales sa WHERE sa.shift_id = s.id AND sa.status <> 'voided') AS gross
           FROM shifts s
          WHERE $where
          ORDER BY s.id DESC
          LIMIT 50",
        $params
    );

    return [
        'title'    => 'Shifts',
        'myShift'  => $myShift,
        'rows'     => $rows,
        'detail'   => $detail,
        'summary'  => $detailData,
        'cashiers' => is_admin() ? db_all('SELECT id, name FROM users WHERE active = 1 ORDER BY name') : [],
    ];
}

/**
 * Everything about one shift: cash vs digital, variance, hourly split, top items.
 */
function shift_summary(array $shift)
{
    $done = "status <> 'voided' AND shift_id = " . (int) $shift['id'];

    $totals = db_one(
        "SELECT COUNT(*) AS orders,
                COALESCE(SUM(total),0)          AS gross,
                COALESCE(SUM(discount_total),0) AS discounts,
                COALESCE(SUM(tax_total),0)      AS tax,
                COALESCE(SUM(CASE WHEN payment_method = 'cash'    THEN total ELSE 0 END),0) AS cash_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'card'    THEN total ELSE 0 END),0) AS card_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'ewallet' THEN total ELSE 0 END),0) AS ewallet_sales
           FROM sales WHERE $done"
    );

    $expected = round((float) $shift['opening_cash'] + (float) $totals['cash_sales'], 2);
    $variance = $shift['closing_cash'] !== null
        ? round((float) $shift['closing_cash'] - $expected, 2)
        : null;

    $byHour = db_all(
        "SELECT HOUR(created_at) AS hr, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS total
           FROM sales WHERE $done
          GROUP BY HOUR(created_at) ORDER BY hr"
    );

    $topItems = db_all(
        "SELECT si.product_name, SUM(si.qty) AS qty,
                COALESCE(SUM(si.qty * (si.unit_price + si.modifiers_total - si.discount_amount)),0) AS revenue
           FROM sale_items si
           JOIN sales s ON s.id = si.sale_id
          WHERE $done
          GROUP BY si.product_id, si.product_name
          ORDER BY qty DESC LIMIT 5"
    );

    $orders = db_all(
        "SELECT id, sale_no, total, payment_method, status, created_at
           FROM sales WHERE $done ORDER BY id DESC LIMIT 25"
    );

    return [
        'totals'   => $totals,
        'expected' => $expected,
        'variance' => $variance,
        'byHour'   => $byHour,
        'topItems' => $topItems,
        'orders'   => $orders,
    ];
}

function shifts_open()
{
    $user = current_user();

    if (current_shift()) {
        flash('You already have an open shift. Close it before opening a new one.', 'warning');
        redirect('index.php?page=shifts');
    }

    db_insert('shifts', [
        'user_id'      => $user['id'],
        'user_name'    => $user['name'],
        'opening_cash' => post_num('opening_cash'),
        'status'       => 'open',
        'note'         => post('note') !== '' ? post('note') : null,
    ]);

    flash('Shift opened with ' . e(money(post_num('opening_cash'))) . ' in the drawer.', 'success');
    redirect('index.php?page=shifts');
}

function shifts_close()
{
    $user  = current_user();
    $shift = current_shift();

    if (!$shift) {
        flash('You do not have an open shift.', 'danger');
        redirect('index.php?page=shifts');
    }

    $summary = shift_summary($shift);
    $closing = post_num('closing_cash');

    db_update('shifts', [
        'closing_cash'  => $closing,
        'expected_cash' => $summary['expected'],
        'closed_at'     => date('Y-m-d H:i:s'),
        'status'        => 'closed',
        'note'          => post('note') !== '' ? post('note') : $shift['note'],
    ], 'id = ?', [$shift['id']]);

    $variance = round($closing - $summary['expected'], 2);
    if (abs($variance) < 0.005) {
        flash('Shift closed — the drawer balances exactly.', 'success');
    } else {
        flash('Shift closed. Drawer variance: ' . e(money($variance)) . '.', $variance < 0 ? 'warning' : 'success');
    }

    redirect('index.php?page=shifts&id=' . $shift['id']);
}
