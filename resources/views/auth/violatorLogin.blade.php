@extends('components.violator')

@section('title', 'POSO Digital Ticket')

@section('violator')
<div class="d-flex flex-column min-vh-100 justify-content-center align-items-center" style="background-color: #ffffff;">
    <!-- Login Card -->
    
    <div class="card shadow" style="width: 90%; max-width: 400px; border-radius: 10px; ">
        <div class="card-body p-4">
            
            @if(session('error'))
                <div class="alert alert-danger text-center">{{ session('error') }}</div>
            @endif
            <div class="text-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800">LOG IN</h3>
            </div>
            <br>
            <!-- Login form -->
            <form method="POST" action="{{ route('violator.showLogin') }}">
                @csrf
                <!-- Username -->
                <div class="mb-3">
                    <label for="badge_num" class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" 
                    autocomplete="username" required/>
                </div>

                <!-- Password with toggle visibility -->
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="password"/>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()" tabindex="-1">
                            <i class="bi bi-eye-slash" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit button -->
                <button type="submit" class="btn w-100 py-2 fw-semibold" style="background-color: #00c853; color: white; border-radius: 12px;">
                    Log in
                </button>
                <div class="bg-red-50 text-red-700 mt-3 rounded-md border border-red-200">
                    <div class="text-sm p-2">Please use the credentials provided by POSO Officer to log in.</div>
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@push('scripts')
    <script src="{{ asset('js/togglePassword.js') }}"></script>
    
@endpush

