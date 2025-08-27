@extends('components.app')
@section('title', 'POSO Digital Ticket')

@section('body')
<div class="d-flex flex-column min-vh-100 justify-content-center align-items-center login-container">
  {{-- Animated Logo --}}
  

  {{-- Login Card --}}
  <div class="card login-card shadow">
    <div class="card-body px-4 py-5">
        <div class="text-center mb-4">
    <i class="bi bi-receipt-cutoff display-1 text-success icon-bounce"></i>
  </div>

      {{-- Error Message --}}
      @if(session('error'))
        <div class="alert alert-danger text-center">{{ session('error') }}</div>
      @endif

      {{-- Title --}}
      <h3 class="text-center fw-bold mb-4 text-success login-title">Enforcers Login</h3>

      {{-- Form --}}
      <form method="POST" action="{{ route('enforcer.login') }}">
        @csrf

        {{-- Badge Number --}}
        <div class="mb-3">
          <label for="badge_num" class="form-label">Badge No.</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-badge text-success"></i></span>
            <input
              type="text"
              name="badge_num"
              id="badge_num"
              value="{{ old('badge_num') }}"
              class="form-control @error('badge_num') is-invalid @enderror"
              placeholder="Enter your badge number"
              required
            />
          </div>
        </div>

        {{-- Password --}}
        <div class="mb-4">
          <label for="password" class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock text-success"></i></span>
            <input
              type="password"
              name="password"
              id="password"
              class="form-control @error('password') is-invalid @enderror"
              placeholder="Enter your password"
              required
            />
            <button
              type="button"
              class="btn btn-outline-secondary"
              onclick="togglePassword()"
              tabindex="-1"
            >
              <i class="bi bi-eye-slash" id="toggleIcon"></i>
            </button>

          </div>
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn btn-login w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Log in
        </button>

        {{-- Validation Errors --}}
        @if($errors->any())
          <div class="mt-3 px-3 py-2 rounded border border-danger bg-light text-danger">
            <ul class="list-unstyled mb-0">
              @foreach($errors->all() as $error)
                <li>• {{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif
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
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-15px); }
    60% { transform: translateY(-7px); }
  }
  .icon-bounce {
    animation: bounce 2s infinite;
  }
  .form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(0,200,83,0.25);
    border-color: #00c853;
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
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    border-color: #dc3545 !important;
  }
</style>

{{-- Scripts --}}
<script>
  function togglePassword() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (pwd.type === 'password') {
      pwd.type = 'text';
      icon.classList.replace('bi-eye-slash', 'bi-eye');
    } else {
      pwd.type = 'password';
      icon.classList.replace('bi-eye', 'bi-eye-slash');
    }
  }
  // remove the red glow on typing
  document.querySelectorAll('#badge_num, #password')
    .forEach(input => {
      input.addEventListener('input', () => {
        input.classList.remove('is-invalid');
      });
    });
</script>
@endsection
