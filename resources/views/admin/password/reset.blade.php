@extends('components.layout')
@section('title','POSO Admin Management - Set New Password')
@section('content')
<div class="container py-5" style="max-width:520px">
  <h3 class="mb-3">Create a new password</h3>
  @if ($errors->any()) <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div> @endif
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.password.forgot.update') }}">
        @csrf
        <label class="form-label">New password</label>
        <input type="password" name="password" class="form-control" required minlength="8" placeholder="At least 8 characters">
        <label class="form-label mt-3">Confirm password</label>
        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
        <button class="btn btn-success mt-3 w-100">Save password</button>
      </form>
    </div>
  </div>
</div>
@endsection
