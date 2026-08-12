/* ExchangePro frontend — premium interactions */
(function () {
  'use strict';

  var root = document.documentElement;

  /* ---------------- Decorative icons are aria-hidden ---------------- */
  document.querySelectorAll('svg.icon').forEach(function (s) {
    if (!s.hasAttribute('aria-hidden')) s.setAttribute('aria-hidden', 'true');
  });

  /* ---------------- Theme (system-aware) ---------------- */
  var THEME_KEY = 'exch-theme';
  var THEME_COLORS = { light: '#f6f7f9', dark: '#0b0e14' };
  var themeMeta = document.querySelector('meta[name="theme-color"]');
  function applyTheme(t) {
    // Hold all transitions off while the theme flips, so every themed color
    // changes at once instead of racing element-by-element.
    root.setAttribute('data-theme-switching', '');
    root.setAttribute('data-theme', t);
    if (themeMeta) themeMeta.setAttribute('content', THEME_COLORS[t] || THEME_COLORS.light);
    var moon = document.querySelector('[data-icon="moon"]');
    var sun = document.querySelector('[data-icon="sun"]');
    if (moon) moon.style.display = t === 'dark' ? 'none' : 'block';
    if (sun) sun.style.display = t === 'dark' ? 'block' : 'none';
    requestAnimationFrame(function () {
      root.removeAttribute('data-theme-switching');
    });
  }
  function currentTheme() {
    var saved = localStorage.getItem(THEME_KEY);
    if (saved === 'dark' || saved === 'light') return saved;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  applyTheme(currentTheme());

  var themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      localStorage.setItem(THEME_KEY, next);
      if (themeToggle.animate) {
        themeToggle.animate(
          [{ transform: 'rotate(0deg) scale(1)' }, { transform: 'rotate(180deg) scale(.8)' }, { transform: 'rotate(360deg) scale(1)' }],
          { duration: 350, easing: 'cubic-bezier(.2,.8,.2,1)' }
        );
      }
    });
  }

  /* ---------------- Mobile sidebar ---------------- */
  var menuToggle = document.getElementById('menuToggle');
  var sidebar = document.getElementById('sidebar');
  var scrim = document.getElementById('sidebarScrim');
  function setSidebar(open) {
    sidebar.classList.toggle('open', open);
    if (scrim) scrim.classList.toggle('show', open);
    if (menuToggle) menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () { setSidebar(!sidebar.classList.contains('open')); });
    if (scrim) scrim.addEventListener('click', function () { setSidebar(false); });
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== menuToggle && e.target !== scrim) {
        setSidebar(false);
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) setSidebar(false);
    });
  }

  /* ---------------- Modal helpers ---------------- */
  function trapFocus(m) {
    if (m._trapped) return;
    m._trapped = true;
    m.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var f = m.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }
  // Close plays a fast exit animation, then hides; reopening mid-close cancels it.
  function dismissModal(m) {
    if (!m || m._closing) return;
    m._closing = true;
    m.classList.remove('open');
    m.classList.add('closing');
    restoreFocus(m);
    setTimeout(function () {
      m.classList.remove('closing');
      m._closing = false;
    }, 190);
  }
  window.openModal = function (id) {
    var m = document.getElementById(id);
    if (!m) return;
    m._prevFocus = document.activeElement;
    m.classList.remove('closing');
    m._closing = false;
    m.classList.add('open');
    trapFocus(m);
    var f = m.querySelector('input, select, textarea, button');
    if (f) setTimeout(function () { f.focus(); }, 60);
  };
  window.closeModal = function (id) {
    var m = document.getElementById(id);
    if (!m) return;
    dismissModal(m);
  };
  function restoreFocus(m) {
    if (m._prevFocus) { m._prevFocus.focus(); m._prevFocus = null; }
  }
  document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
      dismissModal(e.target);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.open').forEach(dismissModal);
    }
  });

  /* ---------------- Customer picker: search + add in one popup ---------------- */
  document.querySelectorAll('[data-picker]').forEach(function (picker) {
    var pickerId = picker.getAttribute('data-picker');
    var modalId = picker.getAttribute('data-picker-modal') || pickerId + 'Modal';
    var modal = document.getElementById(modalId);
    var hidden = picker.querySelector('input[name="customer_id"]');
    var trigger = picker.querySelector('[data-picker-open]');
    var valueEl = picker.querySelector('[data-picker-value]');
    var search = modal ? modal.querySelector('[data-picker-search]') : null;
    var results = modal ? modal.querySelector('[data-picker-results]') : null;
    var addForm = modal ? modal.querySelector('[data-picker-add-form]') : null;
    var addSubmit = modal ? modal.querySelector('[data-picker-add-submit]') : null;
    var addCancel = modal ? modal.querySelector('[data-picker-add-cancel]') : null;
    var feedback = modal ? modal.querySelector('[data-picker-feedback]') : null;
    var closeBtn = modal ? modal.querySelector('[data-picker-close]') : null;
    var searchTimer = null;
    var reqSeq = 0;
    var activeIdx = -1;
    if (!modal || !trigger) return;

    function setSelected(id, name, code) {
      if (hidden) hidden.value = id;
      if (valueEl) valueEl.textContent = name + (code ? ' (' + code + ')' : '');
      trigger.setAttribute('aria-expanded', 'false');
      closeModal(modalId);
    }

    function renderRows(rows, isInitial) {
      results.innerHTML = '';
      activeIdx = -1;
      if (!rows.length) {
        var empty = document.createElement('div');
        empty.className = 'picker-empty';
        empty.textContent = results.getAttribute('data-empty') || '—';
        results.appendChild(empty);
        if (!isInitial && addForm) {
          var hint = document.createElement('p');
          hint.className = 'picker-hint';
          hint.textContent = results.getAttribute('data-hint') || '';
          results.appendChild(hint);
        }
        return;
      }
      rows.forEach(function (c, i) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'picker-result';
        btn.setAttribute('role', 'option');
        btn.setAttribute('aria-selected', 'false');
        btn.tabIndex = -1; // arrow keys drive selection; keep Tab out of the listbox
        var name = document.createElement('strong');
        name.textContent = c.full_name;
        var meta = document.createElement('span');
        meta.className = 'picker-result-meta';
        meta.textContent = c.code + (c.phone ? ' · ' + c.phone : '');
        btn.appendChild(name);
        btn.appendChild(meta);
        btn.addEventListener('click', function () { setSelected(c.id, c.full_name, c.code); });
        results.appendChild(btn);
      });
    }

    function highlight(idx) {
      [].forEach.call(results.children, function (el, i) {
        var on = i === idx;
        el.classList.toggle('is-active', on);
        el.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }

    function fetchSearch(q, initial) {
      var my = ++reqSeq;
      results.innerHTML = '';
      var busy = document.createElement('div');
      busy.className = 'picker-empty';
      busy.textContent = '…';
      results.appendChild(busy);
      var url = '/customers/search?q=' + encodeURIComponent(q) + '&limit=' + (initial ? 8 : 20);
      fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (rows) {
          if (my !== reqSeq) return;
          renderRows(rows || [], initial);
        })
        .catch(function () {
          if (my !== reqSeq) return;
          renderRows([], initial);
        });
    }

    trigger.addEventListener('click', function () {
      trigger.setAttribute('aria-expanded', 'true');
      openModal(modalId);
      if (search) {
        search.value = '';
        fetchSearch('', true);
        setTimeout(function () { search.focus(); }, 60);
      }
    });

    if (closeBtn) closeBtn.addEventListener('click', function () {
      trigger.setAttribute('aria-expanded', 'false');
      closeModal(modalId);
    });

    // Keep aria-expanded in sync no matter how the modal closes
    // (Escape, backdrop click, close button, or after selection).
    if (window.MutationObserver) {
      new MutationObserver(function () {
        trigger.setAttribute('aria-expanded', modal.classList.contains('open') ? 'true' : 'false');
      }).observe(modal, { attributes: true, attributeFilter: ['class'] });
    }

    if (search) {
      search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        searchTimer = setTimeout(function () { fetchSearch(q, false); }, 250);
      });
      search.addEventListener('keydown', function (e) {
        var rows = results.querySelectorAll('.picker-result');
        if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, rows.length - 1); highlight(activeIdx); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); highlight(activeIdx); }
        else if (e.key === 'Enter' && activeIdx >= 0 && rows[activeIdx]) {
          e.preventDefault();
          rows[activeIdx].click();
        }
      });
    }

    if (addForm) {
      addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (feedback) { feedback.textContent = ''; feedback.className = 'picker-add-feedback'; }
        var nameInput = addForm.querySelector('[name="full_name"]');
        if (!nameInput.value.trim()) {
          if (feedback) {
            feedback.textContent = addForm.getAttribute('data-msg-required') || 'Full name is required';
            feedback.className = 'picker-add-feedback is-error';
          }
          nameInput.focus();
          return;
        }
        if (addSubmit) addSubmit.disabled = true;
        fetch('/customers/ajax', {
          method: 'POST',
          body: new FormData(addForm),
          headers: { 'Accept': 'application/json' }
        })
          .then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok, d: d }; });
          })
          .then(function (res) {
            if (addSubmit) addSubmit.disabled = false;
            if (res.ok && res.d && res.d.ok) {
              setSelected(res.d.id, res.d.full_name, res.d.code);
            } else if (feedback) {
              feedback.textContent = (res.d && res.d.error) ? res.d.error : 'Error';
              feedback.className = 'picker-add-feedback is-error';
            }
          })
          .catch(function () {
            if (addSubmit) addSubmit.disabled = false;
            if (feedback) { feedback.textContent = 'Error'; feedback.className = 'picker-add-feedback is-error'; }
          });
      });
      if (addCancel) addCancel.addEventListener('click', function () { addForm.reset(); if (feedback) feedback.textContent = ''; });
    }
  });

  /* ---------------- Generic AJAX create-form panel ---------------- */
  // Any element with [data-ajax-form] opens a create form in a modal instead
  // of navigating to a separate page. The server renders the form bare when
  // the request is fetch(), and store() returns JSON {ok, message, errors}.
  var ajaxFormModal = document.getElementById('ajaxFormModal');
  if (ajaxFormModal) {
    var ajaxFormTitle = document.getElementById('ajaxFormModalTitle');
    var ajaxFormBody = document.getElementById('ajaxFormModalBody');
    var ajaxFormClose = document.getElementById('ajaxFormModalClose');
    var ajaxReqSeq = 0; // stale-response guard for rapid open/reopen

    function openAjaxForm(trigger) {
      var my = ++ajaxReqSeq;
      var url = trigger.getAttribute('data-ajax-form');
      var title = trigger.getAttribute('data-ajax-title') || '';
      var wide = trigger.hasAttribute('data-ajax-wide');
      ajaxFormTitle.textContent = title;
      ajaxFormModal.classList.toggle('ajax-form-modal--wide', wide);
      ajaxFormBody.innerHTML = '<div class="ajax-form-loading"><span class="spinner"></span></div>';
      openModal('ajaxFormModal');
      fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          if (my !== ajaxReqSeq) return; // a newer open() superseded this one
          ajaxFormBody.innerHTML = html;
          var form = ajaxFormBody.querySelector('form');
          if (!form) return;
          bindAjaxForm(form);
          var first = form.querySelector('input:not([type=hidden]), select, textarea');
          if (first) setTimeout(function () { first.focus(); }, 60);
        })
        .catch(function () {
          if (my !== ajaxReqSeq) return;
          // Don't strand the user: fall back to the normal page if we can.
          if (trigger && trigger.href && !ajaxFormBody.querySelector('form')) {
            window.location.href = trigger.href;
            return;
          }
          ajaxFormBody.innerHTML = '<div class="ajax-form-alert">' + (ajaxFormBody.getAttribute('data-load-failed') || 'Error') + '</div>';
        });
    }

    function bindAjaxForm(form) {
      // In-form cancel links close the panel instead of leaving the page.
      form.querySelectorAll('.form-actions a[href]').forEach(function (a) {
        a.addEventListener('click', function (e) { e.preventDefault(); closeModal('ajaxFormModal'); });
      });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('[type=submit]');
        if (btn) btn.disabled = true;
        clearAjaxErrors(form);
        fetch(form.action, {
          method: 'POST',
          headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
          body: new FormData(form)
        })
          .then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok, d: d }; });
          })
          .then(function (res) {
            if (btn) btn.disabled = false;
            if (res.ok && res.d && res.d.ok) {
              // Flash was queued server-side; reload shows the new row + toast.
              closeModal('ajaxFormModal');
              window.location.reload();
              return;
            }
            showAjaxErrors(form, res.d || {});
          })
          .catch(function () {
            if (btn) btn.disabled = false;
            showAjaxErrors(form, { message: ajaxFormBody.getAttribute('data-load-failed') || 'Error' });
          });
      });
    }

    function clearAjaxErrors(form) {
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      form.querySelectorAll('.ajax-form-alert, .form-field-error').forEach(function (el) { el.remove(); });
    }

    function showAjaxErrors(form, d) {
      var msg = d.message || 'Error';
      var alertEl = document.createElement('div');
      alertEl.className = 'ajax-form-alert';
      alertEl.textContent = msg;
      form.insertBefore(alertEl, form.firstChild);
      var errors = d.errors || {};
      Object.keys(errors).forEach(function (field) {
        var input = form.querySelector('[name="' + field + '"]');
        if (!input) return;
        input.classList.add('is-invalid');
        var errEl = document.createElement('small');
        errEl.className = 'form-field-error';
        errEl.textContent = errors[field][0] || '';
        input.parentNode.insertBefore(errEl, input.nextSibling);
      });
      // Focus the first invalid field so the user can act on the errors.
      var firstBad = form.querySelector('.is-invalid');
      if (firstBad) firstBad.focus();
    }

    // Open on click (delegated: works for buttons inside tables/cards too).
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-ajax-form]');
      if (!trigger) return;
      e.preventDefault();
      openAjaxForm(trigger);
    });
    if (ajaxFormClose) ajaxFormClose.addEventListener('click', function () { closeModal('ajaxFormModal'); });
  }

  /* ---------------- Global search (Ctrl/Cmd+K) ---------------- */
  var globalSearch = document.getElementById('globalSearch');
  if (globalSearch) {
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        globalSearch.focus();
      }
    });
  }

  /* ---------------- Amount formatting in forms ---------------- */
  document.querySelectorAll('input[data-format="number"]').forEach(function (input) {
    input.addEventListener('blur', function () {
      var v = parseFloat(this.value.replace(/,/g, ''));
      // Keep the ASCII comma format the PHP controllers parse (server contract),
      // regardless of page locale.
      if (!isNaN(v)) this.value = v.toLocaleString('en-US', { maximumFractionDigits: 8 });
    });
  });

  /* ---------------- Live calculation: BUY / SELL ---------------- */
  var calc = document.getElementById('txCalculator');
  if (calc) {
    var curSel = document.getElementById('currency_id');
    var amt = document.getElementById('foreign_amount');
    var rate = document.getElementById('rate');
    var fee = document.getElementById('fee_amount');
    var feeType = document.getElementById('fee_type');
    var disc = document.getElementById('discount_amount');
    var discType = document.getElementById('discount_type');
    var baseAmt = document.getElementById('calcBaseAmount');
    var feeBase = document.getElementById('calcFeeBase');
    var total = document.getElementById('calcTotal');
    var rateHint = document.getElementById('rateHint');
    var mode = calc.dataset.mode;
    var rates = window.EXCHANGE_RATES || {};

    function num(v) { var n = parseFloat(String(v).replace(/,/g, '')); return isNaN(n) ? 0 : n; }

    // Percent fee/discount apply against the base (subtotal) amount; a fixed fee
    // is expressed in the base currency directly — exactly what
    // TransactionService::feeOf()/discountOf() do server-side, so the preview
    // always matches the committed numbers.
    function recalc() {
      var c = curSel ? curSel.value : '';
      var r = num(rate ? rate.value : 0);
      var a = num(amt ? amt.value : 0);
      var f = num(fee ? fee.value : 0);
      var d = num(disc ? disc.value : 0);
      var base = r * a;
      var feeB = (feeType && feeType.value === 'percent') ? base * f / 100 : f;
      var dVal = (discType && discType.value === 'percent') ? base * d / 100 : d;
      var t;
      if (mode === 'sell') t = base + feeB - dVal;
      else t = base - feeB + dVal;
      if (baseAmt) baseAmt.textContent = fmt(base);
      if (feeBase) feeBase.textContent = fmt(feeB);
      if (total) total.textContent = fmt(t);
      if (rateHint && rates[c]) {
        rateHint.textContent = 'Current: buy ' + fmt(rates[c].buy) + ' / sell ' + fmt(rates[c].sell);
      }
    }
    // Display totals match the server's Money::format output (western digits)
    function fmt(v) {
      return v.toLocaleString('en-US', { maximumFractionDigits: 4 });
    }
    [curSel, amt, rate, fee, feeType, disc, discType].forEach(function (el) {
      if (el) el.addEventListener('input', recalc);
    });
    if (curSel) curSel.addEventListener('change', function () {
      var c = this.value;
      if (rates[c]) {
        if (rate) rate.value = mode === 'sell' ? rates[c].sell : rates[c].buy;
        recalc();
      }
    });
    recalc();
  }

  /* ---------------- Live calculation: EXCHANGE ---------------- */
  var exc = document.getElementById('exchangeCalc');
  if (exc) {
    var srcSel = document.getElementById('source_currency_id');
    var tgtSel = document.getElementById('target_currency_id');
    var srcAmt = document.getElementById('source_amount');
    var tgtAmt = document.getElementById('target_amount');
    var cross = document.getElementById('cross_rate');
    var rates2 = window.EXCHANGE_RATES || {};

    function num2(v) { var n = parseFloat(String(v).replace(/,/g, '')); return isNaN(n) ? 0 : n; }
    function recalc2() {
      var s = srcSel ? srcSel.value : '';
      var t = tgtSel ? tgtSel.value : '';
      var a = num2(srcAmt ? srcAmt.value : 0);
      if (!s || !t || !a) return;
      var buy = rates2[s] ? rates2[s].buy : 0;
      var sell = rates2[t] ? rates2[t].sell : 0;
      if (!buy || !sell) return;
      var cr = buy / sell; // target per 1 source
      if (cross) cross.textContent = cr.toFixed(6);
      if (tgtAmt) tgtAmt.value = (a * cr).toFixed(4);
    }
    [srcSel, tgtSel, srcAmt].forEach(function (el) {
      if (el) el.addEventListener('change', recalc2);
      if (el) el.addEventListener('input', recalc2);
    });
    recalc2();
  }

  /* ---------------- Rate sync "Sync Now" (no full page freeze) ---------------- */
  var syncForm = document.getElementById('syncRatesForm');
  if (syncForm) {
    syncForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = document.getElementById('syncRatesBtn');
      if (btn && btn.disabled) return;
      var label = btn ? btn.querySelector('span') : null;
      var original = label ? label.textContent : '';
      var syncing = btn ? (btn.getAttribute('data-syncing') || original) : original;
      var failed = btn ? (btn.getAttribute('data-failed') || 'Unable to update rates') : 'Unable to update rates';
      if (btn) { btn.disabled = true; btn.classList.add('is-syncing'); }
      if (label) label.textContent = syncing;
      var csrf = syncForm.querySelector('input[name="_csrf"]');
      var body = new FormData();
      if (csrf) body.append('_csrf', csrf.value);
      fetch('/rates/sync', {
        method: 'POST',
        headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
        body: body
      })
        .then(function (r) {
          return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; });
        })
        .then(function (res) {
          if (res.ok) { window.location.reload(); return; }
          if (btn) { btn.disabled = false; btn.classList.remove('is-syncing'); }
          if (label) label.textContent = (res.d && res.d.message) ? res.d.message : failed;
          if (label) setTimeout(function () { label.textContent = original; }, 6000);
        })
        .catch(function () {
          if (btn) { btn.disabled = false; btn.classList.remove('is-syncing'); }
          if (label) label.textContent = failed;
          if (label) setTimeout(function () { label.textContent = original; }, 6000);
        });
    });
  }

  /* ---------------- Rate provider conditional fields (XE credentials) ---------------- */
  var providerSel = document.getElementById('rs_provider');
  var providerDesc = document.getElementById('rs_provider_desc');
  if (providerSel) {
    function toggleProviderFields() {
      var showXe = providerSel.value === 'xe';
      document.querySelectorAll('.xe-fields').forEach(function (el) {
        el.hidden = !showXe;
      });
    }
    function updateProviderDesc() {
      if (!providerDesc) return;
      var opt = providerSel.querySelector('option:checked');
      providerDesc.textContent = opt ? (opt.getAttribute('data-desc') || '') : '';
    }
    providerSel.addEventListener('change', function () {
      toggleProviderFields();
      updateProviderDesc();
    });
    toggleProviderFields();
    updateProviderDesc();
  }

  /* ---------------- Print receipt ---------------- */
  var printBtn = document.getElementById('printReceipt');
  if (printBtn) printBtn.addEventListener('click', function () { window.print(); });

  /* ---------------- Flash toasts ---------------- */
  var FLASH_ICONS = {
    success: 'check-square', danger: 'x', warning: 'alert-triangle', info: 'info'
  };
  document.querySelectorAll('.flash').forEach(function (f) {
    var cls = f.className.match(/flash-(\w+)/);
    var type = cls ? cls[1] : 'info';
    if (!f.querySelector('.icon') && FLASH_ICONS[type]) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'icon');
      svg.setAttribute('aria-hidden', 'true');
      var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
      use.setAttribute('href', '/assets/img/icons.svg#' + FLASH_ICONS[type]);
      svg.appendChild(use);
      f.insertBefore(svg, f.firstChild);
    }
    setTimeout(function () {
      f.style.transition = 'opacity .35s, transform .35s, filter .35s';
      f.style.opacity = '0';
      f.style.transform = 'translateY(-6px)';
      f.style.filter = 'blur(3px)';
      setTimeout(function () { f.remove(); }, 360);
    }, 6000);
  });

  /* ---------------- Double-submit guard for commit forms ---------------- */
  // Any <form data-submit-guard> disables its submit button(s) on the first
  // real submit, so a slow round trip can't double-book a transaction.
  document.querySelectorAll('form[data-submit-guard]').forEach(function (f) {
    f.addEventListener('submit', function () {
      f.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (b) { b.disabled = true; });
    });
  });

  /* ---------------- Staggered entrance for stat cards ---------------- */
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduced) {
    document.querySelectorAll('.stat-card').forEach(function (c, i) {
      c.style.opacity = '0';
      c.style.transform = 'translateY(10px)';
      c.style.transition = 'opacity .4s cubic-bezier(.2,.8,.2,1) .05s, transform .4s cubic-bezier(.2,.8,.2,1) .05s';
      setTimeout(function () {
        c.style.opacity = '1';
        c.style.transform = 'translateY(0)';
        // Drop the inline transition once the entrance finishes so CSS hover
        // transitions (stat-card lift) take over afterwards.
        setTimeout(function () { c.style.transition = ''; }, 500);
      }, 40 + i * 45);
    });
  }

  /* ---------------- Count-up for arriving numbers ---------------- */
  // Stat values and the hero balance are numbers arriving on their own; count
  // them up with an ease-out so the change reads as a single deliberate moment.
  if (!reduced) {
    function countUp(el, delay) {
      var text = el.textContent.trim();
      var m = text.match(/^(-?[\d,]+(?:\.\d+)?)(.*)$/);
      if (!m) return;
      var sign = m[1].charAt(0) === '-';
      var num = parseFloat(m[1].replace(/,/g, ''));
      if (!isFinite(num) || num === 0) return;
      var suffix = m[2];
      var decimals = (m[1].split('.')[1] || '').length;
      var dur = 650, start = null;
      function fmt(v) {
        // Show the minus only once the magnitude is past zero, so negative
        // balances don't flash "-0.00" on the opening frames.
        return (sign && v > 0 ? '-' : '') + v.toLocaleString('en-US', {
          minimumFractionDigits: decimals, maximumFractionDigits: decimals
        }) + suffix;
      }
      function step(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = fmt(Math.abs(num) * eased);
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = text; // restore the exact server-rendered string
      }
      // Zero the value during script evaluation (before first paint) so the
      // server-rendered figure never flashes before the count begins.
      el.textContent = fmt(0);
      setTimeout(function () { requestAnimationFrame(step); }, delay || 0);
    }
    document.querySelectorAll('.hero-value').forEach(function (el) { countUp(el, 120); });
    document.querySelectorAll('.stat-value').forEach(function (el, i) { countUp(el, 80 + i * 45); });
  }
})();

