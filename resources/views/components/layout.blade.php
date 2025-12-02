<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title')</title>

  <!-- Bootstrap + Icons -->
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/serviceworker.js', { scope: '/' });
      });
    }
  </script>
  
  <script src="https://kit.fontawesome.com/7922e0fdab.js" crossorigin="anonymous"></script>

  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('POSO-Logo.png') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  {{-- Minimalist admin layout CSS --}}
  <link rel="stylesheet" href="{{ asset('css/admin-layout.css')}}">
  <link rel="stylesheet" href="{{ asset('css/admin-analytics.css')}}">
  <link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin-ticketTable.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin-violationTable.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin-enforcerTable.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin-violatorTable.css') }}">
  @stack('styles')
</head>
<body>

  <!-- Top Navigation -->
  <header class="top-nav">
    <div class="fw-bold fs-5">
      <a href="/admin/dashboard" class="text-white" style="text-decoration:none" data-ajax>POSO Admin Management</a>
    </div>

    @auth('admin')
      <div class="text-muted-white fw-medium"><span id="currentDateTime">—</span></div>

      <div class="d-flex gap-3 align-items-center">
        <div class="dropdown me-3">
        <button
          class="btn btn-link position-relative"
          id="ticketRequestsBell"
          type="button"
          data-bs-toggle="dropdown"
          aria-expanded="false"
          data-url="{{ route('admin.notifications.ticket_requests') }}"
        >
          <i class="bi bi-bell fs-5"></i>
          <span
            id="ticketRequestsBadge"
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
            style="font-size: 0.65rem;"
          >
            0
          </span>
        </button>

        <div
          class="dropdown-menu dropdown-menu-end p-0 shadow-sm"
          aria-labelledby="ticketRequestsBell"
          style="min-width: 320px;"
        >
          <div class="p-2 border-bottom fw-semibold small">
            Notifications
          </div>
          <div id="ticketRequestsList" style="max-height: 260px; overflow-y: auto;">
            <div class="p-2 small text-muted">No recent notification.</div>
          </div>
        </div>
      </div>
        <div class="btn-group">
          <button type="button" class="btn btn-success " data-bs-toggle="dropdown" aria-expanded="false">
            Settings
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item text-black" data-ajax href="{{ route('admin.profile.edit') }}">Profile</a></li>
            <li><a class="dropdown-item text-black" data-ajax href="{{route('logs.activity')}}">Activity Log</a></li>
          </ul>
        </div>

        <form id="logoutForm" method="POST" action="{{ route('admin.logout') }}" data-no-ajax>
          @csrf
          <button type="submit" id="logoutBtn" data-no-ajax>
            <i class="fa-solid fa-right-from-bracket"></i>&nbsp;Log out
          </button>
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
        <ul class="nav flex-column">
          <li class="nav-item">
            <a data-ajax href="/admin/dashboard" class="nav-link d-flex align-items-center justify-content-start gap-2 w-100">
              <i class="bi bi-columns-gap me-3"></i><span>Dashboard</span>
            </a>
          </li>
          <li class="nav-item">
            <a data-ajax href="/ticket" class="nav-link d-flex align-items-center justify-content-start gap-2 w-100">
              <i class="bi bi-receipt-cutoff me-3"></i><span>Ticket</span>
            </a>
          </li>
          <li class="nav-item">
            <a data-ajax href="/violation" class="nav-link d-flex align-items-center justify-content-start gap-2 w-100">
              <i class="bi bi-list-check me-3"></i><span>Violation List</span>
            </a>
          </li>
          <li class="nav-item">
            <a data-ajax href="/enforcer" class="nav-link d-flex align-items-center justify-content-start gap-2 w-100">
              <i class="bi bi-person-badge me-3"></i><span>Enforcer List</span>
            </a>
          </li>
          <li class="nav-item">
            <a data-ajax href="/violatorTable" class="nav-link d-flex align-items-center justify-content-start gap-2 w-100">
              <i class="bi bi-person-vcard me-3"></i><span>Violator List</span>
            </a>
          </li>
          <li class="nav-item">
            <a data-ajax href="/dataAnalytics" class="nav-link d-flex align-items-center justify-content-start gap-2 w-100">
              <i class="bi bi-graph-up me-3"></i><span>Data Analytics</span>
            </a>
          </li>
          <li class="nav-item">
            <a data-ajax href="/impoundedVehicle" class="nav-link d-flex align-items-center justify-content-start gap-2 w-100">
              <i class="bi bi-car-front me-3 fs-5"></i><span>Impounded Vehicle</span>
            </a>
          </li>
        </ul>
      </nav>
      @endunless

      {{-- Logout confirm + clock --}}
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const logoutBtn  = document.getElementById('logoutBtn');
          const logoutForm = document.getElementById('logoutForm');

          if (logoutBtn && logoutForm) {
            logoutBtn.addEventListener('click', function(e) {
              e.preventDefault();
              Swal.fire({
                title: 'Are you sure you want to logout?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Stay logged in',
                reverseButtons: true
              }).then((result) => { if (result.isConfirmed) logoutForm.submit(); });
            });
          }

          const fmtOpts = {
            weekday:'long', year:'numeric', month:'long', day:'numeric',
            hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false
          };
          function updateDateTime(){
            const el = document.getElementById('currentDateTime');
            if (!el) return;
            el.textContent = new Date().toLocaleString(undefined, fmtOpts);
          }
          setInterval(updateDateTime, 1000);
          updateDateTime();
        });
      </script>
    @endauth
    <div id="app-body">
        @yield('content')
    </div>

    <div id="ajaxLoading" class="loading-overlay d-none">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>


  {{-- Vendor + app scripts --}}
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://unpkg.com/laravel-echo/dist/echo.iife.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js" defer></script>
  <link href="https://unpkg.com/leaflet/dist/leaflet.css" rel="stylesheet"/>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/dexie/3.2.2/dexie.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const bell  = document.getElementById('ticketRequestsBell');
      const badge = document.getElementById('ticketRequestsBadge');
      const list  = document.getElementById('ticketRequestsList');

      if (!bell || !badge || !list) return;

      const url = bell.dataset.url;
      if (!url) return;

      async function refreshTicketRequests() {
        try {
          const res = await fetch(url + '?minutes=2880', {
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });

          if (!res.ok) throw new Error('HTTP ' + res.status);
          const data = await res.json();

          const count = data.count || 0;

          if (count > 0) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.classList.remove('d-none');
          } else {
            badge.classList.add('d-none');
          }

          list.innerHTML = '';

          if (!data.items || data.items.length === 0) {
            list.innerHTML = '<div class="p-2 small text-muted">No recent ticket range requests.</div>';
            return;
          }

          data.items.forEach(item => {
            const enforcerId = item.enforcer_id;
            const badgeNum   = item.badge_num || '—';
            const name       = item.enforcer_name || 'Unknown enforcer';
            const created    = item.created_at || '';

            const a = document.createElement('a');
            a.href = `/enforcer?search=${encodeURIComponent(badgeNum)}&requested=${encodeURIComponent(enforcerId)}`;
            a.className = 'dropdown-item small';
            a.innerHTML = `
              <div class="fw-semibold">${name} (${badgeNum})</div>
              <div class="text-muted">Requested new ticket range</div>
              <div class="text-muted fst-italic" style="font-size: 0.75rem;">${created}</div>
            `;
            list.appendChild(a);
          });
        } catch (err) {
          console.error('ticketRequests refresh failed', err);
        }
      }

      // Refresh when bell is opened
      bell.addEventListener('click', () => {
        refreshTicketRequests();
      });

      // Initial load + periodic refresh every minute
      refreshTicketRequests();
      setInterval(refreshTicketRequests, 60000);
    });
    </script>
  {{-- AJAX navigation (after vendor scripts) --}}
  <script src="{{ asset('js/ajax.js') }}"></script>
  <script src="{{ asset('js/analytics.js') }}"></script>
  <script src="{{ asset('js/enforcer.js') }}"></script>
  <script src="{{ asset('js/ticketTable.js') }}"></script>
  {{-- Page js (delegated handlers; pagination; resolve flow) --}}
  <script src="{{ asset('js/impoundedVehicle.js') }}"></script>
  <script src="{{ asset('js/violationTable.js') }}"></script>
  <script defer src="{{ asset('js/violatorPage.js') }}"></script>
  <script defer src="{{ asset('js/violatorView.js') }}"></script>
  <script src="{{ asset('js/adminIssueTicket.js') }}"></script>
  <script src="{{ asset('js/adminDashboard.js') }}"></script>
  
  {{-- Global notification sound --}}
  <audio id="ticketNotifySound" src="{{ asset('sounds/ticket-notify.mp3') }}" preload="auto"></audio>

  {{-- Expose dashboard version endpoint to JS --}}
  <script>
    window.ADMIN_VERSION_URL = "{{ route('admin.dashboard.version') }}";
  </script>

  {{-- Global live notifications (SweetAlert + sound) --}}
  <script src="{{ asset('js/adminNotify.js') }}"></script>


  @stack('modals')
  @stack('scripts')
</body>
</html>
