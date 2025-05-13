@extends('components.app')
@section('title', 'POSO Enforcer')

@section('body')
<style>
  /* Ensure page is scrollable */
  html, body {
    overflow: auto !important;
    /* allow full height */
    height: auto !important;
  }
</style>
<div class="container-fluid my-3">
    <a href="{{ route('enforcerTicket.create', ['violator_id' => $violators->id]) }}" class="btn btn-success btn-sm mb-3">
      Add Violation
    </a>
    <h2 class="text-center fs-3">Violator's Information</h2>
    <dl class="row p-1">
      <dt class="col-sm-3 text-sm">Name</dt>
      <dd class="col-sm-9 text-sm">{{ $violators->name }}</dd>

      <dt class="col-sm-3 text-sm">License Number</dt>
      <dd class="col-sm-9 text-sm">{{ $violators->license_number }}</dd>
      
      <dt class="col-sm-3 text-sm">Vehicle(s)</dt>
      <dd class="col-sm-9 text-sm">{{ $violators->vehicles->pluck('vehicle_type')->join(' - ') }}</dd>
      
      <dt class="col-sm-3 text-sm">Plate Number(s)</dt>
      <dd class="col-sm-9 text-sm">
        {{ $violators->vehicles->pluck('plate_number')->join(' - ') }}
        @if($violators->vehicles->pluck('plate_number')->isEmpty())
              <p>No registered plate number found.</p>
        @endif
      </dd>

      <dt class="col-sm-3 text-sm">Address</dt>
      <dd class="col-sm-9 text-sm">{{ $violators->address }}</dd>

      
    </dl>

    <h2 class="text-center fs-3">Ticket History</h2>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead class="table-light">
          <tr>
            <th scope="col" class="text-sm">#</th>
            <th scope="col" class="text-sm">Issued At</th>
            <th scope="col" class="text-sm">Location</th>
            <th scope="col" class="text-sm">Violations</th>
            <th scope="col" class="text-sm">Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($violators->tickets as $ticket)
            <tr>
              <td class="text-sm">{{ $ticket->ticket_number }}</td>
              <td class="text-sm">{{ $ticket->issued_at->format('d M Y, H:i') }}</td>
              <td class="text-sm">{{ $ticket->location }}</td>
              <td class="text-sm">
                @foreach(json_decode($ticket->violation_codes) as $code)
                  {{ \App\Models\Violation::where('violation_code', $code)
                      ->value('violation_name') }}<br>
                @endforeach
              </td>
              <td class="text-sm">{{ ucfirst($ticket->status->name) }}</td> 
            </tr>
          @endforeach
        </tbody>
      </table>
          @if($violators->tickets->isEmpty())
              <div class="alert alert-info text-sm">
                No ticket history found.
              </div>
          @endif
    </div>
</div>
@endsection

@push('scripts')
    
@endpush