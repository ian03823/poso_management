<div class="row g-4 mb-5">
  <div class="col-md-4">
    <a href="{{ url('/ticket') }}" class="text-decoration-none" data-ajax>
      <div class="card text-white bg-primary h-100">
        <div class="card-body d-flex align-items-center">
          <i class="bi bi-receipt-cutoff display-4 me-3"></i>
          <div>
            <h6 class="card-title">Total Issued Ticket</h6>
            <h2>{{ $ticketCount }}</h2>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="{{ url('/violatorTable') }}" class="text-decoration-none" data-ajax>
      <div class="card text-white bg-warning h-100">
        <div class="card-body d-flex align-items-center">
          <i class="bi bi-person-vcard display-4 me-3"></i>
          <div>
            <h6 class="card-title">Total Violator(s)</h6>
            <h2>{{ $violatorCount }}</h2>
          </div>
        </div>
      </div>
    </a>
  </div>
</div>
