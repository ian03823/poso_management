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
    <a href="{{ route('enforcerTicket.create', ['violator_id' => $violators->id]) }}" class="btn btn-success mb-3">
      Add Violation
    </a>
    <h2 style="text-align: center">Violator's Information</h2>
    <dl class="row p-3">
      <dt class="col-sm-3">Name</dt>
      <dd class="col-sm-9">{{ $violators->name }}</dd>

      <dt class="col-sm-3">License Number</dt>
      <dd class="col-sm-9">{{ $violators->license_number }}</dd>
      
      <dt class="col-sm-3">Vehicle(s)</dt>
      <dd class="col-sm-9">{{ $violators->vehicles->pluck('vehicle_type')->join(' - ') }}</dd>
      
      <dt class="col-sm-3">Plate Number(s)</dt>
      <dd class="col-sm-9">
        {{ $violators->vehicles->pluck('plate_number')->join(' - ') }}
        @if($violators->vehicles->pluck('plate_number')->isEmpty())
              <p>No registered plate number found.</p>
        @endif
      </dd>

      <dt class="col-sm-3">Address</dt>
      <dd class="col-sm-9">{{ $violators->address }}</dd>

      
    </dl>

    <h3 class="mt-4">Ticket History</h3>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead class="table-light">
          <tr>
            <th scope="col">#</th>
            <th scope="col">Issued At</th>
            <th scope="col">Location</th>
            <th scope="col">Violations</th>
            <th scope="col">Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($violators->tickets as $ticket)
            <tr>
              <td>{{ $ticket->ticket_number }}</td>
              <td>{{ $ticket->issued_at->format('d M Y, H:i') }}</td>
              <td>{{ $ticket->location }}</td>
              <td>
                @foreach(json_decode($ticket->violation_codes) as $code)
                  {{ \App\Models\Violation::where('violation_code', $code)
                      ->value('violation_name') }}<br>
                @endforeach
              </td>
              <td>{{ ucfirst($ticket->status->name) }}</td> 
            </tr>
          @endforeach
        </tbody>
      </table>
          @if($violators->tickets->isEmpty())
              <div class="alert alert-info">
                No ticket history found.
              </div>
          @endif
    </div>
</div>
@endsection

@push('scripts')
    
@endpush