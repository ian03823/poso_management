{{-- resources/views/violator/phone.blade.php --}}
@extends('components.violator')
@section('title','POSO Digital Ticket - Phone Verification')

@section('violator')
<div class="container py-4" style="max-width:560px">
  <h3 class="mb-3">Verify your phone</h3>

  @if (session('status'))
    <div class="alert alert-info">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <form class="mb-3" method="POST" action="{{ route('violator.phone.submit') }}" id="phone-form">
        @csrf
        <label class="form-label">Mobile Number</label>
        <input type="tel" name="phone_number" value="{{ old('phone_number', $phone) }}"
               class="form-control" placeholder="e.g., 09XXXXXXXXX or +639XXXXXXXXX" required>
        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-primary">Save & Send OTP</button>
        </div>
      </form>

      @if($hasPhone && !$isVerified)
        <hr>
        <form method="POST" action="{{ route('violator.otp.verify') }}" id="otp-form">
          @csrf
          <label class="form-label">Enter 6-digit OTP</label>
          <input type="text" name="otp" maxlength="6" class="form-control" inputmode="numeric" autocomplete="one-time-code" required>
          <div class="d-flex align-items-center gap-2 mt-3">
            <button class="btn btn-success">Verify</button>
          </div>
        </form>
        <form method="POST" action="{{ route('violator.otp.resend') }}" class="ms-2">
            @csrf
          <button class="btn btn-outline-secondary" formaction="{{ route('violator.otp.resend') }}">Resend OTP</button>
        </form>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('js/violatorPhone.js') }}"></script>
@endpush
