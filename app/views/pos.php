<?php
/** @var array $data */
$products  = $data['products'];
$categories= $data['categories'];
$recent    = $data['recent'];
$jsSettings= $data['jsSettings'];
?>
<div class="pos">

  <!-- ===================== CATALOG ===================== -->
  <section class="pos-catalog">
    <div class="pos-toolbar">
      <div class="search-wrap">
        <span class="search-ico" aria-hidden="true"></span>
        <input id="productSearch" type="search" autocomplete="off" autofocus
               placeholder="Search by name, SKU or scan barcode  (F2)">
        <kbd class="search-kbd">F2</kbd>
      </div>
      <div class="chips" id="categoryChips">
        <button type="button" class="chip active" data-cat="0">All</button>
        <?php foreach ($categories as $c): ?>
          <button type="button" class="chip" data-cat="<?= e($c['id']) ?>"><?= e($c['name']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="product-grid" id="productGrid"></div>
    <p class="empty-grid" id="emptyGrid" hidden>No products match that search.</p>
  </section>

  <!-- ====================== CART ======================= -->
  <aside class="pos-cart">
    <div class="cart-head">
      <h2>Current Sale</h2>
      <input id="customerName" type="text" class="input" placeholder="Customer name (optional)" autocomplete="off">
    </div>

    <div class="cart-lines" id="cartLines">
      <div class="cart-empty" id="cartEmpty">
        <div class="cart-empty-ico"></div>
        <p>Cart is empty</p>
        <small>Click a product or scan a barcode to begin</small>
      </div>
    </div>

    <div class="cart-foot">
      <div class="row-discount">
        <label class="mini">Discount</label>
        <div class="discount-inputs">
          <input id="discountValue" type="number" min="0" step="0.01" class="input input-sm" value="0">
          <select id="discountType" class="input input-sm">
            <option value="fixed"><?= e(setting('currency_symbol', '₱')) ?></option>
            <option value="percent">%</option>
          </select>
        </div>
      </div>

      <dl class="totals">
        <div><dt>Subtotal</dt><dd id="sumSubtotal"><?= e(money(0)) ?></dd></div>
        <div><dt>Discount</dt><dd id="sumDiscount" class="muted">-<?= e(money(0)) ?></dd></div>
        <div><dt><?= e(setting('tax_name', 'Tax')) ?> <small>(<?= e(rtrim(rtrim(setting('tax_rate', '0'), '0'), '.')) ?>%<?= setting('tax_mode', 'exclusive') === 'inclusive' ? ' incl.' : '' ?>)</small></dt><dd id="sumTax"><?= e(money(0)) ?></dd></div>
        <div class="grand"><dt>Total</dt><dd id="sumTotal"><?= e(money(0)) ?></dd></div>
      </dl>

      <div class="pay-methods" id="payMethods">
        <button type="button" class="pay active" data-method="cash">Cash</button>
        <button type="button" class="pay" data-method="card">Card</button>
        <button type="button" class="pay" data-method="ewallet">E-Wallet</button>
      </div>

      <div class="cash-row" id="cashRow">
        <label class="mini">Cash received</label>
        <input id="cashInput" type="number" min="0" step="0.01" class="input input-lg" value="0">
        <div class="quick-cash" id="quickCash"></div>
      </div>

      <div class="change-row">
        <span>Change due</span>
        <strong id="changeDue"><?= e(money(0)) ?></strong>
      </div>

      <div class="cart-actions">
        <button type="button" class="btn btn-ghost" id="btnClear">Clear <kbd>Esc</kbd></button>
        <button type="button" class="btn btn-primary btn-lg" id="btnCheckout" disabled>
          Complete Sale <kbd>F8</kbd>
        </button>
      </div>
      <p class="error-line" id="posError" hidden></p>
    </div>
  </aside>
</div>

<!-- ================== SUCCESS MODAL =================== -->
<div class="modal" id="successModal" hidden>
  <div class="modal-card">
    <div class="success-mark">✓</div>
    <h3>Sale completed</h3>
    <p class="success-no" id="successNo"></p>
    <dl class="success-totals">
      <div><dt>Total</dt><dd id="successTotal"></dd></div>
      <div><dt>Cash</dt><dd id="successPaid"></dd></div>
      <div><dt>Change</dt><dd id="successChange"></dd></div>
    </dl>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="btnPrint">Print receipt</button>
      <button type="button" class="btn btn-primary" id="btnNewSale">New sale</button>
    </div>
  </div>
</div>

<?php if (count($recent) > 0): ?>
<div class="card recent-card">
  <div class="card-head"><h3>Recent sales</h3>
    <a class="link" href="<?= e(base_url('index.php?page=sales')) ?>">View all</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Sale</th><th>Time</th><th>Payment</th><th class="right">Total</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $s): ?>
        <tr class="<?= $s['status'] === 'voided' ? 'row-voided' : '' ?>">
          <td><strong><?= e($s['sale_no']) ?></strong></td>
          <td><?= e(fmt_date($s['created_at'])) ?></td>
          <td><span class="pill pill-<?= e($s['payment_method']) ?>"><?= e(ucfirst($s['payment_method'])) ?></span></td>
          <td class="right"><?= e(money($s['total'])) ?></td>
          <td class="right">
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('index.php?page=sale&id=' . $s['id'])) ?>">View</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
  window.POS_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  window.POS_SETTINGS = <?= json_encode($jsSettings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
</script>
