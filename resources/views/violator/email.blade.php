@extends('components.violator')
@section('title','POSO Digital Ticket | Register Email')

@section('violator')
<div class="container py-4" style="max-width:560px">
  <h3 class="mb-3">Register your email</h3>

  @if (session('status')) <div class="alert alert-info">{{ session('status') }}</div> @endif
  @if (session('ok'))     <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form method="POST" action="{{ route('violator.email.save') }}">
        @csrf
        <label class="form-label">Email address</label>
        <input type="email" name="email" value="{{ old('email',$email) }}" class="form-control" placeholder="you@example.com" required>
        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-primary">Save Email</button>
        </div>
      </form>

      @if ($email && !$isVerified)
        <hr>
        <form method="POST" action="{{ route('violator.email.verify') }}" class="mb-2">
          @csrf
          <label class="form-label">Enter 6-digit code</label>
          <input type="text" name="otp" maxlength="6" inputmode="numeric" class="form-control" required>
          <button class="btn btn-success mt-3">Confirm Email</button>
        </form>
        <form method="POST" action="{{ route('violator.email.resend') }}">
          @csrf
          <button class="btn btn-outline-secondary">Resend Code</button>
        </form>
      @elseif ($email && $isVerified)
        <div class="alert alert-success mt-3">Your email is verified.</div>
      @endif
    </div>
  </div>
</div>
@endsection
