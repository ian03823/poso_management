@php use App\Models\TicketStatus; @endphp
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
        <td>{{ $t->violator->first_name }} {{ $t->violator->middle_name }} {{ $t->violator->last_name }}</td>
        <td>{{ $t->violation_names }}</td>
        <td>{{ $t->location }}</td>
        <td>{{ $t->issued_at->format('Y-m-d H:i') }}</td>

        <td>
          <select class="form-select form-select-sm status-select" 
            data-ticket-id="{{ $t->id }}"
            data-current-status-id="{{ $t->status_id }}"
          >
            @foreach(TicketStatus::all() as $status)
              <option
                value="{{ $status->id }}"
                {{ $t->status_id == $status->id ? 'selected' : '' }}
              >
                {{ ucfirst($status->name) }}
              </option>
            @endforeach
          </select>
        </td>
      </tr>
    @empty  
      <tr>
        <td colspan="7" class="text-center">No tickets found.</td>
      </tr>
    @endforelse
    </tbody>
  </table>
  
<div class="mt-3 justify-content-center d-flex position-sticky" >
  {{ $tickets->links() }}
</div>

  