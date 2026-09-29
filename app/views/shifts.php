<?php
/** @var array $data */
$myShift = $data['myShift'];
$detail  = $data['detail'];
$summary = $data['summary'];
?>

<div class="grid-2">
  <!-- ============ Open / close drawer ============ -->
  <div class="card">
    <div class="card-head">
      <h3><?= $myShift ? 'My open shift' : 'Open a shift' ?></h3>
      <?php if ($myShift): ?><span class="pill pill-ok">Open since <?= e(fmt_date($myShift['opened_at'])) ?></span><?php endif; ?>
    </div>

    <?php if ($myShift): ?>
      <?php $s = shift_summary($myShift); ?>
      <ul class="kv">
        <li><span>Opening cash</span><strong><?= e(money($myShift['opening_cash'])) ?></strong></li>
        <li><span>Orders this shift</span><strong><?= e($s['totals']['orders']) ?></strong></li>
        <li><span>Cash sales</span><strong><?= e(money($s['totals']['cash_sales'])) ?></strong></li>
        <li><span>Card / e-wallet</span><strong><?= e(money((float) $s['totals']['card_sales'] + (float) $s['totals']['ewallet_sales'])) ?></strong></li>
        <li><span>Expected in drawer</span><strong class="text-ok"><?= e(money($s['expected'])) ?></strong></li>
      </ul>
      <hr class="sep">
      <form method="post" action="<?= e(base_url('index.php?page=shifts&action=close')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Counted cash in drawer</span>
          <input class="input input-lg" type="number" step="0.01" min="0" name="closing_cash" required
                 value="<?= e(money_plain($s['expected'])) ?>">
        </label>
        <label class="field"><span>Note (optional)</span>
          <input class="input" type="text" name="note" placeholder="e.g. spilled drink, IOU from owner">
        </label>
        <button class="btn btn-primary" type="submit">Close shift</button>
      </form>
    <?php else: ?>
      <p class="muted">Count the cash in the drawer before you start selling. Every order you take is logged against this shift.</p>
      <form method="post" action="<?= e(base_url('index.php?page=shifts&action=open')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Opening cash in drawer</span>
          <input class="input input-lg" type="number" step="0.01" min="0" name="opening_cash" value="0.00" required>
        </label>
        <label class="field"><span>Note (optional)</span>
          <input class="input" type="text" name="note" placeholder="e.g. morning shift">
        </label>
        <button class="btn btn-primary" type="submit">Open shift</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- ============ Shift detail ============ -->
  <div class="card">
    <div class="card-head">
      <h3><?= $detail ? 'Shift #' . e($detail['id']) : 'Shift summary' ?></h3>
      <?php if ($detail): ?>
        <span class="pill <?= $detail['status'] === 'open' ? 'pill-ok' : 'pill-muted' ?>"><?= e(ucfirst($detail['status'])) ?></span>
      <?php endif; ?>
    </div>

    <?php if (!$detail || !$summary): ?>
      <p class="muted">No shift selected. Open a shift or pick one from the history below.</p>
    <?php else: ?>
      <ul class="kv">
        <li><span>Cashier</span><strong><?= e($detail['user_name']) ?></strong></li>
        <li><span>Opened</span><strong><?= e(fmt_date($detail['opened_at'])) ?></strong></li>
        <li><span>Closed</span><strong><?= e($detail['closed_at'] ? fmt_date($detail['closed_at']) : '—') ?></strong></li>
        <li><span>Opening cash</span><strong><?= e(money($detail['opening_cash'])) ?></strong></li>
        <li><span>Cash sales</span><strong><?= e(money($summary['totals']['cash_sales'])) ?></strong></li>
        <li><span>Card sales</span><strong><?= e(money($summary['totals']['card_sales'])) ?></strong></li>
        <li><span>E-wallet sales</span><strong><?= e(money($summary['totals']['ewallet_sales'])) ?></strong></li>
        <li><span>Expected in drawer</span><strong><?= e(money($summary['expected'])) ?></strong></li>
        <li><span>Counted</span><strong><?= e($detail['closing_cash'] !== null ? money($detail['closing_cash']) : '—') ?></strong></li>
        <li><span>Variance</span>
          <strong class="<?= $summary['variance'] === null ? 'muted' : (abs($summary['variance']) < 0.005 ? 'text-ok' : 'text-bad') ?>">
            <?= e($summary['variance'] === null ? 'not closed yet' : money($summary['variance'])) ?>
          </strong>
        </li>
      </ul>

      <?php if ($summary['byHour']): ?>
        <hr class="sep">
        <h4>Sales by hour</h4>
        <?php $hMax = 0; foreach ($summary['byHour'] as $h) { $hMax = max($hMax, (float) $h['total']); } ?>
        <div class="bars" style="height:120px">
          <?php foreach ($summary['byHour'] as $h): ?>
            <div class="bar-col" title="<?= e($h['hr']) ?>:00 — <?= e(money($h['total'])) ?> (<?= e($h['cnt']) ?> orders)">
              <div class="bar" style="height:<?= $hMax > 0 ? max(3, round(((float) $h['total'] / $hMax) * 100)) : 3 ?>%"></div>
              <span class="bar-label"><?= e(str_pad((string) $h['hr'], 2, '0', STR_PAD_LEFT)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($summary['topItems']): ?>
        <hr class="sep">
        <h4>Top items this shift</h4>
        <table class="table table-sm">
          <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Revenue</th></tr></thead>
          <tbody>
          <?php foreach ($summary['topItems'] as $t): ?>
            <tr>
              <td><?= e($t['product_name']) ?></td>
              <td class="right"><?= e(fmt_qty($t['qty'])) ?></td>
              <td class="right"><?= e(money($t['revenue'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ============ Shift history ============ -->
<div class="card">
  <div class="card-head">
    <h3>Shift history</h3>
    <?php if (is_admin()): ?>
      <form method="get" action="<?= e(base_url('index.php')) ?>" class="inline-form">
        <input type="hidden" name="page" value="shifts">
        <select class="input input-sm" name="user_id" onchange="this.form.submit()">
          <option value="0">All cashiers</option>
          <?php foreach ($data['cashiers'] as $c): ?>
            <option value="<?= e($c['id']) ?>" <?= (int) get('user_id', 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Shift</th>
          <?php if (is_admin()): ?><th>Cashier</th><?php endif; ?>
          <th>Opened</th><th>Closed</th>
          <th class="right">Orders</th><th class="right">Gross</th>
          <th class="right">Opening</th><th class="right">Expected</th><th class="right">Counted</th>
          <th class="right">Variance</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$data['rows']): ?>
        <tr><td colspan="11" class="empty">No shifts recorded yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($data['rows'] as $s): ?>
        <?php
          $sum   = shift_summary($s);
          $count = $s['closing_cash'] !== null ? (float) $s['closing_cash'] : null;
          $var   = $count !== null ? round($count - $sum['expected'], 2) : null;
        ?>
        <tr>
          <td><strong>#<?= e($s['id']) ?></strong></td>
          <?php if (is_admin()): ?><td><?= e($s['user_name']) ?></td><?php endif; ?>
          <td><?= e(fmt_date($s['opened_at'])) ?></td>
          <td><?= e($s['closed_at'] ? fmt_date($s['closed_at']) : '—') ?></td>
          <td class="right"><?= e($s['order_count']) ?></td>
          <td class="right"><?= e(money($s['gross'])) ?></td>
          <td class="right"><?= e(money($s['opening_cash'])) ?></td>
          <td class="right"><?= e(money($sum['expected'])) ?></td>
          <td class="right"><?= e($count !== null ? money($count) : '—') ?></td>
          <td class="right">
            <?php if ($var === null): ?>
              <span class="pill pill-ok">Open</span>
            <?php else: ?>
              <strong class="<?= abs($var) < 0.005 ? 'text-ok' : 'text-bad' ?>"><?= e(money($var)) ?></strong>
            <?php endif; ?>
          </td>
          <td class="right"><a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=shifts&id=' . $s['id'])) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
