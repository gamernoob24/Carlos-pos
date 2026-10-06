/* ============================================================
   Carlo's Burger POS — point of sale screen
   ============================================================ */
(function () {
  'use strict';

  var PRODUCTS    = window.POS_PRODUCTS || [];
  var MODIFIERS   = window.POS_MODIFIERS || [];
  var SET         = window.POS_SETTINGS || {};
  var cart        = [];
  var filterText  = '';
  var filterCat   = 0;
  var editingLine = -1;

  var el = {
    grid:       document.getElementById('productGrid'),
    emptyGrid:  document.getElementById('emptyGrid'),
    search:     document.getElementById('productSearch'),
    chips:      document.getElementById('categoryChips'),
    lines:      document.getElementById('cartLines'),
    cartEmpty:  document.getElementById('cartEmpty'),
    customer:   document.getElementById('customerName'),
    discVal:    document.getElementById('discountValue'),
    discType:   document.getElementById('discountType'),
    sub:        document.getElementById('sumSubtotal'),
    disc:       document.getElementById('sumDiscount'),
    tax:        document.getElementById('sumTax'),
    total:      document.getElementById('sumTotal'),
    payWrap:    document.getElementById('payMethods'),
    cashRow:    document.getElementById('cashRow'),
    cash:       document.getElementById('cashInput'),
    quick:      document.getElementById('quickCash'),
    change:     document.getElementById('changeDue'),
    btnClear:   document.getElementById('btnClear'),
    btnPay:     document.getElementById('btnCheckout'),
    error:      document.getElementById('posError'),
    modal:      document.getElementById('successModal'),
    mNo:        document.getElementById('successNo'),
    mTotal:     document.getElementById('successTotal'),
    mPaid:      document.getElementById('successPaid'),
    mChange:    document.getElementById('successChange'),
    btnPrint:   document.getElementById('btnPrint'),
    btnKitchen: document.getElementById('btnKitchen'),
    btnNew:     document.getElementById('btnNewSale'),
    modModal:   document.getElementById('modifierModal'),
    modList:    document.getElementById('modList'),
    modTitle:   document.getElementById('modTitle'),
    modSub:     document.getElementById('modSub'),
    modDone:    document.getElementById('modDone')
  };

  var method = 'cash';
  var lastKitchenUrl = 'index.php?page=kitchen';

  /* ---------------------- helpers ---------------------- */
  function money(n) {
    var dp = typeof SET.decimals === 'number' ? SET.decimals : 2;
    var v = (Math.round(Number(n) * 100) / 100).toFixed(dp);
    var parts = v.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var out = parts.join('.');
    return SET.currencyPosition === 'after' ? out + ' ' + SET.currencySymbol : SET.currencySymbol + out;
  }

  function round2(n) { return Math.round(Number(n) * 100) / 100; }
  function qty(n) { return (Math.round(Number(n) * 1000) / 1000).toString(); }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function err(msg) {
    if (!el.error) return;
    el.error.textContent = msg;
    el.error.hidden = false;
    clearTimeout(err._t);
    err._t = setTimeout(function () { el.error.hidden = true; }, 4500);
  }

  /* ------------------ product grid -------------------- */
  function renderProducts() {
    var text = filterText.trim().toLowerCase();
    var html = '';
    var shown = 0;

    for (var i = 0; i < PRODUCTS.length; i++) {
      var p = PRODUCTS[i];
      if (filterCat && p.category_id !== filterCat) continue;
      if (text &&
          p.name.toLowerCase().indexOf(text) === -1 &&
          String(p.sku).toLowerCase().indexOf(text) === -1 &&
          String(p.barcode || '').toLowerCase().indexOf(text) === -1) continue;

      var out = p.stock <= 0;
      var low = !out && p.stock <= 5;
      var disabled = (out && !SET.allowNegative) ? ' disabled' : '';
      html += '<button type="button" class="product-card" data-id="' + p.id + '"' + disabled + '>' +
                '<span class="pc-name">' + escapeHtml(p.name) + '</span>' +
                '<span class="pc-meta">' + escapeHtml(p.sku) + '</span>' +
                '<span class="pc-bottom">' +
                  '<span class="pc-price">' + money(p.price) + '</span>' +
                  '<span class="pc-stock ' + (out ? 'out' : (low ? 'low' : '')) + '">' +
                    (out ? 'Out of stock' : qty(p.stock) + ' ' + escapeHtml(p.unit || 'pc')) +
                  '</span>' +
                '</span>' +
              '</button>';
      shown++;
    }

    el.grid.innerHTML = html;
    if (el.emptyGrid) el.emptyGrid.hidden = shown !== 0;
  }

  function findProduct(id) {
    for (var i = 0; i < PRODUCTS.length; i++) {
      if (PRODUCTS[i].id === Number(id)) return PRODUCTS[i];
    }
    return null;
  }

  /* ---------------------- cart ------------------------ */
  function addToCart(product) {
    if (!product) return;
    if (product.stock <= 0 && !SET.allowNegative) {
      err('"' + product.name + '" is out of stock.');
      return;
    }
    for (var i = 0; i < cart.length; i++) {
      if (cart[i].id === product.id && cart[i].modifiers.length === 0) {
        if (!SET.allowNegative && cart[i].qty + 1 > product.stock) {
          err('Only ' + qty(product.stock) + ' of "' + product.name + '" available.');
          return;
        }
        cart[i].qty += 1;
        renderCart();
        return;
      }
    }
    cart.push({
      id: product.id, name: product.name, sku: product.sku, price: product.price,
      qty: 1, stock: product.stock, unit: product.unit || 'pc',
      tax_exempt: !!product.tax_exempt, modifiers: []
    });
    renderCart();
  }

  function lineUnitPrice(item) {
    var extra = 0;
    for (var i = 0; i < item.modifiers.length; i++) extra += item.modifiers[i].delta;
    return round2(item.price + extra);
  }

  function changeQty(index, delta) {
    if (!cart[index]) return;
    cart[index].qty += delta;
    if (cart[index].qty <= 0) {
      cart.splice(index, 1);
    } else if (!SET.allowNegative && cart[index].qty > cart[index].stock) {
      err('Only ' + qty(cart[index].stock) + ' of "' + cart[index].name + '" available.');
      cart[index].qty = cart[index].stock;
    }
    renderCart();
  }

  function renderCart() {
    if (!cart.length) {
      el.cartEmpty.hidden = false;
      var rows = el.lines.querySelectorAll('.cart-line');
      for (var r = 0; r < rows.length; r++) rows[r].remove();
      updateTotals();
      return;
    }
    el.cartEmpty.hidden = true;

    var old = el.lines.querySelectorAll('.cart-line');
    for (var i = 0; i < old.length; i++) old[i].remove();

    for (var c = 0; c < cart.length; c++) {
      var item   = cart[c];
      var unit   = lineUnitPrice(item);
      var amount = round2(unit * item.qty);
      var div    = document.createElement('div');
      var mods   = '';
      if (item.modifiers.length) {
        mods = '<div class="cl-mods">';
        for (var m = 0; m < item.modifiers.length; m++) {
          var md = item.modifiers[m];
          mods += '<span class="mod-chip">' + escapeHtml(md.name) +
                  (md.delta ? ' <b>+' + money(md.delta) + '</b>' : '') + '</span>';
        }
        mods += '</div>';
      }

      div.className = 'cart-line';
      div.innerHTML =
        '<div>' +
          '<div class="cl-name">' + escapeHtml(item.name) + '</div>' +
          '<div class="cl-sub">' + money(item.price) +
            (item.modifiers.length ? ' <span class="muted">+ extras</span>' : '') +
            ' × ' + qty(item.qty) + ' ' + escapeHtml(item.unit) +
            (item.tax_exempt ? ' · tax exempt' : '') + '</div>' +
          mods +
        '</div>' +
        '<div class="cl-amount">' + money(amount) + '</div>' +
        '<div class="cl-controls">' +
          '<button type="button" class="qty-btn" data-act="dec" data-i="' + c + '">−</button>' +
          '<span class="cl-qty">' + qty(item.qty) + '</span>' +
          '<button type="button" class="qty-btn" data-act="inc" data-i="' + c + '">+</button>' +
          '<button type="button" class="cl-mod" data-act="mod" data-i="' + c + '">Modify</button>' +
          '<button type="button" class="cl-remove" data-act="del" data-i="' + c + '" title="Remove">✕</button>' +
        '</div>' +
        '<div></div>';
      el.lines.appendChild(div);
    }
    updateTotals();
  }

  /* ------------------- modifier dialog ---------------- */
  function openModifiers(index) {
    if (!cart[index]) return;
    editingLine = index;
    var item = cart[index];
    el.modTitle.textContent = 'Modify: ' + item.name;
    el.modSub.textContent   = 'Base price ' + money(item.price) + ' — choose extras or omissions.';

    var html = '';
    for (var i = 0; i < MODIFIERS.length; i++) {
      var m = MODIFIERS[i];
      var checked = false;
      for (var j = 0; j < item.modifiers.length; j++) {
        if (item.modifiers[j].id === m.id) { checked = true; break; }
      }
      html += '<label class="mod-row' + (checked ? ' on' : '') + '">' +
                '<input type="checkbox" data-mod="' + m.id + '"' + (checked ? ' checked' : '') + '>' +
                '<span class="mod-name">' + escapeHtml(m.name) + '</span>' +
                '<span class="mod-price">' + (m.price_delta ? '+' + money(m.price_delta) : 'no charge') + '</span>' +
              '</label>';
    }
    el.modList.innerHTML = html ||
      '<p class="muted">No modifiers configured yet. A manager can add them under Inventory → Modifiers.</p>';
    el.modModal.hidden = false;
  }

  function saveModifiers() {
    if (editingLine < 0 || !cart[editingLine]) return;
    var chosen = [];
    var boxes = el.modList.querySelectorAll('input[data-mod]');
    for (var i = 0; i < boxes.length; i++) {
      if (!boxes[i].checked) continue;
      var id = parseInt(boxes[i].getAttribute('data-mod'), 10);
      for (var j = 0; j < MODIFIERS.length; j++) {
        if (MODIFIERS[j].id === id) {
          chosen.push({ id: id, name: MODIFIERS[j].name, delta: MODIFIERS[j].price_delta });
        }
      }
    }
    cart[editingLine].modifiers = chosen;
    el.modModal.hidden = true;
    editingLine = -1;
    renderCart();
  }

  /* --------------------- totals ----------------------- */
  function calc() {
    var subtotal = 0, taxableNet = 0, i;
    for (i = 0; i < cart.length; i++) {
      var net = round2(lineUnitPrice(cart[i]) * cart[i].qty);
      subtotal += net;
      if (!cart[i].tax_exempt) taxableNet += net;
    }
    subtotal = round2(subtotal);

    var dVal = Math.max(0, parseFloat(el.discVal.value) || 0);
    var discount = el.discType.value === 'percent'
      ? round2(subtotal * Math.min(100, dVal) / 100)
      : round2(dVal);
    if (discount > subtotal) discount = subtotal;

    var share = subtotal > 0 ? taxableNet / subtotal : 0;
    var taxableBase = round2(taxableNet - discount * share);
    var rate = parseFloat(SET.taxRate) || 0;
    var tax = 0;
    if (rate > 0 && taxableBase > 0) {
      tax = SET.taxMode === 'inclusive'
        ? round2(taxableBase - taxableBase / (1 + rate / 100))
        : round2(taxableBase * rate / 100);
    }

    var netSales = round2(subtotal - discount);
    var total = SET.taxMode === 'inclusive' ? netSales : round2(netSales + tax);
    var cash = method === 'cash' ? (parseFloat(el.cash.value) || 0) : total;
    var change = method === 'cash' ? Math.max(0, round2(cash - total)) : 0;

    return { subtotal: subtotal, discount: discount, tax: tax, total: total, paid: cash, change: change };
  }

  function updateTotals() {
    var t = calc();
    el.sub.textContent    = money(t.subtotal);
    el.disc.textContent   = '-' + money(t.discount);
    el.tax.textContent    = money(t.tax);
    el.total.textContent  = money(t.total);
    el.change.textContent = money(t.change);
    el.change.style.color = t.change > 0 ? 'var(--ok)' : 'var(--muted)';
    el.btnPay.disabled    = cart.length === 0;
    renderQuickCash(t.total);
  }

  function renderQuickCash(total) {
    if (!el.quick) return;
    if (method !== 'cash' || total <= 0) { el.quick.innerHTML = ''; return; }
    var amounts = [round2(Math.ceil(total))];
    var steps = [5, 10, 20, 50, 100, 200, 500, 1000];
    for (var i = 0; i < steps.length; i++) {
      var v = Math.ceil(total / steps[i]) * steps[i];
      if (v > total && amounts.indexOf(v) === -1) amounts.push(v);
      if (amounts.length >= 6) break;
    }
    var html = '<button type="button" data-amount="' + total + '">Exact</button>';
    for (var a = 0; a < amounts.length; a++) {
      html += '<button type="button" data-amount="' + amounts[a] + '">' + money(amounts[a]) + '</button>';
    }
    el.quick.innerHTML = html;
  }

  /* -------------------- checkout ---------------------- */
  function checkout() {
    if (!cart.length) return;

    var t = calc();
    if (method === 'cash' && t.paid + 0.005 < t.total) {
      err('Cash received is less than the total.');
      el.cash.focus();
      return;
    }

    var payload = {
      items: cart.map(function (i) {
        return {
          id: i.id,
          qty: i.qty,
          discount: 0,
          modifiers: i.modifiers.map(function (m) { return m.id; })
        };
      }),
      discount: { type: el.discType.value, value: parseFloat(el.discVal.value) || 0 },
      payment: { method: method, cash: method === 'cash' ? t.paid : t.total },
      customer: el.customer ? el.customer.value.trim() : '',
      note: ''
    };

    el.btnPay.disabled = true;
    el.btnPay.textContent = 'Saving…';

    fetch(SET.checkoutUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SET.csrfToken },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        el.btnPay.disabled = false;
        el.btnPay.innerHTML = 'Send to kitchen <kbd>F8</kbd>';

        if (!res.ok) { err(res.error || 'Could not complete the order.'); return; }

        for (var i = 0; i < cart.length; i++) {
          var p = findProduct(cart[i].id);
          if (p) p.stock = Math.round((p.stock - cart[i].qty) * 1000) / 1000;
        }

        el.mNo.textContent     = res.sale_no;
        el.mTotal.textContent  = money(res.total);
        el.mPaid.textContent   = money(res.paid);
        el.mChange.textContent = money(res.change);
        el.modal.hidden = false;
        if (res.kitchen_url) lastKitchenUrl = res.kitchen_url;
        el.btnPrint.setAttribute('data-url', res.receipt_url);

        cart = [];
        renderCart();
        renderProducts();
      })
      .catch(function () {
        el.btnPay.disabled = false;
        el.btnPay.innerHTML = 'Send to kitchen <kbd>F8</kbd>';
        err('Network error — the order was not saved.');
      });
  }

  function resetSale() {
    cart = [];
    if (el.customer) el.customer.value = '';
    el.discVal.value = '0';
    el.discType.value = 'fixed';
    el.cash.value = '0';
    setMethod('cash');
    renderCart();
    renderProducts();
    if (el.search) { el.search.value = ''; filterText = ''; el.search.focus(); }
  }

  function setMethod(m) {
    method = m;
    var btns = el.payWrap.querySelectorAll('.pay');
    for (var i = 0; i < btns.length; i++) {
      btns[i].classList.toggle('active', btns[i].getAttribute('data-method') === m);
    }
    el.cashRow.hidden = (m !== 'cash');
    updateTotals();
  }

  /* -------------------- events ------------------------ */
  if (el.grid) {
    el.grid.addEventListener('click', function (ev) {
      var card = ev.target.closest('.product-card');
      if (!card || card.disabled) return;
      var p = findProduct(card.getAttribute('data-id'));
      if (p) {
        addToCart(p);
        if (el.search) { el.search.value = ''; filterText = ''; renderProducts(); el.search.focus(); }
      }
    });
  }

  if (el.lines) {
    el.lines.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-act]');
      if (!btn) return;
      var i = parseInt(btn.getAttribute('data-i'), 10);
      var act = btn.getAttribute('data-act');
      if (act === 'inc') changeQty(i, 1);
      if (act === 'dec') changeQty(i, -1);
      if (act === 'del') { cart.splice(i, 1); renderCart(); }
      if (act === 'mod') openModifiers(i);
    });
  }

  if (el.modList) {
    el.modList.addEventListener('change', function (ev) {
      var row = ev.target.closest('.mod-row');
      if (row) row.classList.toggle('on', ev.target.checked);
    });
  }

  if (el.modDone) el.modDone.addEventListener('click', saveModifiers);

  if (el.search) {
    el.search.addEventListener('input', function () {
      filterText = el.search.value;
      renderProducts();
    });

    el.search.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter') return;
      ev.preventDefault();
      var q = el.search.value.trim();
      if (q === '') return;

      var visible = el.grid.querySelectorAll('.product-card');
      if (visible.length === 1) {
        addToCart(findProduct(visible[0].getAttribute('data-id')));
        el.search.value = ''; filterText = ''; renderProducts();
        return;
      }

      fetch(SET.searchUrl + '&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.ok || !res.products || !res.products.length) { err('No menu item matches "' + q + '".'); return; }
          var hit = null;
          for (var i = 0; i < res.products.length; i++) {
            var c = res.products[i];
            if (String(c.barcode) === q || String(c.sku).toLowerCase() === q.toLowerCase()) { hit = c; break; }
          }
          if (!hit && res.products.length === 1) hit = res.products[0];
          if (!hit) { err('No menu item matches "' + q + '".'); return; }
          var local = findProduct(hit.id);
          if (!local) { PRODUCTS.push(hit); local = hit; }
          addToCart(local);
          el.search.value = ''; filterText = ''; renderProducts();
        })
        .catch(function () { err('Lookup failed.'); });
    });
  }

  if (el.chips) {
    el.chips.addEventListener('click', function (ev) {
      var chip = ev.target.closest('.chip');
      if (!chip || chip.tagName === 'A') return;
      var all = el.chips.querySelectorAll('button.chip');
      for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
      chip.classList.add('active');
      filterCat = parseInt(chip.getAttribute('data-cat'), 10) || 0;
      renderProducts();
    });
  }

  if (el.discVal) el.discVal.addEventListener('input', updateTotals);
  if (el.discType) el.discType.addEventListener('change', updateTotals);
  if (el.cash) el.cash.addEventListener('input', updateTotals);

  if (el.payWrap) {
    el.payWrap.addEventListener('click', function (ev) {
      var b = ev.target.closest('.pay');
      if (b) setMethod(b.getAttribute('data-method'));
    });
  }

  if (el.quick) {
    el.quick.addEventListener('click', function (ev) {
      var b = ev.target.closest('button[data-amount]');
      if (!b) return;
      el.cash.value = b.getAttribute('data-amount');
      updateTotals();
    });
  }

  if (el.btnClear) el.btnClear.addEventListener('click', function () {
    if (!cart.length) return;
    if (window.confirm('Clear the current order?')) resetSale();
  });

  if (el.btnPay) el.btnPay.addEventListener('click', checkout);

  if (el.btnPrint) el.btnPrint.addEventListener('click', function () {
    var url = el.btnPrint.getAttribute('data-url');
    if (url) window.open(url, '_blank');
  });

  if (el.btnKitchen) {
    el.btnKitchen.addEventListener('click', function () {
      window.location.href = lastKitchenUrl || 'index.php?page=kitchen';
    });
  }

  if (el.btnNew) el.btnNew.addEventListener('click', function () {
    el.modal.hidden = true;
    resetSale();
  });

  /* ------------------ shortcuts ----------------------- */
  document.addEventListener('keydown', function (ev) {
    if (ev.target.tagName === 'INPUT' && ev.target !== el.search && ev.key !== 'F2' && ev.key !== 'F8') return;

    if (ev.key === 'F2') {
      ev.preventDefault();
      if (el.search) { el.search.focus(); el.search.select(); }
    } else if (ev.key === 'F8') {
      ev.preventDefault();
      if (cart.length) checkout();
    } else if (ev.key === 'Escape') {
      if (el.modModal && !el.modModal.hidden) { el.modModal.hidden = true; return; }
      if (el.modal && !el.modal.hidden) { el.modal.hidden = true; return; }
      if (cart.length && window.confirm('Clear the current order?')) resetSale();
    }
  });

  /* ------------------ background AJAX updates ----------------------- */
  function updateRecentOrders(recent) {
    var tableBody = document.querySelector('.recent-card tbody');
    if (!tableBody || !recent) return;

    var html = '';
    for (var i = 0; i < recent.length; i++) {
      var s = recent[i];
      var statusClass = 'pill-muted';
      var statusLabel = s.status;

      if (s.status === 'queued') { statusClass = 'pill-low'; statusLabel = 'Queued'; }
      else if (s.status === 'preparing') { statusClass = 'pill-card'; statusLabel = 'Preparing'; }
      else if (s.status === 'completed') { statusClass = 'pill-ok'; statusLabel = 'Served'; }
      else if (s.status === 'voided') { statusClass = 'pill-muted'; statusLabel = 'Voided'; }

      var isVoided = s.status === 'voided' ? ' class="row-voided"' : '';

      html += '<tr' + isVoided + '>' +
                '<td><strong>' + escapeHtml(s.sale_no) + '</strong></td>' +
                '<td>' + escapeHtml(s.created_at) + '</td>' +
                '<td><span class="pill pill-' + escapeHtml(s.payment_method) + '">' + escapeHtml(s.payment_method.charAt(0).toUpperCase() + s.payment_method.slice(1)) + '</span></td>' +
                '<td><span class="pill ' + statusClass + '">' + escapeHtml(statusLabel) + '</span></td>' +
                '<td class="right">' + money(s.total) + '</td>' +
                '<td class="right"><a class="btn btn-sm btn-ghost" href="index.php?page=sale&id=' + s.id + '">View</a></td>' +
              '</tr>';
    }
    tableBody.innerHTML = html;
  }

  function syncLiveData() {
    if (!SET.liveUrl) return;

    fetch(SET.liveUrl)
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) return;

        // Update product stock levels
        if (res.products && res.products.length) {
          PRODUCTS = res.products;
          renderProducts();
        }

        // Update recent orders table
        if (res.recent) {
          updateRecentOrders(res.recent);
        }

        // Update kitchen badge counter
        var kitchenBadge = document.querySelector('.chip-link strong');
        if (kitchenBadge && res.openOrders !== undefined) {
          kitchenBadge.textContent = res.openOrders;
        }
      })
      .catch(function () {
        /* Silently ignore network hiccup */
      });
  }

  // Poll for background data updates every 8 seconds
  setInterval(syncLiveData, 8000);

  /* --------------------- init ------------------------- */
  renderProducts();
  renderCart();
  setMethod('cash');
  if (el.search) el.search.focus();
})();