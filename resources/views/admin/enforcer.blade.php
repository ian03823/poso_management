@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')

<div class="container mt-4">
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
              <select name="sort"
                      id="sort_table"
                      class="form-select">
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
          </div>
        </form>
  
    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
  
    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif


    <div id="enforcerContainer">
        @include('admin.partials.enforcerTable')
    </div>
    
</div>
    @include('admin.modals.editEnforcer')

@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/sweetalerts.js') }}"></script>
    <script src="{{ asset('js/update-modal.js') }}"></script>
    <script src="{{ asset('js/ajax.js') }}"></script>
    <script src="{{ asset('js/enforcer-filter.js') }}"></script>
@endpush
  
