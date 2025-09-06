(function($){
  /* ------------------------------
   * 0) Bootstrap 5 modal handles
   * ------------------------------ */
  const refEl   = document.getElementById('ticketRefModal');
  const pwdEl   = document.getElementById('ticketPwdModal');
  const refModal= bootstrap.Modal.getOrCreateInstance(refEl, {backdrop:true,keyboard:true,focus:true});
  const pwdModal= bootstrap.Modal.getOrCreateInstance(pwdEl, {backdrop:true,keyboard:true,focus:true});

  /* --------------------------------
   * 1) Sort & pagination (unchanged)
   * -------------------------------- */
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

  /* ---------------------------------------
   * 2) Payment-flow state and helpers
   * --------------------------------------- */
  let _lastSelect = null;   // jQuery object of the last select
  let _lastValue  = null;   // old status id
  let _submitting = false;  // true only while making the API call

  function csrf() {
    return $('meta[name="csrf-token"]').attr('content') || '';
  }

  function revertSelect() {
    if (_lastSelect && _lastValue != null) _lastSelect.val(_lastValue);
    _lastSelect = null; _lastValue = null; _submitting = false;
  }

  function postStatus(ticketId, data, onDone) {
    data._token = $('meta[name="csrf-token"]').attr('content') || '';

    $.ajax({
      url: `${window.STATUS_UPDATE_URL}/${ticketId}/status`,
      method: 'POST',
      data,
      headers: { 'X-Requested-With':'XMLHttpRequest' }
    })
    .done(json => {
      Swal?.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: json?.message || 'Status updated',
        timer: 1500,
        showConfirmButton: false
      });
      onDone?.(true);
      // refresh table keeping page & sort AFTER modal fully hides
      const finishReload = () => {
        [refEl, pwdEl].forEach(el => el.removeEventListener('hidden.bs.modal', finishReload));
        const sort = $('#ticket-sort').val();
        const page = new URLSearchParams(location.search).get('page') || 1;
        loadTable({ sort_option: sort, page }, /*push=*/false);
      };
      refEl.addEventListener('hidden.bs.modal', finishReload);
      pwdEl.addEventListener('hidden.bs.modal', finishReload);
      bootstrap.Modal.getInstance(refEl)?.hide();
      bootstrap.Modal.getInstance(pwdEl)?.hide();
      _lastSelect = null; _lastValue = null;
    })
    .fail(xhr => {
      Swal?.fire('Error', xhr?.responseJSON?.message || 'Could not update status.', 'error');
      onDone?.(false);
      revertSelect();
    });
  }

  /* -------------------------------------------------
   * 3) Intercept status <select> (central decision)
   * ------------------------------------------------- */
  $(document).on('change', '.status-select', function() {
    const $sel      = $(this);
    const ticketId  = +$sel.data('ticket-id');
    const oldStatus = +$sel.data('current-status-id');
    const newStatus = +$sel.val();
    const paidId    = Number(window.PAID_STATUS_ID);

    _lastSelect = $sel;
    _lastValue  = oldStatus;

    if (oldStatus === newStatus) return;

    if (newStatus === paidId) {
      // TO PAID: ask Reference ONLY, then submit immediately
      $('#ref_ticket_id').val(ticketId);
      $('#reference_number').val('');
      refModal.show();
      return;
    }

    // ANY OTHER CHANGE: ask Password ONLY, then submit immediately
    $('#pwd_ticket_id').val(ticketId);
    $('#pwd_new_status').val(newStatus);
    $('#admin_password').val('');
    pwdModal.show();
  });

  /* -----------------------------------------------
   * 4) Ref modal close: if user cancels, revert
   * ----------------------------------------------- */
  $('#ticketRefModal').on('hidden.bs.modal', () => {
    if (!_submitting) revertSelect();
  });

  /* ----------------------------------------------
   * 5) Ref form submit: SUBMIT immediately (no pwd)
   * ---------------------------------------------- */
  $('#ticketRefForm').on('submit', function(e){
    e.preventDefault();
    const refNo   = $('#reference_number').val().trim();
    const ticketId= $('#ref_ticket_id').val();
    if (!refNo) return;

    _submitting = true;
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);

    postStatus(ticketId, {
      status_id:        window.PAID_STATUS_ID,
      reference_number: refNo
    }, () => {
      _submitting = false;
      $btn.prop('disabled', false);
    });
  });

  /* -----------------------------------------------
   * 6) Password modal close: if user cancels, revert
   * ----------------------------------------------- */
  $('#ticketPwdModal').on('hidden.bs.modal', () => {
    if (!_submitting) revertSelect();
  });

  /* -------------------------------------------
   * 7) Password form submit: SUBMIT immediately
   * ------------------------------------------- */
  $('#ticketPwdForm').on('submit', function(e){
    e.preventDefault();
    const ticketId = $('#pwd_ticket_id').val();
    const statusId = $('#pwd_new_status').val();
    const pw       = $('#admin_password').val();

    _submitting = true;
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);

    postStatus(ticketId, {
      status_id:      statusId,
      admin_password: pw
    }, () => {
      _submitting = false;
      $btn.prop('disabled', false);
    });
  });

  /* ----------------------------------------------------------
   * 8) Backdrop clean-up (prevents dim background persisting)
   * ---------------------------------------------------------- */
  document.addEventListener('hidden.bs.modal', () => {
    // If no modal is visible, ensure body/backdrops are clean
    if (!document.querySelector('.modal.show')) {
      document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
    }
  });
})(jQuery);
