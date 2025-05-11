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
    <script src="https://kit.fontawesome.com/7922e0fdab.js" crossorigin="anonymous"></script>
</head>

<body>

    <header class="top-nav py-3 d-flex justify-content-between align-items-center">
        <div class="fw-bold fs-6 text-white">POSO Digital Ticket System</div>
      
        @auth('violator')
          <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#offcanvasMenu"
            aria-controls="offcanvasMenu">
            <i class="fa-solid fa-bars"></i>
          </button>
        @endauth
      </header>
      
      @auth('violator')
        <!-- offcanvas panel -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasMenu"
          aria-labelledby="offcanvasMenuLabel">
          <div class="offcanvas-header">
            <h3 class="offcanvas-title" id="offcanvasMenuLabel">Menu</h3>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
              aria-label="Close"></button>
          </div>
          <div class="offcanvas-body">
            <a href="#" class="btn btn-primary w-100 ">
              <i class="fa-solid fa-id-badge me-2"></i>
              Profile
            </a>
      
            <!-- Logout form -->
            <form method="POST" action="{{ route('violator.logout') }}">
              @csrf
              <button type="submit" class="btn btn-danger w-100 mt-3"
              ><i class="fa-solid fa-right-from-bracket me-2"></i>
                Log out
              </button>
            </form>
          </div>
        </div>
      @endauth
      
    

        @yield('violator')

        @stack('scripts')
</body>
</html>