/* ==========================================================================
   Quick transaction (keyboard-first operator screen)
   ========================================================================== */
(function () {
  'use strict';
  var form = document.getElementById('quickForm');
  if (!form) return;

  var dirInput = document.getElementById('quickDirection');
  var curWrap = document.getElementById('quickCurrencies');
  var amt = document.getElementById('quickAmount');
  var rate = document.getElementById('quickRate');
  var totalEl = document.getElementById('quickTotal');
  var confirmBtn = document.getElementById('quickConfirm');
  var largeConfirmed = document.getElementById('quickLargeConfirmed');
  var threshold = parseFloat(form.getAttribute('data-large-threshold') || '0');
  var baseCode = form.getAttribute('data-base-code') || '';
  var fee = document.getElementById('quickFee');
  var disc = document.getElementById('quickDiscount');
  var curBtns = curWrap ? Array.prototype.slice.call(curWrap.querySelectorAll('.quick-cur')) : [];
  var activeCur = null;

  function num(v) { var n = parseFloat(String(v).replace(/,/g, '')); return isNaN(n) ? 0 : n; }
  function fmt(v) { return v.toLocaleString('en-US', { maximumFractionDigits: 4 }); }

  function setDirection(dir) {
    if (dirInput) dirInput.value = dir;
    var tabs = form.querySelectorAll('.quick-dir');
    tabs.forEach(function (b) {
      var on = b.getAttribute('data-dir') === dir;
      b.classList.toggle('active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    if (activeCur && rate) rate.value = dir === 'sell' ? activeCur.sell : activeCur.buy;
    if (totalEl) totalEl.classList.toggle('is-sell', dir === 'sell');
    recalc();
  }

  function setCurrency(el) {
    activeCur = el ? { buy: el.getAttribute('data-buy'), sell: el.getAttribute('data-sell'), prec: el.getAttribute('data-prec') || '2' } : null;
    curBtns.forEach(function (b) { b.classList.toggle('active', b === el); });
    if (activeCur && rate) {
      rate.value = (dirInput ? dirInput.value : 'buy') === 'sell' ? activeCur.sell : activeCur.buy;
    }
    recalc();
  }

  function recalc() {
    var a = num(amt ? amt.value : 0);
    var r = num(rate ? rate.value : 0);
    var base = r * a;
    var f = num(fee ? fee.value : 0);
    var d = num(disc ? disc.value : 0);
    var feeType = form.querySelector('select[name="fee_type"]');
    var discType = form.querySelector('select[name="discount_type"]');
    var feeB = (feeType && feeType.value === 'percent') ? base * f / 100 : f;
    var dVal = (discType && discType.value === 'percent') ? base * d / 100 : d;
    var t = (dirInput && dirInput.value === 'sell') ? base + feeB - dVal : base - feeB + dVal;
    if (totalEl) totalEl.textContent = fmt(t);
    var big = threshold > 0 && base >= threshold;
    // Once a large transaction is confirmed, keep the confirmation until the
    // amount actually drops below the threshold again (never silently reset it).
    if (largeConfirmed && !big) largeConfirmed.value = '0';
    if (confirmBtn && big) confirmBtn.classList.add('is-large');
    else if (confirmBtn) confirmBtn.classList.remove('is-large');
  }

  // Direction tabs
  form.querySelectorAll('.quick-dir').forEach(function (b) {
    b.addEventListener('click', function () { setDirection(b.getAttribute('data-dir')); });
  });
  // Currency chips
  curBtns.forEach(function (b) {
    b.addEventListener('click', function () { setCurrency(b); });
  });
  [amt, rate, fee, disc].forEach(function (el) {
    if (el) el.addEventListener('input', recalc);
  });
  form.querySelectorAll('select[name="fee_type"], select[name="discount_type"]').forEach(function (el) {
    el.addEventListener('change', recalc);
  });

  // Keyboard shortcuts: F1 buy, F2 sell, F3 customer search, F4 currency, F5 calculator, ESC reset
  var customerPicker = form.querySelector('[data-picker]');
  document.addEventListener('keydown', function (e) {
    if (e.key === 'F1') { e.preventDefault(); setDirection('buy'); if (amt) amt.focus(); }
    else if (e.key === 'F2') { e.preventDefault(); setDirection('sell'); if (amt) amt.focus(); }
    else if (e.key === 'F3') { e.preventDefault(); if (customerPicker) { var trig = customerPicker.querySelector('[data-picker-open]'); if (trig) trig.click(); } }
    else if (e.key === 'F4') { e.preventDefault(); if (curWrap) curWrap.focus(); }
    else if (e.key === 'F5') { e.preventDefault(); window.location.href = '/calculator'; }
    else if (e.key === 'Escape') { if (form) form.reset(); if (activeCur) setCurrency(null); recalc(); }
  });

  // Confirm flow: large transactions need explicit confirmation
  if (confirmBtn && largeConfirmed) {
    confirmBtn.addEventListener('click', function (e) {
      var a = num(amt ? amt.value : 0);
      var r = num(rate ? rate.value : 0);
      if (threshold > 0 && a * r >= threshold && largeConfirmed.value !== '1') {
        e.preventDefault();
        largeConfirmed.value = '1';
        var hint = confirmBtn.querySelector('.quick-large-hint');
        if (!hint) {
          hint = document.createElement('span');
          hint.className = 'quick-large-hint';
          confirmBtn.appendChild(hint);
        }
        hint.textContent = ' ' + (confirmBtn.getAttribute('data-large-msg') || '— ' + (baseCode || '') + ' ' + fmt(threshold) + ' — ' + (form.getAttribute('data-large-confirm') || 'Click again to confirm'));
        confirmBtn.classList.add('is-large-confirm');
        return;
      }
      if (largeConfirmed.value === '1') confirmBtn.classList.remove('is-large-confirm');
    });
  }

  setDirection(dirInput ? dirInput.value : 'buy');
  setCurrency(curBtns.length ? (curWrap.querySelector('.quick-cur.active') || curBtns[0]) : null);
})();

/* ==========================================================================
   Transaction Calculator (dedicated pre-transaction calculator)
   ========================================================================== */
(function () {
  'use strict';
  var form = document.getElementById('calcForm');
  if (!form) return;

  var dirInput = document.getElementById('calcDirection');
  var curId = document.getElementById('calcCurrencyId');
  var curWrap = document.getElementById('calcCurrencies');
  var amount = document.getElementById('calcAmount');
  var rate = document.getElementById('calcRate');
  var amountUnit = document.getElementById('calcAmountUnit');
  var otherValue = document.getElementById('calcOtherValue');
  var otherUnit = document.getElementById('calcOtherUnit');
  var subtotalEl = document.getElementById('calcSubtotal');
  var feeEl = document.getElementById('calcFeeB');
  var discEl = document.getElementById('calcDiscB');
  var finalEl = document.getElementById('calcFinal');
  var finalLabel = document.getElementById('calcFinalLabel');
  var feeInput = document.getElementById('calcFee');
  var discInput = document.getElementById('calcDisc');
  var feeType = document.getElementById('calcFeeType');
  var discType = document.getElementById('calcDiscType');
  var foreignHidden = document.getElementById('calcForeign');
  var calculateBtn = document.getElementById('calcCalculate');
  var createBtn = document.getElementById('calcCreate');
  var miniValue = document.getElementById('calcMiniValue');
  var miniLabel = document.getElementById('calcMiniLabel');
  var rateStrip = document.getElementById('calcRateStrip');
  var presetsWrap = document.getElementById('calcPresets');
  var availEl = document.getElementById('calcAvail');
  var rateBadge = document.getElementById('calcRateBadge');
  var rateReset = document.getElementById('calcRateReset');
  var rateBoardEl = document.getElementById('calcRateBoard');
  var FOREIGN_PRESETS = [100, 500, 1000, 5000, 10000];
  var baseCode = form.getAttribute('data-base-code') || '';
  var basePrec = parseInt(form.getAttribute('data-base-prec') || '2', 10);
  var curBtns = curWrap ? Array.prototype.slice.call(curWrap.querySelectorAll('.quick-cur')) : [];
  var activeCur = null;
  var mode = 'to_base'; // to_base: enter foreign → result base | from_base: enter base → result foreign

  function num(v) { var n = parseFloat(String(v).replace(/,/g, '')); return isNaN(n) ? 0 : n; }
  function fmt(v, p) {
    p = (p === undefined || p === null || isNaN(p)) ? 2 : p;
    return Number(v).toLocaleString('en-US', { minimumFractionDigits: p, maximumFractionDigits: p });
  }
  function basePrecision() { return activeCur ? parseInt(activeCur.ap, 10) : 2; }
  function ratePrecision() { return activeCur ? parseInt(activeCur.rp, 10) : 2; }

  /* Quick-amount presets — foreign units in to_base, base units derived from the rate otherwise */
  function renderPresets() {
    if (!presetsWrap) return;
    var rateNow = num(rate ? rate.value : 0);
    var vals = (mode === 'from_base' && rateNow > 0)
      ? FOREIGN_PRESETS.map(function (f) {
          var v = f * rateNow;
          var m = Math.pow(10, basePrec);
          return Math.round(v * m) / m;
        })
      : FOREIGN_PRESETS;
    presetsWrap.innerHTML = '';
    vals.forEach(function (v) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'preset-btn';
      b.textContent = fmt(v, v % 1 === 0 ? 0 : basePrec);
      b.setAttribute('aria-label', fmt(v, v % 1 === 0 ? 0 : basePrec));
      b.addEventListener('click', function () {
        amount.value = String(v);
        recalc();
        if (amount) amount.focus();
      });
      presetsWrap.appendChild(b);
    });
  }

  function setDirection(dir) {
    dirInput.value = dir;
    form.querySelectorAll('.quick-dir').forEach(function (b) {
      var on = b.getAttribute('data-dir') === dir;
      b.classList.toggle('active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    if (activeCur) rate.value = dir === 'sell' ? activeCur.sell : activeCur.buy;
    recalc();
  }

  function setMode(m) {
    mode = (m === 'from_base') ? 'from_base' : 'to_base';
    form.querySelectorAll('.calc-mode-btn').forEach(function (b) {
      var on = b.getAttribute('data-mode') === mode;
      b.classList.toggle('active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    renderPresets();
    recalc();
  }

  function setCurrency(el) {
    activeCur = el ? {
      id: el.getAttribute('data-id'),
      code: el.getAttribute('data-code'),
      buy: el.getAttribute('data-buy'),
      sell: el.getAttribute('data-sell'),
      ap: el.getAttribute('data-ap') || '2',
      rp: el.getAttribute('data-rp') || '2',
      qty: el.getAttribute('data-qty') || '0'
    } : null;
    curBtns.forEach(function (b) { b.classList.toggle('active', b === el); });
    if (activeCur) {
      curId.value = activeCur.id;
      rate.value = (dirInput ? dirInput.value : 'buy') === 'sell' ? activeCur.sell : activeCur.buy;
    }
    if (rateStrip && activeCur) {
      var bl = rateStrip.getAttribute('data-buy-label') || 'Buy';
      var sl = rateStrip.getAttribute('data-sell-label') || 'Sell';
      rateStrip.textContent = bl + ' ' + fmt(num(activeCur.buy), ratePrecision())
        + ' · ' + sl + ' ' + fmt(num(activeCur.sell), ratePrecision());
    }
    renderPresets();
    recalc();
  }

  function recalc() {
    var a = num(amount ? amount.value : 0);
    var r = num(rate ? rate.value : 0);
    var foreign = (mode === 'from_base' && r > 0) ? a / r : a;
    var base = (mode === 'from_base') ? a : foreign * r;
    var f = num(feeInput ? feeInput.value : 0);
    var d = num(discInput ? discInput.value : 0);
    var feeB = (feeType && feeType.value === 'percent') ? base * f / 100 : f;
    var discB = (discType && discType.value === 'percent') ? base * d / 100 : d;
    var isSell = dirInput && dirInput.value === 'sell';
    var total = isSell ? base + feeB - discB : base - feeB + discB;

    var code = activeCur ? activeCur.code : '—';
    // Units and the result-box label swap with the mode
    if (amountUnit) amountUnit.textContent = mode === 'to_base' ? code : baseCode;
    if (otherUnit) otherUnit.textContent = mode === 'to_base' ? baseCode : code;
    var otherLabel = document.getElementById('calcOtherLabel');
    if (otherLabel) {
      otherLabel.textContent = mode === 'to_base'
        ? (isSell ? (form.getAttribute('data-pays') || '') : (form.getAttribute('data-receives') || ''))
        : (form.getAttribute('data-result') || 'Result');
    }
    if (otherValue) {
      otherValue.textContent = mode === 'to_base' ? fmt(total, basePrec) : fmt(foreign, basePrecision());
      otherValue.classList.toggle('is-sell', isSell);
    }
    // Slim live total inside the form (mobile-friendly mirror of the receipt)
    if (miniValue) miniValue.textContent = mode === 'to_base' ? fmt(total, basePrec) : fmt(foreign, basePrecision());
    if (miniLabel) miniLabel.textContent = otherLabel ? otherLabel.textContent : '';
    if (subtotalEl) subtotalEl.textContent = fmt(base, basePrec) + ' ' + baseCode;
    if (feeEl) feeEl.textContent = fmt(feeB, basePrec) + ' ' + baseCode;
    if (discEl) discEl.textContent = fmt(discB, basePrec) + ' ' + baseCode;
    if (finalEl) finalEl.textContent = fmt(total, basePrec) + ' ' + baseCode;
    if (finalLabel) finalLabel.textContent = isSell ? (form.getAttribute('data-pays') || '') : (form.getAttribute('data-receives') || '');
    // Carry the foreign amount that the backend expects
    if (foreignHidden) foreignHidden.value = String(foreign);

    // Custom-rate indicator + tap-to-reset hint
    var board = activeCur ? num(dirInput && dirInput.value === 'sell' ? activeCur.sell : activeCur.buy) : 0;
    var isCustom = activeCur && r > 0 && board > 0 && Math.abs(r - board) > 1e-9;
    if (rateBadge) rateBadge.hidden = !isCustom;
    if (rateBoardEl && activeCur) rateBoardEl.textContent = fmt(board, ratePrecision());

    // Live inventory pre-check (sell only) — backend remains the source of truth.
    // Compare the FOREIGN amount (the amount field holds base units in from_base mode).
    if (availEl && activeCur) {
      var isSellNow = dirInput && dirInput.value === 'sell';
      if (isSellNow) {
        var q = parseFloat(activeCur.qty || '0');
        var over = foreign > q;
        availEl.hidden = false;
        availEl.classList.toggle('is-warn', over);
        availEl.textContent = (over ? availEl.getAttribute('data-not-enough') : availEl.getAttribute('data-available'))
          + ' ' + fmt(q, basePrecision()) + ' ' + activeCur.code;
      } else {
        availEl.hidden = true;
        availEl.classList.remove('is-warn');
      }
    }
  }

  /* ---- events ---- */
  form.querySelectorAll('.quick-dir').forEach(function (b) {
    b.addEventListener('click', function () { setDirection(b.getAttribute('data-dir')); });
  });
  form.querySelectorAll('.calc-mode-btn').forEach(function (b) {
    b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); });
  });
  curBtns.forEach(function (b) {
    b.addEventListener('click', function () { setCurrency(b); });
  });
  [amount, rate, feeInput, discInput].forEach(function (el) {
    if (el) el.addEventListener('input', recalc);
  });
  [feeType, discType].forEach(function (el) {
    if (el) el.addEventListener('change', recalc);
  });

  /* [Calculate] — explicit recalc with visible feedback */
  if (calculateBtn) calculateBtn.addEventListener('click', function () {
    recalc();
    var bd = document.getElementById('calcBreakdown');
    if (bd) {
      bd.classList.remove('calc-pulse');
      void bd.offsetWidth; /* restart animation */
      bd.classList.add('calc-pulse');
    }
  });

  /* [Create transaction] — confirm modal, then submit */
  function customerName() {
    var v = document.querySelector('.customer-picker-value');
    return v && v.textContent.trim() && v.textContent.trim() !== '—' ? v.textContent.trim() : '—';
  }

  if (rateReset) rateReset.addEventListener('click', function () {
    if (activeCur) {
      rate.value = (dirInput && dirInput.value === 'sell') ? activeCur.sell : activeCur.buy;
      recalc();
      if (rate) rate.focus();
    }
  });

  if (createBtn) createBtn.addEventListener('click', function () {
    recalc();
    var r = num(rate ? rate.value : 0);
    var a = num(amount ? amount.value : 0);
    if (r <= 0 || a <= 0) { if (amount) amount.focus(); return; }
    // Refuse to open the confirmation when selling more than is in stock.
    // Compare the foreign amount (foreignHidden is populated by recalc() above).
    if (activeCur && dirInput && dirInput.value === 'sell') {
      var q = parseFloat(activeCur.qty || '0');
      var fAmt = parseFloat(foreignHidden ? foreignHidden.value : a);
      if (fAmt > q) {
        if (amount) amount.focus();
        if (availEl) availEl.classList.add('is-warn');
        return;
      }
    }

    var code = activeCur ? activeCur.code : '—';
    var isSell = dirInput && dirInput.value === 'sell';
    var dirLabel = document.getElementById('calcConfirmDir');
    if (dirLabel) dirLabel.textContent = (isSell ? 'SELL ' : 'BUY ') + code;
    setText('calcConfirmTotal', (document.getElementById('calcFinal') || {}).textContent || '');
    setText('calcConfirmAmount', fmt(num(foreignHidden ? foreignHidden.value : 0), basePrecision()) + ' ' + code);
    setText('calcConfirmRate', fmt(r, ratePrecision()) + ' ' + baseCode);
    setText('calcConfirmFee', (document.getElementById('calcFeeB') || {}).textContent || '');
    setText('calcConfirmDisc', (document.getElementById('calcDiscB') || {}).textContent || '');
    setText('calcConfirmCustomer', customerName());
    if (window.openModal) window.openModal('calcConfirmModal');
  });

  function setText(id, txt) {
    var el = document.getElementById(id);
    if (el) el.textContent = txt;
  }

  var closeBtn = document.getElementById('calcConfirmClose');
  if (closeBtn) closeBtn.addEventListener('click', function () { if (window.closeModal) window.closeModal('calcConfirmModal'); });
  var cancelBtn = document.getElementById('calcConfirmCancel');
  if (cancelBtn) cancelBtn.addEventListener('click', function () { if (window.closeModal) window.closeModal('calcConfirmModal'); });
  var submitBtn = document.getElementById('calcConfirmSubmit');
  if (submitBtn) submitBtn.addEventListener('click', function () {
    var lc = form.querySelector('input[name="large_confirmed"]');
    if (lc) lc.value = '1'; // explicit confirmation from the modal
    if (window.closeModal) window.closeModal('calcConfirmModal');
    if (form.requestSubmit) form.requestSubmit(); else form.submit();
  });

  /* Double-submit guard — one transaction per click. The modal confirm passes
     through requestSubmit(), so this listener always runs before the POST. */
  var calcSubmitted = false;
  form.addEventListener('submit', function (e) {
    if (calcSubmitted) { e.preventDefault(); return; }
    calcSubmitted = true;
    var cb = document.getElementById('calcCreate');
    var calcBtn = document.getElementById('calcCalculate');
    var sb = document.getElementById('calcConfirmSubmit');
    if (cb) {
      cb.disabled = true;
      var saving = cb.getAttribute('data-saving');
      if (saving) {
        var lbl = cb.querySelector('span');
        if (lbl) lbl.textContent = saving;
      }
    }
    if (calcBtn) calcBtn.disabled = true;
    if (sb) sb.disabled = true;
  });

  /* ---- init ---- */
  setDirection(dirInput ? dirInput.value : 'buy');
  setMode('to_base');
  setCurrency(curBtns.length ? (curWrap.querySelector('.quick-cur.active') || curBtns[0]) : null);
})();

/* ==========================================================================
   Mobile app shell (phones ≤ 640px)
   ========================================================================== */
(function () {
  'use strict';

  var mq = window.matchMedia('(max-width: 640px)');

  /* ---- Label table cells from their <th> so stacked mobile cards read clearly ---- */
  function labelTables() {
    document.querySelectorAll('table.table').forEach(function (t) {
      var ths = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) {
        return (th.textContent || '').replace(/\s+/g, ' ').trim();
      });
      t.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) {
          if (ths[i]) td.setAttribute('data-label', ths[i]);
        });
      });
    });
  }
  if (mq.matches) labelTables();
  // Re-label on resize/rotation so the stacked cards always keep column context.
  var onChange = function () {
    if (mq.matches) labelTables();
  };
  if (mq.addEventListener) mq.addEventListener('change', onChange);
  else if (mq.addListener) mq.addListener(onChange);

  /* ---- "New" FAB action sheet ---- */
  var fab = document.getElementById('fabNew');
  var sheet = document.getElementById('newSheet');
  if (fab && sheet) {
    var lastFocus = null;
    function openSheet() {
      lastFocus = document.activeElement;
      sheet.classList.add('open');
      sheet.setAttribute('aria-hidden', 'false');
      fab.setAttribute('aria-expanded', 'true');
      fab.classList.add('open');
      var first = sheet.querySelector('a, button');
      if (first) setTimeout(function () { first.focus(); }, 120);
    }
    function closeSheet() {
      sheet.classList.remove('open');
      sheet.setAttribute('aria-hidden', 'true');
      fab.setAttribute('aria-expanded', 'false');
      fab.classList.remove('open');
      if (lastFocus) lastFocus.focus();
    }
    fab.addEventListener('click', function () {
      sheet.classList.contains('open') ? closeSheet() : openSheet();
    });
    // Trap Tab inside the sheet so keyboard users don't escape the dialog.
    sheet.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var items = sheet.querySelectorAll('a[href], button:not([disabled])');
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    sheet.addEventListener('click', function (e) {
      if (e.target === sheet) closeSheet(); // backdrop tap
    });
    sheet.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { closeSheet(); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sheet.classList.contains('open')) closeSheet();
    });
  }

  /* ---- "More" tab opens the existing sidebar drawer ---- */
  var moreBtn = document.getElementById('moreBtn');
  var menuToggle = document.getElementById('menuToggle');
  if (moreBtn && menuToggle) {
    moreBtn.addEventListener('click', function () { menuToggle.click(); });
  }

  /* ---- Close the drawer after picking a destination on phones ---- */
  if (mq.matches) {
    var sidebar = document.getElementById('sidebar');
    var scrim = document.getElementById('sidebarScrim');
    if (sidebar) {
      sidebar.querySelectorAll('a.nav-link').forEach(function (a) {
        a.addEventListener('click', function () {
          sidebar.classList.remove('open');
          if (scrim) scrim.classList.remove('show');
        });
      });
    }
  }
})();

