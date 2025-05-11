<table class="table table-bordered table-hover">
    <thead>
      <tr>
        <th class="text-center">#</th>
        <th class="text-center">Enforcer</th>
        <th class="text-center">Violator</th>
        <th class="text-center">Violation(s)</th>
        <th class="text-center">Location</th>
        <th class="text-center">Issued At</th>
        <th class="text-center">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($tickets as $t)
      <tr>
        <td class="text-right">{{ $t->ticket_number }}</td>
        <td>{{ $t->enforcer->fname }}</td>
        <td>{{ $t->violator->name }}</td>
        <td>{{ $t->violation_names }}</td>
        <td>{{ $t->location }}</td>
        <td>{{ $t->issued_at->format('Y-m-d H:i') }}</td>
        
        
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
  
<div class="mt-3 justify-content-center d-flex position-sticky" >
  {{ $tickets->links() }}
</div>

  