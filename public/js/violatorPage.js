/* public/js/violator.js — SPA table + SweetAlert details + Paid/password prompts */
(function ($) {
  if (window.__violatorBound) return;
  window.__violatorBound = true;

  // ---------- helpers ----------
  const csrf = $('meta[name="csrf-token"]').attr('content') || '';

  function $page() { return document.getElementById('violatorPage'); }
  function cfg() {
    const p = $page();
    return {
      partialUrl:  p?.dataset.partialUrl || '/violatorTable/partial',
      statusUrl:   p?.dataset.statusUrl  || '/paid',
      paidId:      Number(p?.dataset.paidId || 0),
    };
  }

  function getParams() {
    const url = new URL(location.href);
    const q = {
      sort_option:  $('#sort_table').val()   ?? url.searchParams.get('sort_option')  ?? 'date_desc',
      vehicle_type: $('#vehicle_type').val() ?? url.searchParams.get('vehicle_type') ?? 'all',
      search:       $('#search_input').val() ?? url.searchParams.get('search')       ?? '',
      page:         url.searchParams.get('page') || '1'
    };
    return q;
  }

  function pushUrl(q) {
    const p = new URLSearchParams();
    if (q.sort_option && q.sort_option !== 'date_desc') p.set('sort_option', q.sort_option);
    if (q.vehicle_type && q.vehicle_type !== 'all')     p.set('vehicle_type', q.vehicle_type);
    if ((q.search || '').trim() !== '')                 p.set('search', q.search.trim());
    if (q.page && q.page !== '1')                       p.set('page', q.page);
    history.pushState({}, '', location.pathname + '?' + p.toString());
  }

  function setLoading(on) {
    const wrap = document.getElementById('violatorContainer');
    if (!wrap) return;
    wrap.classList.toggle('is-loading', !!on);
    if (on) {
      wrap.style.position = 'relative';
      wrap.insertAdjacentHTML('beforeend',
        '<div class="ajax-overlay" data-vtr-loader style="position:absolute;inset:0;background:rgba(255,255,255,.6);display:flex;align-items:center;justify-content:center;"><div class="spinner-border" role="status"></div></div>');
    } else {
      wrap.querySelector('[data-vtr-loader]')?.remove();
    }
  }

  function injectTable(html, newUrlForHistory) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const partial = doc.querySelector('#vtr-table-wrap');
    const wrap = document.getElementById('violatorContainer');
    if (!wrap) return;
    wrap.innerHTML = partial ? partial.outerHTML : html;
    if (newUrlForHistory) pushUrl(newUrlForHistory);
    // Scroll to top of table after navigation
    wrap.scrollIntoView({ behavior: 'instant', block: 'start' });
  }

  function loadPage(page = '1', push = true) {
    const c = cfg();
    const q = getParams();
    q.page = page;

    setLoading(true);
    $.ajax({
      url: c.partialUrl,
      data: q,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .done(html => injectTable(html, push ? q : null))
    .always(() => setLoading(false));
  }

  function toastOk(msg) {
    if (!window.Swal) return;
    Swal.fire({ toast:true, position:'top-end', icon:'success', title: msg || 'Done', timer:1500, showConfirmButton:false });
  }

  // ---------- Status update helpers (Paid & password) ----------
  function postStatus(ticketId, data) {
    const c = cfg();
    return $.ajax({
      url: `${c.statusUrl}/${ticketId}/status`,
      method: 'POST',
      data: Object.assign({ _token: csrf }, data),
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
  }

  async function promptReference(ticketId) {
    const { value, isConfirmed } = await Swal.fire({
      title: 'Enter Reference Number',
      input: 'text',
      inputLabel: 'Reference # (required for Paid)',
      inputPlaceholder: 'e.g., OR-123456',
      showCancelButton: true,
      confirmButtonText: 'Submit',
      showLoaderOnConfirm: true,
      preConfirm: async (ref) => {
        ref = (ref || '').trim();
        if (!ref) { Swal.showValidationMessage('Reference number is required.'); return false; }
        try {
          await postStatus(ticketId, { status_id: cfg().paidId, reference_number: ref });
        } catch (xhr) {
          const msg = xhr?.responseJSON?.message || 'Could not mark as Paid.';
          Swal.showValidationMessage(msg); return false;
        }
        return true;
      },
      allowOutsideClick: () => !Swal.isLoading()
    });
    return isConfirmed;
  }

  async function promptPassword(ticketId, newStatus) {
    const { value, isConfirmed } = await Swal.fire({
      title: 'Admin Password Required',
      input: 'password',
      inputLabel: 'Enter admin password to confirm',
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      showLoaderOnConfirm: true,
      preConfirm: async (pw) => {
        pw = (pw || '').trim();
        if (!pw) { Swal.showValidationMessage('Password is required.'); return false; }
        try {
          await postStatus(ticketId, { status_id: newStatus, admin_password: pw });
        } catch (xhr) {
          const msg = xhr?.responseJSON?.message || 'Password check failed.';
          Swal.showValidationMessage(msg); return false;
        }
        return true;
      },
      allowOutsideClick: () => !Swal.isLoading()
    });
    return isConfirmed;
  }

  // ---------- SweetAlert details (history) ----------
  let _detailUrl = null;

  function openDetails(url) {
    _detailUrl = url;
    Swal.fire({
      title: 'Violator Details',
      html: '<div class="p-3" id="violatorDetails">Loading…</div>',
      width: '70rem',
      showCloseButton: true,
      showConfirmButton: false,
      didOpen: () => {
        $('#violatorDetails').load(url, function () {
          // ensure any table inside is scrollable
          const box = this.closest('.swal2-html-container');
          if (box) box.style.maxHeight = '70vh';
        });
      }
    });
  }

  function reloadDetailsIfOpen() {
    if (!_detailUrl) return;
    const host = document.getElementById('violatorDetails');
    if (!host) return;
    $('#violatorDetails').load(_detailUrl);
  }

  // ---------- INIT (hard load + SPA) ----------
  function init() {
    if (!$('#violatorContainer').length) return;

    // If we landed via SPA and server didn’t render the table yet, fetch it
    if (!$('#vtr-table-wrap').length) {
      const url = new URL(location.href);
      const q = {
        sort_option:  url.searchParams.get('sort_option')  || $('#sort_table').val()   || 'date_desc',
        vehicle_type: url.searchParams.get('vehicle_type') || $('#vehicle_type').val() || 'all',
        search:       url.searchParams.get('search')       || $('#search_input').val() || '',
        page:         url.searchParams.get('page')         || '1'
      };
      $('#sort_table').val(q.sort_option);
      $('#vehicle_type').val(q.vehicle_type);
      $('#search_input').val(q.search);
      loadPage(q.page, /*push=*/false);
    }
  }
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('page:loaded', init);

  // ---------- Filters ----------
  $(document).on('click', '#search_btn', () => loadPage('1'));
  $(document).on('keydown', '#search_input', (e) => { if (e.key === 'Enter') { e.preventDefault(); loadPage('1'); }});
  $(document).on('change', '#sort_table',   () => loadPage('1'));
  $(document).on('change', '#vehicle_type', () => loadPage('1'));

  // ---------- Pagination ----------
  $(document).on('click', '#violatorContainer .pagination a', function (e) {
    e.preventDefault();
    const newPage = new URL(this.href).searchParams.get('page') || '1';
    loadPage(newPage);
  });

  // ---------- SPA back/forward ----------
  window.addEventListener('popstate', () => {
    if (!$('#violatorContainer').length) return;
    const url = new URL(location.href);
    $('#sort_table').val(url.searchParams.get('sort_option')  || 'date_desc');
    $('#vehicle_type').val(url.searchParams.get('vehicle_type') || 'all');
    $('#search_input').val(url.searchParams.get('search') || '');
    loadPage(url.searchParams.get('page') || '1', /*push=*/false);
  });

  // ---------- View More (history) via SweetAlert ----------
  $(document).on('click', '.view-tickets-btn', function (e) {
    e.preventDefault();
    const url = this.getAttribute('data-url');
    if (!url) return;
    openDetails(url);
  });

  // ---------- Status select inside the SweetAlert details ----------
  $(document).on('change', '.swal2-container .status-select', async function () {
    const $sel = $(this);
    const ticketId  = Number($sel.data('ticket-id'));
    const oldStatus = Number($sel.data('current-status-id'));
    const newStatus = Number($sel.val());
    const paidId    = cfg().paidId;

    if (!ticketId) return;

    // revert helper
    const revert = () => $sel.val(oldStatus);

    try {
      let ok = false;
      if (newStatus === paidId) {
        ok = await promptReference(ticketId);
      } else if (oldStatus === paidId && newStatus !== paidId) {
        ok = await promptPassword(ticketId, newStatus);
      } else {
        await postStatus(ticketId, { status_id: newStatus });
        ok = true;
      }

      if (!ok) { revert(); return; }

      toastOk('Status updated');
      reloadDetailsIfOpen();
    } catch (err) {
      console.error(err);
      Swal.fire('Error', String(err?.responseJSON?.message || err?.message || err || 'Update failed'), 'error');
      revert();
    }
  });

})(jQuery);
