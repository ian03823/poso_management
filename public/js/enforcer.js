/* public/js/enforcer.js — delegated bindings so it works after AJAX swaps */
(function ($) {
  /* ==============================
   *          CONSTANTS
   * ============================== */
  const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  /* ==============================
   *          HELPERS
   * ============================== */
  function $(sel, root = document) { return root.querySelector(sel); }
  function $all(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

  function setLoading(on) {
    const wrap = $('#table-container');
    if (wrap) wrap.classList.toggle('is-loading', !!on);
  }

  function injectTable(html, targetUrlForHistory) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const partial = doc.querySelector('#table-container');
    const wrap = $('#table-container');
    if (!wrap) return;
    wrap.innerHTML = partial ? partial.innerHTML : html;
    if (targetUrlForHistory) history.pushState({}, '', targetUrlForHistory);
  }

  function currentQuery() {
    // read current controls (they may be re-rendered by AJAX)
    const sortSelect  = $('#enforcerContainer #sort_table');
    const searchInput = $('#enforcerContainer #search_input');

    const url = new URL(location.href);
    const show = url.searchParams.get('show') || 'active';
    const sort_option = (sortSelect?.value) || url.searchParams.get('sort_option') || 'date_desc';
    const search = (searchInput?.value ?? url.searchParams.get('search') ?? '');

    const q = new URLSearchParams();
    q.set('show', show);
    q.set('sort_option', sort_option);
    if (search) q.set('search', search);
    return q;
  }

  function loadTable(pushState = true) {
    const q = currentQuery();
    const url = '/enforcer?' + q.toString();

    setLoading(true);
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.text())
      .then(html => injectTable(html, pushState ? url : null))
      .catch(console.error)
      .finally(() => setLoading(false));
  }

  function navigateAjax(url) {
    // Use your global a[data-ajax] handler
    const a = document.createElement('a');
    a.href = url;
    a.setAttribute('data-ajax', '');
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  function secureRandomString(len = 12) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    let out = '';
    if (window.crypto?.getRandomValues) {
      crypto.getRandomValues(new Uint32Array(len)).forEach(n => out += chars[n % chars.length]);
    } else {
      for (let i = 0; i < len; i++) out += chars[Math.floor(Math.random() * chars.length)];
    }
    return out;
  }

  function pad3(n) {
    n = Math.max(0, Math.min(999, Number(n) || 0));
    return n.toString().padStart(3, '0');
  }
  function incBadge(badge) {
    const num = parseInt(badge, 10);
    if (Number.isNaN(num)) return badge || '';
    const len = (badge || '').length || 3;
    return (num + 1).toString().padStart(len, '0');
  }

  /* ==============================
   *       GLOBAL (DELEGATED) UI
   * ============================== */

  // Search button
  document.addEventListener('click', (e) => {
    if (e.target.closest('#enforcerContainer #search_btn')) {
      e.preventDefault();
      loadTable(true);
    }
  });

  // Search input (Enter)
  document.addEventListener('keydown', (e) => {
    const el = e.target;
    if (el && el.matches('#enforcerContainer #search_input') && e.key === 'Enter') {
      e.preventDefault();
      loadTable(true);
    }
  });

  // Sort select
  document.addEventListener('change', (e) => {
    if (e.target && e.target.matches('#enforcerContainer #sort_table')) {
      loadTable(true);
    }
  });

  // Pagination links
  document.addEventListener('click', (e) => {
    const a = e.target.closest('#enforcerContainer .pagination a');
    if (!a) return;
    e.preventDefault();
    const url = a.href;
    setLoading(true);
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.text())
      .then(html => injectTable(html, url))
      .catch(console.error)
      .finally(() => setLoading(false));
  });

  // History (back/forward) on the list page
  window.addEventListener('popstate', () => {
    // if table exists, reload it
    if ($('#enforcerContainer')) loadTable(false);
  });

  /* ==============================
   *       EDIT MODAL (delegated)
   * ============================== */

  // Ensure #editModal lives under <body> when it appears
  document.addEventListener('show.bs.modal', (ev) => {
    const modalEl = ev.target;
    if (modalEl.id !== 'editModal') return;

    // Move under body to avoid stacking-context traps
    if (modalEl.parentElement !== document.body) {
      document.body.appendChild(modalEl);
    }

    // Clear any loader that could block
    $('#table-container')?.classList.remove('is-loading');

    // Prefill from the triggering button
    const btn = ev.relatedTarget;
    if (!btn) return;

    const form = $('#editEnforcerForm');
    const id   = btn.getAttribute('data-id');
    const url  = btn.getAttribute('data-url') || `/enforcer/${id}`;
    if (form) form.setAttribute('action', url);

    const setVal = (sel, val) => { const el = $(sel); if (el) el.value = val || ''; };

    setVal('#edit_badge_num',     btn.getAttribute('data-badge'));
    setVal('#edit_fname',         btn.getAttribute('data-fname'));
    setVal('#edit_mname',         btn.getAttribute('data-mname'));
    setVal('#edit_lname',         btn.getAttribute('data-lname'));
    setVal('#edit_phone',         btn.getAttribute('data-phone'));
    setVal('#edit_ticket_start',  btn.getAttribute('data-ticket-start'));
    setVal('#edit_ticket_end',    btn.getAttribute('data-ticket-end'));
    setVal('#edit_password',      '');
  });

  document.addEventListener('shown.bs.modal', (ev) => {
    const modalEl = ev.target;
    if (modalEl.id !== 'editModal') return;

    // Remove duplicate backdrops if any
    const backs = $all('.modal-backdrop');
    backs.forEach((bd, i) => { if (i < backs.length - 1) bd.remove(); });

    const topBackdrop = $('.modal-backdrop');
    if (topBackdrop) topBackdrop.style.zIndex = '1990';
    modalEl.style.zIndex = '2000';
  });

  document.addEventListener('hidden.bs.modal', (ev) => {
    if (ev.target.id !== 'editModal') return;
    $all('.modal-backdrop').forEach(bd => bd.remove());
  });

  // Generate password (works for both edit and add if IDs match)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#btnGenPass, #btnGenPwd');
    if (!btn) return;
    const target = $('#edit_password') || $('#password');
    if (target) target.value = secureRandomString(12);
  });

  // Submit Edit (AJAX)
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form || form.id !== 'editEnforcerForm') return;

    e.preventDefault();
    const action = form.getAttribute('action');
    const fd = new FormData(form);
    fd.set('_method', 'PUT');

    fetch(action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
      body: fd
    })
      .then(async (r) => {
        if (!r.ok) throw new Error(await r.text() || 'Update failed');
        return r.json().catch(() => ({}));
      })
      .then((data) => {
        const modal = bootstrap.Modal.getInstance($('#editModal'));
        modal?.hide();

        if (data?.raw_password) {
          Swal.fire({
            icon: 'success',
            title: 'Password Reset',
            html: `<div class="text-start" style="font-size:.95rem">
                     <div><strong>New Password:</strong> ${data.raw_password}</div>
                     <small class="text-muted">Provide this to the enforcer.</small>
                   </div>`,
            timer: 2000
          });
        } else {
          Swal.fire({ icon: 'success', title: 'Updated', timer: 1200, showConfirmButton: false });
        }

        loadTable(false);
      })
      .catch((err) => {
        console.error(err);
        Swal.fire({ icon: 'error', title: 'Update failed', text: 'Please check your inputs.' });
      });
  });

  /* ==========================================
   * ACTIVATE / INACTIVATE (AJAX + admin pwd)
   * ========================================== */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('#enforcerContainer .status-btn');
    if (!btn) return;

    const action = btn.getAttribute('data-action');
    const method = (btn.getAttribute('data-method') || 'POST').toUpperCase();

    const { isConfirmed, value: adminPwd } = await Swal.fire({
      title: method === 'DELETE' ? 'Confirm Inactivate' : 'Confirm Activate',
      text: 'Please enter the admin password to proceed.',
      input: 'password',
      inputAttributes: { autocapitalize: 'off', autocomplete: 'current-password', maxlength: 128 },
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      cancelButtonText: 'Cancel',
      preConfirm: (val) => {
        if (!val) { Swal.showValidationMessage('Admin password is required'); return false; }
        return val;
      }
    });

    if (!isConfirmed) return;

    const fd = new FormData();
    if (method !== 'POST') fd.set('_method', method);
    fd.set('admin_password', adminPwd);

    setLoading(true);
    try {
      const res = await fetch(action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
        body: fd
      });

      if (!res.ok) {
        let msg = 'Action failed.';
        try { const data = await res.json(); msg = data.message || msg; }
        catch { msg = await res.text() || msg; }
        await Swal.fire({ icon: 'error', title: 'Error', text: msg });
        return;
      }

      await Swal.fire({
        icon: 'success',
        title: method === 'DELETE' ? 'Enforcer Inactivated' : 'Enforcer Activated',
        timer: 1200, showConfirmButton: false
      });

      loadTable(false);
    } catch (err) {
      console.error(err);
      await Swal.fire({ icon: 'error', title: 'Network Error', text: 'Please try again.' });
    } finally {
      setLoading(false);
    }
  });

  /* ==============================
   *       ADD PAGE BEHAVIOR
   * ============================== */

  // Confirm Back (works after AJAX swaps)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.confirm-back');
    if (!btn) return;

    const url = btn.dataset.back || '/enforcer';
    const { isConfirmed } = await Swal.fire({
      title: 'Leave this page?',
      text: 'Your inputs will be discarded.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, go back',
      cancelButtonText: 'Stay'
    });
    if (!isConfirmed) return;

    navigateAjax(url);
  });

  // Submit Add form (delegated)
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form || form.id !== 'enforcerForm') return;

    e.preventDefault();
    const action = form.getAttribute('action');
    const fd = new FormData(form);

    // show small overlay if present
    const overlay = $('.form-card .loading-overlay');
    if (overlay) overlay.style.display = 'flex';

    try {
      const res = await fetch(action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
        body: fd
      });

      if (!res.ok) {
        let msg = 'Failed to add enforcer.';
        try {
          const data = await res.json();
          msg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : msg);
        } catch { msg = (await res.text()) || msg; }
        await Swal.fire({ icon: 'error', title: 'Error', text: msg });
        return;
      }

      const data = await res.json(); // { success:true, raw_password:"..." }

      await Swal.fire({
        icon: 'success',
        title: 'Enforcer Added',
        html: data.raw_password
          ? `<div class="text-start" style="font-size:.95rem">
               <div><strong>Temporary Password:</strong> ${data.raw_password}</div>
               <small class="text-muted">Give this to the enforcer; they can change it later.</small>
             </div>`
          : 'Enforcer was added successfully.',
        showCancelButton: true,
        confirmButtonText: 'Add another',
        cancelButtonText: 'Go to list'
      }).then(({ isConfirmed }) => {
        if (isConfirmed) {
          // Reset + advance badge/ticket + fresh password
          form.reset();

          const badgeEl = $('#badge_num');
          const startEl = $('#ticket_start');
          const endEl   = $('#ticket_end');

          if (badgeEl?.value) badgeEl.value = incBadge(badgeEl.value);

          if (startEl && endEl) {
            const prevEnd   = parseInt(endEl.value, 10) || 0;
            const nextStart = Math.min(999, prevEnd + 1);
            const nextEnd   = Math.min(999, nextStart + 99);
            startEl.value = pad3(nextStart);
            endEl.value   = pad3(nextEnd);
          }

          const pwdEl = $('#password');
          if (pwdEl) pwdEl.value = secureRandomString(12);
          $('#fname')?.focus();

        } else {
          navigateAjax('/enforcer');
        }
      });

    } catch (err) {
      console.error(err);
      await Swal.fire({ icon: 'error', title: 'Network Error', text: 'Please try again.' });
    } finally {
      if (overlay) overlay.style.display = 'none';
    }
  });

})(window.jQuery || (() => { throw new Error('jQuery required'); })());
