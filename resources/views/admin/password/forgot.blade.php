@extends('components.layout')
@section('title','POSO Admin Management - Forgot Password')

@section('content')
<div class="container py-5">
  <div class="mx-auto bg-white rounded shadow p-4" style="max-width:420px">
    <h1 class="h5 mb-3 fw-bold">Forgot your password?</h1>
    @if(session('status')) <div class="alert alert-info">{{ session('status') }}</div> @endif
    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.password.forgot.submit') }}" class="space-y-4">
      @csrf
      <label class="form-label">Email address</label>
      <input type="email" name="email" class="form-control" required placeholder="admin@yourdomain.com">
      <button class="btn btn-primary w-100 mt-3">Send reset link</button>
    </form>
    <div class="text-center mt-3">
      <a href="{{ route('admin.showLogin') }}" class="small">Back to login</a>
    </div>
  </div>
</div>
@endsection
