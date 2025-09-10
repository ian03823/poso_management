@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')
@php
  $paidStatusId = \App\Models\TicketStatus::where('name','paid')->value('id');
@endphp
<div  class="container-fluid mt-4"
      id="violatorPage"
      data-page="violator"
      data-partial-url="{{ route('violatorTable.partial') }}"
      data-status-url="{{ url('paid') }}"
      data-paid-id="{{ $paidStatusId }}">
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
  
      {{-- Search --}}
      <div class="col-auto">
        <label class="form-label" for="search_input">Search:</label>
        <input type="text" id="search_input" class="form-control"
              placeholder="Name, license, plate…" value="{{ $search }}">
      </div>
      <div class="col-auto align-self-end">
        <button id="search_btn" class="btn btn-primary" type="button">Go</button>
      </div>
    </form>
    

    <div id="violatorContainer">
        @include('admin.partials.violatorTable')
    </div>

</div>
@endsection