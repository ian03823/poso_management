<div class="table-responsive">
    <table class="table table-striped table-bordered" id="releasedTable">
        <thead class="table-light text-center">
        <tr>
            <th>Ticket No.</th>
            <th>Reference No.</th>
            <th>Released At</th>
            <th>Violator</th>
            <th>Vehicle Type</th>
            <th>Plate No.</th>
        </tr>
        </thead>
        <tbody>
            @forelse($releasedTickets as $ticket)
              <tr id="released-{{ $ticket->id }}">
                <td>{{ $ticket->ticket_number }}</td>
                <td>{{ $ticket->releasedVehicle->reference_number }}</td>
                <td>{{ $ticket->releasedVehicle->released_at->format('d M Y, H:i') }}</td>
                <td>{{ $ticket->violator->first_name }} {{ $ticket->violator->middle_name }} {{ $ticket->violator->last_name }}</td>
                <td>{{ $ticket->vehicle->vehicle_type }}</td>
                <td>{{ $ticket->vehicle->plate_number }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center">No released vehicle(s) yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

  <div class="d-flex justify-content-center">
    {{ $tickets->links('pagination::bootstrap-5') }}
  </div>