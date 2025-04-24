<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <!-- Mobile-first scaling -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- IE Compatibility Mode -->
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title')</title>

    <!-- PWA manifest and service worker registration -->
    <link rel="manifest" href="/pwa-manifest.json">
    <meta name="theme-color" content="#ffffff">
    <script>
      if ('serviceWorker' in navigator) {
          navigator.serviceWorker.register('/serviceworker.js');
      }
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    
    <!-- Navbar -->
    @auth('enforcer')
    <nav class="navbar bg-light px-3 border-bottom">
        <div class="container-fluid">
            <button class="btn btn-outline-secondary btn-sm mt-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#enforcerMenu" aria-controls="enforcerMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <span class="navbar-brand ms-auto fw-bold text-success">POSO Enforcer</span>
        </div>
    </nav>
    <!-- Offcanvas Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="enforcerMenu" aria-labelledby="enforcerMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="enforcerMenuLabel">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            @if(auth()->guard('enforcer')->check())
                <p><strong>{{ auth()->guard('enforcer')->user()->fname }} {{ auth()->guard('enforcer')->user()->lname }}</strong></p>
                <p class="text-muted">Badge No: {{ auth()->guard('enforcer')->user()->badge_num }}</p>
                <a href="" class="btn btn-primary w-100 ">
                    <i class="bi bi-person-badge"></i> Profile
                  </a>
                <form method="POST" action="{{ route('enforcer.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger w-100 mt-3"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            @endif
        </div>
    </div>
    @endauth

    <main>
        @yield('body')
    </main>
    <script src="https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"></script>
    <!-- Bootstrap Icons (Optional) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS (Via CDN or your build) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


    @stack('scripts')
    
</body>
</html>
