

@extends('components.violator')
@section('title', 'POSO Digital Ticket')
@section('violator')
<div class="container-fluid mt-4">
    <h2 class="mb-4">Active Ticket</h2>
    @include('admin.partials.violatorTickets')
    
    <h2 class="mb-4 mt-4">Completed</h2>
    
</div>
@endsection