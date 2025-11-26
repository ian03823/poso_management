<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    /* 58mm / 80mm thermal style */
    body {
      font-family: 'Courier New', monospace;
      background: #ffffff;
    }
    .ticket-wrap {
      width: 280px; /* good for 58mm */
      margin: 0 auto;
      padding: 8px 6px;
      font-size: 11px;
      line-height: 1.35;
    }

    .center   { text-align: center; }
    .right    { text-align: right; }
    .bold     { font-weight: 700; }
    .small    { font-size: 10px; }
    .tiny     { font-size: 9px; }
    .mt-2     { margin-top: 2px; }
    .mt-4     { margin-top: 4px; }
    .mt-8     { margin-top: 8px; }
    .mb-2     { margin-bottom: 2px; }
    .mb-4     { margin-bottom: 4px; }

    .divider  {
      border-top: 1px dashed #000;
      margin: 4px 0;
    }

    .header-line {
      border-top: 1px solid #000;
      border-bottom: 1px solid #000;
      padding: 2px 0;
      margin-bottom: 4px;
    }

    .label-block {
      text-transform: uppercase;
      font-weight: 700;
      margin-top: 4px;
      margin-bottom: 2px;
    }

    .footer-note {
      margin-top: 4px;
      text-align: center;
    }

    @media print {
      body {
        margin: 0;
      }
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body>
@php
  $violator = $ticket->violator;
  $vehicle  = $ticket->vehicle;
  $enforcer = $ticket->enforcer;

  // ==== URL + QR ====
  // Official violator portal login URL
  $portalUrl = 'https://posomanagement-production.up.railway.app/vlogin';

  // QR code for the portal URL
  $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data='
          . urlencode($portalUrl);

  // Date reprinted (now on server, Asia/Manila)
  $reprintAt = now()->timezone('Asia/Manila');

  // ==== masking helpers ====
  function maskLicensePrint($license) {
    if (!$license) return '';
    $raw = strtoupper($license);
    $chars = str_split($raw);

    $idx = [];
    foreach ($chars as $i => $ch) {
      if (preg_match('/[A-Z0-9]/', $ch)) $idx[] = $i;
    }
    if (!count($idx)) return $raw;

    $keep = array_slice($idx, -4);
    $keepSet = array_flip($keep);

    foreach ($chars as $i => &$ch) {
      if (!preg_match('/[A-Z0-9]/', $ch)) continue;
      if (!isset($keepSet[$i])) $ch = '*';
    }
    return implode('', $chars);
  }

  function maskNamePrint($v) {
    if (!$v) return '';
    $parts = array_filter([
      $v->first_name ?? null,
      $v->middle_name ?? null,
      $v->last_name ?? null,
    ]);
    if (!count($parts)) return 'N/A';

    $out = [];
    foreach ($parts as $w) {
      $w = trim($w);
      if ($w === '') continue;
      if (mb_strlen($w) === 1) {
        $out[] = $w . '*';
      } else {
        $len = mb_strlen($w);
        $stars = min($len - 1, 3);
        $out[] = mb_substr($w, 0, 1) . str_repeat('*', $stars);
      }
    }
    return implode(' ', $out);
  }

  function maskAddressPrint($addr) {
    if (!$addr) return '';
    $str = (string) $addr;
    $visible = 6;
    $count = 0;
    $out = '';
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
      $ch = $str[$i];
      if (preg_match('/[A-Za-z0-9]/', $ch)) {
        if ($count < $visible) {
          $out .= $ch;
          $count++;
        } else {
          $out .= '*';
        }
      } else {
        $out .= $ch;
      }
    }
    return $out;
  }

  function maskBirthdatePrint($date) {
    if (!$date) return '';
    $str = (string) $date; // e.g. 1999-01-23
    $visibleDigits = 4;
    $seen = 0;
    $out = '';
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
      $ch = $str[$i];
      if (preg_match('/[0-9]/', $ch)) {
        if ($seen < $visibleDigits) {
          $out .= $ch;
          $seen++;
        } else {
          $out .= '*';
        }
      } else {
        $out .= $ch;
      }
    }
    return $out; // 1999-**-**
  }

  $maskedName      = $violator ? maskNamePrint($violator) : 'N/A';
  $maskedAddress   = maskAddressPrint($violator->address ?? '');
  $maskedLicense   = maskLicensePrint($violator->license_number ?? '');
  $maskedBirthdate = maskBirthdatePrint($violator->birthdate ?? '');
