/* ticketTable.js — Bootstrap-free filters (SweetAlert2), SPA-safe
   - Filter dialog via Swal (no Bootstrap modal = no backdrops, no freeze)
   - Status change via Swal (Paid -> ref#, Others -> admin PW)
   - AJAX partial reloads with fade, sort, pagination, pushState
   - Version-based polling so new tickets appear automatically
*/
(function ($) {
  if (window.__adminTicketTableInit) return;
  window.__adminTicketTableInit = true;

  /* ---------------- config ---------------- */
  function root() { 
    return document.getElementById('ticketContainer'); 
  }

  function cfg() {
    const r = root();
    return {
      paidStatusId:      Number(r?.dataset.paidStatusId || 0),
      statusUpdateUrl:   r?.dataset.statusUpdateUrl   || '/ticket',
      ticketPartialUrl:  r?.dataset.ticketPartialUrl  || null,
      versionUrl:        r?.dataset.versionUrl        || null,
      violationsByCatUrl:r?.dataset.violationsByCatUrl|| null,
      categories:        safeParseJSON(r?.dataset.violationCategories) || [] // array of strings
    };
  }

  let lastVersionToken = null;
  let lastLatestTicketId = null;
  let pollHandle       = null;
  function playNotifySound() {
    const audio = document.getElementById('ticketNotifySound');
    if (!audio) return;

    // rewind to start in case it was played recently
    audio.currentTime = 0;
    audio.play().catch(() => {
      // Some browsers block autoplay until user interacts with the page.
      // We just silently ignore the error.
    });
  }


  function showNewTicketToast(info) {
    if (!window.Swal) return;
    const num = info && info.latestTicketNumber ? info.latestTicketNumber : null;
    playNotifySound();
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      title: num 
        ? `New ticket #${num} issued`
        : 'New ticket issued',
      timer: 3000,
      showConfirmButton: false
    });
  }

  async function checkVersionAndMaybeReload() {
    const container = root();
    if (!container) return; // not on ticket page

    const C = cfg();
    if (!C.versionUrl || !C.ticketPartialUrl) return;

    // Respect current filters so the token matches the visible table:
    const f = readFiltersFromURL();
    try {
      const res = await $.getJSON(C.versionUrl, {
        status:       f.status       || '',
        category:     f.category     || '',
        violation_id: f.violation_id || ''
      });

      const token      = res && res.v ? String(res.v) : null;
      const latestId   = res && res.latestTicketId ? Number(res.latestTicketId) : null;

      // First time: baseline only, no toast, no reload
      if (!lastVersionToken && token) {
        lastVersionToken   = token;
        lastLatestTicketId = latestId;
        return;
      }

      // Subsequent: version changed = something updated
      if (token && lastVersionToken && token !== lastVersionToken) {

        // If there is a strictly newer ticket id, show toast
        if (latestId && lastLatestTicketId && latestId > lastLatestTicketId) {
          showNewTicketToast(res);
        }

        lastVersionToken   = token;
        lastLatestTicketId = latestId ?? lastLatestTicketId;

        // Refresh the table for current filters
        loadTable({
          sort_option:  getSort(),
          page:         f.page        || 1,
          status:       f.status      || '',
          category:     f.category    || '',
          violation_id: f.violation_id|| ''
        }, /*push=*/false);
      }
    } catch (e) {
      // ignore transient errors; keep polling
    }
  }

  function startPolling() {
    const container = root();

    // If we are NOT on the ticket page, stop any existing poller and exit
    if (!container) {
      if (pollHandle) {
        clearInterval(pollHandle);
        pollHandle = null;
      }
      return;
    }

    // We ARE on the ticket page
    // Reset baseline and interval
    if (pollHandle) {
      clearInterval(pollHandle);
      pollHandle = null;
    }
    lastVersionToken = null;
    lastLatestTicketId = null;
    // Initial check (baseline token)
    checkVersionAndMaybeReload();

    // Poll every 5 seconds (tweak if you want faster)
    pollHandle = setInterval(checkVersionAndMaybeReload, 3000);
  }

  function safeParseJSON(s) { 
    try { return s ? JSON.parse(s) : null; } 
    catch { return null; } 
  }

  /* ---------------- helpers ---------------- */
  const csrfToken = () => $('meta[name="csrf-token"]').attr('content') || '';
  const getSort   = () => ($('#ticket-sort').val() || 'date_desc');

  const setLoading = (on) => {
    const ov = document.getElementById('ticketLoading');
    if (!ov) return;
    ov.style.display = on ? 'flex' : 'none';
  };

  function readFiltersFromURL() {
    const sp = new URLSearchParams(location.search);
    return {
      sort_option:  sp.get('sort_option')  || 'date_desc',
      page:         sp.get('page')         || 1,
      status:       sp.get('status')       || '',
      category:     sp.get('category')     || '',
      violation_id: sp.get('violation_id') || ''
    };
  }

  function normalizeParams(params) {
    return Object.assign({
      sort_option: 'date_desc',
      page:        1,
      status:      '',
      category:    '',
      violation_id:''
    }, params || {});
  }

  function swapHtml($el, html) {
    $el.removeClass('fade-in').addClass('fade-out');
    setTimeout(() => {
      $el.html(html);
      $el.removeClass('fade-out').addClass('fade-in');
    }, 120);
  }

  function renderActiveFilters() {
    const { status, category, violation_id } = readFiltersFromURL();
    const $af   = $('#active-filters');
    const parts = [];
    if (status)       parts.push(`Status: ${status}`);
    if (category)     parts.push(`Category: ${category}`);
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
        if (push) {
          history.pushState(null, '', '/ticket?' + $.param(opts));
        }
        renderActiveFilters();
      })
      .always(() => setLoading(false));
  }
  // Optional global if you ever need it elsewhere
  window.loadTicketTable = loadTable;

  function toastOk(msg) {
    if (!window.Swal) return;
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: msg || 'Updated',
      timer: 1500,
      showConfirmButton: false
    });
  }

  // POST status as Promise (for Swal preConfirm)
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

  /* ---------------- initial load ---------------- */
  function initialLoadIfNeeded() {
    // Only run on ticket page
    if (!document.getElementById('ticketContainer')) return;

    const hasTable = !!document.querySelector('#ticket-table table');
    const params   = readFiltersFromURL();

    if (!hasTable) {
      // First time (partial) – load via AJAX but don't push a new state
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
    loadTable({
      sort_option: $(this).val(),
      page:        1,
      status:      f.status,
      category:    f.category,
      violation_id:f.violation_id
    });
  });

  $(document).on('click', '#ticket-table .pagination a', function (e) {
    e.preventDefault();
    const page = new URL(this.href).searchParams.get('page') || 1;
    const f    = readFiltersFromURL();

    loadTable({
      sort_option: getSort(),
      page,
      status:      f.status,
      category:    f.category,
      violation_id:f.violation_id
    });
  });

  /* ---------------- status change (SweetAlert) ---------------- */
  let _lastSelect = null;
  let _lastValue  = null;

  function revertSelect() {
    if (_lastSelect && _lastValue != null) _lastSelect.val(_lastValue);
    _lastSelect = null; 
    _lastValue  = null;
  }

  async function promptReference(ticketId) {
    if (!window.Swal) return false;
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
          await postStatusPromise(ticketId, { 
            status_id:         cfg().paidStatusId, 
            reference_number:  refNo 
          });
        } catch (errMsg) {
          Swal.showValidationMessage(errMsg);
          return false;
        }
        return true;
      },
      allowOutsideClick: () => !Swal.isLoading()
    });
    return isConfirmed;
  }

  async function promptPassword(ticketId, newStatus) {
    if (!window.Swal) return false;
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
          await postStatusPromise(ticketId, { 
            status_id:      newStatus, 
            admin_password: pw 
          });
        } catch (errMsg) {
          Swal.showValidationMessage(errMsg);
          return false;
        }
        return true;
      },
      allowOutsideClick: () => !Swal.isLoading()
    });
    return isConfirmed;
  }

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
      if (newStatus === paidId) ok = await promptReference(ticketId);
      else                      ok = await promptPassword(ticketId, newStatus);

      if (!ok) { 
        revertSelect(); 
        return; 
      }

      toastOk('Status updated');

      const f = readFiltersFromURL();
      loadTable({
        sort_option: getSort(),
        page:        f.page,
        status:      f.status,
        category:    f.category,
        violation_id:f.violation_id
      }, /*push=*/false);

      _lastSelect = null; 
      _lastValue  = null;
    } catch (err) {
      if (window.Swal) Swal.fire('Error', String(err), 'error');
      revertSelect();
    }
  });

  /* ---------------- Filter dialog via SweetAlert2 (NO Bootstrap) ---------------- */
  function filterDialogHTML(C, current) {
    const catOptions = ['<option value="">All</option>']
      .concat((C.categories || []).map(cat => {
        const sel = (cat === current.category) ? ' selected' : '';
        return `<option value="${String(cat)}"${sel}>${String(cat)}</option>`;
      })).join('');

    return `
      <div class="text-start">
        <div class="mb-3">
          <label class="form-label fw-semibold">Status</label>
          <select id="sw-status" class="form-select">
            <option value="" ${current.status===''?'selected':''}>All</option>
            <option value="paid" ${current.status==='paid'?'selected':''}>Paid</option>
            <option value="unpaid" ${current.status==='unpaid'?'selected':''}>Unpaid</option>
            <option value="pending" ${current.status==='pending'?'selected':''}>Pending</option>
            <option value="cancelled" ${current.status==='cancelled'?'selected':''}>Cancelled</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Violation Category</label>
          <select id="sw-category" class="form-select">
            ${catOptions}
          </select>
        </div>

        <div class="mb-1">
          <label class="form-label fw-semibold">Violation</label>
          <select id="sw-violation" class="form-select">
            <option value="">All</option>
          </select>
          <div class="form-text">Choose a category first to load specific violations.</div>
        </div>
      </div>
    `;
  }

  async function openFilterDialog() {
    const C       = cfg();
    const current = readFiltersFromURL();

    const { isConfirmed } = await Swal.fire({
      title: 'Filter Tickets',
      html:  filterDialogHTML(C, current),
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Apply',
      width: 600,
      willOpen: () => {
        const $v = $('#sw-violation');
        $v.prop('disabled', true).html('<option value="">All</option>');
      },
      didOpen: () => {
        // Bind change for category → fetch violations
        $('#sw-category').off('change').on('change', function () {
          const cat = $(this).val();
          const $v  = $('#sw-violation');
          $v.prop('disabled', true).html('<option value="">All</option>');

          if (!cat || !C.violationsByCatUrl) return;

          $.get(C.violationsByCatUrl, { category: cat }).done(items => {
            items.forEach(it => {
              $('#sw-violation').append(
                `<option value="${it.id}">${it.violation_name} (${it.violation_code})</option>`
              );
            });
            $v.prop('disabled', false);
          });
        });

        // If there is an existing category, trigger fetch and preselect violation
        if (current.category) {
          $('#sw-category').trigger('change');
          const want = String(current.violation_id || '');
          if (want) {
            setTimeout(() => $('#sw-violation').val(want), 200);
          }
        }
      },
      preConfirm: () => {
        const filters = {
          status:       $('#sw-status').val()    || '',
          category:     $('#sw-category').val()  || '',
          violation_id: $('#sw-violation').val() || ''
        };
        // keep current sort; reset to page 1
        loadTable(Object.assign({ 
          page: 1, 
          sort_option: getSort() 
        }, filters), true);
      }
    });

    return isConfirmed;
  }

  // Open dialog when clicking Filter button
  $(document).on('click', '#btn-filter, #btn-ticket-filters', function (e) {
    e.preventDefault();
    openFilterDialog();
  });

  // Reset filters
  $(document).on('click', '#btn-reset-filters', function (e) {
    e.preventDefault();
    loadTable({ 
      sort_option: getSort(), 
      page:        1, 
      status:      '', 
      category:    '', 
      violation_id:'' 
    }, true);
  });

  /* ---------------- hook polling into SPA lifecycle ---------------- */
  // Start/stop polling when document loads or SPA swaps page
  $(document).on('DOMContentLoaded page:loaded', startPolling);

})(jQuery);
