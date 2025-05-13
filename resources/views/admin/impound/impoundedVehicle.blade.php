@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')
<div class="container-fluid mt-4">
    <h2 class="mb-3">Impounded Vehicle</h2>

    

    <div class="">
        @include('admin.partials.impoundTable')
    </div>
</div>
@endsection

@push('scripts')

@endpush