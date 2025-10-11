@php
  use App\Models\TicketStatus;
  $statusFilter = request('status'); // 'paid' | 'unpaid' | 'pending' | 'cancelled' | null
@endphp

@if($statusFilter === 'paid')
  <table class="table table-hover align-middle mb-0">
    <thead class="small text-uppercase">
      <tr>
        <th class="text-center" style="width:110px;">Ticket #</th>
        <th>Enforcer</th>
        <th>Violator</th>
        <th>Reference #</th>
        <th>Paid At</th>
        <th>Location</th>
        <th class="text-center">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($tickets as $t)
      <tr>
        <td class="text-center fw-semibold">{{ $t->ticket_number }}</td>
        <td>{{ $t->enforcer->fname }}</td>
        <td>{{ $t->violator->first_name }} {{ $t->violator->middle_name }} {{ $t->violator->last_name }}</td>
        <td class="fw-semibold">{{ $t->paid_reference ?? '—' }}</td>
        <td>{{ $t->paid_at ? \Carbon\Carbon::parse($t->paid_at)->format('Y-m-d H:i') : '—' }}</td>
        <td class="text-wrap">{{ $t->location }}</td>
        <td class="text-center">
          <span class="badge bg-success-subtle text-success border-success-subtle px-3 py-2">Paid</span>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center py-4 text-muted">No paid tickets found.</td></tr>
      @endforelse
    </tbody>
  </table>
@else
  <table class="table table-hover align-middle mb-0">
    <thead class="small text-uppercase">
      <tr>
        <th class="text-center" style="width:110px;">Ticket #</th>
        <th>Enforcer</th>
        <th>Violator</th>
        <th>Violation(s)</th>
        <th>Location</th>
        <th class="col-issued">Issued At</th>
        <th class="col-status text-center">Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($tickets as $t)
      <tr>
        <td class="text-center fw-semibold">{{ $t->ticket_number }}</td>
        <td>{{ $t->enforcer->fname }}</td>
        <td>{{ $t->violator->first_name }} {{ $t->violator->middle_name }} {{ $t->violator->last_name }}</td>
        <td class="text-wrap">{{ $t->violation_names }}</td>
        <td class="text-wrap">{{ $t->location }}</td>
        <td>{{ $t->issued_at->format('Y-m-d H:i') }}</td>
        <td>
          <select class="form-select status-select"
            data-ticket-id="{{ $t->id }}"
            data-current-status-id="{{ $t->status_id }}">
            @foreach(TicketStatus::all() as $status)
              <option value="{{ $status->id }}" {{ $t->status_id == $status->id ? 'selected' : '' }}>
                {{ ucfirst($status->name) }}
              </option>
            @endforeach
          </select>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center py-4 text-muted">No tickets found.</td></tr>
      @endforelse
    </tbody>
  </table>
@endif

<div class="vtr-pager d-flex justify-content-between align-items-center mt-3">
    <div class="d-none d-md-block small text-muted">
      Showing {{ $tickets->firstItem() }} to {{ $tickets->lastItem() }} of {{ $tickets->total() }} results
    </div>
    <div class="w-100 d-flex justify-content-center">
      {{ $tickets->links() }}
    </div>
  </div>
</div>
