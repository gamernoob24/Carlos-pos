<?php
/** @var array $data */
$orders = $data['orders'];
$served = $data['served'];
?>
<div class="kitchen-head">
  <div class="kpi-row compact" style="flex:1;margin:0">
    <div class="kpi"><span>Queued</span><strong class="<?= $data['counts']['queued'] > 4 ? 'text-bad' : '' ?>"><?= e($data['counts']['queued']) ?></strong></div>
    <div class="kpi"><span>Preparing</span><strong><?= e($data['counts']['preparing']) ?></strong></div>
    <div class="kpi"><span>Served today</span><strong><?= e(count($served)) ?></strong></div>
  </div>
  <div class="kitchen-meta">
    <label class="check"><input type="checkbox" id="autoRefresh" checked> <span>Auto-refresh</span></label>
    <span class="muted small" id="lastUpdate">updated <?= e(date('h:i:s A')) ?></span>
    <a class="btn btn-ghost btn-sm" href="<?= e(base_url('index.php?page=kitchen')) ?>">Refresh now</a>
  </div>
</div>

<?php if (!$orders): ?>
  <div class="card empty-state">
    <h2>No open orders 🍔</h2>
    <p class="muted">Orders placed at the counter appear here instantly.</p>
    <a class="btn btn-primary" href="<?= e(base_url('index.php?page=pos')) ?>">Go to counter</a>
  </div>
<?php else: ?>
<div class="kitchen-grid" id="kitchenGrid">
  <?php foreach ($orders as $order): ?>
    <?php $isPrep = $order['status'] === 'preparing'; ?>
    <div class="order-card <?= $isPrep ? 'is-preparing' : 'is-queued' ?>">
      <div class="oc-head">
        <div>
          <strong><?= e($order['sale_no']) ?></strong>
          <?php if ($order['customer_name']): ?>
            <div class="oc-customer"><?= e($order['customer_name']) ?></div>
          <?php endif; ?>
        </div>
        <div class="oc-age <?= $order['age_minutes'] >= 10 ? 'late' : '' ?>"><?= e($order['age_minutes']) ?> min</div>
      </div>

      <ul class="oc-items">
        <?php foreach ($order['items'] as $item): ?>
          <li>
            <span class="oc-qty"><?= e(fmt_qty($item['qty'])) ?>×</span>
            <span class="oc-name">
              <?= e($item['product_name']) ?>
              <?php if (!empty($item['modifiers'])): ?>
                <span class="oc-mods">
                  <?php foreach ($item['modifiers'] as $m): ?>
                    <span class="mod-chip <?= (float) $m['price_delta'] > 0 ? 'paid' : 'free' ?>">
                      <?= e($m['modifier_name']) ?><?= (float) $m['price_delta'] > 0 ? ' +' . e(money($m['price_delta'])) : '' ?>
                    </span>
                  <?php endforeach; ?>
                </span>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="oc-foot">
        <span class="muted small"><?= e($order['user_name']) ?> · <?= e(date('h:i A', strtotime($order['created_at']))) ?></span>
        <div class="oc-actions">
          <form method="post" action="<?= e(base_url('index.php?page=kitchen&action=advance')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($order['id']) ?>">
            <input type="hidden" name="to" value="<?= $isPrep ? 'completed' : 'preparing' ?>">
            <button class="btn btn-primary btn-sm" type="submit">
              <?= $isPrep ? 'Mark served ✓' : 'Start preparing' ?>
            </button>
          </form>
          <a class="btn btn-ghost btn-sm" href="<?= e(base_url('index.php?page=sale&id=' . $order['id'])) ?>">Receipt</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($served): ?>
<div class="card">
  <div class="card-head"><h3>Served today</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Order</th><th>Ready</th><th>Cashier</th><th class="right">Total</th></tr></thead>
      <tbody>
      <?php foreach ($served as $s): ?>
        <tr>
          <td><strong><?= e($s['sale_no']) ?></strong></td>
          <td><?= e($s['ready_at'] ? date('h:i A', strtotime($s['ready_at'])) : '—') ?></td>
          <td><?= e($s['user_name']) ?></td>
          <td class="right"><?= e(money($s['total'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
/* Live kitchen queue: poll the API, reload only when something changed. */
(function () {
  var signature = <?= json_encode(implode('|', array_map(function ($o) { return $o['id'] . ':' . $o['status']; }, $orders)), JSON_HEX_TAG) ?>;
  var box = document.getElementById('autoRefresh');
  var stamp = document.getElementById('lastUpdate');

  function poll() {
    if (box && !box.checked) return;
    fetch('<?= e(base_url('api.php?action=kitchen_orders')) ?>')
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) return;
        var next = res.orders.map(function (o) { return o.id + ':' + o.status; }).join('|');
        if (stamp) stamp.textContent = 'updated ' + (res.server || '');
        if (next !== signature) location.reload();
      })
      .catch(function () { /* offline: keep the last known board */ });
  }

  setInterval(poll, 8000);
})();
</script>
