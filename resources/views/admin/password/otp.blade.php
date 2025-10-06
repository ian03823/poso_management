@extends('components.layout')
@section('title','POSO Admin Management - Code Verification')
@section('content')
<div class="container py-5" style="max-width:520px">
  <h3 class="mb-3">Enter the 6-digit code</h3>
  @if (session('status')) <div class="alert alert-info">{{ session('status') }}</div> @endif
  @if ($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div> @endif
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.password.forgot.verify') }}">
        @csrf
        <input type="text" name="otp" maxlength="6" inputmode="numeric" class="form-control" placeholder="123456" required>
        <button class="btn btn-primary mt-3">Verify</button>
      </form>
    </div>
  </div>
</div>
@endsection
