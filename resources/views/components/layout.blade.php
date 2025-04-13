<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column vh-100">
    <!-- Top Navigation Bar -->
    <header class="top-nav">
        <h1 class="h4 mb-0">POSO Admin Management</h1>
        <button class="btn btn-light d-md-none" id="toggleSidebar">☰</button>
        @auth('admin')
        <div class="d-none d-md-flex align-items-center gap-3">
                <a href="#" class="text-white text-decoration-none">Profile</a>
                <form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-link text-white text-decoration-none">Log out</button>
                </form>
        </div>
        @endauth
    </header>

    <div class="d-flex flex-grow-1">
        <!-- Side Navigation Bar -->
        @auth('admin')
        <nav class="sidebar d-md-block d-none" id="sidebar">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{route('admin.dashboard')}}" class="nav-link text-white">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="/enforcer" class="nav-link text-white">Enforcer</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link text-white">Violator</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link text-white">Issue a Ticket</a>
                </li>
                <li class="nav-item">
                    <a href="/violation" class="nav-link text-white">Violation List</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link text-white">Issued Ticket</a>
                </li>
            </ul>
        </nav>
        @endauth
        <!-- Main Content -->
        <main class="content p-3 w-100">
            @yield('content')
        </main> 
    </div>

    <script>
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('d-none');
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
