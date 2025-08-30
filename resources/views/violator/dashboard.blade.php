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

{{-- =========================
     Floating Help Button + Modal
     ========================= --}}
<button
  id="helpFab"
  class="help-fab btn btn-success d-flex align-items-center gap-2"
  type="button"
  data-bs-toggle="modal"
  data-bs-target="#helpModal"
  aria-label="Open Help and FAQs"
>
  <i class="bi bi-question-circle fs-5"></i>
  <span class="fw-semibold">Help</span>
  <span class="fab-pulse" aria-hidden="true"></span>
</button>

<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title d-flex align-items-center gap-2" id="helpModalLabel">
          <i class="bi bi-life-preserver"></i>
          Help & FAQs
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        {{-- Quick contact --}}
        <div class="alert alert-info d-flex align-items-start gap-3">
          <i class="bi bi-info-circle fs-4"></i>
          <div>
            <div class="fw-semibold">Need immediate assistance?</div>
            <div class="small">Visit the POSO Office or contact support.</div>
            <div class="mt-2 d-flex flex-wrap gap-2">
              <a href="tel:+63-900-000-0000" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-telephone"></i> Call POSO
              </a>
              <a href="mailto:support@poso.gov.ph" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-envelope"></i> Email Support
              </a>
              <button id="copyPayInfo" class="btn btn-sm btn-outline-success">
                <i class="bi bi-clipboard-check"></i> Copy payment info
              </button>
            </div>
          </div>
        </div>

        {{-- FAQs as Accordion --}}
        <div class="accordion" id="faqAccordion">
          <div class="accordion-item">
            <h2 class="accordion-header" id="faq1h">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                Where can I pay my traffic ticket?
              </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse show" aria-labelledby="faq1h" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                You can pay at the <strong>Public Order and Safety Office (POSO)</strong>, City Hall compound, San Carlos City, Pangasinan.
                Bring your ticket number and a valid ID. Office hours: <span id="officeHours">Mon–Fri, 8:00 AM–5:00 PM</span>.
                <div class="small text-muted mt-2">*Update this to your official schedule/location if needed.</div>
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="faq2h">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                What do I need to bring when paying?
              </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" aria-labelledby="faq2h" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Prepare your <strong>ticket number</strong>, a <strong>valid ID</strong>, and exact amount for faster processing.
                If your license was confiscated, bring the claim stub or any document provided by the enforcer.
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="faq3h">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                Can I pay online?
              </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" aria-labelledby="faq3h" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Online payment is currently <strong>not available</strong>. Please settle your ticket at the Cashier Office of Municipal Hall.
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="faq4h">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                How do I view my violations and ticket status?
              </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" aria-labelledby="faq4h" data-bs-parent="#faqAccordion">
              <div class="accordion-body">
                Use the <strong>login credentials</strong> printed on your ticket to sign in to the Violator Portal.
                From your dashboard, you can see your tickets and status updates.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
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

  /* ========= Floating Help FAB ========= */
  .help-fab {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    padding: .6rem 1rem;
    border-radius: 9999px; /* oval / pill */
    box-shadow: 0 8px 30px rgba(0,0,0,.15);
    z-index: 1080; /* above content */
    font-weight: 600;
    letter-spacing: .2px;
    transition: transform .2s ease, box-shadow .2s ease, opacity .2s ease;
  }
  .help-fab:hover { transform: translateY(-2px); box-shadow: 0 12px 38px rgba(0,0,0,.18); }
  .help-fab:active { transform: translateY(0); }

  /* Gentle bob animation */
  @keyframes fab-bob {
    0%   { transform: translateY(0); }
    50%  { transform: translateY(-4px); }
    100% { transform: translateY(0); }
  }
  .help-fab { animation: fab-bob 3s ease-in-out infinite; }

  /* Pulsing ring */
  .fab-pulse {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    pointer-events: none;
    box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.45); /* Bootstrap .btn-success base */
    animation: fab-pulse 2.2s ease-out infinite;
  }
  @keyframes fab-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.40); }
    70%  { box-shadow: 0 0 0 14px rgba(25, 135, 84, 0.00); }
    100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.00); }
  }

  /* Respect users who prefer reduced motion */
  @media (prefers-reduced-motion: reduce) {
    .help-fab,
    .fab-pulse { animation: none !important; }
  }

  /* Modal body scroll on small screens */
  #helpModal .modal-body {
    max-height: min(70vh, 650px);
    overflow-y: auto;
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
            if (reg.active) {
              reg.active.postMessage({
                title: 'Ticket Overdue Reminder',
                options: {
                  body: 'Your ticket is overdue. Pay within 3 weekdays or it will be forwarded to the LTO.',
                }
              });
            }
          }).catch(()=>{});
        }
      });
    })
    .catch(err => console.error(err));

  /* ===== Help FAB helpers ===== */
  const copyBtn = document.getElementById('copyPayInfo');
  const helpModalEl = document.getElementById('helpModal');
  const openHelpDeepLink = document.getElementById('openHelpDeepLink');

  // Copy key payment info to clipboard
  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const text = [
        'POSO Office, City Hall compound, San Carlos City, Pangasinan',
        'Hours: Mon–Fri, 8:00 AM–5:00 PM',
        'Bring: Ticket #, Valid ID, exact amount'
      ].join('\\n');

      try {
        await navigator.clipboard.writeText(text);
        copyBtn.innerHTML = '<i class="bi bi-check2-circle"></i> Copied';
        setTimeout(() => (copyBtn.innerHTML = '<i class="bi bi-clipboard-check"></i> Copy payment info'), 1600);
      } catch {
        alert('Unable to copy. Please copy manually.');
      }
    });
  }

  // Deep link: /violator/dashboard#help opens modal
  if (location.hash === '#help' && helpModalEl && window.bootstrap && bootstrap.Modal) {
    const modal = new bootstrap.Modal(helpModalEl);
    modal.show();
  }

  // Replace hash with #help when user clicks "Open full guide"
  if (openHelpDeepLink && helpModalEl) {
    openHelpDeepLink.addEventListener('click', (e) => {
      e.preventDefault();
      if (window.history && history.replaceState) {
        history.replaceState({}, '', '#help');
      }
      if (window.bootstrap && bootstrap.Modal) {
        const modal = new bootstrap.Modal(helpModalEl);
        modal.show();
      }
    });
  }

  // Optional keyboard shortcut: press "?" to open Help
  document.addEventListener('keydown', (e) => {
    if ((e.key === '?' || (e.shiftKey && e.key === '/')) && helpModalEl && window.bootstrap && bootstrap.Modal) {
      const modal = new bootstrap.Modal(helpModalEl);
      modal.show();
    }
  });

  // Example: tweak office hours text based on weekday (simple demo)
  const officeHoursSpan = document.getElementById('officeHours');
  if (officeHoursSpan) {
    const d = new Date();
    const day = d.getDay(); // 0 Sun ... 6 Sat
    // Assume closed weekends (adjust if needed)
    if (day === 0 || day === 6) officeHoursSpan.textContent = 'Mon–Fri, 8:00 AM–5:00 PM (Closed today)';
  }
});
</script>
@endpush
