@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')
<div class="container-fluid mt-4" id="ticketContainer">
  <h2 class="mb-3">All Issued Tickets - Recently</h2>

  <a href="/ticket/create"
      class="btn btn-success mb-3" data-ajax>
    <i class="bi bi-receipt-cutoff me-3"></i>Add Ticket
  </a>

    <div id="ticket-sort-form" class="row mb-4">
      <div class="col-auto">
        <label for="ticket-sort" class="col-form-label fw-semibold">Sort by:</label>
      </div>
      <div class="col-auto">
        <select id="ticket-sort" class="form-select">
          <option value="date_desc" {{ $sortOption==='date_desc'?'selected':'' }}>
            Date (Newest First)
          </option>
          <option value="date_asc" {{ $sortOption==='date_asc'?'selected':'' }}>
            Date (Oldest First)
          </option>
          <option value="name_asc" {{ $sortOption==='name_asc'?'selected':'' }}>
            Violator A → Z
          </option>
          <option value="name_desc" {{ $sortOption==='name_desc'?'selected':'' }}>
            Violator Z → A
          </option>
        </select>
      </div>
    </div>

    {{-- pulls in the table partial with $tickets --}}
    <div id="ticket-table">
      @include('admin.partials.ticketTable')
    </div>
  </div>

@endsection

@push('scripts')
  <script>const ticketPartialUrl = @json(route('ticket.partial'));</script>
  <script src="{{ asset('js/sweetalerts.js') }}"></script>
  <script src="{{ asset('js/ajax.js') }}"></script>
  <script src="{{ asset('js/ticketTable.js') }}"></script>

@endpush