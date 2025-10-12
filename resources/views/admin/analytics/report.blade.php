<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>POSO Analytics</title>
<style>
  @page { size: A4; margin: 20mm 20mm 20mm 20mm; }
  body   { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; }
  h1,h2  { margin: 0 0 8px; }
  .center { text-align: center; }

  /* Header */
  .header-title { font-weight: 700; font-size: 16px; }
  .header-sub   { font-size: 11px; color: #555; }

  /* Watermark logo centered behind text */
  .wm {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 420px; opacity: 0.10;
    z-index: -1;
  }

  .muted { color:#555; }
  .mb-2 { margin-bottom: 8px; }
  .mb-3 { margin-bottom: 12px; }
  .mb-4 { margin-bottom: 16px; }
  .sep   { border-bottom: 1px solid #ddd; margin: 12px 0; }

  .metrics { margin-top: 6px; }
  .metrics div { margin: 2px 0; }

  /* Table for hotspots */
  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  th, td { border: 1px solid #ddd; padding: 6px 8px; vertical-align: top; }
  th { background: #f3f3f3; text-align: left; }
</style>
</head>
<body>

  {{-- watermark image --}}
  @if(file_exists($logo_url))
    <img class="wm" src="{{ $logo_url }}">
  @endif

  {{-- Header --}}
  <div class="center">
    <div class="header-title">{{ $title }}</div>
    <br>
    <div class="header-sub">{{ $generated_on }}</div>
  </div>

  <div class="sep"></div>

  <h1 class="center mb-3">DATA ANALYTICS</h1>

  <div class="mb-3"><strong>Date applied:</strong> <span class="muted">{{ $filters_line }}</span></div>

  <h2>Totals</h2>
  <div class="metrics">
    <div>Total Tickets: <strong>{{ $total_all }}</strong></div>
    <div>Total Paid Tickets: <strong>{{ $paid }}</strong></div>
    <div>Total Unpaid Tickets: <strong>{{ $unpaid }}</strong></div>
    <div>Total Revenue Collected: Php <strong>{{ $revenue }}</strong></div>
  </div>
  <br>
  <br>
  <div class="mb-2"></div>
  <h2>Top 3 Hotspots &amp; Smart Recommendations</h2>
  @if(empty($top3))
    <div class="muted">No geo-tagged tickets in this coverage.</div>
  @else
    <table>
      <thead>
        <tr>
          <th style="width:35%">Hotspot</th>
          <th style="width:10%">Tickets</th>
          <th>Recommendation</th>
        </tr>
      </thead>
      <tbody>
        @foreach($top3 as $r)
          <tr>
            <td>{{ $r['place'] }}</td>
            <td>{{ $r['count'] }}</td>
            <td>{{ $r['reco'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

</body>
</html>
