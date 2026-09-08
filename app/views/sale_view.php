<?php
/** @var array $data */
$sale  = $data['sale'];
$items = $data['items'];
$voided = $sale['status'] === 'voided';
?>
<div class="receipt-actions no-print">
  <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=sales')) ?>">← Back to sales</a>
  <a class="btn btn-ghost" href="<?= e(base_url('index.php?page=pos')) ?>">New sale</a>
  <button class="btn btn-primary" type="button" id="printBtn">Print receipt</button>
  <?php if (!$voided && is_admin()): ?>
    <button class="btn btn-danger" type="button"
            data-void-sale="<?= e($sale['id']) ?>" data-sale-no="<?= e($sale['sale_no']) ?>">Void sale</button>
  <?php endif; ?>
</div>

<div class="receipt-page">
  <div class="receipt<?= $voided ? ' is-voided' : '' ?>" id="receipt">
    <div class="r-head">
      <h2><?= e(setting('business_name', 'Aronium POS Web')) ?></h2>
      <?php if (setting('business_address')): ?><p><?= nl2br(e(setting('business_address'))) ?></p><?php endif; ?>
      <?php if (setting('business_phone')): ?><p><?= e(setting('business_phone')) ?></p><?php endif; ?>
      <?php if (setting('business_tin')): ?><p>TIN: <?= e(setting('business_tin')) ?></p><?php endif; ?>
    </div>

    <?php if ($voided): ?>
      <div class="r-voided">*** VOIDED ***</div>
    <?php endif; ?>

    <div class="r-meta">
      <div><span>Sale no.</span><strong><?= e($sale['sale_no']) ?></strong></div>
      <div><span>Date</span><strong><?= e(fmt_date($sale['created_at'])) ?></strong></div>
      <div><span>Cashier</span><strong><?= e($sale['user_name']) ?></strong></div>
      <?php if ($sale['customer_name']): ?>
        <div><span>Customer</span><strong><?= e($sale['customer_name']) ?></strong></div>
      <?php endif; ?>
    </div>

    <table class="r-items">
      <thead>
        <tr><th>Item</th><th class="c">Qty</th><th class="r">Price</th><th class="r">Amount</th></tr>
      </thead>
      <tbody>
      <?php foreach ($items as $i): ?>
        <tr>
          <td>
            <?= e($i['product_name']) ?>
            <div class="r-sku"><?= e($i['sku']) ?></div>
            <?php if ((float) $i['discount_amount'] > 0): ?>
              <div class="r-disc">less discount -<?= e(money($i['discount_amount'])) ?></div>
            <?php endif; ?>
          </td>
          <td class="c"><?= e(fmt_qty($i['qty'])) ?></td>
          <td class="r"><?= e(money($i['unit_price'])) ?></td>
          <td class="r"><?= e(money($i['line_total'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="r-totals">
      <div><span>Subtotal</span><span><?= e(money($sale['subtotal'])) ?></span></div>
      <?php if ((float) $sale['discount_total'] > 0): ?>
        <div><span>Discount</span><span>-<?= e(money($sale['discount_total'])) ?></span></div>
      <?php endif; ?>
      <?php if ((float) $sale['tax_total'] > 0 || setting('tax_rate', '0') !== '0'): ?>
        <div>
          <span><?= e(setting('tax_name', 'Tax')) ?> <?= setting('tax_mode', 'exclusive') === 'inclusive' ? '(incl.)' : '' ?></span>
          <span><?= e(money($sale['tax_total'])) ?></span>
        </div>
      <?php endif; ?>
      <div class="r-grand"><span>TOTAL</span><span><?= e(money($sale['total'])) ?></span></div>
      <div><span><?= e(ucfirst($sale['payment_method'])) ?></span><span><?= e(money($sale['paid_amount'])) ?></span></div>
      <div><span>Change</span><span><?= e(money($sale['change_amount'])) ?></span></div>
    </div>

    <?php if ($sale['note']): ?>
      <p class="r-note">Note: <?= e($sale['note']) ?></p>
    <?php endif; ?>
    <?php if ($voided): ?>
      <p class="r-note">Voided by <?= e($sale['voided_by']) ?> on <?= e(fmt_date($sale['voided_at'])) ?></p>
    <?php endif; ?>

    <p class="r-footer"><?= nl2br(e(setting('receipt_footer', 'Thank you!'))); ?></p>
    <p class="r-barcode">* <?= e($sale['sale_no']) ?> *</p>
  </div>
</div>

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

<?php if ($data['autoprint']): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });</script>
<?php endif; ?>
