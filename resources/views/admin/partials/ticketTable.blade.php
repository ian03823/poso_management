<table class="table table-bordered">
    <thead>
      <tr>
        <th class="text-center">ID</th>
        <th class="text-center">Violator</th>
        <th class="text-center">Plate #</th>
        <th class="text-center">Violation(s)</th>
        <th class="text-center">Issued At</th>
        <th class="text-center">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($tickets as $t)
        <tr data-id="{{ $t->id }}">
          <td class="text-center">{{ $t->id }}</td>
          <td class="text-center">{{ $t->violator->name }}</td>
          <td class="text-center">
            {{ optional($t->vehicle)->plate_number ?? 'N/A' }}
          </td>
          <td>
            {{ $t->violations
                  ->pluck('violation_name')
                  ->join(', ') }}
          </td>
          <td class="text-center">
            {{ $t->issued_at->format('Y-m-d H:i') }}
          </td>
          <td class="text-center">
            <select class="form-select form-select-sm status-select">
              <option value="pending"   {{ $t->status=='pending'   ? 'selected' : '' }}>
                Pending
              </option>
              <option value="paid"      {{ $t->status=='paid'      ? 'selected' : '' }}>
                Paid
              </option>
              <option value="cancelled" {{ $t->status=='cancelled' ? 'selected' : '' }}>
                Cancelled
              </option>
            </select>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="text-center">No tickets found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
  
  <nav aria-label="Pages navigation" class="mt-3">
    {{ $tickets->links('pagination::bootstrap-5') }}
  </nav>
  