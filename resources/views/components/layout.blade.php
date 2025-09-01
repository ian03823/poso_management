<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/7922e0fdab.js" crossorigin="anonymous"></script>
    <link rel="icon" type="image/png" sizes="32x32"
      href="{{ asset('POSO-Logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/analytics.js'])

</head>
<body>

    <!-- Top Navigation -->
    <header class="top-nav">
        <div class="fw-bold fs-5"><a href="/admin" class="text-white" style="text-decoration: none">POSO Admin Management</a></div>
        @auth('admin')
        <div class="text-muted-white fw-medium"><span id="currentDateTime">—</span></div>

        <div class="d-flex gap-3 align-items-center">
            <a href="{{ route('admin.profile.edit') }}" class="text-white" style="text-decoration: none"><i class="fa-solid fa-id-badge"></i> &nbsp Profile</a>
            <form id="logoutForm" method="POST" action="{{ route('admin.logout') }}" data-no-ajax>
                @csrf
                <button type="submit" id="logoutBtn" data-no-ajax> <i class="fa-solid fa-right-from-bracket"></i> &nbspLog out</button>
            </form>
        </div>
        @endauth
    </header>
    <div class="half-bg-image">
        <img src="{{ asset('images/icons/POSO-Logo.png') }}" alt="POSO Logo">
    </div>

    <div class="d-flex">
        <!-- Sidebar -->
        @auth('admin')
            @unless(request()->routeIs('admin.profile.edit'))
                <nav class="sidebar d-none d-md-block">
                    <img src="{{ asset('images/icons/POSO-Logo.png') }}" alt="POSO Logo">
                    <ul class="nav flex-column ">
                        <li class="nav-item">
                            <a href="/admin" class="nav-link active d-flex align-items-center justify-content-start gap-2 w-100">
                                <i class="bi bi-columns-gap me-3"></i>   
                                <span class="">Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/ticket" class="nav-link active d-flex align-items-center justify-content-start gap-2 w-100">
                                <i class="bi bi-receipt-cutoff me-3"></i>
                                <span class="">Ticket</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/violation" class="nav-link active d-flex align-items-center justify-content-start gap-2 w-100">
                                <i class="bi bi-list-check me-3"></i>   
                                <span>Violation List</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/enforcer" class="nav-link active d-flex align-items-center justify-content-start gap-2 w-100">
                                <i class="bi bi-person-badge me-3"></i>   
                                <span>Enforcer List</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/violatorTable" class="nav-link active d-flex align-items-center justify-content-start gap-2 w-100">
                                <i class="bi bi-person-vcard me-3"></i>   
                                <span>Violator List</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/dataAnalytics" class="nav-link active d-flex align-items-center justify-content-start gap-2 w-100">
                                <i class="bi bi-graph-up me-3"></i>  
                                <span>Data Analytics</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/impoundedVehicle" class="nav-link active d-flex align-items-center justify-content-start gap-2 w-100">
                                <i class="bi bi-car-front me-3 fs-5"></i>
                                <span>Impounded Vehicle</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            @endunless
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                const logoutBtn  = document.getElementById('logoutBtn');
                const logoutForm = document.getElementById('logoutForm');

                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault(); // stop the form from submitting immediately

                    Swal.fire({
                    title: 'Are you sure you want to logout?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, logout',
                    cancelButtonText: 'Stay logged in',
                    reverseButtons: true
                    }).then((result) => {
                    if (result.isConfirmed) {
                        logoutForm.submit();
                    }
                    });
                });
                // Format options for day/month/year and 24h time
                const fmtOpts = {
                weekday: 'long',    // e.g. "Thursday"
                year: 'numeric',    // e.g. "2025"
                month: 'long',      // e.g. "April"
                day: 'numeric',     // e.g. "24"
                hour: '2-digit',    // e.g. "07"
                minute: '2-digit',  // e.g. "05"
                second: '2-digit',  // e.g. "09"
                hour12: false       // 24-hour clock
                };

                function updateDateTime() {
                const now = new Date();
                // e.g. "Thursday, April 24, 2025 07:05:09"
                const s = now.toLocaleString(undefined, fmtOpts);
                    document.getElementById('currentDateTime').textContent = s;
                }

                // Update every second
                setInterval(updateDateTime, 1000);
                // Initial call
                updateDateTime();
                });
            </script>
        @endauth
        <!-- Main Content -->
        <main class="content" id="app-body">
            @yield('content')
        </main>

    </div>

    

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link  href="https://unpkg.com/leaflet/dist/leaflet.css" rel="stylesheet"/>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/ajax.js') }}"></script>
    
    <script src="{{ asset('js/enforcer.js') }}" defer></script>
    <script src="{{ asset('js/ticketTable.js') }}" defer></script>
    
    @stack('scripts')
</body>
</html>
