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

    <header class="top-nav py-3">
        <div class="fw-bold fs-6">POSO Digital Ticket System</div>
        @auth('violator')
        <div class="text-muted-white fw-medium" id="currentDateTime">—</div>

        <div class="d-flex gap-3 align-items-center">
            <a href="#" class="text-white" style="text-decoration: none"><i class="fa-solid fa-id-badge"></i> &nbsp Profile</a>
            <form id="logoutForm" method="POST">
                @csrf
                <button type="submit" id="logoutBtn"> <i class="fa-solid fa-right-from-bracket"></i> &nbspLog out</button>
            </form>
        </div>
        @endauth
    </header>
    

    @yield('body')

    
</body>
</html>