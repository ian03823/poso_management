$(function(){
    const $cont      = $('#ticketContainer');
    const $status    = $('#status_filter');
    const $enforcer  = $('#enforcer_filter');
    const $category  = $('#category_filter');
    const $from      = $('#date_from');
    const $to        = $('#date_to');
    const $search    = $('#search_input');
    const $btn       = $('#search_btn');
  
    function loadTickets(page=1){
      const params={
        status:   $status.val(),
        enforcer: $enforcer.val(),
        category: $category.val(),
        date_from:$from.val(),
        date_to:  $to.val(),
        search:   $search.val().trim(),
        page
      };
      $.ajax({
        url:'/ticket/partial',
        data:params,
        headers:{'X-Requested-With':'XMLHttpRequest'},
        success(html){ $cont.html(html); },
        error(err){ console.error(err); }
      });
    }
  
    // Filters & search
    $status.change(()=>loadTickets());
    $enforcer.change(()=>loadTickets());
    $category.change(()=>loadTickets());
    $from.change(()=>loadTickets());
    $to.change(()=>loadTickets());
    $btn.click(e=>{ e.preventDefault(); loadTickets(); });
    $search.keypress(e=>{ if(e.which===13){e.preventDefault(); loadTickets();}});
  
    // Pagination click
    $cont.on('click','.pagination a',function(e){
      e.preventDefault();
      const page=new URL(this.href).searchParams.get('page')||1;
      loadTickets(page);
    });
  });