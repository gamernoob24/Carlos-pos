<?php
/** @var array $data */
$rows = $data['rows'];
$pager = $data['pager'];
$tot = $data['totals'];
?>
<div class="card">
  <form class="filters" method="get" action="<?= e(base_url('index.php')) ?>">
    <input type="hidden" name="page" value="sales">
    <label class="field-inline"><span>From</span><input class="input" type="date" name="from" value="<?= e($data['from']) ?>"></label>
    <label class="field-inline"><span>To</span><input class="input" type="date" name="to" value="<?= e($data['to']) ?>"></label>
    <input class="input" type="text" name="q" placeholder="Sale no, customer, cashier…" value="<?= e($data['q']) ?>">
    <select class="input" name="pay">
      <option value="all">All payments</option>
      <?php foreach (['cash' => 'Cash', 'card' => 'Card', 'ewallet' => 'E-Wallet'] as $k => $l): ?>
        <option value="<?= e($k) ?>" <?= $data['pay'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="input" name="status">
      <option value="all" <?= $data['status'] === 'all' ? 'selected' : '' ?>>All statuses</option>
      <option value="completed" <?= $data['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
      <option value="voided" <?= $data['status'] === 'voided' ? 'selected' : '' ?>>Voided</option>
    </select>
    <button class="btn btn-ghost" type="submit">Apply</button>
    <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=sales&action=export&from=' . urlencode($data['from']) . '&to=' . urlencode($data['to']))) ?>">Export CSV</a>
  </form>
</div>

<div class="kpi-row">
  <div class="kpi"><span>Transactions</span><strong><?= e(number_format((int) $tot['cnt'])) ?></strong></div>
  <div class="kpi"><span>Gross sales</span><strong><?= e(money($tot['gross'])) ?></strong></div>
  <div class="kpi"><span>Discounts</span><strong class="muted">-<?= e(money($tot['discounts'])) ?></strong></div>
  <div class="kpi"><span><?= e(setting('tax_name', 'Tax')) ?></span><strong><?= e(money($tot['tax'])) ?></strong></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Sale</th><th>Date</th><th>Cashier</th><th>Customer</th>
          <th class="right">Items</th><th>Payment</th>
          <th class="right">Total</th><th>Status</th><th class="right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="empty">No sales in this range.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $s): ?>
        <tr class="<?= $s['status'] === 'voided' ? 'row-voided' : '' ?>">
          <td><strong><?= e($s['sale_no']) ?></strong></td>
          <td><?= e(fmt_date($s['created_at'])) ?></td>
          <td><?= e($s['user_name']) ?></td>
          <td><?= e($s['customer_name'] ?: '—') ?></td>
          <td class="right"><?= e($s['item_count']) ?></td>
          <td><span class="pill pill-<?= e($s['payment_method']) ?>"><?= e(ucfirst($s['payment_method'])) ?></span></td>
          <td class="right"><strong><?= e(money($s['total'])) ?></strong></td>
          <td>
            <?php if ($s['status'] === 'voided'): ?>
              <span class="pill pill-muted">Voided</span>
            <?php else: ?>
              <span class="pill pill-ok">Completed</span>
            <?php endif; ?>
          </td>
          <td class="right">
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=sale&id=' . $s['id'])) ?>">View</a>
            <?php if ($s['status'] === 'completed' && is_admin()): ?>
              <button class="btn btn-sm btn-danger" type="button"
                      data-void-sale="<?= e($s['id']) ?>" data-sale-no="<?= e($s['sale_no']) ?>">Void</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pager['pages'] > 1): ?>
    <div class="pager">
      <span class="muted">Showing <?= e($pager['from']) ?>–<?= e($pager['to']) ?> of <?= e($pager['total']) ?></span>
      <div class="pager-links">
        <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
          <a class="pager-link<?= $i === $pager['page'] ? ' active' : '' ?>"
             href="<?= e(base_url('index.php?page=sales&p=' . $i . '&from=' . urlencode($data['from']) . '&to=' . urlencode($data['to']) . '&q=' . urlencode($data['q']) . '&pay=' . urlencode($data['pay']) . '&status=' . urlencode($data['status']))) ?>"><?= e($i) ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Void dialog -->
<div class="modal" id="voidModal" hidden>
  <div class="modal-card">
    <h3>Void sale <span id="voidNo"></span>?</h3>
    <p class="muted">This marks the sale as void and returns every item to stock.</p>
    <form method="post" id="voidForm" action="<?= e(base_url('index.php?page=sales&action=void')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="voidId" value="">
      <label class="field"><span>Reason</span>
        <input class="input" type="text" name="reason" placeholder="e.g. Wrong entry, customer cancelled">
      </label>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-danger">Void sale</button>
      </div>
    </form>
  </div>
</div>
