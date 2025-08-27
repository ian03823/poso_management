(function($){
  // 1) Sort & pagination (unchanged) …
  $(document).on('change','#ticket-sort', function(){
    loadTable({ sort_option: $(this).val(), page: 1 });
  });
  $(document).on('click','#ticket-table .pagination a', function(e){
    e.preventDefault();
    const page = new URL(this.href).searchParams.get('page') || 1;
    loadTable({ sort_option: $('#ticket-sort').val(), page });
  });
  window.addEventListener('popstate', () => {
    const p = new URLSearchParams(location.search);
    loadTable({
      sort_option: p.get('sort_option') || 'date_desc',
      page:        p.get('page')        || 1
    }, /*push=*/false);
    $('#ticket-sort').val(p.get('sort_option') || 'date_desc');
  });
  function loadTable(opts, push = true) {
    const qs = $.param(opts);
    $.get(ticketPartialUrl + '?' + qs, html => {
      $('#ticket-table').html(html);
      if (push) history.pushState(null,'','/ticket?' + qs);
    });
  }
  $(function(){
    const p = new URLSearchParams(location.search);
    loadTable({
      sort_option: p.get('sort_option') || 'date_desc',
      page:        p.get('page')        || 1
    }, /*push=*/false);
    $('#ticket-sort').val(p.get('sort_option') || 'date_desc');
    history.replaceState(null,'',location.pathname + location.search);
  });

  // 2) Payment-flow state
  let _lastSelect, _lastValue, _paidConfirmed;

  // 3) Intercept status <select>
  $(document).on('change', '.status-select', function() {
    const $sel      = $(this);
    const ticketId  = +$sel.data('ticket-id');
    const oldStatus = +$sel.data('current-status-id');
    const newStatus = +$sel.val();
    const paidId    = window.PAID_STATUS_ID;

    _lastSelect    = $sel;
    _lastValue     = oldStatus;
    _paidConfirmed = false;

    // into Paid → show Ref modal
    if (newStatus === paidId) {
      $('#ref_ticket_id').val(ticketId);
      new bootstrap.Modal($('#ticketRefModal')).show();
    }
    // away from Paid → show Pwd modal
    else if (oldStatus === paidId && newStatus !== paidId) {
      $('#pwd_ticket_id').val(ticketId);
      $('#pwd_new_status').val(newStatus);
      new bootstrap.Modal($('#ticketPwdModal')).show();
    }
    // otherwise → AJAX
    else {
      postStatus(ticketId, { status_id: newStatus });
    }
  });

  // 4) Ref-Modal hidden: revert if cancelled
  $('#ticketRefModal').on('hidden.bs.modal', () => {
    if (!_paidConfirmed && _lastSelect) {
      _lastSelect.val(_lastValue);
    }
  });

  // 5) Ref form submit
  $('#ticketRefForm').on('submit', function(e){
    e.preventDefault();
    _paidConfirmed = true;
    const ticketId = $('#ref_ticket_id').val();
    const refNo    = $('#reference_number').val().trim();

    postStatus(ticketId, {
      status_id:        window.PAID_STATUS_ID,
      reference_number: refNo
    }, () => {
      $('#ticketRefModal').modal('hide');
    });
  });

  // 6) Pwd-Modal hidden: revert if cancelled
  $('#ticketPwdModal').on('hidden.bs.modal', () => {
    if (!_paidConfirmed && _lastSelect) {
      _lastSelect.val(_lastValue);
    }
  });

  // 7) Pwd form submit
  $('#ticketPwdForm').on('submit', function(e){
    e.preventDefault();
    _paidConfirmed = true;
    const ticketId = $('#pwd_ticket_id').val();
    const statusId = $('#pwd_new_status').val();
    const pw       = $('#admin_password').val();

    postStatus(ticketId, {
      status_id:      statusId,
      admin_password: pw
    }, () => {
      $('#ticketPwdModal').modal('hide');
    });
  });

  // 8) AJAX helper + reload
  function postStatus(ticketId, data, onSuccess) {
    data._token = $('meta[name="csrf-token"]').attr('content');
    $.post(`${window.STATUS_UPDATE_URL}/${ticketId}/status`, data)
      .done(json => {
        onSuccess?.();
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: json.message,
          timer: 1500,
          showConfirmButton: false
        });
        // refresh table
        const sort = $('#ticket-sort').val();
        const page = new URLSearchParams(location.search)
                     .get('page') || 1;
        loadTable({ sort_option: sort, page }, /*push=*/false);
      })
      .fail(xhr => {
        Swal.fire('Error',
          xhr.responseJSON?.message || 'Could not update status',
          'error'
        );
      });
  }
})(jQuery);
