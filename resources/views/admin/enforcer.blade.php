@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')

<div class="container mt-4" id="enforcerContainer">
    <h2 class="mb-3">Enforcer List</h2>
  
    <a href="{{ url('/enforcer/create') }}"
            class="btn btn-success mb-3"
            data-ajax>
        <i class="bi bi-person-plus-fill"></i> Add Enforcer
    </a>

        <form method="GET" id="filterForm" onsubmit="return false;" class="mb-4">
          <div class="row g-2 align-items-center">
            <div class="col-auto">
              <label for="sort_option" class="col-form-label fw-semibold">
                Sort by:
              </label>
            </div>
            <div class="col-auto">  
              <select name="sort" id="sort_table" class="form-select">
                <option value="date_desc" {{ $sortOption==='date_desc'?'selected':'' }}>
                  Date Modified (Newest First)
                </option>
                <option value="date_asc" {{ $sortOption==='date_asc'?'selected':'' }}>
                  Date Modified (Oldest First)
                </option>
                <option value="name_asc" {{ $sortOption==='name_asc'?'selected':'' }}>
                  Name A → Z
                </option>
                <option value="name_desc" {{ $sortOption==='name_desc'?'selected':'' }}>
                  Name Z → A
                </option>
              </select>
            </div>
            <div class="col-6 justify-content-end d-flex">
              <label for="search_input" class="col-form-label fw-semibold">Search:</label>
            </div>
            <div class="col-auto">
              <input type="text" id="search_input" name="search" class="form-control" placeholder="Code or name…" value="{{ $search ?? '' }}">
            </div>
            <div class="col-auto">
              <button type="button" id="search_btn" name="search_btn" class="btn btn-primary">
                Go
              </button>
          </div>
        </form>

        
        
        <div id="table-container">
          @include('admin.partials.enforcerTable')
        </div>
       
</div>
      @include('admin.modals.editEnforcer')
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('js/update-modal.js') }}"></script>
    <script src="{{ asset('js/enforcer-ajax.js') }}"></script>

@endpush
  
