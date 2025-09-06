@php use App\Models\TicketStatus; @endphp

<table class="table table-hover align-middle mb-0">
  <thead class="small text-uppercase">
    <tr>
      <th class="text-center" style="width:110px;">Ticket #</th>
      <th style="min-width:160px;">Enforcer</th>
      <th style="min-width:220px;">Violator</th>
      <th style="min-width:260px;">Violation(s)</th>
      <th style="min-width:180px;">Location</th>
      <th style="min-width:160px;">Issued At</th>
      <th style="width:190px;">Status</th>
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
        <select
          class="form-select form-select-sm status-select"
          data-ticket-id="{{ $t->id }}"
          data-current-status-id="{{ $t->status_id }}"
        >
          @foreach(TicketStatus::all() as $status)
            <option value="{{ $status->id }}" {{ $t->status_id == $status->id ? 'selected' : '' }}>
              {{ ucfirst($status->name) }}
            </option>
          @endforeach
        </select>
      </td>
    </tr>
    @empty
    <tr>
      <td colspan="7" class="text-center py-4 text-muted">No tickets found.</td>
    </tr>
    @endforelse
  </tbody>
</table>

<div class="p-3 d-flex justify-content-center">
  {{ $tickets->links() }}
</div>
