@extends('components.layout')
@section('title', 'POSO Admin Management')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/admin-ticketTable.css') }}">
@endpush

@section('content')

<div class="container-fluid mt-4 position-relative" id="ticketContainer">

  {{-- Loading overlay --}}
  <div class="loading-overlay" id="ticketLoading">
    <div class="spinner-border" role="status" aria-hidden="true"></div>
  </div>

  {{-- Header / Toolbar --}}
  <div class="toolbar mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h2 class="mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-clipboard2-check-fill"></i>
        All Issued Tickets
      </h2>
      <div class="subtitle small">Recently created • Manage & update statuses</div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <a href="/ticket/create" class="btn btn-light text-success fw-semibold" data-ajax>
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
        <option value="name_asc"  {{ $sortOption==='name_asc'?'selected':'' }}>Violator A → Z</option>
        <option value="name_desc" {{ $sortOption==='name_desc'?'selected':'' }}>Violator Z → A</option>
      </select>
    </div>
  </div>

  {{-- Table card --}}
  <div class="card-shell bg-white">
    <div class="table-responsive" id="ticket-table">
      @include('admin.partials.ticketTable')
    </div>
  </div>
</div>

@php
  $paidStatusId = \App\Models\TicketStatus::where('name','paid')->value('id');
@endphp

<script>
  window.PAID_STATUS_ID    = @json($paidStatusId);
  window.STATUS_UPDATE_URL = "{{ url('ticket') }}"; // base /ticket
  window.ticketPartialUrl  = @json(route('ticket.partial')); // for partial reload
</script>
@push('modals')
    {{-- Reference-Number Modal --}}
<div class="modal fade" id="ticketRefModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="ticketRefForm" class="modal-content">
      @csrf
      <input type="hidden" id="ref_ticket_id" name="ticket_id">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-hash me-2"></i>Enter Reference Number</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="refCancel"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="reference_number" name="reference_number" class="form-control" placeholder="Reference #" required>
        <div class="form-text">Required when marking a ticket as <strong>Paid</strong>.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="refCancel2">Cancel</button>
        <button type="submit" class="btn btn-success">Continue</button>
      </div>
    </form>
  </div>
</div>

{{-- Admin-Password Modal --}}
<div class="modal fade" id="ticketPwdModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="ticketPwdForm" class="modal-content">
      @csrf
      <input type="hidden" id="pwd_ticket_id" name="ticket_id">
      <input type="hidden" id="pwd_new_status" name="status_id">
      <input type="hidden" id="pwd_reference_number" name="reference_number">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Admin Password Required</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="pwdCancel"></button>
      </div>
      <div class="modal-body">
        <label for="admin_password" class="form-label">Password</label>
        <input type="password" id="admin_password" name="admin_password" class="form-control" required>
        <div class="invalid-feedback" id="pwdError" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="pwdCancel2">Cancel</button>
        <button type="submit" class="btn btn-primary" id="pwdSubmitBtn">Confirm</button>
      </div>
    </form>
  </div>
</div>
@endpush

@endsection

@push('scripts')
  <script src="{{ asset('js/ticketTable.js') }}"></script>
@endpush
