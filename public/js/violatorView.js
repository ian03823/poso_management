
  // Set up CSRF for all jQuery AJAX requests
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute('content')
    }
  });

  let detailUrl;

  // 1) Load detail partial on “View More”
  $(document).on('click', '.view-tickets-btn', function(e) {
    e.preventDefault();
    detailUrl = $(this).data('url');

    $('#violatorModalBody').html('<div class="text-center py-5">Loading…</div>');
    new bootstrap.Modal($('#violatorModal')).show();

    $.get(detailUrl, html => {
      $('#violatorModalBody').html(html);
    });
  });

  // 2) Handle status change
  $(document).on('change', '.status-select', function() {
    const ticketId = $(this).data('ticket-id');
    const statusId = $(this).val();

    $.post(`/ticket/${ticketId}/status`, { status_id: statusId })
      .done(() => {
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'Status updated',
          timer: 1500,
          showConfirmButton: false
        });
        // re-load the same partial
        $.get(detailUrl, html => {
          $('#violatorModalBody').html(html);
        });
      })
      .fail(() => {
        Swal.fire('Error','Could not update status','error');
      });
  });
