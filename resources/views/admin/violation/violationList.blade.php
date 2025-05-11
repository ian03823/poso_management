@extends('components.layout')
@section('title', 'POSO Admin Management')  
@section('content')
<div class="container-fluid mt-4">
    <h2 class="mb-3">Violation List</h2>
  
    <a href="{{ route('violation.create') }}"
       class="btn btn-success mb-3" data-ajax>
      <i class="bi bi-list-check"></i> Add Violation
    </a>
  
    {{-- ▶ Filter Form --}}
    <form method="GET" id="filterForm" onsubmit="return false;" class="mb-4">
        <div class="row g-2 align-items-center justify-content-between d-flex">
          <div class="col-auto">
            <label class="col-form-label fw-semibold">
              Sort by:
            </label>
          </div>
          <div class="col-auto">
            <select name="category" class="form-select" id="category_filter">
                    <option value="all" {{ $categoryFilter==='all'?'selected':'' }}>
                      All
                    </option>
                    @foreach($categories as $cat)
                      <option value="{{ $cat }}"
                        {{ $categoryFilter===$cat?'selected':'' }}>
                        {{ $cat }}
                      </option>
                    @endforeach
            </select>
          </div>
          <div class="col-5 justify-content-end d-flex">
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
        </div>
      </form>

      <div id="violationContainer">
        @include('admin.partials.violationTable')
      </div>

      @include('admin.modals.editViolation')

</div>
@endsection

@push('scripts')
  
  <script src="{{ asset('js/updateViolation.js') }}"></script>
  <script src="{{ asset('js/sweetalerts.js') }}"></script>
  <script src="{{ asset('js/violationTable.js') }}"></script>
  

@endpush
