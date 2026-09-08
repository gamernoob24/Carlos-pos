<?php /** @var array $data */ ?>
<div class="grid-2">

  <?php if (is_admin()): ?>
  <div class="card span-2">
    <div class="card-head"><h3>Business &amp; receipt</h3></div>
    <form method="post" action="<?= e(base_url('index.php?page=settings&action=save')) ?>" class="form-grid">
      <?= csrf_field() ?>

      <label class="field span-2"><span>Business name</span>
        <input class="input" type="text" name="business_name" value="<?= e(setting('business_name')) ?>">
      </label>
      <label class="field span-2"><span>Address (shown on receipts)</span>
        <textarea class="input" name="business_address" rows="2"><?= e(setting('business_address')) ?></textarea>
      </label>
      <label class="field"><span>Phone</span>
        <input class="input" type="text" name="business_phone" value="<?= e(setting('business_phone')) ?>">
      </label>
      <label class="field"><span>Tax / registration no.</span>
        <input class="input" type="text" name="business_tin" value="<?= e(setting('business_tin')) ?>">
      </label>
      <label class="field span-2"><span>Receipt footer</span>
        <textarea class="input" name="receipt_footer" rows="2"><?= e(setting('receipt_footer')) ?></textarea>
      </label>
      <label class="field"><span>Receipt paper width</span>
        <select class="input" name="receipt_width">
          <option value="58" <?= setting('receipt_width') === '58' ? 'selected' : '' ?>>58 mm</option>
          <option value="80" <?= setting('receipt_width', '80') === '80' ? 'selected' : '' ?>>80 mm</option>
        </select>
      </label>
      <label class="field"><span>Sale number prefix</span>
        <input class="input" type="text" name="sale_prefix" value="<?= e(setting('sale_prefix', 'INV')) ?>">
      </label>

      <hr class="sep span-2">
      <h4 class="span-2">Currency &amp; tax</h4>

      <label class="field"><span>Currency symbol</span>
        <input class="input" type="text" name="currency_symbol" value="<?= e(setting('currency_symbol', '₱')) ?>">
      </label>
      <label class="field"><span>Symbol position</span>
        <select class="input" name="currency_position">
          <option value="before" <?= setting('currency_position') === 'before' ? 'selected' : '' ?>>Before amount (<?= e(setting('currency_symbol', '₱')) ?>1,000.00)</option>
          <option value="after" <?= setting('currency_position') === 'after' ? 'selected' : '' ?>>After amount (1,000.00 <?= e(setting('currency_symbol', '₱')) ?>)</option>
        </select>
      </label>
      <label class="field"><span>Decimal places</span>
        <select class="input" name="decimal_places">
          <?php foreach (['0' => '0', '2' => '2', '3' => '3'] as $k => $l): ?>
            <option value="<?= e($k) ?>" <?= setting('decimal_places', '2') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>Tax name</span>
        <input class="input" type="text" name="tax_name" value="<?= e(setting('tax_name', 'VAT')) ?>">
      </label>
      <label class="field"><span>Tax rate (%)</span>
        <input class="input" type="number" step="0.001" min="0" name="tax_rate" value="<?= e(setting('tax_rate', '0')) ?>">
      </label>
      <label class="field"><span>Tax handling</span>
        <select class="input" name="tax_mode">
          <option value="exclusive" <?= setting('tax_mode') === 'exclusive' ? 'selected' : '' ?>>Added on top of price (exclusive)</option>
          <option value="inclusive" <?= setting('tax_mode') === 'inclusive' ? 'selected' : '' ?>>Already included in price (inclusive)</option>
        </select>
      </label>

      <hr class="sep span-2">
      <h4 class="span-2">Stock</h4>

      <label class="field"><span>Default reorder level</span>
        <input class="input" type="number" step="1" min="0" name="low_stock_threshold" value="<?= e(setting('low_stock_threshold', '5')) ?>">
      </label>
      <div class="field checks">
        <label class="check">
          <input type="checkbox" name="allow_negative_stock" value="1" <?= setting('allow_negative_stock', '0') === '1' ? 'checked' : '' ?>>
          <span>Allow selling when stock is zero (negative stock)</span>
        </label>
      </div>

      <div class="form-actions span-2">
        <button class="btn btn-primary" type="submit">Save settings</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="card span-2">
    <div class="card-head"><h3>Business &amp; receipt</h3></div>
    <p class="muted">Only an administrator can change business, tax and receipt settings.</p>
    <ul class="kv">
      <li><span>Business</span><strong><?= e(setting('business_name')) ?></strong></li>
      <li><span>Tax</span><strong><?= e(setting('tax_name')) ?> <?= e(setting('tax_rate')) ?>%</strong></li>
      <li><span>Currency</span><strong><?= e(setting('currency_symbol')) ?></strong></li>
    </ul>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><h3>Change my password</h3></div>
    <form method="post" action="<?= e(base_url('index.php?page=settings&action=password')) ?>" class="form">
      <?= csrf_field() ?>
      <label class="field"><span>Current password</span>
        <input class="input" type="password" name="current_password" required>
      </label>
      <label class="field"><span>New password</span>
        <input class="input" type="password" name="new_password" required minlength="6">
      </label>
      <label class="field"><span>Confirm new password</span>
        <input class="input" type="password" name="confirm_password" required minlength="6">
      </label>
      <button class="btn btn-primary" type="submit">Update password</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>System</h3></div>
    <ul class="kv">
      <li><span>App</span><strong><?= e(APP_NAME) ?></strong></li>
      <li><span>PHP version</span><strong><?= e(PHP_VERSION) ?></strong></li>
      <li><span>Database</span><strong><?= e(DB_NAME) ?> @ <?= e(DB_HOST) ?></strong></li>
      <li><span>Server time</span><strong><?= e(date('M d, Y h:i A')) ?></strong></li>
      <li><span>Timezone</span><strong><?= e(APP_TIMEZONE) ?></strong></li>
      <li><span>Tables</span><strong><?= e(count($data['dbTables'])) ?> installed</strong></li>
    </ul>
    <div class="form-actions">
      <a class="btn btn-ghost btn-sm" href="<?= e(base_url('index.php?page=products&action=export')) ?>">Export products</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(base_url('index.php?page=sales&action=export')) ?>">Export sales</a>
    </div>
  </div>
</div>
