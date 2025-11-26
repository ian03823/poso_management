@php use App\Models\TicketStatus; @endphp

<div 
  class="container-fluid my-3" 
  id="violatorDetail"
  data-url="{{ route('violatorTable.show', $violator->id) }}"
>
  <a 
    href="{{ route('ticket.create', ['violator_id' => $violator->id]) }}" 
    class="btn btn-success mb-3">
    Add Violation
  </a>

  <h2 class="text-center">Violator’s Information</h2>
  <dl class="row p-3">
    <dt class="col-sm-3">Name</dt>
    <dd class="col-sm-9">{{ $violator->first_name }} {{ $violator->middle_name }} {{ $violator->last_name }}</dd>

    <dt class="col-sm-3">License Number</dt>
    <dd class="col-sm-9">{{ $violator->license_number }}</dd>

    <dt class="col-sm-3">Address</dt>
    <dd class="col-sm-9">{{ $violator->address }}</dd>
  </dl>

  <h3 class="mt-4">Ticket History</h3>
  <div class="table-responsive">
    <table class="table table-striped">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Issued At</th>
          <th>Location</th>
          <th>Violation(s)</th>
          <th>Vehicle Type</th>
          <th>Plate Number</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($violator->tickets as $ticket)
          <tr>
            <td>{{ $ticket->ticket_number }}</td>
            <td>{{ $ticket->issued_at->format('d M Y, H:i') }}</td>
            <td>{{ $ticket->location }}</td>
            <td>
                 @php
    // Build "Name [Archived]" for any soft-deleted violations
    $vioList = $ticket->violations->map(function($v){
      $name = $v->violation_name ?? 'Unnamed';
      $arch = (method_exists($v,'trashed') && $v->trashed()) ? ' [Archived]' : '';
      return $name.$arch;
    })->implode(', ');
  @endphp

  {{-- Primary: relation output (includes archived) --}}
  @if($vioList !== '')
    {{ $vioList }}
  @else
    {{-- Fallback: if relation is empty but violation_codes exists, decode & resolve WITH trashed --}}
    @php
      $codes = is_array($ticket->violation_codes)
        ? array_filter($ticket->violation_codes)
        : (array) (json_decode($ticket->violation_codes, true) ?: []);

      $names = [];
      if (!empty($codes)) {
        $byCode = \App\Models\Violation::withTrashed()
                  ->whereIn('violation_code', $codes)
                  ->get(['violation_code','violation_name','deleted_at'])
                  ->keyBy('violation_code');

        foreach ($codes as $c) {
          $row = $byCode->get($c);
          if ($row) {
            $names[] = $row->violation_name . ($row->deleted_at ? ' [Archived]' : '');
          }
        }
      }
      echo e(implode(', ', $names));
    @endphp
  @endif
            </td>
            <td>{{ $ticket->vehicle?->vehicle_type ?? 'N/A' }}</td>
            <td>{{ $ticket->vehicle?->plate_number ?? 'N/A' }}</td>
            <td style="min-width:140px;">
              <select
                class="form-select form-select-sm status-select"
                data-ticket-id="{{ $ticket->id }}"
                data-current-status-id="{{ $ticket->status_id }}"
              >
                @foreach(TicketStatus::all() as $status)
                  <option
                    value="{{ $status->id }}"
                    {{ $ticket->status_id == $status->id ? 'selected' : '' }}
                  >
                    {{ ucfirst($status->name) }}
                  </option>
                @endforeach
              </select>
            </td>
            <td class="text-center" style="min-width:130px;">
              <button
                type="button"
                class="btn btn-outline-primary btn-sm reprint-ticket-btn"
                data-url="{{ route('ticket.receipt', $ticket) }}"
              >
                <i class="bi bi-printer"></i> Reprint
              </button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center">No ticket history found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
