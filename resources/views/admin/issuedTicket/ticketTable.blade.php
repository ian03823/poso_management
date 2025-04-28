@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')

  <div class="container py-4">
    <h1 class="h4 mb-4">All Issued Tickets</h1>

    {{-- pulls in the table partial with $tickets --}}
    <div class="ticketContainer">
      @include('admin.partials.ticketTable')
    </div>
  </div>


@endsection

@push('scripts')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="{{ asset('js/sweetalerts.js') }}"></script>
  <script src="{{ asset('js/ajax.js') }}"></script>
  <script src="{{ asset('js/ticketTable.js') }}"></script>
  <script src="{{ asset('js/ticket-filter.js') }}"></script>

@endpush