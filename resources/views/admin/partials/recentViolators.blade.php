<div class="card h-100 shadow-sm">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Recent Violators</h5>
    <a href="{{ url('/violatorTable') }}" class="small text-decoration-none" data-ajax>View more</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead class="table-light">
        <tr>
          <th>Name</th>
          <th>License #</th>
          <th>Issued</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentViolators as $v)
          <tr>
            <td>{{ $v->first_name }} {{ $v->middle_name }} {{ $v->last_name }}</td>
            <td>{{ $v->license_number }}</td>
            <td>{{ $v->created_at->format('d M Y') }}</td>
          </tr>
        @empty
          <tr><td colspan="3" class="text-center py-3">No violators yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
