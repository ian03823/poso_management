@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')
<div class="container mt-4">
    <h2 class="mb-3">Violation List</h2>
  
    <a href="{{ route('violation.create') }}"
       class="btn btn-success mb-3" data-ajax>
      <i class="bi bi-list-check"></i> Add Violation
    </a>
  
    {{-- ▶ Filter Form --}}
    <form method="GET" action="{{ route('violation.index') }}" class="mb-4">
        <div class="row g-2 align-items-center">
          <div class="col-auto">
            <label for="sort_option" class="col-form-label fw-semibold">
              Sort by:
            </label>
          </div>
          <div class="col-auto">
            <select name="sort_option"
                    id="sort_option"
                    class="form-select"
                    onchange="this.form.submit()">
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
  
    <table class="table table-bordered">
      <thead>
        <tr>
          <th class="text-center">Code</th>
          <th class="text-center">Name</th>
          <th class="text-center">Fine Amount</th>
          <th class="text-center">Category</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($violation as $violations)
          <tr>
            <td>{{ $violations->violation_code }}</td>
            <td>{{ $violations->violation_name }}</td>
            <td>₱{{ number_format($violations->fine_amount, 2) }}</td>
            <td>{{ $violations->category }}</td>
            <td class="text-center">
              <a href="{{ route('violation.edit', $violations->id) }}"
                 class="btn btn-warning btn-sm">Edit
                </a>
  
              <form action="violation/{{ $violations->id }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="btn btn-danger btn-sm delete-btn" data-name="{{ $violations->violation_name }} Violation">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center">No records found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  
    {{-- ▶ Pagination links --}}
    {{ $violation->links() }}
  </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="{{ asset('js/sweetalerts.js') }}"></script>
  <script src="{{ asset('js/ajax.js') }}"></script>
@endsection
