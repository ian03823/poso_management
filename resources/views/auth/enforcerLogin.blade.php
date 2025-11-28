@extends('components.app')
@section('title', 'POSO Digital Ticket')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/enforcer-login.css')}}">
@endpush

@section('body')

@php
  $isError   = session('error') || $errors->any();
  $remaining = session('lockout_remaining'); // seconds
@endphp

<div class="auth-page">
  {{-- Login Card --}}
  <div class="card auth-card shadow {{ $isError ? 'is-error' : '' }} {{ $remaining ? 'lockout' : '' }}">
    <div class="card-body px-4 py-5">

      <div class="badge-icon mb-2">
        <i class="bi bi-receipt-cutoff display-1 text-success icon-bounce"></i>
      </div>

      {{-- Error Message --}}
      @if(session('error'))
        <div class="alert alert-danger text-center mb-3">{{ session('error') }}</div>
      @endif

      {{-- Top-of-form validation --}}
      @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
          {{ $errors->first() }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      {{-- Lockout notice --}}
      @if ($remaining)
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
          <i class="bi bi-hourglass-split"></i>
          <div>
            Too many failed attempts. You can try again in
            <span id="lockout-timer" class="fw-bold"></span>.
          </div>
        </div>
      @endif

      {{-- Title --}}
      <h3 class="auth-title">Enforcers Login</h3>

      {{-- Form --}}
      <form method="POST" action="{{ route('enforcer.login') }}" id="enforcerLoginForm">
        @csrf

        {{-- Badge Number --}}
        <div class="mb-3">
          <label for="badge_num" class="form-label">Badge No.</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-badge text-success"></i></span>
            <input
              type="text"
              name="badge_num"
              id="badge_num"
              value="{{ old('badge_num') }}"
              class="form-control @error('badge_num') is-invalid @enderror"
              placeholder="Enter your badge number"
              required
            />
          </div>
        </div>

        {{-- Password --}}
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock text-success"></i></span>
            <input
              type="password"
              name="password"
              id="password"
              class="form-control @error('password') is-invalid @enderror"
              placeholder="Enter your password"
              required
            />
            <button
              type="button"
              class="btn btn-outline-secondary"
              onclick="togglePassword()"
              tabindex="-1"
              aria-label="Toggle password visibility"
            >
              <i class="bi bi-eye-slash" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        {{-- NEW: Online/Offline status indicator --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
          <small id="offlineHint" class="text-muted">
            Status: <span id="netStatus">Checking…</span>
          </small>
        </div>

        {{-- Submit --}}
        <button id="loginBtn" type="submit" class="btn btn-login w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-2"></i>Log in
        </button>

      </form>
    </div>
  </div>
</div>

{{-- Lockout countdown --}}
@if ($remaining)
<script>
(function(){
  let remaining = {{ (int) $remaining }};
  const btn = document.getElementById('loginBtn');
  const timerEl = document.getElementById('lockout-timer');
  if (btn) btn.disabled = true;
  function fmt(sec){
    const m = String(Math.floor(sec/60)).padStart(2,'0');
    const s = String(sec%60).padStart(2,'0');
    return `${m}:${s}`;
  }
  (function tick(){
    if (timerEl) timerEl.textContent = fmt(remaining);
    if (remaining <= 0){
      if (btn) btn.disabled = false;
      return;
    }
    remaining--;
    setTimeout(tick, 1000);
  })();
})();
</script>
@endif
@endsection

@push('scripts')
  {{-- Dexie + offline auth helper --}}
  <script defer src="{{ asset('vendor/dexie/dexie.min.js') }}"></script>
  <script defer src="{{ asset('js/enforcer.offline.auth.js') }}"></script>

  <script>
    function togglePassword() {
      const pwd = document.getElementById('password');
      const icon = document.getElementById('toggleIcon');
      if (!pwd || !icon) return;
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
      } else {
        pwd.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      const form     = document.getElementById('enforcerLoginForm');
      const badgeEl  = document.getElementById('badge_num');
      const passEl   = document.getElementById('password');
      const statusEl = document.getElementById('netStatus');

      // Clear invalid class when typing
      document.querySelectorAll('#badge_num, #password').forEach(el => {
        el.addEventListener('input', () => el.classList.remove('is-invalid'));
      });

      // Net status label
      function updateNetStatus() {
        if (!statusEl) return;
        if (navigator.onLine) {
          statusEl.textContent = 'Online';
          statusEl.className = 'text-success';
        } else {
          statusEl.textContent = 'Offline';
          statusEl.className = 'text-danger';
        }
      }
      updateNetStatus();
      window.addEventListener('online', updateNetStatus);
      window.addEventListener('offline', updateNetStatus);

      if (!form || !badgeEl || !passEl) return;

      form.addEventListener('submit', async (e) => {
        const badge = (badgeEl.value || '').trim();
        const pwd   = passEl.value || '';

        // OFFLINE FLOW
        if (!navigator.onLine) {
          e.preventDefault();

          if (!window.EnforcerOfflineAuth) {
            alert('Offline login is not available yet. Please login once while online to cache your credentials.');
            return;
          }

          try {
            const profile = await window.EnforcerOfflineAuth.tryOfflineLogin(badge, pwd);
            if (profile) {
              try {
                localStorage.setItem('enforcer_offline_session', JSON.stringify(profile));
              } catch (_) {}

              alert('Offline login successful. Redirecting to ticket issuance.');
              // PWA start URL (must be cached by SW)
              window.location.href = '/pwa';
              return;
            } else {
              alert('Offline login failed. Make sure you have logged in at least once while online using this badge & password.');
              return;
            }
          } catch (err) {
            console.error('Offline login error', err);
            alert('Offline login error: ' + (err.message || String(err)));
            return;
          }
        }

        // ONLINE FLOW – opportunistically cache credentials before sending to Laravel
        if (window.EnforcerOfflineAuth) {
          try {
            await window.EnforcerOfflineAuth.cacheLogin(
              badge,
              pwd,
              { badge_num: badge } // you can extend this profile object later
            );
          } catch (err) {
            console.warn('Failed to cache offline login', err);
          }
        }

        // Let the form submit normally to EnforcerAuthController::login()
        // (your lockout + default password logic stays the same)
      });
    });
  </script>
@endpush
