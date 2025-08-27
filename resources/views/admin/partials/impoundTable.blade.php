<div class="table-responsive">
    <table class="table table-striped table-hover table-bordered" id="impoundedTable">
      <thead class="table-light">
        <tr class="text-center">
          <th scope="col">Ticket No.</th>
          <th scope="col">Issued At</th>
          <th scope="col">Violator</th>
          <th scope="col">Vehicle Type</th>
          <th scope="col">Plate No.</th>
          <th scope="col">Location</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tickets as $ticket)
          <tr data-ticket-id="{{ $ticket->id }}">
            <td>{{ $ticket->ticket_number }}</td>
            <td>{{ $ticket->issued_at->format('d M Y, H:i') }}</td>
            <td>{{ $ticket->violator->first_name }} {{ $ticket->violator->middle_name }} {{ $ticket->violator->last_name }}</td>
            <td>{{ $ticket->vehicle->vehicle_type }}</td>
            <td>{{ $ticket->vehicle->plate_number }}</td>
            <td>{{ $ticket->location }}</td>
            <td class="text-center">
                <button class="btn btn-outline-warning btn-sm btn-resolve"
                      data-bs-toggle="modal"
                      data-bs-target="#resolveModal">
                Resolve
              </button>
            </td>
          </tr> 
        @empty
          <tr>
            <td colspan="8" class="text-center">No impounded vehicle(s) found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
</div>

  <div class="d-flex justify-content-center">
    {{ $tickets->links('pagination::bootstrap-5') }}
  </div>