

@extends('components.violator')
@section('title', 'POSO Digital Ticket')
@section('violator')
<div class="container-fluid mt-4">
    <h2 class="mb-4">My Ticket(s)</h2>
    @include('admin.partials.violatorTickets')
    
</div>
@endsection