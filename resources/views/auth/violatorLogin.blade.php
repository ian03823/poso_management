@extends('components.violator')

@section('title', 'POSO Digital Ticket')

@section('violator')
<div class="container-fluid">
    <div class="flex justify-center items-center min-h-[calc(100vh-80px)]">
        <div class="rounded-lg shadow-md p-8 w-full max-w-md" style="background-color: #017C3F;">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-light">LOGIN</h2>
            </div>
            @if(session('error'))
                <div class="alert alert-danger text-center">{{ session('error') }}</div>
            @endif
            <form action="{{ route('violator.showLogin') }}" method="POST" class="space-y-6">
                @csrf
                <div class="space-y-2 form-floating">
                    
                    <input type="text" name="username" id="username" placeholder="username" required value="{{ old('username') }}"
                        class="form-control" autocomplete="username" placeholder="Enter your username">
                    <label for="username">Username</label>
                </div>
                <div class="input-group">
                    <div class="space-y-2 form-floating">
                        <input type="password" name="password" id="password" required
                        class="form-control" autocomplete="current-password" placeholder="Password">
                        <label for="password">Password</label>
                    </div>
                    <span
                        class="input-group-text"
                        id="togglePassword"
                        style="cursor: pointer;">
                        <i class="fa-solid fa-eye-slash"></i>
                    </span>
                </div>

                <br>

                <div>
                    <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border-white rounded-md shadow-sm text-sm font-medium text-white bg-green-700 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        Login
                    </button>
                </div>

                @if($errors->any()) 
                    <div class="bg-red-50 text-red-700 p-4 rounded-md border border-red-200">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection


@push('scripts')
    <script src="{{ asset('js/togglePassword.js') }}"></script>

@endpush

