<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title')</title>
    <link rel="manifest" href="/pwa-manifest.json">
    <meta name="theme-color" content="#ffffff">
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/serviceworker.js');
    }
    </script>
     @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    <header>
        @if(auth()->guard('enforcer')->check())
        <h1>Hello, {{ auth()->guard('enforcer')->user()->fname }} {{ auth()->guard('enforcer')->user()->lname }}</h1>
        <h1>Badge No: {{ auth()->guard('enforcer')->user()->badge_num }}</h1>
        @endif

        @if(auth()->guard('enforcer')->check())
        <form method="POST" action="{{ route('enforcer.logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
        @endif
    </header>

    <main>
        @yield('body')
    </main>

</body>

</html>