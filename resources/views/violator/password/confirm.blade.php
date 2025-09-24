@extends('components.violator')
@section('title','POSO Digital Ticket | Confirm Email')

@section('violator')
<div class="container py-4" style="max-width:520px">
  <h3 class="mb-3">Is this you?</h3>
  @if ($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div> @endif
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $name ?: '—' }}</dd>
        <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $address ?: '—' }}</dd>
        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $email ?: '—' }}</dd>
      </dl>
      <form method="POST" action="{{ route('violator.password.forgot.sendOtp') }}" class="mt-3">
        @csrf
        <button class="btn btn-success">Yes, it’s me</button>
        <a href="{{ route('violator.password.forgot.request') }}" class="btn btn-outline-secondary ms-2">No, go back</a>
      </form>
    </div>
  </div>
</div>
@endsection
