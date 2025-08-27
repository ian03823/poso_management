document.addEventListener('DOMContentLoaded', () => {
    let ticketIdInput = document.getElementById('modal_ticket_id');
  
    // store which ticket was clicked
    document.querySelectorAll('.btn-resolve').forEach(btn => {
      btn.addEventListener('click', e => {
        let tr = e.target.closest('tr');
        ticketIdInput.value = tr.dataset.ticketId;
      });
    });
  
    // handle form submit
    document.getElementById('resolveForm')
      .addEventListener('submit', function(e) {
        e.preventDefault();
  
        let form = e.target;
        let data = new FormData(form);
  
        fetch("{{ route('impound.resolve') }}", {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
           },
          body: data
        })
        .then(r => r.json().then(json => ({ status: r.status, body: json })))
        .then(({status, body}) => {
          if (status === 200 && body.status === 'success') {
            // move row
            let row = document
              .querySelector(`tr[data-ticket-id="${data.get('ticket_id')}"]`);
            let clone = row.cloneNode(true);
  
            // update clone for released table
            let refCell = clone.insertCell(1);
            refCell.textContent = data.get('reference_number');
            let dateCell = clone.insertCell(2);
            let now = new Date();
            dateCell.textContent = now.toLocaleString();
  
            // remove action cell
            clone.deleteCell(7);
  
            row.remove();
            document.querySelector('#releasedTable tbody')
                    .prepend(clone);
  
            // hide modal
            let modalEl = document.getElementById('resolveModal');
            bootstrap.Modal.getInstance(modalEl).hide();
  
            // success alert
            Swal.fire({
              icon: 'success',
              title: 'Resolved!',
              text: body.message
            });
          } else {
            throw new Error(body.message || 'Something went wrong');
          }
        })
        .catch(err => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message
          });
        });
    });
  });