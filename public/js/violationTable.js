// public/js/violationTable.js
console.log('✅ inline archive script loaded');
(function($){
  console.log('✅ violationTable IIFE running');
  const $category = $('#category_filter');
  const $search   = $('#search_input');
  const $btn      = $('#search_btn');
  const $cont     = $('#violationContainer');

  // Read URL params
  function getUrlParams() {
    const p = new URLSearchParams(window.location.search);
    return {
      category: p.get('category') || '',
      search:   p.get('search')   || '',
      page:     p.get('page')     || '1'
    };
  }

  // Push new URL without reload
  function updateUrl(category, search, page) {
    const p = new URLSearchParams();
    if (category) p.set('category', category);
    if (search)   p.set('search',   search);
    if (page && page !== '1') p.set('page', page);
    history.pushState(null, '', `${window.location.pathname}?${p}`);
  }

  // Load the table partial via AJAX
  function loadPage(page = '1', push = true) {
    const category = $category.val() || '';
    const search   = ($search.val() || '').trim();

    $.ajax({
      url: '/violation/partial',
      data: { category, search, page },
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      success(html) {
        $cont.html(html);
        if (push) updateUrl(category, search, page);
      },
      error(err) {
        console.error('AJAX Error:', err);
      }
    });
  }

  // Initial load
  $(function(){
    
    const { category, search, page } = getUrlParams();
    if (category) $category.val(category);
    if (search)   $search.val(search);
    loadPage(page, /*push=*/false);
  });

  // Filters & pagination
  $category.on('change', () => loadPage('1'));
  $btn.attr('type','button').on('click', () => loadPage('1'));
  $search.on('keypress', e => {
    if (e.which === 13) {
      e.preventDefault();
      loadPage('1');
    }
  });

  // Delegate pagination links inside the container
  $cont.on('click', '.pagination a', function(e) {
    e.preventDefault();
    const newPage = new URL(this.href).searchParams.get('page') || '1';
    loadPage(newPage);
  });

  // ─────────── Archive (soft-delete) handler ───────────
  // Make sure your Blade uses <button class="archive-btn"> on the form that DELETEs /violation/{id}
  $cont.on('click', '.archive-btn', function(e) {
    e.preventDefault();
    const $btn  = $(this);
    const $form = $btn.closest('form');
    
    const name  = $btn.data('name'); // e.g. "V106 – Illegal Parking"

    Swal.fire({
      title: `Archive “${name}”?`,
      text: "It will be hidden from the active list but remain linked to past tickets.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, archive it!',
      cancelButtonText: 'Cancel'
    }).then(result => {
      
      if (!result.isConfirmed) return;
      $.ajax({
        url:    $form.attr('action'),
        method: 'DELETE',
        data:   $form.serialize(),
        success() {
          Swal.fire('Archived!', `"${name}" has been archived.`, 'success');
          // reload current page so the table updates
          const { page } = getUrlParams();
          loadPage(page);
        },
        error() {
          Swal.fire('Error', 'Could not archive. Try again.', 'error');
        }
      });
    });
  });

  // Handle back/forward
  window.addEventListener('popstate', () => {
    const { category, search, page } = getUrlParams();
    $category.val(category);
    $search.val(search);
    loadPage(page, /*push=*/false);
  });

})(jQuery);
