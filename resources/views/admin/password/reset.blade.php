@extends('components.layout')
@section('title','POSO Admin Management - Reset Password')

@section('content')
<div class="container py-5">
  <div class="mx-auto bg-white rounded shadow p-4" style="max-width:420px">
    <h1 class="h5 mb-3 fw-bold">Set a new password</h1>
    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.password.reset') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <input type="hidden" name="email" value="{{ $email }}">

      <label class="form-label">New password</label>
      <input type="password" name="password" class="form-control" required minlength="8" placeholder="At least 8 characters">

      <label class="form-label mt-3">Confirm password</label>
      <input type="password" name="password_confirmation" class="form-control" required minlength="8">

      <button class="btn btn-success w-100 mt-3">Update password</button>
    </form>

    <div class="text-center mt-3">
      <a href="{{ route('admin.showLogin') }}" class="small">Back to login</a>
    </div>
  </div>
</div>
@endsection
