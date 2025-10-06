@extends('components.layout')
@section('title','POSO Admin Management - Forgot Password')
@section('content')
<div class="container py-5" style="max-width:520px">
  <h3 class="mb-3">Forgot your password?</h3>
  @if (session('status')) <div class="alert alert-info">{{ session('status') }}</div> @endif
  @if ($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div> @endif
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.password.forgot.submit') }}">
        @csrf
        <label class="form-label">Enter your email</label>
        <input type="email" name="email" class="form-control" required placeholder="admin@yourdomain.com">
        <button class="btn btn-primary mt-3 w-100">Continue</button>
      </form>
    </div>
  </div>
</div>
@endsection
