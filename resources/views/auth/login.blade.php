@extends('components.layout')

@section('pageTitle', 'Login')

@section('body')



    <form action="{{route('login')}}" method="POST">
        @csrf

        <h1 style="text-align: center;">Admin Login</h1>

        <label for="username">Username: </label>
        <input type="text" name="username" required value="{{old('username')}}"> <br>

        <label for="password">Password: </label>
        <input type="password" name="password" required>
        

        <button type="submit">Login</button>


        @if($errors->any()){
            <ul>
                @foreach($errors->all() as $error)
                    {{$error}}
                @endforeach
            </ul>
        }
        @endif
    </form>

@endsection