@endphp

<div class="ticket-wrap">

  {{-- Top header line with ticket & reprint info --}}
  <div class="header-line">
    <div>
      TICKET #: {{ $ticket->ticket_number }}
    </div>
    <div class="small">
      DATE ISSUED : {{ optional($ticket->issued_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }}<br>
      DATE REPRINT: {{ $reprintAt->format('M d, Y h:i A') }}
    </div>
  </div>

  {{-- Agency header --}}
  <div class="center bold">
    CITY OF SAN CARLOS
  </div>
  <div class="center">
    Public Order and Safety Office
  </div>
  <div class="center mt-2">
    TRAFFIC CITATION TICKET (REPRINT BY ADMIN)
  </div>

  <div class="divider"></div>

  {{-- QR + Portal URL --}}
  <div class="center mt-2">
    <img src="{{ $qrSrc }}" alt="Portal QR" style="width:110px;height:110px;">
  </div>
  <div class="center small mt-2">
    Scan to access violator portal<br>
    {{ $portalUrl }}
  </div>

  <div class="divider"></div>

  {{-- Ticket status --}}
  <div class="label-block">TICKET DETAILS</div>
  <div>
    STATUS   : {{ strtoupper(optional($ticket->status)->name ?? 'N/A') }}<br>
    LOCATION : {{ $ticket->location ?? 'N/A' }}<br>
    IMPOUND  : {{ $ticket->is_impounded ? 'YES' : 'NO' }}
  </div>

  <div class="divider"></div>

  {{-- Violator Info (masked) --}}
  <div class="label-block">VIOLATOR</div>
  <div>
    NAME     : {{ $maskedName }}<br>
    BIRTHDATE: {{ $maskedBirthdate }}<br>
    ADDRESS  : {{ $maskedAddress }}<br>
    LICENSE  : {{ $maskedLicense }}
  </div>

  <div class="divider"></div>

  {{-- Vehicle --}}
  <div class="label-block">VEHICLE</div>
  <div>
    TYPE     : {{ $vehicle->vehicle_type ?? 'N/A' }}<br>
    PLATE NO : {{ $vehicle->plate_number ?? 'N/A' }}<br>
    OWNER    : {{ ($vehicle && $vehicle->is_owner) ? 'SELF' : 'OTHER' }}
    @if($vehicle && !$vehicle->is_owner && $vehicle->owner_name)
      ({{ $vehicle->owner_name }})
    @endif
  </div>

  <div class="divider"></div>

  {{-- Violations --}}
  <div class="label-block">VIOLATIONS</div>
  <div>
    @php
      $lines = [];
      foreach ($ticket->violations as $v) {
        $lines[] = sprintf(
          '%s - P%s',
          $v->violation_name,
          number_format($v->fine_amount, 2)
        );
      }
    @endphp

    @if (count($lines))
      @foreach($lines as $line)
        - {{ $line }}<br>
      @endforeach
    @else
      None recorded.
    @endif
  </div>

  <div class="divider"></div>

  {{-- Enforcer --}}
  <div class="label-block">APPREHENDING ENFORCER</div>
  <div>
    BADGE NO : {{ $enforcer->badge_num ?? 'N/A' }}
  </div>

  <div class="divider"></div>

  {{-- Portal Credentials --}}
  <div class="label-block">PORTAL ACCESS</div>
  <div>
    USERNAME : {{ $violator->username ?? 'N/A' }}<br>
    @if($tempPassword)
      DEFAULT PW: {{ $tempPassword }}<br>
      (Use this login credentials to access your portal.)
    @else
      (Use your existing credentials or Forgot Password.)
    @endif
  </div>

  <div class="divider"></div>

  <div class="footer-note tiny">
    * Reprinted ticket. Some details are masked for privacy and security purposes. *
  </div>

  <div class="mt-8 center no-print">
    <button onclick="window.print()">Print</button>
  </div>
</div>

<script>
  // Auto-print when opened (browser print dialog; choose your Bluetooth printer)
  window.addEventListener('load', () => {
    window.print();
  });
</script>
</body>
</html>
