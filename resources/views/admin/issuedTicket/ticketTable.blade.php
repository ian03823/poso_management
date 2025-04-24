@extends('components.layout')
@section('title', 'POSO Admin Management')
@section('content')
<div class="container mt-4">
    <h2 class="mb-3">All Issued Tickets</h2>
  
    @if($tickets->isEmpty())
      <div class="alert alert-info">No tickets have been issued yet.</div>
    @else
      <table class="table table-bordered table-hover">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Enforcer</th>
            <th>Violator</th>
            <th>Plate No.</th>
            <th>Location</th>
            <th>Issued At</th>
            <th>Status</th>
            <th>Confiscated</th>
            <th>Impounded</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tickets as $t)
            <tr>
              <td>{{ $t->id }}</td>
              <td>
                {{ $t->enforcer->fname }} {{ $t->enforcer->lname }}<br>
                <small>Badge: {{ $t->enforcer->badge_num }}</small>
              </td>
              <td>{{ $t->violator->name }}</td> 
              <td>{{ $t->vehicle->plate_number }}</td>
              <td>{{ $t->location }}</td>
              <td>{{ \Carbon\Carbon::parse($t->issued_at)
                        ->format('d M Y, H:i') }}</td>
              <td>{{ ucfirst($t->status) }}</td>
              <td>{{ $t->confiscated }}</td>
              <td>{{ $t->is_impounded ? 'Yes' : 'No' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
  
      {{ $tickets->links() }}
    @endif
  </div>
@endsection