<table class="table table-striped table-hover">
  <thead class="table-light">
    <tr>
      <th>Ticket #</th>
      <th>Name</th>
      <th>Address</th>
      <th>License #</th>
      <th>Vehicle Type</th>
      <th>Plate #</th>
      <th>Violations</th>
      <th class="text-center">Actions</th>
    </tr>
  </thead>
  <tbody>
    @forelse($tickets as $t)
        <tr>
          {{-- Ticket number --}}
          <td>{{ $t->id }}</td>

          {{-- Violator info --}}
          <td>{{ $t->violator->name }}</td>
          <td>{{ $t->violator->address }}</td>
          <td>{{ $t->violator->license_number }}</td>

          {{-- Vehicle info --}}
          <td>{{ $t->vehicle?->vehicle_type}}</td>
          <td>{{ $t->vehicle?->plate_number}}</td>

          {{-- Decoded violation names (via accessor) --}}
          <td>{{ $t->violation_names }}</td>

          {{-- Actions --}}
          <td class="text-center">
            <a href=""
               class="btn btn-sm btn-info">
               View More
            </a>
            <form action=""
                  method="POST"
                  class="d-inline"
                  onsubmit="return confirm('Delete this violator and all their tickets?');">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-danger">
                Delete
              </button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center">No tickets found.</td>
        </tr>
      @endforelse
  </tbody>
</table>

{{-- Pagination --}}
<div class="mt-3">
  {{ $tickets->links('pagination::bootstrap-5') }}
</div>
</div>