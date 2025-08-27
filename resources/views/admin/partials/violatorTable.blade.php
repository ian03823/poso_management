<div class="table-responsive">
<table class="table table-bordered table-hover">
  <thead>
    <tr>
      <th class="text-center">Ticket #</th>
      <th class="text-center">Name</th>
      <th class="text-center">Address</th>
      <th class="text-center">License #</th>
      <th class="text-center">Vehicle Type</th>
      <th class="text-center">Plate #</th>
      <th class="text-center">Recent Violation</th>
      <th class="text-center">Actions</th>
    </tr>
  </thead>
  <tbody>
    @forelse($tickets as $t)
        <tr>
          <td>{{ $t->ticket_number }}</td>
          <td>{{ $t->violator->first_name }} {{ $t->violator->middle_name }} {{ $t->violator->middle_name }}</td>
          <td>{{ $t->violator->address }}</td>
          <td>{{ $t->violator->license_number }}</td>
          <td>{{ $t->vehicle?->vehicle_type}}</td>
          <td>{{ $t->vehicle?->plate_number}}</td>
          <td>{{ $t->violation_names }}</td>

          {{-- Actions --}}
          <td class="text-center">
            <a href=""
               class="btn btn-sm btn-info text-white view-tickets-btn"
               data-violator-id="{{ $t->violator->id }}"
               data-violator-name="{{ $t->violator->name }}"
               data-url="{{ route('violatorTable.show', $t->violator->id) }}">
               View More
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center">No violator(s) found.</td>
        </tr>
      @endforelse
  </tbody>
</table>

{{-- Pagination --}}
<div class="d-flex justify-content-between align-items-center mt-3">
  <div class="d-none d-md-block">
     Showing {{ $tickets->firstItem() }} to {{ $tickets->lastItem() }} of {{ $tickets->total() }} results
  </div>
  <div class="w-100 d-flex justify-content-center">
    {{ $tickets->links() }}
  </div>
</div>
</div>