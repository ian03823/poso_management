@extends('components.app')

@section('title', 'POSO Digital Ticket - Dashboard')

@section('body')
<div class="container-fluid bg-light min-vh-100 p-3">
  <!-- Enforcer's Information Card -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="row gx-2 gy-2">
        <div class="col-12 d-flex align-items-center">
          <i class="bi bi-person-circle fs-4 me-2"></i>
          {{-- Enforcer's Full Name --}}
          <span class="fw-semibold">Name: {{ auth()->guard('enforcer')->user()->fname }} {{ auth()->guard('enforcer')->user()->lname }} </span> 
        </div>
        <div class="col-12 d-flex align-items-center">
          <i class="bi-ticket fs-4 me-2"></i>
          {{-- Enforcer's Ticket Range (But include the used to ticket number) --}}
          <span class="fw-semibold">Badge No.: {{ auth()->guard('enforcer')->user()->badge_num }}</span>
        </div>
        <div class="col-12 d-flex align-items-center">
          {{-- Enforcer's Address--}}
          <i class="bi bi-geo-alt-fill fs-4 me-2"></i>
          <span class="fw-semibold">Phone.: {{ auth()->guard('enforcer')->user()->phone }}</span>
        </div>
        <div class="col-12 d-flex align-items-center">
          <i class="bi bi-ticket-perforated fs-4 me-2"></i>
          @if(!empty($ticketSummary) && !is_null($ticketSummary['min_start']))
            @php
              $min = str_pad($ticketSummary['min_start'], 3, '0', STR_PAD_LEFT);
              $max = str_pad($ticketSummary['max_end'],   3, '0', STR_PAD_LEFT);
            @endphp
            <div class="fw-semibold">
              Ticket Range: {{ $min }}–{{ $max }}
              <span class="d-block small fw-normal text-muted">
                Used: {{ $ticketSummary['used'] }} / {{ $ticketSummary['total_allocated'] }}
                @if(!is_null($ticketSummary['last_used']))
                  · Last issued: {{ str_pad($ticketSummary['last_used'], 3, '0', STR_PAD_LEFT) }}
                @endif
                · Remaining: {{ $ticketSummary['remaining'] }}
              </span>
            </div>
          @else
            <span class="fw-semibold text-danger">
              Ticket range not assigned. Please contact the admin.
            </span>
          @endif
           <a
              href="{{ route('enforcer.tickets.index') }}"
              class="btn btn-outline-primary btn-sm shadow-sm rounded-pill"
            >
              <i class="bi bi-card-checklist me-2"></i>
              View My Tickets
          </a>
        </div>
        
      </div>
    </div>
  </div>
  <!-- Search Card -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="input-group input-group-lg">
        <span class="input-group-text bg-white border-0">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input
          id="violator-search"
          type="text"
          class="form-control border-0 rounded-end-pill"
          placeholder="Search violators..."
          autocomplete="off"
          aria-label="Search violators"
        >
        <button
          id="search-clear"
          class="btn btn-outline-secondary ms-1 rounded-pill"
          type="button"
          disabled
          aria-label="Clear search"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <small class="text-muted d-block text-center mt-3">
        Search by name · plate number · license number
      </small>
      <div
        id="suggestions"
        class="list-group position-relative mt-2"
        style="z-index:1000; max-height:250px; overflow-y:auto;"
      ></div>
    </div>
  </div>
  @php
    $isExhausted = $ticketExhausted ?? false;
  @endphp

  <!-- Issue Ticket Button -->
  <div class="text-center mb-5">
    <a
      href="{{ $isExhausted ? '#' : url('/enforcerTicket/create') }}"
      id="issueTicketBtn"
      class="btn btn-lg shadow-sm rounded-pill {{ $isExhausted ? 'btn-secondary disabled' : 'btn-success' }}"
      @if($isExhausted) aria-disabled="true" @endif
    >
      <i class="bi bi-ticket-perforated-fill me-2"></i>
      {{ $isExhausted ? 'No Tickets Available' : 'Issue a Ticket' }}
    </a>
  </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/dexie/dexie.min.js') }}"></script>
<script src="{{ asset('js/enforcer.offline.auth.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const exhausted = @json($ticketExhausted ?? false);
  const btn = document.getElementById('issueTicketBtn');
  if (!btn) return;

  if (exhausted) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();

      if (window.Swal) {
        Swal.fire({
          icon: 'warning',
          title: 'No ticket numbers available',
          text: 'You have already used all of your allocated ticket numbers. Please contact the admin to request a new batch.',
          confirmButtonText: 'OK'
        });
      } else {
        alert('You have already used all of your allocated ticket numbers. Please contact the admin to request a new batch.');
      }
    });
  }
});
</script>
<script>
  document.addEventListener('DOMContentLoaded', async () => {
    const u = sessionStorage.getItem('pending_login_user');
    const p = sessionStorage.getItem('pending_login_pass');

    if (u && p && window.EnforcerOfflineAuth) {
      const profile = {
        id:        {{ auth('enforcer')->id() ?? 'null' }},
        badge_num: @json(optional(auth('enforcer')->user())->badge_num),
        fname:     @json(optional(auth('enforcer')->user())->fname),
        lname:     @json(optional(auth('enforcer')->user())->lname),
      };
      try { await EnforcerOfflineAuth.cacheLogin(u, p, profile); }
      catch (e) { console.warn('cacheLogin failed:', e); }
      sessionStorage.removeItem('pending_login_user');
      sessionStorage.removeItem('pending_login_pass');
    }
  });

  // Optional: when back online later, refresh cached profile
  window.addEventListener('online', () => {
    const user = {{ auth('enforcer')->check() ? json_encode(strtolower(auth('enforcer')->user()->badge_num)) : 'null' }};
    if (user && window.EnforcerOfflineAuth) {
      EnforcerOfflineAuth.revalidateOnline(user, {
        badge_num: @json(optional(auth('enforcer')->user())->badge_num),
        fname:     @json(optional(auth('enforcer')->user())->fname),
        lname:     @json(optional(auth('enforcer')->user())->lname),
      });
    }
  });
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const input       = document.getElementById('violator-search');
  const suggestions = document.getElementById('suggestions');
  const clearBtn    = document.getElementById('search-clear');
  const url         = "{{ route('enforcer.violators.suggestions') }}";
  let timer;

  clearBtn.addEventListener('click', () => {
    input.value = '';
    clearBtn.disabled = true;
    suggestions.innerHTML = '';
    input.focus();
  });

  input.addEventListener('input', () => {
    const term = input.value.trim();
    clearBtn.disabled = (term === '');
    clearTimeout(timer);

    if (term.length < 2) {
      suggestions.innerHTML = '';
      return;
    }

    timer = setTimeout(() => {
      fetch(`${url}?q=${encodeURIComponent(term)}`, { credentials: 'same-origin' })
        .then(res => {
          if (!res.ok) throw new Error(res.status);
          return res.json();
        })
        .then(data => {
          suggestions.innerHTML = '';

          if (data.length === 0) {
            const noItem = document.createElement('div');
            noItem.className = 'list-group-item list-group-item-warning text-center';
            noItem.textContent = `No "${term}" violator found`;
            suggestions.appendChild(noItem);
            return;
          }

          data.forEach(v => {
            // build the full name, skipping empty middle
            const fullName = [v.first_name, v.middle_name, v.last_name]
                               .filter(n => n && n.trim())
                               .join(' ');

            const a = document.createElement('a');
            a.href      = `/violators/${v.id}`;
            a.className = 'list-group-item list-group-item-action';
            a.innerHTML = `
              <strong>${fullName}</strong><br>
              <small class="text-muted">
                ${v.license_number} &middot; ${v.plate_number || '—'}
              </small>
            `;
            suggestions.appendChild(a);
          });
        })
        .catch(err => {
          suggestions.innerHTML = `
            <div class="list-group-item text-danger text-center">
              Error fetching results (${err.message || err})
            </div>
          `;
        });
    }, 300);
  });

  // close suggestions when clicking elsewhere
  document.addEventListener('click', e => {
    if (!suggestions.contains(e.target) && e.target !== input) {
      suggestions.innerHTML = '';
    }
  });
});

// When back online from offline mode, silently refresh the cache
window.addEventListener('online', () => {
  window.EnforcerOfflineAuth?.revalidateOnline?.();
});
</script>
@endpush
