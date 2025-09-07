@extends('components.layout')
@section('title', 'POSO Admin Management')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/admin-violationTable.css') }}">
@endpush  

@section('content')
<div class="container-fluid mt-4" id="violations-page">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="m-0">Violation List</h2>
    <a href="{{ route('violation.create') }}" class="btn btn-success" data-ajax>
      <i class="bi bi-list-check"></i> Add Violation
    </a>
  </div>

  {{-- Toolbar --}}
  <form method="GET" id="filterForm" onsubmit="return false;" class="vio-toolbar mb-3">
    <div class="row g-2 align-items-center">
      <div class="col-auto">
        <label class="col-form-label fw-semibold">Category:</label>
      </div>
      <div class="col-auto">
        <select name="category" class="form-select" id="category_filter">
          <option value="all" {{ $categoryFilter==='all'?'selected':'' }}>All</option>
          @foreach($categories as $cat)
            <option value="{{ $cat }}" {{ $categoryFilter===$cat?'selected':'' }}>{{ $cat }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-auto ms-auto">
        <label for="search_input" class="col-form-label fw-semibold">Search:</label>
      </div>
      <div class="col-auto">
        <input type="text" id="search_input" name="search" class="form-control"
               placeholder="Code or name…" value="{{ $search ?? '' }}">
      </div>
      <div class="col-auto">
        <button type="button" id="search_btn" class="btn btn-primary">Go</button>
      </div>
    </div>
  </form>

  {{-- Table Card --}}
  <div class="vio-card">
    <div id="violationContainer">
      @include('admin.partials.violationTable')
    </div>
  </div>

</div>
@endsection 

@push('modals')
  @include('admin.modals.editViolation')
@endpush

@push('scripts')
  <script>window.violationPartialUrl = "{{ route('violation.partial') }}";</script>
  <script src="{{ asset('js/violationTable.js') }}"></script>
@endpush
