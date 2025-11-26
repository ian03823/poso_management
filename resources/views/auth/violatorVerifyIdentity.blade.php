@extends('components.violator')

@section('title', 'POSO Digital Ticket - Verify Identity')

@section('violator')
<div class="d-flex flex-column min-vh-100 justify-content-center align-items-center bg-light login-container">
  <div class="card login-card shadow-lg" style="width: 90%; max-width: 420px; border-radius: 12px;">
    <div class="card-body p-4">

      {{-- Header --}}
      <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill display-6 text-success mb-2"></i>
        <h4 class="fw-bold text-success mb-1">Verify Your Identity</h4>
        <p class="text-muted small mb-0">
          For your security, please confirm your license number and full name.
        </p>
      </div>

      {{-- Error alert --}}
      @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          {{ $errors->first() }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      {{-- Info: username display (optional) --}}
      @isset($violator)
        <p class="small text-muted mb-3">
          Logged in as <strong>{{ $violator->username }}</strong>
        </p>
      @endisset

      <form method="POST" action="{{ route('violator.identity.verify') }}">
        @csrf

        {{-- License number --}}
        <div class="mb-3 form-floating">
          <input
            type="text"
            name="license_number"
            id="license_number"
            class="form-control @error('license_number') is-invalid @enderror"
            placeholder="A12-34-567890"
            value="{{ old('license_number') }}"
            required
          >
          <label for="license_number">Driver’s License Number</label>
          @error('license_number')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        {{-- Full name --}}
        <div class="mb-4 form-floating">
          <input
            type="text"
            name="full_name"
            id="full_name"
            class="form-control @error('full_name') is-invalid @enderror"
            placeholder="Juan Dela Cruz"
            value="{{ old('full_name') }}"
            required
          >
          <label for="full_name">Full Name (First Middle Last)</label>
          @error('full_name')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold" style="border-radius: 12px;">
          Continue
        </button>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    @if (session('identity_verified'))
      Swal.fire({
        icon: 'success',
        title: 'Verified',
        text: 'Identity confirmed. Redirecting…',
        timer: 2000,
        showConfirmButton: false
      });
    @endif
  });
</script>
@endpush
