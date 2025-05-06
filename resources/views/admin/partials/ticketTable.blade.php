<table class="table table-bordered">
    <thead>
      <tr>
        <th>#</th>
        <th>Enforcer</th>
        <th>Violator</th>
        <th>Violation(s)</th>
        <th>Location</th>
        <th>Issued At</th>
        <th>Confiscated</th>
        <th>Impound</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($tickets as $t)
      <tr>
        <td>{{ $t->id }}</td>
        <td>{{ $t->enforcer->fname }}</td>
        <td>{{ $t->violator->name }}</td>
        <td>{{ $t->violation_names }}</td>
        <td>{{ $t->location }}</td>
        <td>{{ $t->issued_at->format('Y-m-d H:i') }}</td>
        <td>{{ $t->confiscated }}</td>
        <td>{{ $t->is_impounded ? 'Yes' : 'No' }}</td>
        <td>
          <select class="form-select form-select-sm status-select" data-id="{{ $t->id }}">
            @foreach(['pending','paid','unpaid','cancelled'] as $st)
              <option value="{{ $st }}"
                {{ $t->status === $st ? 'selected' : '' }}>
                {{ ucfirst($st) }}
              </option>
            @endforeach
          </select>
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="9" class="text-center">No tickets found.</td>
      </tr>
    @endforelse
    </tbody>
  </table>
  
  <nav aria-label="Pages navigation" class="mt-3">
    {{ $tickets->links('pagination::bootstrap-5') }}
  </nav>
  