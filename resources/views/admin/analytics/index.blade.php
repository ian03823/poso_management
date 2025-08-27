@extends('components.layout')
@section('title', 'POSO Admin Management')

@section('content')

@php
    $latestTicket = \App\Models\Ticket::latest()->first();
    $defaultLat = $latestTicket->latitude ?? 15.9285;
    $defaultLng = $latestTicket->longitude ?? 120.3487;
@endphp
<div id="loadingIndicator">Loading statistics…</div>
<div class="container-fluid mt-4">
  <h2 class="mb-3">Real-Time Analytics</h2>
    <div class="row">
      <div class="col-md-4">
        <canvas id="pieChart"></canvas>
      </div>
      <div class="col-md-4">
        <canvas id="barChart"></canvas>
      </div>
      <div class="col-md-4">
        <div id="map" style="height:300px;"></div>
      </div>
    </div>
    <a href="{{ route('reports.download','xlsx') }}" 
     class="btn btn-success">
    <i class="bi bi-file-earmark-spreadsheet"></i>
    Download Excel
  </a>

  <a href="{{ route('reports.download','docx') }}" 
     class="btn btn-primary">
    <i class="bi bi-file-earmark-word"></i>
    Download Word
  </a>
</div>
@endsection

@push('scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link  href="https://unpkg.com/leaflet/dist/leaflet.css" rel="stylesheet"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>


@endpush
