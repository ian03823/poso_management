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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    @yield('body')
    
</body>
</html>