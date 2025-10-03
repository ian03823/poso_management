/* ticketTable.js — Admin Issued Tickets
   - SweetAlert status changes (Paid ref#, Admin PW for others)
   - Filter modal (status + category -> violations)
   - AJAX partial reloads with fade, sort, pagination, pushState
   - SPA-safe: single bind guard, modal cleanup on navigation
*/
(function ($) {
  if (window.__adminTicketTableInit) return;
  window.__adminTicketTableInit = true;

  /* ---------------- config ---------------- */
  function root() { return document.getElementById('ticketContainer'); }
  function cfg() {
    const r = root();
    return {
      paidStatusId: Number(r?.dataset.paidStatusId || 0),
      statusUpdateUrl: r?.dataset.statusUpdateUrl || '/ticket',
      ticketPartialUrl: r?.dataset.ticketPartialUrl || null,
      violationsByCatUrl: r?.dataset.violationsByCatUrl || null
    };
  }

  /* ---------------- helpers ---------------- */
  const csrfToken = () => $('meta[name="csrf-token"]').attr('content') || '';
  const getSort = () => ($('#ticket-sort').val() || 'date_desc');

  // Loader must not trap clicks unless active; we also toggle display to be extra safe
  const setLoading = (on) => {
    const ov = document.getElementById('ticketLoading');
    if (!ov) return;
    if (on) { ov.style.display = 'flex'; ov.classList.add('active'); }
    else    { ov.classList.remove('active'); ov.style.display = 'none'; }
  };

  function readFiltersFromURL() {
    const sp = new URLSearchParams(location.search);
    return {
      sort_option: sp.get('sort_option') || 'date_desc',
      page: sp.get('page') || 1,
      status: sp.get('status') || '',
      category: sp.get('category') || '',
      violation_id: sp.get('violation_id') || ''
    };
  }

  function normalizeParams(params) {
    return Object.assign({
      sort_option: getSort(),
      page: 1,
      status: '',
      category: '',
      violation_id: ''
    }, params || {});
  }

  function swapHtml($el, html) {
    $el.removeClass('fade-in').addClass('fade-out');
    setTimeout(() => {
      $el.html(html);
      $el.removeClass('fade-out').addClass('fade-in');
    }, 140);
  }

  function renderActiveFilters() {
    const { status, category, violation_id } = readFiltersFromURL();
    const $af = $('#active-filters');
    const parts = [];
    if (status) parts.push(`Status: ${status}`);
    if (category) parts.push(`Category: ${category}`);
    if (violation_id) parts.push(`Violation: #${violation_id}`);
    $af.text(parts.length ? parts.join(' • ') : '');
  }

  function loadTable(params, push = true) {
    const C = cfg();
    if (!C.ticketPartialUrl) return;
    const opts = normalizeParams(params);
    setLoading(true);
    $.get(C.ticketPartialUrl + '?' + $.param(opts))
      .done(html => {
        swapHtml($('#ticket-table'), html);
        if (push) history.pushState(null, '', '/ticket?' + $.param(opts));
        renderActiveFilters();
      })
      .always(() => setLoading(false));
  }

  function toastOk(msg) {
    if (!window.Swal) return;
    Swal.fire({
      toast: true, position: 'top-end', icon: 'success',
      title: msg || 'Updated', timer: 1500, showConfirmButton: false
    });
  }

  function hideFilterModalSafely() {
    const el = document.getElementById('ticketFilterModal');
    if (!el) return;
    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(el).hide();
    } else {
      document.body.classList.remove('modal-open');
      document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    }
  }

  // Ensure the page isn’t blocked by loader or lingering backdrop
  $(document).on('show.bs.modal', '#ticketFilterModal', function () {
    setLoading(false); // make sure loader is off
  });
  $(document).on('hidden.bs.modal', '#ticketFilterModal', function () {
    // belt & suspenders cleanup in SPA
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
  });
  window.addEventListener('popstate', () => {
    const el = document.getElementById('ticketFilterModal');
    if (el && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(el).hide();
  });

  /* ---------------- status helpers ---------------- */
  let _lastSelect = null;
  let _lastValue  = null;
  function revertSelect() {
    if (_lastSelect && _lastValue != null) _lastSelect.val(_lastValue);
    _lastSelect = null; _lastValue = null;
  }

  function postStatusPromise(ticketId, data) {
    const C = cfg();
    return new Promise((resolve, reject) => {
      $.ajax({
        url: `${C.statusUpdateUrl}/${ticketId}/status`,
        method: 'POST',
        data: Object.assign({ _token: csrfToken() }, data),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .done(resolve)
      .fail(xhr => reject(xhr?.responseJSON?.message || 'Could not update status.'));
    });
  }

  async function promptReference(ticketId) {
    if (window.Swal) {
      const { isConfirmed } = await Swal.fire({
        title: 'Enter Reference Number',
        input: 'text',
        inputLabel: 'Reference # (required for Paid)',
        inputPlaceholder: 'e.g., OR-123456',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
        showCancelButton: true,
        confirmButtonText: 'Submit',
        showLoaderOnConfirm: true,
        preConfirm: async (refNo) => {
          refNo = (refNo || '').trim();
          if (!refNo) {
            Swal.showValidationMessage('Reference number is required.');
            return false;
          }
          try {
            await postStatusPromise(ticketId, { status_id: cfg().paidStatusId, reference_number: refNo });
          } catch (errMsg) {
            Swal.showValidationMessage(errMsg);
            return false;
          }
          return true;
        },
        allowOutsideClick: () => !Swal.isLoading()
      });
      return isConfirmed;
    } else {
      const ref = (prompt('Enter Reference Number:') || '').trim();
      if (!ref) return false;
      await postStatusPromise(ticketId, { status_id: cfg().paidStatusId, reference_number: ref });
      return true;
    }
  }

  async function promptPassword(ticketId, newStatus) {
    if (window.Swal) {
      const { isConfirmed } = await Swal.fire({
        title: 'Admin Password Required',
        input: 'password',
        inputLabel: 'Enter password to confirm',
        inputPlaceholder: 'Password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
        showCancelButton: true,
        confirmButtonText: 'Confirm',
        showLoaderOnConfirm: true,
        preConfirm: async (pw) => {
          pw = (pw || '').trim();
          if (!pw) {
            Swal.showValidationMessage('Password is required.');
            return false;
          }
          try {
            await postStatusPromise(ticketId, { status_id: newStatus, admin_password: pw });
          } catch (errMsg) {
            Swal.showValidationMessage(errMsg);
            return false;
          }
          return true;
        },
        allowOutsideClick: () => !Swal.isLoading()
      });
      return isConfirmed;
    } else {
      const pw = (prompt('Enter admin password:') || '').trim();
      if (!pw) return false;
      await postStatusPromise(ticketId, { status_id: newStatus, admin_password: pw });
      return true;
    }
  }

  /* ---------------- initial load ---------------- */
  function initialLoadIfNeeded() {
    if (!document.getElementById('ticket-table')) return;
    const hasTable = !!document.querySelector('#ticket-table table');
    const params = readFiltersFromURL();
    if (!hasTable) {
      loadTable(params, /*push=*/false);
      $('#ticket-sort').val(params.sort_option || 'date_desc');
      history.replaceState(null, '', location.pathname + location.search);
    } else {
      $('#ticket-sort').val(params.sort_option || 'date_desc');
      renderActiveFilters();
    }
  }
  $(initialLoadIfNeeded);
  document.addEventListener('page:loaded', initialLoadIfNeeded);

  /* ---------------- sort & pagination ---------------- */
  $(document).on('change', '#ticket-sort', function () {
    const f = readFiltersFromURL();
    loadTable({ sort_option: $(this).val(), page: 1, status: f.status, category: f.category, violation_id: f.violation_id });
  });

  $(document).on('click', '#ticket-table .pagination a', function (e) {
    e.preventDefault();
    const page = new URL(this.href).searchParams.get('page') || 1;
    const f = readFiltersFromURL();
    loadTable({ sort_option: getSort(), page, status: f.status, category: f.category, violation_id: f.violation_id });
  });

  /* ---------------- status change (SweetAlert) ---------------- */
  $(document).on('change', '.status-select', async function () {
    const $sel      = $(this);
    const ticketId  = +$sel.data('ticket-id');
    const oldStatus = +$sel.data('current-status-id');
    const newStatus = +$sel.val();
    const paidId    = Number(cfg().paidStatusId);

    _lastSelect = $sel;
    _lastValue  = oldStatus;
    if (oldStatus === newStatus) return;

    try {
      let ok = false;
      if (newStatus === paidId) {
        ok = await promptReference(ticketId);
      } else {
        ok = await promptPassword(ticketId, newStatus);
      }

      if (!ok) { revertSelect(); return; }

      toastOk('Status updated');
      const f = readFiltersFromURL();
      loadTable({ sort_option: getSort(), page: f.page, status: f.status, category: f.category, violation_id: f.violation_id }, /*push=*/false);
      _lastSelect = null; _lastValue = null;
    } catch (err) {
      if (window.Swal) Swal.fire('Error', String(err), 'error');
      revertSelect();
    }
  });

  /* ---------------- filter modal wiring ---------------- */
  // Open modal: prefill from URL and load violations if category is present
  $(document).on('show.bs.modal', '#ticketFilterModal', function () {
    const f = readFiltersFromURL();
    $('#filter-status').val(f.status || '');
    $('#filter-category').val(f.category || '');

    const $viol = $('#filter-violation');
    $viol.prop('disabled', true).html('<option value="">All</option>');

    const C = cfg();
    if (f.category && C.violationsByCatUrl) {
      $.get(C.violationsByCatUrl, { category: f.category }).done(items => {
        items.forEach(it => {
          $viol.append(`<option value="${it.id}">${it.violation_name} (${it.violation_code})</option>`);
        });
        $viol.prop('disabled', false);
        if (f.violation_id) $viol.val(String(f.violation_id));
      });
    }
  });

  // Category change -> fetch violations
  $(document).on('change', '#filter-category', function () {
    const cat = $(this).val();
    const $viol = $('#filter-violation');
    $viol.prop('disabled', true).html('<option value="">All</option>');

    const C = cfg();
    if (!cat || !C.violationsByCatUrl) return;

    $.get(C.violationsByCatUrl, { category: cat }).done(items => {
      items.forEach(it => {
        $viol.append(`<option value="${it.id}">${it.violation_name} (${it.violation_code})</option>`);
      });
      $viol.prop('disabled', false);
    });
  });

  // Apply filters
  $(document).on('submit', '#ticket-filter-form', function (e) {
    e.preventDefault();
    const filters = {
      status: $('#filter-status').val() || '',
      category: $('#filter-category').val() || '',
      violation_id: $('#filter-violation').val() || ''
    };
    hideFilterModalSafely();
    loadTable(Object.assign({ page: 1, sort_option: getSort() }, filters), /*push=*/true);
  });

  // Reset filters
  $(document).on('click', '#btn-reset-filters', function () {
    $('#filter-status').val('');
    $('#filter-category').val('');
    $('#filter-violation').val('').prop('disabled', true);
    loadTable({ sort_option: getSort(), page: 1, status: '', category: '', violation_id: '' }, /*push=*/true);
  });

  // SPA safety: close any open modal on history nav
  window.addEventListener('popstate', () => {
    const el = document.getElementById('ticketFilterModal');
    if (el && window.bootstrap?.Modal) window.bootstrap.Modal.getOrCreateInstance(el).hide();
  });

})(jQuery);
