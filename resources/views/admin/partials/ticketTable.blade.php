<table class="table table-striped table-hover">
    <thead>
      <tr>
        <th><input type="checkbox" id="select-all"></th>
        <th>ID</th>
        <th>Violator</th>
        <th>Plate #</th>
        <th>Violations</th>
        <th>Issued At</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($tickets as $t)
        <tr data-id="{{ $t->id }}">
          <td><input type="checkbox" class="row-select"></td>
          <td>{{ $t->id }}</td>
          <td>{{ $t->violator->name }}</td>
          <td>{{ $t->vehicle->plate_number }}</td>
          <td>{{ implode(', ', json_decode($t->violation_codes)) }}</td>
          <td>{{ $t->issued_at->format('Y-m-d H:i') }}</td>
          <td>
            <select class="form-select form-select-sm status-select">
              <option value="pending" {{ $t->status=='pending'?'selected':'' }}>Pending</option>
              <option value="paid" {{ $t->status=='paid'?'selected':'' }}>Paid</option>
              <option value="cancelled" {{ $t->status=='cancelled'?'selected':'' }}>Cancelled</option>
            </select>
          </td>
          <td>
            <button class="btn btn-sm btn-info view-btn" data-id="{{ $t->id }}">
              <i class="bi bi-eye"></i>
            </button>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center">No tickets found.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div class="mt-3">
    {{ $tickets->links('pagination::bootstrap-5') }}
  </div>