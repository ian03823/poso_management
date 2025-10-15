<div class="card h-100 shadow-sm">
  <div class="card-header bg-white d-flex align-items-center justify-content-between">
    <h5 class="mb-0"><i class="bi bi-receipt-cutoff me-2"></i>Recent Ticket</h5>
    <a href="{{ url('/ticket') }}" class="small text-decoration-none" data-ajax>View more</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Violator</th>
          <th>Enforcer</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recentTickets as $t)
          <tr>
            <td>{{ $t->ticket_number }}</td>
            <td>{{ $t->violator->first_name }} {{ $t->violator->middle_name }} {{ $t->violator->last_name }}</td>
            <td>{{ $t->enforcer->fname.' '.$t->enforcer->lname }}</td>
            <td>{{ \Carbon\Carbon::parse($t->issued_at)->format('d M Y, g:i A') }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center py-3">No tickets issued.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
