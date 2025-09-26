@extends('components.layout')
@section('title', 'POSO Admin Management - Issued Tickets')

@section('content')
<div class="container-fluid mt-4" id="ticketContainer"
  data-page="ticket"
  data-paid-status-id="{{ \App\Models\TicketStatus::where('name','paid')->value('id') }}"
  data-status-update-url="{{ url('ticket') }}"
  data-ticket-partial-url="{{ route('ticket.partial') }}"
  data-violations-by-cat-url="{{ route('violations.byCategory') }}">

  {{-- local loader used by ticketTable.js during partial refreshes --}}
  <div class="loading-overlay" id="ticketLoading" style="display:none;">
    <div class="spinner-border" role="status" aria-hidden="true"></div>
  </div>

  {{-- Header / Toolbar --}}
  <div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h2 class="mb-1 d-flex align-items-center gap-2">Issued Tickets</h2>
      <div class="subtitle small text-muted">Filter by status and violation.</div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-secondary" id="btn-filter" data-bs-toggle="modal" data-bs-target="#ticketFilterModal">
        <i class="bi bi-funnel me-2"></i>Filter
      </button>
      <button class="btn btn-outline-dark" id="btn-reset-filters">
        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
      </button>
      <a href="{{route('admin.tickets.create')}}" class="btn btn-light text-success fw-semibold" data-ajax>
        <i class="bi bi-receipt-cutoff me-2"></i>Cite A Ticket
      </a>
    </div>
  </div>

  {{-- Sort row --}}
  <div id="ticket-sort-form" class="row g-2 mb-3">
    <div class="col-auto">
      <label for="ticket-sort" class="col-form-label fw-semibold">Sort by</label>
    </div>
    <div class="col-auto">
      <select id="ticket-sort" class="form-select">
        <option value="date_desc" {{ $sortOption==='date_desc'?'selected':'' }}>Date (Newest First)</option>
        <option value="date_asc"  {{ $sortOption==='date_asc'?'selected':'' }}>Date (Oldest First)</option>
      </select>
    </div>
    {{-- current filter chips (optional, simple text) --}}
    <div class="col d-flex align-items-center small text-muted" id="active-filters"></div>
  </div>

  {{-- Table card --}}
  <div class="card-shell bg-white">
    <div class="table-responsive swap-area fade-in" id="ticket-table">
      @include('admin.partials.ticketTable', ['paidOnly' => false])
    </div>
  </div>

  {{-- Filter Modal --}}
  <div class="modal fade" id="ticketFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-funnel me-2"></i>Filter Tickets</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="ticket-filter-form">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Status</label>
              <select name="status" id="filter-status" class="form-select">
                <option value="">All</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="pending">Pending</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Violation Category</label>
              <select name="category" id="filter-category" class="form-select">
                <option value="">All</option>
                @foreach($violationCategories as $cat)
                  <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-1">
              <label class="form-label fw-semibold">Violation</label>
              <select name="violation_id" id="filter-violation" class="form-select" disabled>
                <option value="">All</option>
              </select>
              <div class="form-text">Choose a category first to load specific violations.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check2 me-1"></i>Apply Filters
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @php $paidStatusId = \App\Models\TicketStatus::where('name','paid')->value('id'); @endphp
</div>
@endsection
