// public/js/violationTable.js — smooth filters/pagination + admin-password archive + edit modal
(function ($) {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const partialUrl = window.violationPartialUrl || '/violation/partial';
  const $wrap = $('#violationContainer');

  function setLoading(on){ $wrap.toggleClass('is-loading', !!on); }

  function getParams(){
    const p = new URLSearchParams(location.search);
    return {
      category: p.get('category') || $('#category_filter').val() || 'all',
      search:   p.get('search')   || $('#search_input').val()   || '',
      page:     p.get('page')     || '1'
    };
  }

  function pushUrl(category, search, page){
    const p = new URLSearchParams();
    if (category && category !== 'all') p.set('category', category);
    if (search) p.set('search', search);
    if (page && page !== '1') p.set('page', page);
    history.pushState(null, '', `${location.pathname}?${p}`);
  }

  function inject(html, pushTo){
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const partial = doc.querySelector('#table-container');
    $wrap.html(partial ? partial.innerHTML : html);
    if (pushTo) pushUrl(pushTo.category, pushTo.search, pushTo.page);
  }

  function loadPage(page='1', push=true){
    const category = $('#category_filter').val() || 'all';
    const search   = ($('#search_input').val() || '').trim();

    setLoading(true);
    $.ajax({
      url: partialUrl,
      data: { category, search, page },
      headers: { 'X-Requested-With':'XMLHttpRequest' }
    })
    .done(html => inject(html, push ? {category, search, page} : null))
    .fail(err => console.error('AJAX error:', err))
    .always(()=> setLoading(false));
  }

  // Initial load: keep current params
  $(function(){
    const {category, search, page} = getParams();
    $('#category_filter').val(category);
    $('#search_input').val(search);
    loadPage(page, /*push=*/false);
  });

  // Filters
  $(document).on('change', '#category_filter', () => loadPage('1'));
  $(document).on('click',  '#search_btn',      () => loadPage('1'));
  $(document).on('keypress','#search_input', (e) => {
    if (e.which === 13){ e.preventDefault(); loadPage('1'); }
  });

  // Pagination
  $(document).on('click', '#violationContainer .pagination a', function(e){
    e.preventDefault();
    const newPage = new URL(this.href).searchParams.get('page') || '1';
    loadPage(newPage);
  });

  // Browser back/forward
  window.addEventListener('popstate', () => {
    const {category, search, page} = getParams();
    $('#category_filter').val(category);
    $('#search_input').val(search);
    loadPage(page, /*push=*/false);
  });

  /* -------------------- Edit Modal -------------------- */
  // Prefill using data-* from "Edit" button
  document.addEventListener('show.bs.modal', (ev) => {
    const modal = ev.target;
    if (modal.id !== 'editModal') return;

    // Move modal directly under body to avoid stacking issues
    if (modal.parentElement !== document.body) document.body.appendChild(modal);

    const btn = ev.relatedTarget;
    if (!btn) return;

    const $form = $('#editViolationForm');
    const url   = btn.getAttribute('data-url');
    $form.attr('action', url);

    $('#edit_violation_code').val(btn.getAttribute('data-code') || '');
    $('#edit_violation_name').val(btn.getAttribute('data-name') || '');
    $('#edit_fine_amount').val(btn.getAttribute('data-fine') || '');
    $('#edit_category').val(btn.getAttribute('data-category') || '');
  });

  $('#editViolationForm').on('submit', function(e){
    e.preventDefault();
    const action = $(this).attr('action');
    const fd = new FormData(this);
    fd.set('_method','PUT');

    $.ajax({
      url: action, method:'POST', data: fd, processData:false, contentType:false,
      headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }
    })
    .done(() => {
      const modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
      modal?.hide();
      Swal.fire({ icon:'success', title:'Updated', timer:1200, showConfirmButton:false });
      const {page} = getParams(); loadPage(page);
    })
    .fail(async (xhr)=>{
      let msg = 'Update failed';
      try{ msg = xhr.responseJSON?.message || msg; }catch(_){}
      Swal.fire({ icon:'error', title:'Error', text:msg });
    });
  });

  /* -------------------- Archive (admin password) -------------------- */
  $(document).on('click', '.archive-btn', async function(e){
    e.preventDefault();
    const name   = $(this).data('name');
    const action = $(this).data('action');

    // Ask for admin password
    const { isConfirmed, value: adminPwd } = await Swal.fire({
      title: `Archive “${name}”?`,
      text: 'Enter admin password to proceed.',
      input: 'password',
      inputAttributes: { autocapitalize:'off', autocomplete:'current-password', maxlength:128 },
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Archive',
      cancelButtonText: 'Cancel',
      preConfirm: (val) => {
        if (!val){ Swal.showValidationMessage('Admin password is required'); return false; }
        return val;
      }
    });
    if (!isConfirmed) return;

    setLoading(true);
    $.ajax({
      url: action, method:'POST',
      data: { _method:'DELETE', admin_password: adminPwd },
      headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }
    })
    .done(() => {
      Swal.fire({ icon:'success', title:'Archived', timer:1200, showConfirmButton:false });
      const {page} = getParams(); loadPage(page);
    })
    .fail(async (xhr)=>{
      let msg = 'Could not archive.';
      try{ msg = xhr.responseJSON?.message || msg; }catch(_){}
      Swal.fire({ icon:'error', title:'Error', text: msg });
    })
    .always(()=> setLoading(false));
  });

})(jQuery);
