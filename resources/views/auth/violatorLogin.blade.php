@extends('components.violator')

@section('title', 'Login')

@section('body')
    <h2>Login</h2>

    <form action="{{route('violator.showLogin')}}" method="POST">
        @csrf
        <label for="username">Username:</label>
        <input type="text" name="username" placeholder="Enter your username" autocomplete="off" required><br>

        <label for="password">Password:</label>
        <input type="password" name="password" placeholder="Enter your password" autocomplete="off" required><br>
        <button type="submit">Login</button>
    </form>


@endsection

