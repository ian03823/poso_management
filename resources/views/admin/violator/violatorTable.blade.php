@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')
@php
  $paidStatusId = \App\Models\TicketStatus::where('name','paid')->value('id');
  $paidId      = \App\Models\TicketStatus::where('name','paid')->value('id');
  $pendingId   = \App\Models\TicketStatus::where('name','pending')->value('id');
  $unpaidId    = \App\Models\TicketStatus::where('name','unpaid')->value('id');
  $cancelledId = \App\Models\TicketStatus::where('name','cancelled')->value('id');
@endphp
<div  class="container-fluid mt-4"
      id="violatorPage"
      data-page="violator"
      data-status-url="{{ url('paid') }}"
      data-partial-url="{{ route('violatorTable.partial') }}"
      data-paid-id="{{ $paidId }}"
      data-pending-id="{{ $pendingId }}"
      data-unpaid-id="{{ $unpaidId }}"
      data-cancelled-id="{{ $cancelledId }}">
    <h2 class="mb-3">Violator List</h2>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch gap-2 mb-4">
    {{-- Left: Sort --}}
    <div>
      <label class="form-label small mb-1" for="sort_table">Sort by:</label>
      <select id="sort_table" class="form-select form-select-sm" style="min-width:220px">
        <option value="date_desc" {{ $sortOption==='date_desc'?'selected':'' }}>Date (Newest)</option>
        <option value="date_asc"  {{ $sortOption==='date_asc' ?'selected':'' }}>Date (Oldest)</option>
        <option value="name_asc"  {{ $sortOption==='name_asc' ?'selected':'' }}>Name A→Z</option>
        <option value="name_desc" {{ $sortOption==='name_desc'?'selected':'' }}>Name Z→A</option>
      </select>
    </div>

    {{-- Right: Search --}}
    <div class="d-flex align-items-end gap-2 justify-content-md-end">
      <div>
        <label class="form-label small mb-1" for="search_input">Search:</label>
        <input type="text" id="search_input" class="form-control"
              placeholder="Name, license, plate…" value="{{ $search }}"
              style="width:320px;max-width:100%;">
      </div>
      <button id="search_btn" class="btn btn-primary mb-1">Go</button>
    </div>
  </div>
    

    <div id="violatorContainer">
        @include('admin.partials.violatorTable')
    </div>

    <!-- KEEP ONLY THIS MODAL (history/details) -->
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
</div>
@endsection