/* public/js/violatorTable.js  —  Violator List (SPA) + Modal (history) + Status updates
   Requirements:
   - jQuery, Bootstrap 5, SweetAlert2 are loaded.
   - Blade page container: #violatorPage with data-*:
       data-partial-url   (e.g. route('violatorTable.partial'))
       data-status-url    (base, e.g. '/paid' — controller expects POST /paid/{ticket}/status)
       data-paid-id, data-pending-id, data-unpaid-id, data-cancelled-id
   - List container: #violatorContainer (server returns #vtr-table-wrap inside)
   - Controls: #sort_table, #search_input, #search_btn
   - Modal: #violatorModal with body #violatorModalBody
*/
(function ($) {
  if (window.__violatorBound) return; // prevent double-bind on SPA swaps
  window.__violatorBound = true;

  /* --------------------------
   * Utilities
   * ------------------------ */
  function cfg() {
    const p = document.getElementById('violatorPage');
    return {
      partialUrl:   p?.dataset.partialUrl || '/violatorTable/partial',
      statusUrl:    p?.dataset.statusUrl  || '/paid',
      paidId:       Number(p?.dataset.paidId || 0),
      pendingId:    Number(p?.dataset.pendingId || 0),
      unpaidId:     Number(p?.dataset.unpaidId || 0),
      cancelledId:  Number(p?.dataset.cancelledId || 0),
      csrf:         document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    };
  }

  function debounce(fn, wait = 300) {
    let t, last;
    return function debounced(ev) {
      clearTimeout(t);
      const v = ev && ev.target ? ev.target.value : undefined;
      t = setTimeout(() => {
        if (v !== undefined && v === last) return;
        last = v;
        fn.call(this, ev);
      }, wait);
    };
  }

  function killBackdrops() {
    document.querySelectorAll('.modal-backdrop').forEach(bd => bd.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
  }

  /* --------------------------
   * Param helpers (no vehicle_type)
   * ------------------------ */
  function getParamsFromUI() {
    return {
      sort_option: $('#sort_table').val() || 'date_desc',
      search:      ($('#search_input').val() || '').trim(),
    };
  }
  function getParamsFromURL() {
    const u = new URL(location.href);
    return {
      sort_option: u.searchParams.get('sort_option') || 'date_desc',
      search:      u.searchParams.get('search')      || '',
      page:        u.searchParams.get('page')        || '1',
    };
  }
  function pushUrl(q) {
    const p = new URLSearchParams();
    if (q.sort_option !== 'date_desc') p.set('sort_option', q.sort_option);
    if ((q.search || '').trim() !== '') p.set('search', q.search.trim());
    if (q.page && q.page !== '1') p.set('page', q.page);
    const qs = p.toString();
    history.pushState({}, '', location.pathname + (qs ? '?' + qs : ''));
  }

  /* --------------------------
   * AJAX load & inject
   * ------------------------ */
  function setLoading(on) {
    const wrap = document.getElementById('violatorContainer');
    if (!wrap) return;
    wrap.classList.toggle('is-loading', !!on);
    if (on) {
      wrap.style.position = 'relative';
      wrap.insertAdjacentHTML(
        'beforeend',
        '<div class="ajax-overlay" data-vtr-loader style="position:absolute;inset:0;background:rgba(255,255,255,.6);display:flex;align-items:center;justify-content:center;z-index:9;"><div class="spinner-border" role="status" aria-hidden="true"></div></div>'
      );
    } else {
      wrap.querySelector('[data-vtr-loader]')?.remove();
    }
  }

  function injectTable(html, newUrlParams) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const partial = doc.querySelector('#vtr-table-wrap');
    const wrap = document.getElementById('violatorContainer');
    if (wrap) wrap.innerHTML = partial ? partial.outerHTML : html;
    if (newUrlParams) pushUrl(newUrlParams);
    killBackdrops(); // SPA safety
  }

  function loadPage(page = '1', push = true) {
    const c = cfg();
    const base = getParamsFromUI();
    const q = Object.assign({}, base, { page });
    setLoading(true);
    $.ajax({
      url: c.partialUrl,
      data: q,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .done(html => injectTable(html, push ? q : null))
      .fail(() => {
        const wrap = document.getElementById('violatorContainer');
        if (wrap) wrap.innerHTML = '<div class="alert alert-danger">Failed to load list.</div>';
      })
      .always(() => setLoading(false));
  }

  /* --------------------------
   * Init (hard load + SPA swap)
   * ------------------------ */
  function initList() {
    const root = document.getElementById('violatorPage');
    if (!root) return;

    // If we navigated via SPA and the table isn't present yet, sync UI from URL then load.
    if (!document.getElementById('vtr-table-wrap')) {
      const s = getParamsFromURL();
      $('#sort_table').val(s.sort_option);
      $('#search_input').val(s.search);
      loadPage(s.page, /* push= */ false);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initList);
  } else { initList(); }
  document.addEventListener('page:loaded', initList);

  /* --------------------------
   * Toolbar events
   * ------------------------ */
  $(document).on('click',  '#search_btn',   () => loadPage('1'));
  $(document).on('change', '#sort_table',   () => loadPage('1'));

  // live search (debounced), Enter submits immediately
  $(document).on('input',   '#search_input', debounce(() => loadPage('1'), 300));
  $(document).on('keydown', '#search_input', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); loadPage('1'); }
  });

  /* --------------------------
   * Pagination (AJAX)
   * ------------------------ */
  $(document).on('click', '#violatorContainer .pagination a', function (e) {
    e.preventDefault();
    const newPage = new URL(this.href).searchParams.get('page') || '1';
    loadPage(newPage);
  });

  /* --------------------------
   * Browser back/forward
   * ------------------------ */
  window.addEventListener('popstate', () => {
    const root = document.getElementById('violatorPage');
    if (!root) return;
    const s = getParamsFromURL();
    $('#sort_table').val(s.sort_option);
    $('#search_input').val(s.search);
    loadPage(s.page, /* push= */ false);
  });

  /* --------------------------
   * History modal (Bootstrap)
   * ------------------------ */
  let currentDetailUrl = null;
  window.currentDetailUrl = null; // also expose globally for internal refresh

  function openHistory(url) {
    currentDetailUrl = url;
    window.currentDetailUrl = url;

    const modalEl = document.getElementById('violatorModal');
    // Move under body to avoid stacking-context issues in nested containers
    if (modalEl.parentElement !== document.body) document.body.appendChild(modalEl);

    killBackdrops();
    $('#violatorModalBody').html('<div class="py-5 text-center"><div class="spinner-border" role="status"></div></div>');

    const modal = new bootstrap.Modal(modalEl, { backdrop: true, focus: true });
    modal.show();

    $.get(url, (html) => {
      $('#violatorModalBody').html(html);
      // ensure only a single backdrop
      const backs = Array.from(document.querySelectorAll('.modal-backdrop'));
      backs.slice(0, backs.length - 1).forEach(b => b.remove());
      modalEl.style.zIndex = '2000';
      backs.at(-1) && (backs.at(-1).style.zIndex = '1990');
    });
  }

  $(document).on('click', '.view-tickets-btn', function (e) {
    e.preventDefault();
    const url = this.getAttribute('data-url');
    if (url) openHistory(url);
  });

  document.addEventListener('hidden.bs.modal', (ev) => {
    if (ev.target.id !== 'violatorModal') return;
    $('#violatorModalBody').empty();
    killBackdrops();
  });

  /* --------------------------
   * Status changes (inside modal) with SweetAlert guards
   * ------------------------ */
  function postStatus(ticketId, data) {
    const c = cfg();
    return $.ajax({
      url: `${c.statusUrl}/${ticketId}/status`,
      method: 'POST',
      data: Object.assign({ _token: c.csrf }, data),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
  }

  async function askReferenceForLeavingPaid(ticketId) {
    const target = document.getElementById('violatorModal'); // render inside modal
    const { isConfirmed } = await Swal.fire({
      target,
      title: 'Reference Number',
      input: 'text',
      inputLabel: 'Enter 8-character reference #',
      inputPlaceholder: 'e.g., ABCD1234',
      inputAttributes: { maxlength: 8, minlength: 8, autocapitalize: 'off', autocorrect: 'off', autocomplete: 'off' },
      allowOutsideClick: false,
      allowEscapeKey: false,
      showCancelButton: true,
      confirmButtonText: 'Submit',
      showLoaderOnConfirm: true,
      preConfirm: async (ref) => {
        ref = (ref || '').trim();
        if (ref.length !== 8) { Swal.showValidationMessage('Reference must be exactly 8 characters.'); return false; }
        try {
          await postStatus(ticketId, { status_id: 'LEAVING_PAID', reference_number: ref });
        } catch (xhr) {
          Swal.showValidationMessage(xhr?.responseJSON?.message || 'Could not update status.');
          return false;
        }
        return true;
      }
    });
    return isConfirmed;
  }

  async function askAdminPassword(ticketId, newStatus) {
    const target = document.getElementById('violatorModal');
    const { isConfirmed } = await Swal.fire({
      target,
      title: 'Admin Password Required',
      input: 'password',
      inputLabel: 'Enter admin password to confirm',
      inputAttributes: { autocapitalize: 'off', autocomplete: 'current-password', maxlength: 128 },
      allowOutsideClick: false,
      allowEscapeKey: false,
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      showLoaderOnConfirm: true,
      preConfirm: async (pw) => {
        pw = (pw || '').trim();
        if (!pw) { Swal.showValidationMessage('Password is required.'); return false; }
        try {
          await postStatus(ticketId, { status_id: newStatus, admin_password: pw });
        } catch (xhr) {
          Swal.showValidationMessage(xhr?.responseJSON?.message || 'Password check failed.');
          return false;
        }
        return true;
      }
    });
    return isConfirmed;
  }

  let _changing = false;
  $(document).on('change', '#violatorModal .status-select', async function () {
    if (_changing) return;
    _changing = true;

    const c = cfg();
    const $sel      = $(this);
    const ticketId  = Number($sel.data('ticket-id'));
    const oldStatus = Number($sel.data('current-status-id'));
    const newStatus = Number($sel.val());

    const revert = () => $sel.val(oldStatus);

    try {
      let ok = false;

      // Rule 1: PAID -> (anything else) requires reference #
      if (oldStatus === c.paidId && newStatus !== c.paidId) {
        ok = await askReferenceForLeavingPaid(ticketId);
      }
      // Rule 2: PENDING -> (UNPAID or CANCELLED) requires admin password
      else if (oldStatus === c.pendingId && (newStatus === c.unpaidId || newStatus === c.cancelledId)) {
        ok = await askAdminPassword(ticketId, newStatus);
      }
      // Otherwise, straightforward update
      else {
        await postStatus(ticketId, { status_id: newStatus });
        ok = true;
      }

      if (!ok) { revert(); return; }

      // Success: mark new value and refresh modal body
      $sel.attr('data-current-status-id', newStatus);
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Status updated', timer: 1200, showConfirmButton: false });

      if (window.currentDetailUrl) {
        $('#violatorModalBody').html('<div class="py-5 text-center"><div class="spinner-border" role="status"></div></div>');
        $.get(window.currentDetailUrl, html => $('#violatorModalBody').html(html));
      }
    } catch (err) {
      revert();
      Swal.fire('Error', String(err?.responseJSON?.message || err?.message || err || 'Update failed'), 'error');
    } finally {
      _changing = false;
    }
  });

})(jQuery);
