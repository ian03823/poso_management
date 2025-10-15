/* public/js/enforcer.js — SPA table (filters/pagination) + SweetAlert edit + activate/inactivate */
console.log("Loaded enforcer.js");
(function () {
  if (window.__enforcerBound) return;
  window.__enforcerBound = true;

  // ---------- helpers ----------
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const abs = (u) => { try { return new URL(u, location.origin).href; } catch { return u; } };

  function root() { return document.getElementById('enforcerContainer'); }
  function partialUrl() { return root()?.dataset.partialUrl || '/enforcer/partial'; }

  function setLoading(on) {
    const ov = document.getElementById('enfLoading');
    if (ov) { ov.style.display = on ? 'flex' : 'none'; return; }
    $('#table-container')?.classList.toggle('is-loading', !!on);
  }

  // ---- URL state
  function getParams() {
    const p = new URLSearchParams(location.search);
    const show   = $('#show_filter')?.value || p.get('show') || 'active';
    const sort   = $('#sort_table')?.value || p.get('sort_option') || 'date_desc';
    const search = $('#search_input')?.value ?? p.get('search') ?? '';
    const page   = p.get('page') || '1';
    return { show, sort, search, page };
  }
  function pushUrl({ show, sort, search, page }) {
    const p = new URLSearchParams();
    if (show && show !== 'active') p.set('show', show);
    if (sort && sort !== 'date_desc') p.set('sort_option', sort);
    if (search) p.set('search', search);
    if (page && page !== '1') p.set('page', page);
    history.pushState(null, '', `${location.pathname}?${p}`);
  }

  // ---- rendering
  function inject(html, pushTo) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const inner = doc.querySelector('#table-container')?.innerHTML || html;
    const wrap = $('#table-container');
    if (wrap) wrap.innerHTML = inner;
    if (pushTo) pushUrl(pushTo);
  }

  // ---- AJAX load
  function loadPage(page = '1', push = true) {
    const { show, sort, search } = getParams();
    const q = new URLSearchParams({ show, sort_option: sort, search, page });

    setLoading(true);
    fetch(`${partialUrl()}?${q.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.text())
      .then(html => inject(html, push ? { show, sort, search, page } : null))
      .catch(console.error)
      .finally(() => setLoading(false));
  }

  // Debounce helper for responsive typing
  let typingTimer;
  const debounce = (fn, ms = 250) => (...args) => { clearTimeout(typingTimer); typingTimer = setTimeout(() => fn(...args), ms); };

  // ---- init on hard load and SPA nav
  function initPage() {
    if (!root()) return;
    const p = getParams();
    if ($('#show_filter')) $('#show_filter').value = p.show;
    if ($('#sort_table')) $('#sort_table').value = p.sort;
    if ($('#search_input')) $('#search_input').value = p.search;

    if (!$('#table-container table')) loadPage(p.page, /*push=*/false);
  }
  document.addEventListener('DOMContentLoaded', initPage);
  document.addEventListener('page:loaded', initPage);
  window.addEventListener('popstate', () => { if (root()) loadPage(getParams().page, /*push=*/false); });

  // ---------- filters
  document.addEventListener('change', (e) => {
    if (!root()) return;
    if (e.target.matches('#enforcerContainer #show_filter'))  { loadPage('1'); }
    if (e.target.matches('#enforcerContainer #sort_table'))   { loadPage('1'); }
  });
  document.addEventListener('click', (e) => {
    if (!root()) return;
    if (e.target.closest('#enforcerContainer #search_btn')) { e.preventDefault(); loadPage('1'); }
  });
  document.addEventListener('input', debounce((e) => {
    if (!root()) return;
    if (e.target && e.target.matches('#enforcerContainer #search_input')) loadPage('1');
  }, 280));

  // ---------- pagination
  document.addEventListener('click', (e) => {
    if (!root()) return;
    const a = e.target.closest('#enforcerContainer .pagination a, #table-container .pagination a');
    if (!a) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const page = new URL(a.href, location.origin).searchParams.get('page') || '1';
    loadPage(page);
  });

  // =========================================
  // EDIT via SweetAlert
  // =========================================
  function toastOk(msg) {
    if (!window.Swal) return;
    Swal.fire({ toast:true, position:'top-end', icon:'success', title: msg || 'Saved', timer:1500, showConfirmButton:false });
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#enforcerContainer .editBtn');
    if (!btn) return;
    e.preventDefault();

    const init = {
      url:          abs(btn.getAttribute('data-url')),
      badge_num:    btn.getAttribute('data-badge') || '',
      fname:        btn.getAttribute('data-fname') || '',
      mname:        btn.getAttribute('data-mname') || '',
      lname:        btn.getAttribute('data-lname') || '',
      phone:        btn.getAttribute('data-phone') || '',
      ticket_start: btn.getAttribute('data-ticket-start') || '',
      ticket_end:   btn.getAttribute('data-ticket-end') || '',
    };

    const html = `
      <div class="text-start">
        <div class="mb-2">
          <label class="form-label">Badge #</label>
          <input id="sw_badge" class="form-control" maxlength="5" value="${init.badge_num}" disabled>
        </div>
        <div class="row g-2">
          <div class="col-md-4 mb-2">
            <label class="form-label">First Name</label>
            <input id="sw_fname" class="form-control" value="${init.fname}">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">Middle Name</label>
            <input id="sw_mname" class="form-control" value="${init.mname}">
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">Last Name</label>
            <input id="sw_lname" class="form-control" value="${init.lname}">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Phone (11 digits)</label>
          <input id="sw_phone" class="form-control" maxlength="11" value="${init.phone}">
        </div>
        <div class="row g-2">
          <div class="col-md-6 mb-2">
            <label class="form-label">Ticket Start</label>
            <input id="sw_tstart" class="form-control" value="${init.ticket_start}" disabled>
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label">Ticket End</label>
            <input id="sw_tend" class="form-control" value="${init.ticket_end}" disabled>
          </div>
        </div>
        <div class="mb-1">
          <label class="form-label">Reset Password (optional)</label>
          <div class="input-group">
            <input id="sw_pwd" class="form-control" placeholder="Leave blank to keep current">
            <button class="btn btn-outline-secondary" type="button" id="sw_gen">Generate</button>
          </div>
        </div>
      </div>
    `;

    Swal.fire({
      title: 'Edit Enforcer',
      html,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Save',
      didOpen: () => {
        const $ = (sel, root=document) => root.querySelector(sel);
        $('#sw_gen')?.addEventListener('click', () => {
          const pwd = `posoenforcer_${genRotatingTail(3)}`;
          $('#sw_pwd').value = pwd;
          $('#sw_pwd').focus();
          const end = pwd.length;
          $('#sw_pwd').setSelectionRange(end, end);
        });
      },
      preConfirm: async () => {
        const badge = $('#sw_badge').value.trim();
        const fname = $('#sw_fname').value.trim();
        const mname = $('#sw_mname').value.trim();
        const lname = $('#sw_lname').value.trim();
        const phone = $('#sw_phone').value.trim();
        const tstart= $('#sw_tstart').value.trim();
        const tend  = $('#sw_tend').value.trim();
        const pwd   = $('#sw_pwd').value.trim();

        if (!fname || !lname) { Swal.showValidationMessage('First and Last name are required'); return false; }
        if (phone && !/^\d{11}$/.test(phone)) { Swal.showValidationMessage('Phone must be 11 digits'); return false; }

        const fd = new FormData();
        fd.set('_method','PUT');
        if (badge)  fd.set('badge_num', badge);
        if (fname)  fd.set('fname', fname);
        if (mname)  fd.set('mname', mname);
        if (lname)  fd.set('lname', lname);
        if (phone)  fd.set('phone', phone);
        if (tstart) fd.set('ticket_start', tstart);
        if (tend)   fd.set('ticket_end', tend);
        if (pwd)    fd.set('password', pwd);

        try {
          const res = await fetch(init.url, {
            method: 'POST',
            headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            body: fd
          });
          if (!res.ok) {
            let msg = 'Update failed';
            try { const j = await res.json(); msg = j.message || msg; } catch { msg = await res.text() || msg; }
            throw new Error(msg);
          }
          return await res.json().catch(()=> ({}));
        } catch (err) {
          Swal.showValidationMessage(String(err.message || err));
          return false;
        }
      },
      allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
      if (!result.isConfirmed) return;
      toastOk('Updated');
      const { page } = getParams();
      loadPage(page);
      if (result.value?.raw_password) {
        Swal.fire({ icon:'info', title:'Password Reset', html:`<div class="text-start"><strong>New Password:</strong> ${result.value.raw_password}</div>` });
      }
    });
  });

  // =========================================
  // ADD ENFORCER
  // =========================================

  // (A) Password generator for the Add Enforcer form — format: posoenforcer_XXX
  function genRotatingTail(len = 3) {
    // 3 digits (0-9) each time you click => keeps “rotating”
    if (window.crypto?.getRandomValues) {
      const buf = new Uint32Array(len);
      crypto.getRandomValues(buf);
      return Array.from(buf, n => (n % 10)).join('');
    }
    // fallback
    let s = '';
    for (let i=0;i<len;i++) s += Math.floor(Math.random()*10);
    return s;
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#btnGenPwd');
    if (!btn) return;
    const pwdInput = $('#password') || document.getElementById('password');
    if (!pwdInput) return;
    const pwd = `posoenforcer_${genRotatingTail(3)}`;
    pwdInput.value = pwd;
    // Optional: place cursor at end & highlight for easy copy
    pwdInput.focus();
    pwdInput.setSelectionRange(pwd.length, pwd.length);
  });

  // (B) Submit handler
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('#addEnforcerForm');
    if (!form) return;
    e.preventDefault();

    const fd = new FormData(form);

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
        body: fd
      });

      if (res.status === 422) {
        let msg = 'Please check your inputs.';
        try { const j = await res.json(); msg = j.errors ? Object.values(j.errors).flat().join('\n') : (j.message || msg); } catch {}
        await Swal.fire({ icon:'error', title:'Validation error', text: msg });
        return;
      }
      if (!res.ok) throw new Error((await res.text().catch(()=>'')) || 'Request failed.');

      const payload = await res.json().catch(()=> ({}));

      const ask = await Swal.fire({
        icon: 'success',
        title: 'Enforcer added',
        html: payload.raw_password
          ? `<div class="text-start"><strong>Temporary Password:</strong> ${payload.raw_password}</div>
             <div class="mt-2">Add another?</div>`
          : `Add another?`,
        showCancelButton: true,
        confirmButtonText: 'Add another',
        cancelButtonText: 'Go to list',
        reverseButtons: true
      });

      if (ask.isConfirmed) {
        // Reset the form and refresh server-suggested fields (badge/ticket range)
        form.reset();
        try {
          const html = await fetch('/enforcer/create', { headers:{ 'X-Requested-With':'XMLHttpRequest' } }).then(r => r.text());
          const doc = new DOMParser().parseFromString(html, 'text/html');
          const fresh = doc.querySelector('#addEnforcerForm');
          if (fresh) form.outerHTML = fresh.outerHTML;
        } catch {}
      } else {
        // ✅ Ensure it actually goes to the list page (hard navigation)
        window.location.href = '/enforcer';
      }
    } catch (err) {
      console.error(err);
      await Swal.fire({ icon:'error', title:'Error', text: err.message || String(err) });
    }
  });

  // =========================================
  // Activate / Inactivate
  // =========================================
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('#enforcerContainer .status-btn');
    if (!btn) return;
    e.preventDefault();

    const action = abs(btn.getAttribute('data-action'));
    const method = (btn.getAttribute('data-method') || 'POST').toUpperCase();

    const { isConfirmed, value: adminPwd } = await Swal.fire({
      title: method === 'DELETE' ? 'Confirm Inactivate' : 'Confirm Activate',
      text: 'Enter admin password to proceed.',
      input: 'password',
      inputAttributes: { autocapitalize:'off', autocomplete:'current-password', maxlength:128 },
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      cancelButtonText: 'Cancel',
      preConfirm: (val) => { if (!val) { Swal.showValidationMessage('Admin password is required'); return false; } return val; }
    });
    if (!isConfirmed) return;

    const fd = new FormData();
    if (method !== 'POST') fd.set('_method', method);
    fd.set('admin_password', adminPwd);

    setLoading(true);
    try {
      const res = await fetch(action, {
        method: 'POST',
        headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
        body: fd
      });
      if (!res.ok) {
        let msg = 'Action failed.';
        try { const j = await res.json(); msg = j.message || msg; } catch { msg = await res.text() || msg; }
        await Swal.fire({ icon:'error', title:'Error', text: msg });
        return;
      }
      await Swal.fire({ icon:'success', title: method === 'DELETE' ? 'Enforcer Inactivated' : 'Enforcer Activated', timer:1200, showConfirmButton:false });
      const { page } = getParams();
      loadPage(page);
    } catch (err) {
      console.error(err);
      await Swal.fire({ icon:'error', title:'Network Error', text:'Please try again.' });
    } finally {
      setLoading(false);
    }
  });

})();
