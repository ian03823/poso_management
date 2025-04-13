@extends('components.app') 
@section('title', 'POSO Enforcer Login')

@section('body')

    <form method="POST" action="{{ route('enforcer.login') }}">
        @csrf
        <h2>Enforcer Login</h2>
        <label for="badge_num" class="form-label">Username</label>
        <input type="text" class="form-control" id="badge_num" name="badge_num" value="{{ old('badge_num') }}" required> <br>
        
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
        
        <button type="submit" class="btn btn-primary">Login</button>
    
    </form>
    @if ($errors->any()) 
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

@endsection
