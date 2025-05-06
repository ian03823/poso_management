(function($){
  // 1) Handle sort control change
  $(document).on('change','#ticket-sort', function(){
    const sort = $(this).val();
    loadTable({ sort_option: sort, page: 1 });
  });

  // 2) Delegate pagination links
  $(document).on('click','#ticket-table .pagination a', function(e){
    e.preventDefault();
    // extract page number from href
    const page = new URL(this.href).searchParams.get('page') || 1;
    // read current sort
    const sort = $('#ticket-sort').val();
    loadTable({ sort_option: sort, page });

  });

  // 3) Popstate (back/forward)
  window.addEventListener('popstate', () => {
    // parse params
    const params = new URLSearchParams(location.search);
    const sort  = params.get('sort_option') || 'date_desc';
    const page  = params.get('page')        || 1;
    // update the select
    $('#ticket-sort').val(sort);
    // reload table without pushing state
    loadTable({ sort_option: sort, page }, /*push=*/false);
  });

  // 4) Main loader
  function loadTable(opts, push = true) {
    // build query string
    const qs = $.param(opts);
    $.get(ticketPartialUrl + '?' + qs, html => {
      $('#ticket-table').html(html);
      if (push) {
        // update URL to /ticket?sort_option=…&page=…
        history.pushState(null,'','/ticket?' + qs);
      }
    });
  }
  

  // 5) Initialize on first load
  $(function(){
    const params = new URLSearchParams(location.search);
    const sort  = params.get('sort_option') || 'date_desc';
    const page  = params.get('page')        || 1;
    $('#ticket-sort').val(sort);
    loadTable({ sort_option: sort, page }, /*push=*/false);
    // replace initial history state
    history.replaceState(null,'',location.pathname + location.search);
  });
})(jQuery);
