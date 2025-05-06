(function($){
    // debounce helper
    function debounce(fn, delay=300){
      let t;
      return function(...args){
        clearTimeout(t);
        t = setTimeout(()=> fn.apply(this,args), delay);
      };
    }
  
    // fetch & render partial
    function loadTable(opts, push=true){
      const qs = $.param(opts);
      $.get(`${violatorPartialUrl}?${qs}`, html => {
        $('#violator-table-container').html(html);
        if(push){
          history.pushState(null,'',`?${qs}`);
        }
      });
    }
  
    $(function(){
      // initialize from URL
      const params = new URLSearchParams(location.search);
      const sort   = params.get('sort_option') || 'date_desc';
      const search = params.get('search')      || '';
      const page   = params.get('page')        || 1;
  
      $('#violator-sort').val(sort);
      $('#violator-search').val(search);
  
      loadTable({ sort_option: sort, search, page }, false);
      history.replaceState(null,'',location.pathname + location.search);
  
      // sort change
      $(document).on('change','#violator-sort', function(){
        loadTable({
          sort_option: this.value,
          search:      $('#violator-search').val(),
          page:        1
        });
      });
  
      // live-search (debounced)
      $(document).on('input','#violator-search',
        debounce(function(){
          loadTable({
            sort_option: $('#violator-sort').val(),
            search:      this.value,
            page:        1
          });
        }, 500)
      );
  
      // pagination click
      $(document).on('click','#violator-table-container .pagination a', function(e){
        e.preventDefault();
        const p = new URL(this.href).searchParams.get('page') || 1;
        loadTable({
          sort_option: $('#violator-sort').val(),
          search:      $('#violator-search').val(),
          page:        p
        });
      });
  
      // back/forward buttons
      window.addEventListener('popstate', () => {
        const pr = new URLSearchParams(location.search);
        loadTable({
          sort_option: pr.get('sort_option') || 'date_desc',
          search:      pr.get('search')      || '',
          page:        pr.get('page')        || 1
        }, false);
      });
  
      // delete button (delegated)
      $(document).on('click','.delete-btn', function(){
        const id = $(this).data('id');
        if(confirm('Are you sure you want to delete this violator?')){
          $(`#delete-form-${id}`).submit();
        }
      });
    });
  })(jQuery);
  