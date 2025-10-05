{{-- resources/views/admin/analytics/index.blade.php --}}
@extends('components.layout')
@section('title','POSO Admin Management')

@section('content')
@php
  $latestTicket  = \App\Models\Ticket::latest()->first();
  $defaultLat    = $latestTicket->latitude  ?? 15.9285;
  $defaultLng    = $latestTicket->longitude ?? 120.3487;
  $violationOpts = \App\Models\Violation::select('id','violation_name')->orderBy('violation_name')->limit(50)->get();
@endphp

<div class="container-fluid py-3" 
     id="analyticsRoot"
     data-latest-endpoint="{{ route('dataAnalytics.latest') }}"
     data-hotspot-endpoint="{{ route('dataAnalytics.hotspotTickets') }}"
     data-default-lat="{{ $defaultLat }}"
     data-default-lng="{{ $defaultLng }}"
>
  {{-- Toolbar --}}
  <div class="card shadow-sm mb-3 analytics-toolbar">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label small mb-1">From (month)</label>
          <input type="month" id="fltFrom" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small mb-1">To (month)</label>
          <input type="month" id="fltTo" class="form-control form-control-sm">
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label small mb-1 d-block">Violations</label>
          <div class="dropdown w-100">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" data-bs-toggle="dropdown">
              <span id="vioBtnText">All Violations</span>
            </button>
            <div class="dropdown-menu p-2 w-100" style="max-height: 300px; overflow:auto; min-width: 260px;">
              <div id="vioList" class="small">
                @forelse($violationOpts as $v)
                  <div class="form-check">
                    <input class="form-check-input vio-opt" type="checkbox" value="{{ $v->id }}" id="vio_{{ $v->id }}">
                    <label class="form-check-label" for="vio_{{ $v->id }}">{{ $v->violation_name }}</label>
                  </div>
                @empty
                  <div class="text-muted">No violations available.</div>
                @endforelse
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label small mb-1 d-block">Status</label>
          <div class="btn-group" role="group" aria-label="Status">
            <input class="btn-check" type="radio" name="fltStatus" id="st_all" value="" checked>
            <label class="btn btn-sm btn-outline-success" for="st_all">All</label>
            <input class="btn-check" type="radio" name="fltStatus" id="st_paid" value="paid">
            <label class="btn btn-sm btn-outline-success" for="st_paid">Paid</label>
            <input class="btn-check" type="radio" name="fltStatus" id="st_unpaid" value="unpaid">
            <label class="btn btn-sm btn-outline-success" for="st_unpaid">Unpaid</label>
          </div>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
          <button id="btnApply" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-funnel"></i> Apply
          </button>
          <button id="btnReset" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
        </div>
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row g-3 mb-1">
    <div class="col-12 col-md-4">
      <div class="card kpi-card shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Total Tickets</div>
            <div class="fs-4 fw-bold" id="kpiTotal">—</div>
          </div>
          <i class="bi bi-collection fs-3 text-secondary"></i>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card kpi-card shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Paid</div>
            <div class="fs-4 fw-bold text-success" id="kpiPaid">—</div>
          </div>
          <i class="bi bi-cash-coin fs-3 text-success"></i>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card kpi-card shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Unpaid</div>
            <div class="fs-4 fw-bold text-danger" id="kpiUnpaid">—</div>
          </div>
          <i class="bi bi-exclamation-triangle fs-3 text-danger"></i>
        </div>
      </div>
    </div>
  </div>

  {{-- Charts + Big Map --}}
  <div class="row g-3">
    <div class="col-lg-6">
      {{-- Paid vs Unpaid --}}
      <div class="card shadow-sm h-100">
        <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
          <span>Paid vs Unpaid</span>
          <span class="spinner-border spinner-border-sm d-none" id="spinPie"></span>
        </div>
        <div class="card-body">
          <div class="chart-box">
            <canvas id="chartPie"></canvas>
          </div>
          <div class="text-muted small mt-2" id="pieEmpty" style="display:none">No data to display.</div>
        </div>
      </div>

      {{-- Tickets by Month --}}
      <div class="card shadow-sm h-100 mt-3">
        <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
          <span>Tickets by Month</span>
          <span class="spinner-border spinner-border-sm d-none" id="spinBar"></span>
        </div>
        <div class="card-body">
          <div class="chart-box">
            <canvas id="chartBar"></canvas>
          </div>
          <div class="text-muted small mt-2" id="barEmpty" style="display:none">No data to display.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      {{-- Hotspot Map --}}
      <div class="card shadow-sm h-100">
        <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
          <span>Hotspot Map</span>
          <small class="text-muted">Click dots to drill-down</small>
        </div>
        <div class="card-body">
          <div id="map" class="rounded analytics-map"></div>
          <div class="heat-legend text-muted small mt-2">
            <span class="me-2"><span class="heat-dot heat-low"></span>Low</span>
            <span class="me-2"><span class="heat-dot heat-mid"></span>Medium</span>
            <span><span class="heat-dot heat-high"></span>High</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Insights + Export --}}
  <div class="row g-3 mt-3">
    <div class="col-lg-8">
      <div class="card shadow-sm insights-card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span class="fw-semibold">Data Insights (auto-generated)</span>
          <button id="btnCopyInsights" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clipboard"></i> Copy
          </button>
        </div>
        <div class="card-body">
          <textarea id="insightsBox" class="form-control flex-grow-1" rows="10"
            placeholder="Insights will appear here; you can edit them before exporting."></textarea>
          <div class="text-muted small mt-2" id="insightsHint">
            Tips: adjust filters above to update the insights.
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex flex-column gap-2">
          <a id="btnExportXlsx" class="btn btn-success" href="{{ route('reports.download','xlsx') }}" data-no-ajax>
            <i class="bi bi-file-earmark-spreadsheet"></i> Monthly Report Excel
          </a>
          <a id="btnExportDocx" class="btn btn-primary" href="{{ route('reports.download','docx') }}" data-no-ajax>
            <i class="bi bi-file-earmark-word"></i> Download Word
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
