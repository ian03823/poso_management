@extends('components.violator')
@section('title', 'POSO Digital Ticket')

@section('violator')

@php
    $ticketOverdue = $ticket_overdue ?? false;
@endphp
<div id="violator-dashboard" class="container-fluid mt-4" 
     data-login-success="{{ $loginSuccess  ? '1' : '0' }}"
     data-ticket-overdue="{{ $ticketOverdue ? '1' : '0' }}">
  <div class="violator-card">
      <h4 id="greeting-text" class="mb-2 fw-bold"></h4>
      <p class="mb-0">
        <i class="bi bi-person-circle me-2"></i> 
        {{ $violator->first_name }} {{ $violator->middle_name }} {{ $violator->last_name }}<br>
        <i class="bi bi-card-heading me-2"></i> 
        License #: {{ $violator->license_number }} <br>
        <i class="bi bi-geo-alt me-2"></i>
        Address: {{ $violator->address }}<br>
      </p>
  </div>

    <div class="dashboard-header">
      <i class="bi bi-ticket-perforated"></i>
      <h2 class="mb-0">Unsolve Ticket(s)</h2>
    </div>

    @include('admin.partials.violatorTickets')

    <div class="dashboard-header mt-5">
      <i class="bi bi-check-circle"></i>
      <h2 class="mb-0">Completed Tickets (Paid)</h2>
    </div>

    {{-- Example completed ticket placeholder --}}
    <div class="table-responsive overflow-auto mt-3">
      @if($completed->isEmpty())
          <p class="text-muted">No paid tickets yet.</p>
      @else
      <table class="table table-sm table-bordered table-hover">
          <thead>
              <tr>
                  <th class="text-center text-sm">#</th>
                  <th class="text-center text-sm">Issued</th>
                  <th class="text-center text-sm">Plate</th>
                  <th class="text-center text-sm">Vehicle</th>
                  <th class="text-center text-sm">Violation</th>
                  <th class="text-center text-sm">Status</th>
              </tr>
          </thead>
          <tbody class="text-sm">
              @foreach($completed as $t)
              <tr>
                  <td>{{ $t->id }}</td>
                  <td>{{ $t->issued_at->format('d M Y') }}</td>
                  <td>{{ optional($t->vehicle)->plate_number ?? '—' }}</td>
                  <td>{{ optional($t->vehicle)->vehicle_type ?? '—' }}</td>
                  <td>{{ $t->violation_names }}</td>
                  <td>
                      <button type="button" class="btn btn-outline-success btn-sm" disabled>
                          {{ optional($t->status)->name ?? 'Paid' }}
                      </button>
                  </td>
              </tr>
              @endforeach
          </tbody>
      </table>
      @endif
  </div>
</div>
@endsection

<style>
  .dashboard-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    animation: fadeSlideIn 0.8s ease;
  }

  .dashboard-header i {
    font-size: 2rem;
    color: #198754; /* Bootstrap success color */
  }

  .card-ticket {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .violator-card {
    background: #f8f9fa;
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
    animation: fadeSlideIn 0.6s ease;
  }

  .card-ticket:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
  }

  @keyframes fadeSlideIn {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const root      = document.getElementById('violator-dashboard');
  if (!root) return;
  const loginOk   = root.dataset.loginSuccess  === '1';
  const overdueOk = root.dataset.ticketOverdue === '1';
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().then(p =>
      console.log('Notification.permission after load:', p)
    );
  }
  // Greeting
  const hr       = new Date().getHours();
  const greet    = hr < 12 ? 'Good morning'
                : hr < 18 ? 'Good afternoon'
                : 'Good evening';
  const greetEl  = document.getElementById('greeting-text');
  if (greetEl) greetEl.textContent = `${greet}, `;

  // Chain: loginOk → ask permission → overdueOk
  Promise.resolve()
    // 1) login reminder + requestPermission
    .then(() => {
      if (!loginOk) return;
      return Swal.fire({
        title: 'Reminder',
        text:  'Your unpaid ticket must be settled within 3 weekdays. Failure to pay within this period may result in forwarding your ticket to the LTO.',
        icon:  'info',
        confirmButtonText: 'Okay',
        customClass: { confirmButton: 'btn btn-success' },
        buttonsStyling: false
      });
    })
    // 2) overdue warning + native notification
    .then(() => {
      if (!overdueOk) return;

      return Swal.fire({
        title: 'Warning!',
        text:  'You have an unpaid ticket that has passed its due date. Please settle it immediately to avoid forwarding to the LTO.',
        icon:  'warning',
        confirmButtonText: 'Understood',
        customClass: { confirmButton: 'btn btn-danger' },
        buttonsStyling: false
      }).then(() => {
        if (Notification.permission === 'granted') {
          new Notification('Ticket Overdue Reminder', {
            body: 'Your ticket is overdue. Pay within 3 weekdays or it will be forwarded to the LTO.',

          });
        } else if ('serviceWorker' in navigator) {
          navigator.serviceWorker.register('/sw.js').then(reg => {
            reg.active.postMessage({
              title: 'Ticket Overdue Reminder',
              options: {
                body: 'Your ticket is overdue. Pay within 3 weekdays or it will be forwarded to the LTO.',
              }
            });
          });
        }
      });
    })
    .catch(err => console.error(err));
});
</script>
@endpush