/* ==========================================================================
   Install to home screen (PWA) — full-screen, native-app experience
   ========================================================================== */
(function () {
  'use strict';

  var btn = document.getElementById('installAppBtn');
  var deferredPrompt = null;
  var dismissed = false;

  function isStandalone() {
    return window.matchMedia && window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  }

  function isIOS() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS 13+
  }

  /* ---- Service worker registration (helps installability + offline shell) ---- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () { /* silent: SW is progressive enhancement */ });
    });
  }

  /* ---- Chrome/Edge/Android install prompt ---- */
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    if (btn && !dismissed) btn.hidden = false;
  });

  /* ---- iOS Safari: no install prompt — show the button so users can
         open the how-to modal (Share → Add to Home Screen). ---- */
  var ios = isIOS() && !isStandalone();
  if (ios && btn) btn.hidden = false;

  /* iOS has no install prompt, so the modal's action button can't trigger one.
     Hide it and keep the modal as pure instructions. */
  var modalAction = document.getElementById('installModalAction');
  if (modalAction && ios) modalAction.hidden = true;

  /* ---- Already installed / running full-screen: hide the button ---- */
  if (isStandalone() && btn) btn.hidden = true;

  window.addEventListener('appinstalled', function () {
    if (btn) btn.hidden = true;
    dismissed = true;
    if (window.closeModal) window.closeModal('installModal');
  });

  if (btn) {
    btn.addEventListener('click', function () {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
          if (choice.outcome === 'dismissed') {
            /* user said no — keep the button available */
          }
          deferredPrompt = null;
        });
      } else {
        /* iOS or unsupported browser → show instructions */
        if (window.openModal) window.openModal('installModal');
      }
    });
  }

  /* ---- Install modal wiring ---- */
  var modalClose = document.getElementById('installModalClose');
  if (modalClose) modalClose.addEventListener('click', function () {
    dismissed = true;
    if (window.closeModal) window.closeModal('installModal');
  });
  var installNow = document.getElementById('installModalAction');
  if (installNow) installNow.addEventListener('click', function () {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () { deferredPrompt = null; });
    }
    dismissed = true;
    if (window.closeModal) window.closeModal('installModal');
  });
})();
