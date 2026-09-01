(function () {
  'use strict';

  // --- Menu mobile ---
  var burger = document.getElementById('burger');
  var sidebar = document.getElementById('sidebar');
  var scrim = document.getElementById('scrim');

  function closeNav() {
    document.body.classList.remove('nav-open');
  }
  if (burger && sidebar) {
    burger.addEventListener('click', function () {
      document.body.classList.toggle('nav-open');
    });
  }
  if (scrim) scrim.addEventListener('click', closeNav);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeNav();
  });

  // --- Confirmation de suppression ---
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.dataset && form.dataset.confirm) {
      if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    }
  });

  // --- Recherche instantanee dans un tableau ---
  document.querySelectorAll('[data-filter]').forEach(function (input) {
    var table = document.querySelector(input.getAttribute('data-filter'));
    if (!table) return;
    input.addEventListener('input', function () {
      var term = input.value.toLowerCase().trim();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = !term || tr.textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
      });
    });
  });

  // --- Onglets ---
  document.querySelectorAll('[data-tabs]').forEach(function (group) {
    var buttons = group.querySelectorAll('[data-tab]');
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-tab');
        buttons.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
        document.querySelectorAll('[data-panel]').forEach(function (p) {
          if (p.closest('[data-tabs-scope="' + group.getAttribute('data-tabs') + '"]') || true) {
            if (p.getAttribute('data-panel-group') === group.getAttribute('data-tabs')) {
              p.hidden = p.getAttribute('data-panel') !== target;
            }
          }
        });
        history.replaceState(null, '', '?tab=' + encodeURIComponent(target));
      });
    });
  });

  // --- Lignes dynamiques (commandes / ventes) ---
  document.querySelectorAll('[data-lines]').forEach(function (wrap) {
    var body = wrap.querySelector('[data-lines-body]');
    var tpl = wrap.querySelector('template');
    var addBtn = wrap.querySelector('[data-add-line]');
    var totalEl = wrap.querySelector('[data-total]');

    function recalc() {
      var total = 0;
      body.querySelectorAll('[data-line]').forEach(function (row) {
        var qty = parseFloat(row.querySelector('[name="quantite[]"]').value.replace(',', '.')) || 0;
        var pu = parseFloat(row.querySelector('[name="prix_unitaire[]"]').value.replace(',', '.')) || 0;
        var sub = qty * pu;
        row.querySelector('[data-sub]').textContent = sub.toFixed(3);
        total += sub;
      });
      if (totalEl) totalEl.textContent = total.toFixed(3);
    }

    if (addBtn && tpl) {
      addBtn.addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        recalc();
      });
    }
    wrap.addEventListener('input', recalc);
    wrap.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-remove-line]');
      if (!rm) return;
      var rows = body.querySelectorAll('[data-line]');
      if (rows.length > 1) rm.closest('[data-line]').remove();
      recalc();
    });
    recalc();
  });
})();
