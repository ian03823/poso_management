@extends('components.layout')

@section('title', 'POSO Admin Management')

@section('content')
<div id="admin-dashboard" class="container-fluid py-4"
     data-version-url="{{ route('admin.dashboard.version') }}"
     data-summary-url="{{ route('admin.dashboard.summary') }}"
     data-violators-url="{{ route('admin.dashboard.recentViolators') }}"
     data-tickets-url="{{ route('admin.dashboard.recentTickets') }}">

  <!-- Summary Cards -->
  <div id="dash-summary">
    @include('admin.partials.dashboardSummary', ['ticketCount' => $ticketCount, 'violatorCount' => $violatorCount])
  </div>

  <!-- Recent Violators & Tickets -->
  <div class="row">
    <div class="col-lg-6 mb-4 mt-3" id="dash-recent-violators">
      @include('admin.partials.recentViolators', ['recentViolators' => $recentViolators])
    </div>

    <div class="col-lg-6 mb-4 mt-3" id="dash-recent-tickets">
      @include('admin.partials.recentTickets', ['recentTickets' => $recentTickets])
    </div>
  </div>
</div>
@endsection

