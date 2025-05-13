@extends('components.violator')

@section('title', 'POSO Digital Ticket')

@section('violator')

<div class="container-fluid">
    <h2 class="mb-3">Change Your Password</h2>
    <h2>Change Your Password</h2>
    <form method="POST" action="{{ route('violator.password.update') }}">
        @csrf
        <div class="mb-3 form-group">
            <label for="password">New Password</label>
            <input id="password" type="password" name="password"
                class="form-control @error('password') is-invalid @enderror" required>
            @error('password')
            <div class9="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" type="password"
                name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Save New Password</button>
    </form>
</div>
@endsection

