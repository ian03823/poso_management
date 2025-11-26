@extends('components.app')

@section('title', 'POSO Digital Ticket - View Issued Tickets')

@section('body')
<div class="container-fluid bg-light min-vh-100 p-3">

  {{-- Page header --}}
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
    <div>
      <h1 class="h4 mb-1">Issued Tickets History</h1>
      <small class="text-muted">
        Tickets issued under your badge number.
      </small>
    </div>
    <div class="mt-2 mt-md-0">
      <a href="{{ url('/enforcerTicket') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
      </a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">

      {{-- Filters --}}
      <form method="GET" class="row g-2 mb-3">
        <div class="col-sm-4">
          <label class="form-label small mb-1">Status</label>
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach(\App\Models\TicketStatus::all() as $st)
              <option value="{{ $st->name }}" {{ request('status') === $st->name ? 'selected' : '' }}>
                {{ ucfirst($st->name) }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-sm-4">
          <label class="form-label small mb-1">Sort by date</label>
          <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="date_desc" {{ request('sort', 'date_desc') === 'date_desc' ? 'selected' : '' }}>
              Newest first
            </option>
            <option value="date_asc" {{ request('sort') === 'date_asc' ? 'selected' : '' }}>
              Oldest first
            </option>
          </select>
        </div>
      </form>

      {{-- Table --}}
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="small text-uppercase">
            <tr>
              <th class="text-center" style="width:110px;">Ticket #</th>
              <th>Violator</th>
              <th>Plate</th>
              <th>Location</th>
              <th>Issued At</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($tickets as $t)
              @php
                $statusName = optional($t->status)->name ?? 'unknown';
              @endphp
              <tr>
                <td class="text-center fw-semibold">{{ $t->ticket_number }}</td>
                <td>
                  {{ optional($t->violator)->first_name }}
                  {{ optional($t->violator)->middle_name }}
                  {{ optional($t->violator)->last_name }}
                </td>
                <td>{{ optional($t->vehicle)->plate_number ?? '—' }}</td>
                <td class="text-wrap">{{ $t->location }}</td>
                <td>
                  {{ optional($t->issued_at)->timezone('Asia/Manila')->format('Y-m-d H:i') }}
                </td>
                <td class="text-center">
                  <span class="badge
                    @if($statusName === 'paid')
                      bg-success-subtle text-success border-success-subtle
                    @elseif($statusName === 'unpaid')
                      bg-danger-subtle text-danger border-danger-subtle
                    @elseif($statusName === 'pending')
                      bg-warning-subtle text-warning border-warning-subtle
                    @else
                      bg-secondary-subtle text-secondary border-secondary-subtle
                    @endif
                  px-3 py-2">
                    {{ ucfirst($statusName) }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                  You haven’t issued any tickets yet.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Pagination --}}
      <div class="mt-3 d-flex justify-content-between align-items-center">
        <div class="small text-muted d-none d-md-block">
          @if($tickets->total())
            Showing {{ $tickets->firstItem() }} to {{ $tickets->lastItem() }} of {{ $tickets->total() }} tickets
          @else
            No tickets to show.
          @endif
        </div>
        <div class="w-100 d-flex justify-content-center">
          {{ $tickets->links() }}
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
