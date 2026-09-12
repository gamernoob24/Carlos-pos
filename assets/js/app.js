/* ============================================================
   Carlos POS Web — global UI behaviour
   ============================================================ */
(function () {
  'use strict';

  /* ---------- clock ---------- */
  function tickClock() {
    var el = document.getElementById('clock');
    if (!el) return;
    var d = new Date();
    var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var h = d.getHours();
    var m = ('0' + d.getMinutes()).slice(-2);
    var ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    el.textContent = days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' +
      ('0' + d.getDate()).slice(-2) + ' · ' + h + ':' + m + ' ' + ap;
  }
  tickClock();
  setInterval(tickClock, 30000);

  /* ---------- sidebar (mobile) ---------- */
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-toggle-sidebar]');
    if (t) {
      document.body.classList.toggle('sidebar-open');
    }
  });

  /* ---------- dismissible alerts ---------- */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.alert-close');
    if (btn && btn.closest('.alert')) {
      btn.closest('.alert').remove();
    }
  });

  /* ---------- confirm before destructive actions ---------- */
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-confirm]');
    if (!el) return;
    if (el.tagName === 'BUTTON' || el.tagName === 'A') {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        ev.preventDefault();
        ev.stopPropagation();
      }
    }
  }, true);

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var btn = form.querySelector('[data-confirm]');
    if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
      ev.preventDefault();
    }
  });

  /* ---------- modals ---------- */
  function openModal(id) {
    var m = document.getElementById(id);
    if (m) m.hidden = false;
  }
  function closeModals() {
    document.querySelectorAll('.modal').forEach(function (m) { m.hidden = true; });
  }

  document.addEventListener('click', function (ev) {
    var closer = ev.target.closest('[data-close-modal]');
    if (closer) { closeModals(); return; }
    if (ev.target.classList && ev.target.classList.contains('modal')) { closeModals(); return; }

    var voidBtn = ev.target.closest('[data-void-sale]');
    if (voidBtn) {
      var modal = document.getElementById('voidModal');
      if (modal) {
        document.getElementById('voidId').value = voidBtn.getAttribute('data-void-sale');
        var no = document.getElementById('voidNo');
        if (no) no.textContent = voidBtn.getAttribute('data-sale-no') || '';
        modal.hidden = false;
      }
      return;
    }

    var resetBtn = ev.target.closest('[data-reset-user]');
    if (resetBtn) {
      var rModal = document.getElementById('resetModal');
      if (rModal) {
        document.getElementById('resetId').value = resetBtn.getAttribute('data-reset-user');
        var who = document.getElementById('resetWho');
        if (who) who.textContent = 'Set a new password for ' + resetBtn.getAttribute('data-user-name') + '.';
        rModal.hidden = false;
      }
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') closeModals();
  });

  /* ---------- print button on the receipt page ---------- */
  var printBtn = document.getElementById('printBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }

  /* ---------- keep GET filter forms tidy ---------- */
  document.querySelectorAll('form.filters').forEach(function (form) {
    form.addEventListener('submit', function () {
      form.querySelectorAll('input,select').forEach(function (i) {
        if (i.value === '' && i.name !== 'page') i.disabled = true;
      });
    });
  });
})();
