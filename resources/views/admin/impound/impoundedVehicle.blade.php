@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')
<div class="container-fluid mt-4">
    <h2 class="mb-3">Temporary Impounded Vehicle</h2>
    <div class="">
        @include('admin.partials.impoundTable')
    </div>

    <h2 class="mt-4">Released Vehicle</h2>
    @include('admin.partials.releasedVehicle')

    
</div>

<div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form id="resolveForm">
        @csrf
        <input type="hidden" name="ticket_id" id="modal_ticket_id">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Resolve Vehicle</h5>
            <button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="reference_number" class="form-label">
                Reference Number (8 digits)
              </label>
              <input type="text"
                     class="form-control"
                     id="reference_number"
                     name="reference_number"
                     maxlength="8"
                     required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">Cancel</button>
            <button type="submit"
                    class="btn btn-primary">Confirm</button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
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


    </script>
    <script src="{{ asset('js/impoundedVehicle.js') }}"></script>
@endpush