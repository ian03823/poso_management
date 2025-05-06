@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')

<div class="container mt-4">
    <h2 class="mb-3">Violator List</h2>

    {{-- <div class="row mb-3" id="violator-controls">
        <div class="col-auto">
          <label class="form-label" for="violator-sort">Sort by:</label>
          <select id="violator-sort" class="form-select">
            <option value="date_desc" {{ $sortOption==='date_desc'?'selected':'' }}>
              Date (Newest First)
            </option>
            <option value="date_asc"  {{ $sortOption==='date_asc' ?'selected':'' }}>
              Date (Oldest First)
            </option>
            <option value="name_asc"  {{ $sortOption==='name_asc' ?'selected':'' }}>
              Name A → Z
            </option>
            <option value="name_desc" {{ $sortOption==='name_desc'?'selected':'' }}>
              Name Z → A
            </option>
          </select>
        </div>
        <div class="col-auto">
          <label class="form-label" for="violator-search">Search:</label>
          <input type="text"
                 id="violator-search"
                 class="form-control"
                 placeholder="Name, license, plate…"
                 value="{{ $search }}">
        </div>
      </div> --}}

    <div id="violatorContainer">
        @include('admin.partials.violatorTable')
    </div>

</div>
@endsection

@push('scripts')


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>const violatorPartialUrl = @json(route('violatorTable.partial'));</script>
    <script src="{{ asset('js/sweetalerts.js') }}"></script>
    <script src="{{ asset('js/ajax.js') }}"></script>
    <script src="{{ asset('js/violatorTable.js') }}"></script>
@endpush