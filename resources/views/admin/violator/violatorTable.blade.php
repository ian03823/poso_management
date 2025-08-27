@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')

<div class="container-fluid mt-4">
    <h2 class="mb-3">Violator List</h2>

    <form id="filterForm" onsubmit="return false;" class="row g-2 mb-4">
      <!-- Sort -->
      <div class="col-auto">
        <label class="form-label" for="sort_table">Sort by:</label>
        <select name="category" id="category_filter" class="form-select">
          <option value="date_desc" {{ $sortOption==='date_desc'?'selected':'' }}>
            Date (Newest)
          </option>
          <option value="date_asc" {{ $sortOption==='date_asc'?'selected':'' }}>
            Date (Oldest)
          </option>
          <option value="name_asc" {{ $sortOption==='name_asc'?'selected':'' }}>
            Name A→Z
          </option>
          <option value="name_desc" {{ $sortOption==='name_desc'?'selected':'' }}>
            Name Z→A
          </option>
        </select>
      </div>
  
      <!-- Vehicle Type -->
      <div class="col-auto">
        <label class="form-label" for="vehicle_type">Vehicle:</label>
        <select id="vehicle_type" class="form-select">
          <option value="all" {{ $vehicleType==='all'?'selected':'' }}>All</option>
          @foreach($vehicleTypes as $type)
            <option value="{{ $type }}" {{ $vehicleType===$type?'selected':'' }}>
              {{ $type }}
            </option>
          @endforeach
        </select>
      </div>
  
      <!-- Search -->
      <div class="col-auto">
        <label class="form-label" for="search_input">Search:</label>
        <input type="text" id="search_input" class="form-control"
               placeholder="Name, license, plate…" value="{{ $search }}">
      </div>
      <div class="col-auto align-self-end">
        <button id="search_btn" class="btn btn-primary">Go</button>
      </div>
    </form>
    

    <div id="violatorContainer">
        @include('admin.partials.violatorTable')
    </div>

</div>

{{-- Reference-Number Modal & JS globals --}}
@php
  $paidStatusId = \App\Models\TicketStatus::where('name','paid')->value('id');
@endphp
<script>
  window.PAID_STATUS_ID   = @json($paidStatusId);
  window.STATUS_UPDATE_URL = "{{ url('paid') }}";
  console.log('PAID_STATUS_ID=', window.PAID_STATUS_ID);
</script>

<div class="modal fade" id="refModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="refForm">
      @csrf
      <input type="hidden" id="ref_ticket_id" name="ticket_id">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Enter Reference Number</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="text"
                 class="form-control"
                 id="reference_number"
                 name="reference_number"
                 placeholder="Reference #"
                 required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="pwdModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="pwdForm">
      @csrf
      <input type="hidden" id="pwd_ticket_id" name="ticket_id">
      <input type="hidden" id="pwd_new_status" name="status_id">

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Admin Password Required</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label for="admin_password" class="form-label">Password</label>
          <input
            type="password"
            class="form-control"
            id="admin_password"
            name="admin_password"
            required
          >
        </div>
        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
      </div>
    </form>
  </div>
</div>


<div class="modal fade" id="violatorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Violator Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="violatorModalBody">
        <!-- loaded via AJAX -->
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('js/violatorTable.js') }}"></script>
    <script src="{{ asset('js/violatorView.js') }}"></script>
@endpush
