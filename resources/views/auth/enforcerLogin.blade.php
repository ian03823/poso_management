@extends('components.app') 
@section('title', 'POSO Enforcer Login')

@section('body')


<div class="d-flex flex-column min-vh-100 justify-content-center align-items-center" style="background-color: #017C3F;">
    <div class="text-center mb-3">
        <h4 class="text-white mb-0">POSO Digital Ticket</h4>
    </div>
        
    <!-- Login Card -->
    <div class="card shadow" style="width: 90%; max-width: 400px; border-radius: 10px;">
        <div class="card-body p-4">
            
            @if(session('error'))
                <div class="alert alert-danger text-center">{{ session('error') }}</div>
            @endif

            <h5 class="text-center fw-bold mb-4">Log in</h5>

            <!-- Login form -->
            <form method="POST" action="{{ route('enforcer.login') }}">
                @csrf

                <!-- Username -->
                <div class="mb-3">
                    <label for="badge_num" class="form-label">Username</label>
                    <input 
                        type="text"
                        name="badge_num"
                        id="badge_num"
                        class="form-control"
                        placeholder="Enter your badge number"
                        required
                    />
                </div>

                <!-- Password with toggle visibility -->
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input 
                            type="password"
                            name="password"
                            id="password"
                            class="form-control"
                            placeholder="Enter your password"
                            required
                        />
                        <button 
                            type="button"
                            class="btn btn-outline-secondary"
                            onclick="togglePassword()"
                            tabindex="-1"
                        >
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit button -->
                <button 
                    type="submit"
                    class="btn w-100 py-2 fw-semibold"
                    style="background-color: #00c853; color: white; border-radius: 12px;"
                >
                    Log in
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Password Visibility Script -->
<script>
function togglePassword() {
  const passwordInput = document.getElementById("password");
  const icon = document.getElementById("toggleIcon");
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    icon.classList.remove("bi-eye-slash");
    icon.classList.add("bi-eye");
  } else {
    passwordInput.type = "password";
    icon.classList.remove("bi-eye");
    icon.classList.add("bi-eye-slash");
  }
}
</script>
@endsection
