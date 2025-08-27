@extends('components.app')
@section('title', 'POSO Enforcer Management')

@section('body')
<div class="d-flex flex-column min-vh-100 justify-content-center align-items-center login-container">
  {{-- Animated Icon --}}
  

  {{-- Change Password Card --}}
  <div class="card login-card shadow">
    <div class="text-center">
      <i class="bi bi-key-fill display-1 text-success icon-bounce"></i>
    </div>
    <div class="card-body px-4 py-5">
      {{-- Status Message --}}
      @if(session('status'))
        <div class="alert alert-success text-center">{{ session('status') }}</div>
      @endif

      {{-- Title --}}
      <h3 class="text-center fw-bold mb-4 text-success login-title">Reset Password</h3>

      {{-- Form --}}
      <form method="POST" action="{{ route('enforcer.password.update') }}">
        @csrf

        {{-- New Password --}}
        <div class="mb-3">
          <label for="password" class="form-label">New Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock text-success"></i></span>
            <input
              type="password"
              name="password"
              id="password"
              class="form-control @error('password') is-invalid @enderror"
              placeholder="Enter new password"
              required
              minlength="8"
            />
            <button type="button" class="btn btn-outline-secondary" onclick="toggleField('password','toggleIcon1')" tabindex="-1">
              <i class="bi bi-eye-slash" id="toggleIcon1"></i>
            </button>
            @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        {{-- Confirm Password --}}
        <div class="mb-4">
          <label for="password_confirmation" class="form-label">Confirm Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-shield-lock text-success"></i></span>
            <input
              type="password"
              name="password_confirmation"
              id="password_confirmation"
              class="form-control @error('password_confirmation') is-invalid @enderror"
              placeholder="Re-enter new password"
              required
              minlength="8"
            />
            <button type="button" class="btn btn-outline-secondary" onclick="toggleField('password_confirmation','toggleIcon2')" tabindex="-1">
              <i class="bi bi-eye-slash" id="toggleIcon2"></i>
            </button>
            @error('password_confirmation')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn btn-login w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Update Password
        </button>
      </form>
    </div>
  </div>
</div>

{{-- Styles & Animations --}}
<style>
  .login-container {
    background-color: #017C3F;
  }
  .login-card {
    width: 90%;
    max-width: 400px;
    border-radius: 12px;
    border: none;
    opacity: 0;
    animation: fadeInUp 0.8s ease-out forwards;
  }
  .login-title {
    opacity: 0;
    animation: fadeIn 0.8s 0.2s forwards;
  }
  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0);     }
  }
  @keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
  }
  @keyframes bounce {
    0%,20%,50%,80%,100% { transform: translateY(0); }
    40% { transform: translateY(-15px); }
    60% { transform: translateY(-7px); }
  }
  .icon-bounce {
    animation: bounce 2s infinite;
  }
  .form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(0,200,83,0.25) !important;
    border-color: #00c853 !important;
  }
  .btn-login {
    background-color: #00c853;
    color: #fff;
    border-radius: 8px;
    transition: transform 0.2s;
  }
  .btn-login:hover {
    transform: scale(1.05);
  }
  .form-control.is-invalid {
    box-shadow: 0 0 0 0.2rem rgba(220,53,69,0.25) !important;
    border-color: #dc3545 !important;
  }
</style>

{{-- Scripts --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Show alert if default password is detected
  document.addEventListener('DOMContentLoaded', () => {
      Swal.fire({
        title: 'Default Password Detected',
        text:  'You must change your password before continuing.',
        icon:  'warning',
        confirmButtonText: 'Okay',
        allowOutsideClick: false,
        allowEscapeKey: false
      });
    });
  // Toggle visibility for each password field
  function toggleField(fieldId, iconId) {
    const input = document.getElementById(fieldId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('bi-eye-slash','bi-eye');
    } else {
      input.type = 'password';
      icon.classList.replace('bi-eye','bi-eye-slash');
    }
  }

  // Remove red glow on typing
  ['password','password_confirmation'].forEach(id => {
    document.getElementById(id).addEventListener('input', e => {
      e.target.classList.remove('is-invalid');
    });
  });
</script>
@endpush
@endsection
