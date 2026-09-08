<?php
/** @var array $data */
$s   = $data['summary'];
$max = 0.0;
foreach ($data['timeline'] as $t) { $max = max($max, (float) $t['total']); }
$totalSales = max(0.01, (float) $s['gross']);

$quick = [
    'Today'        => [date('Y-m-d'), date('Y-m-d')],
    'Yesterday'    => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'Last 7 days'  => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    'This month'   => [date('Y-m-01'), date('Y-m-d')],
    'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
];
?>
<div class="card">
  <form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
    <input type="hidden" name="page" value="reports">
    <label class="field-inline"><span>From</span><input class="input" type="date" name="from" value="<?= e($data['from']) ?>"></label>
    <label class="field-inline"><span>To</span><input class="input" type="date" name="to" value="<?= e($data['to']) ?>"></label>
    <button class="btn btn-primary" type="submit">Show</button>
    <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=reports&action=export&from=' . urlencode($data['from']) . '&to=' . urlencode($data['to']))) ?>">Export product sales</a>
  </form>
  <div class="chips">
    <?php foreach ($quick as $label => [$f, $t]): ?>
      <a class="chip<?= $data['from'] === $f && $data['to'] === $t ? ' active' : '' ?>"
         href="<?= e(base_url('index.php?page=reports&from=' . $f . '&to=' . $t)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="kpi-row">
  <div class="kpi accent"><span>Gross sales</span><strong><?= e(money($s['gross'])) ?></strong></div>
  <div class="kpi"><span>Transactions</span><strong><?= e(number_format($s['transactions'])) ?></strong></div>
  <div class="kpi"><span>Average sale</span><strong><?= e(money($s['avg'])) ?></strong></div>
  <div class="kpi"><span>Discounts</span><strong class="muted">-<?= e(money($s['discounts'])) ?></strong></div>
  <div class="kpi"><span><?= e(setting('tax_name', 'Tax')) ?> collected</span><strong><?= e(money($s['tax'])) ?></strong></div>
  <div class="kpi"><span>Gross profit (est.)</span><strong class="<?= (float) $s['profit'] >= 0 ? 'text-ok' : 'text-bad' ?>"><?= e(money($s['profit'])) ?></strong></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Sales over time</h3><span class="muted small"><?= e(count($data['timeline'])) ?> periods</span></div>
    <?php if (!$data['timeline']): ?>
      <p class="empty">No sales in this range.</p>
    <?php else: ?>
      <div class="bars">
        <?php foreach ($data['timeline'] as $t): ?>
          <?php $h = $max > 0 ? max(3, round(((float) $t['total'] / $max) * 100)) : 3; ?>
          <div class="bar-col" title="<?= e($t['period']) ?>: <?= e(money($t['total'])) ?> (<?= e($t['cnt']) ?> sales)">
            <div class="bar" style="height:<?= e($h) ?>%"></div>
            <span class="bar-label"><?= e(substr((string) $t['period'], -5)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3>Payment mix</h3></div>
    <?php if (!$data['payments']): ?>
      <p class="empty">No sales in this range.</p>
    <?php else: ?>
      <ul class="meter-list">
        <?php foreach ($data['payments'] as $p): ?>
          <?php $pct = round(((float) $p['total'] / $totalSales) * 100, 1); ?>
          <li>
            <div class="meter-top"><span class="pill pill-<?= e($p['payment_method']) ?>"><?= e(ucfirst($p['payment_method'])) ?></span>
              <strong><?= e(money($p['total'])) ?> <span class="muted">(<?= e($pct) ?>%)</span></strong></div>
            <div class="meter"><div class="meter-fill m-<?= e($p['payment_method']) ?>" style="width:<?= e($pct) ?>%"></div></div>
            <span class="muted small"><?= e($p['cnt']) ?> transactions</span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Top selling products</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Product</th><th class="right">Qty</th><th class="right">Revenue</th></tr></thead>
        <tbody>
        <?php if (!$data['topProducts']): ?>
          <tr><td colspan="3" class="empty">No sales in this range.</td></tr>
        <?php endif; ?>
        <?php foreach ($data['topProducts'] as $r): ?>
          <tr>
            <td><strong><?= e($r['product_name']) ?></strong><div class="muted small"><?= e($r['sku']) ?></div></td>
            <td class="right"><?= e(fmt_qty($r['qty_sold'])) ?></td>
            <td class="right"><?= e(money($r['revenue'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>By category</h3></div>
    <?php if (!$data['byCategory']): ?>
      <p class="empty">No sales in this range.</p>
    <?php else: ?>
      <?php $catMax = 0.0; foreach ($data['byCategory'] as $c) { $catMax = max($catMax, (float) $c['revenue']); } ?>
      <ul class="meter-list">
        <?php foreach ($data['byCategory'] as $c): ?>
          <?php $pct = $catMax > 0 ? round(((float) $c['revenue'] / $catMax) * 100, 1) : 0; ?>
          <li>
            <div class="meter-top"><span><?= e($c['name']) ?></span><strong><?= e(money($c['revenue'])) ?></strong></div>
            <div class="meter"><div class="meter-fill" style="width:<?= e($pct) ?>%"></div></div>
            <span class="muted small"><?= e(fmt_qty($c['qty_sold'])) ?> sold</span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Cashier performance</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Cashier</th><th class="right">Sales</th><th class="right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($data['byCashier'] as $c): ?>
          <tr>
            <td><?= e($c['user_name']) ?></td>
            <td class="right"><?= e($c['cnt']) ?></td>
            <td class="right"><?= e(money($c['total'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['byCashier']): ?>
          <tr><td colspan="3" class="empty">No sales in this range.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Inventory snapshot</h3>
      <a class="link" href="<?= e(base_url('index.php?page=products&status=low')) ?>">Low stock</a>
    </div>
    <div class="kpi-row compact">
      <div class="kpi"><span>Active products</span><strong><?= e(number_format($data['stock']['products'])) ?></strong></div>
      <div class="kpi"><span>Stock at cost</span><strong><?= e(money($data['stock']['value'])) ?></strong></div>
      <div class="kpi"><span>Stock at retail</span><strong><?= e(money($data['stock']['retail'])) ?></strong></div>
      <div class="kpi"><span>Low stock items</span><strong class="<?= $data['stock']['low'] > 0 ? 'text-bad' : '' ?>"><?= e($data['stock']['low']) ?></strong></div>
    </div>
  </div>
</div>
