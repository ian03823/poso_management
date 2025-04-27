// public/js/violationTable.js
$(function() {
    const $category = $('#category_filter');
    const $search   = $('#search_input');
    const $btn      = $('#search_btn');
    const $cont     = $('#violationContainer');
  
    // Central loader: pulls in the partial
    function loadPage(page = 1) {
      const data = {
        category: $category.val(),
        search:   $search.val().trim(),
        page
      };
      $.ajax({
        url: '/violation/partial',
        data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success(html) {
          $cont.html(html);
        },
        error(err) {
          console.error('AJAX Error:', err);
        }
      });
    }
  
    // 1) Category change
    $category.on('change', () => loadPage());
  
    // 2) Search button click
    $btn.attr('type','button')  // ensure it doesn't submit
        .on('click', e => {
          e.preventDefault();
          loadPage();
        });
  
    // 3) Enter key in search input
    $search.on('keypress', e => {
      if (e.which === 13) {
        e.preventDefault();
        loadPage();
      }
    });
  
    // 4) AJAX pagination links (delegated)
    $cont.on('click', '.pagination a', function(e) {
      e.preventDefault();
      // extract ?page= from href
      const page = new URL(this.href).searchParams.get('page') || 1;
      loadPage(page);
    });
  });
  