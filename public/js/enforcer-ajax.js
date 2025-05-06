// public/js/enforcer.js
(function($){
  const container      = $('#enforcerContainer');    // main wrapper
  const tableContainer = $('#table-container');      // where the <table> lives
  const partialRoute   = '/enforcer/partial';

  //
  // ——— SPA‐style navigation: Add / Back / popstate —————————————————————————
  //
  $('#add-btn').on('click', function(e){
    e.preventDefault();
    $.get('/enforcer/create', html => {
      container.html(html);
      history.pushState(null,'','/enforcer/create');
    });
  });

  $(document).on('click','#back-btn', function(e){
    e.preventDefault();
    $.get('/enforcer', html => {
      container.html(html);
      history.pushState(null,'','/enforcer');
    });
  });

  window.addEventListener('popstate', () => {
    const url = location.pathname + location.search;
    $.get(url, html => {
      container.html(html);
    });
  });
  history.replaceState(null,'',location.pathname + location.search);


  //
  // ——— Form submission with SweetAlert —————————————————————————————————
  //
  $(document).on('submit','#enforcerForm', function(e){
    e.preventDefault();
    const $f = $(this);

    $.post($f.attr('action'), $f.serialize(), tableHtml => {
      Swal.fire({
        icon: 'success',
        title: 'Enforcer added!',
        text: 'Add another?',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
      }).then(res => {
        if (res.isConfirmed) {
          $('#add-btn').click();
        } else {
          tableContainer.html(tableHtml);
          history.pushState(null,'','/enforcer');
        }
      });
    }).fail(xhr => {
      let errs = {};
      if (xhr.status===422 && xhr.responseJSON.errors) {
        errs = xhr.responseJSON.errors;
      } else {
        errs.general = ['Something went wrong'];
      }
      let list = '<ul class="text-start">';
      $.each(errs,(k,v)=> list+=`<li>${v[0]||v}</li>`);
      list += '</ul>';
      Swal.fire({ icon:'error', title:'Error', html:list });
    });
  });


  //
  // ——— Table filtering: sort / search / pagination ——————————————————————
  //
  const $sort   = $('#sort_table');
  const $search = $('#search_input');
  const $btn    = $('#search_btn');

  function getParams() {
    const p = new URLSearchParams(window.location.search);
    return {
      sort:   p.get('sort_option') || 'date_desc',
      search: p.get('search')      || '',
      page:   p.get('page')        || '1',
    };
  }

  function updateUrl(sort, search, page){
    const p = new URLSearchParams();
    if (sort   && sort!=='date_desc') p.set('sort_option', sort);
    if (search && search!=='')        p.set('search', search);
    if (page   && page!=='1')         p.set('page', page);
    history.pushState(null,'',`${window.location.pathname}?${p}`);
  }

  function loadTable(page='1', push=true){
    const sort   = $sort.val();
    const search = $search.val().trim();

    $.ajax({
      url: partialRoute,
      data: { sort_option: sort, search, page },
      headers: { 'X-Requested-With':'XMLHttpRequest' },
      success(html){
        tableContainer.html(html);
        if (push) updateUrl(sort, search, page);
      },
      error(err){
        console.error('Enforcer table load error',err);
      }
    });
  }

  $(function(){
    const { sort, search, page } = getParams();
    $sort.val(sort);
    $search.val(search);
    loadTable(page,false);
  });

  $sort.on('change', ()=> loadTable('1'));
  $btn .on('click',  ()=> loadTable('1'));
  $search.on('keypress', e => {
    if (e.which===13) {
      e.preventDefault();
      loadTable('1');
    }
  });

  tableContainer.on('click','.pagination a', function(e){
    e.preventDefault();
    const newPage = new URL(this.href).searchParams.get('page') || '1';
    loadTable(newPage);
  });

})(jQuery);
