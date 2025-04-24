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


    <div id="enforcer-content">
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
@endpush
  
