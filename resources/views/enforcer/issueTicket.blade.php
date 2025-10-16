@extends('components.app')

@section('title', 'POSO Digital Ticket - Cite Ticket')

@push('head')
  {{-- CSRF meta for JS --}}
  <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('body')

{{-- ✅ Include the stylesheet --}}
{{-- If you use Vite --}}

{{-- Or, if you placed it in public/css --}}
{{-- <link rel="stylesheet" href="{{ asset('css/issueTicket.css') }}"> --}}

@php
  $allFlags = \App\Models\Flag::whereIn('key',['is_resident','is_impounded'])->get();
  $old      = old('flags', []);
  $v        = $violator;
@endphp

<div class="issue-page">
  <div class="container py-3">
    {{-- Sticky page topbar --}}
    <div class="page-topbar rounded-3 px-2">
      <h1 class="page-title">Traffic Citation Ticket</h1>
      {{-- Queued badge (updates live) --}}
      <button type="button" class="btn btn-outline-secondary me-2" id="queueInfoBtn" title="Offline queue">
        <i class="bi bi-cloud-arrow-down"></i>
        <span class="ms-1">Queued:</span>
        <span class="badge bg-secondary" id="queueCount">0</span>
      </button>

      {{-- Sync Now --}}
      {{-- <button type="button" class="btn btn-outline-primary me-2" id="syncNowBtn">
       Sync now
      </button> --}}

      <button type="button" class="btn btn-outline-success ms-auto" data-bs-toggle="modal" data-bs-target="#scanIdModal" id="openScanId">
         Scan ID
      </button>
    </div>

    {{-- Form Card --}}
    <div class="card ticket-card mx-auto mt-3">
      <div class="card-body">
        <form id="ticketForm" action="/enforcerTicket" method="POST" data-check-license-url="{{ route('violators.checkLicense') }}">
          @csrf
          @if(isset($v) && $v?->id)
            <input type="hidden" id="current_violator_id" value="{{ $v->id }}">
          @endif
          <div class="row g-3">
            <input type="hidden" id="client_uuid" name="client_uuid" value="{{ old('client_uuid') }}">
            <input type="hidden" id="enforcer_id" name="enforcer_id" value="{{ auth('enforcer')->id() }}">

            {{-- Name --}}
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="first_name" name="first_name"
                     placeholder="First Name" value="{{ $v->first_name ?? old('first_name') }}"  autocomplete="name" autocapitalize="words" inputmode="text" maxlength="50" pattern="^[A-Za-zÀ-ÖØ-öø-ÿ\s.\-']+$" required>
              <label for="first_name">First Name</label>
              <div class="invalid-feedback">Letters only (spaces, dot, apostrophe, hyphen allowed).</div>
            </div>
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="middle_name" name="middle_name"
                     placeholder="Middle Name" value="{{ $v->middle_name ?? old('middle_name') }}" autocapitalize="words" inputmode="text" maxlength="50" pattern="^[A-Za-zÀ-ÖØ-öø-ÿ\s.\-']+$">
              <label for="middle_name">Middle Name</label>
              <div class="invalid-feedback">Letters only (spaces, dot, apostrophe, hyphen allowed).</div>
            </div>
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="last_name" name="last_name"
                     placeholder="Last Name" value="{{ $v->last_name ?? old('last_name') }}" autocomplete="family-name" autocapitalize="words" inputmode="text" maxlength="50" pattern="^[A-Za-zÀ-ÖØ-öø-ÿ\s.\-']+$" required>
              <label for="last_name">Last Name</label>
              <div class="invalid-feedback">Letters only (spaces, dot, apostrophe, hyphen allowed).</div>
            </div>

            {{-- License No. --}}
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="license_num" name="license_num"
                    placeholder="License number" autocomplete="off"
                    value="{{ $v->license_number ?? old('license_num') }}"
                    inputmode="text" maxlength="13"
                    pattern="^[A-Z]\d{2}-\d{2}-\d{6}$" required>
              <label for="license_num">License No.</label>
              <div class="invalid-feedback">Format must be like <strong>A12-34-567890</strong>.</div>
            </div>

            {{-- Address --}}
            <div class="col-12 col-md-6 form-floating">
              <textarea class="form-control" id="address" name="address" placeholder="Full address">{{ $v->address ?? old('address') }}</textarea required>
              <label for="address">Address</label>
            </div>

            {{-- Birthdate --}}
            <div class="col-6 col-md-3 form-floating">
              <input type="date" class="form-control" id="birthdate" name="birthdate"
                     placeholder="Birthdate" value="{{ $v->birthdate ?? old('birthdate') }}" required>
              <label for="birthdate">Birthdate</label>
            </div>

            {{-- Plate No. --}}
            <div class="col-6 col-md-3 form-floating">
              <input type="text" class="form-control" id="plate_num" name="plate_num"
                     placeholder="Plate number" autocomplete="off" value="{{ old('plate_num') }}">
              <label for="plate_num">Plate No.</label>
            </div>

            {{-- Confiscated --}}
            <div class="col-12 col-md-6 form-floating">
              <select class="form-select" id="confiscation_type_id" name="confiscation_type_id">
                <option value="" disabled {{ old('confiscation_type_id')?'':'selected' }}>Choose…</option>
                @foreach(\App\Models\ConfiscationType::all() as $type)
                  <option value="{{ $type->id }}" {{ old('confiscation_type_id') == $type->id ? 'selected' : '' }}>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
              <label for="confiscation_type_id">Confiscated</label>
            </div>

            {{-- Vehicle Type --}}
            <div class="col-12 col-md-6 form-floating">
              <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                <option value="" disabled selected>Choose…</option>
                @foreach(['Motorcycle','Tricycle','Truck','Sedan','Bus','Jeepney','Van','Closed Van','SUV','Pickup'] as $type)
                  <option>{{ $type }}</option>
                @endforeach
              </select>
              <label for="vehicle_type">Vehicle Type</label>
            </div>

            {{-- Owner & Flags --}}
            <div class="col-12 col-md-6 d-flex justify-content-between align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_owner" name="is_owner" value="1" checked>
                <label class="form-check-label" for="is_owner">Violator is owner</label>
              </div>
            </div>

            {{-- Owner Name --}}
            <div class="col-12 col-md-6 form-floating">
              <input type="text" class="form-control" id="owner_name" name="owner_name"
                    placeholder="Owner name" value="{{ old('owner_name') }}"
                    autocapitalize="words" inputmode="text" maxlength="80"
                    pattern="^[A-Za-zÀ-ÖØ-öø-ÿ\s.\-']+$">
              <label for="owner_name">Owner Name</label>
              <div class="invalid-feedback">Letters only (spaces, dot, apostrophe, hyphen allowed).</div>
            </div>
          </div>

          {{-- Violations --}}
          
          <h5 class="section-title mt-3">Select Violations</h5>
          <div class="border-0">
            <div class="form-floating mb-3">
              <select class="form-select" id="categorySelect" aria-label="Select violation category">
                <option value="" disabled selected>Choose category…</option>
                @foreach($violationGroups->keys() as $category)
                  <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
              </select>
              <label for="categorySelect">Category</label>
            </div>
            <div class="input-group mb-2">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="violationSearch" class="form-control" placeholder="Search within category…">
              </div>
            <div id="violationsContainer" class="violations-box"></div>
          </div>

          {{-- Flags & Location --}}
          <div class="row g-3 mt-2">
            <div class="col-12">
              <div class="flags-row"> 
                @foreach($allFlags as $flag)
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="{{ $flag->key }}" name="flags[]" value="{{ $flag->id }}"
                           {{ in_array($flag->id, $old) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $flag->key }}">{{ $flag->label }}</label>
                  </div>
                @endforeach
              </div>
            </div>

            <div class="col-12 col-md-6 form-floating">
              <input type="text" class="form-control" id="location" name="location" placeholder="e.g. Brgy, Sitio" required>
              <label for="location">Location of Apprehension</label>
            </div>
          </div>

          <input type="hidden" name="latitude" id="latitude">
          <input type="hidden" name="longitude" id="longitude">

          {{-- Sticky Submit --}}
          <div class="form-actions">
            <button type="submit" class="btn btn-success w-100 btn-lg">Save &amp; Print</button>
          </div>
        </form>

        {{-- JS Globals --}}
        <script>
          window.violationGroups = @json($violationGroups->toArray());
          window.flagsLookup = @json($allFlags->mapWithKeys(fn($f)=>[$f->id => ['key'=>$f->key,'label'=>$f->label]]));
        </script>
      </div>
    </div>
  </div>
</div>

{{-- Scan ID Modal (OCR-only) --}}
<div class="modal fade" id="scanIdModal" tabindex="-1" aria-labelledby="scanIdModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title fw-semibold" id="scanIdModalLabel">Scan Violator ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="scan-close"></button>
      </div>

      <div class="modal-body p-0">
        <style>
          /* --- Camera Layout (mobile-first) --- */
          .scan-wrap { display:flex; flex-direction:column; height: 80vh; }
          @media (min-width: 576px){ .scan-wrap { height: 70vh; } }
          .camera-area {
            position: relative;
            flex: 1 1 auto;
            display: grid;
            place-items: center;
            background: #000;
            overflow: hidden;
          }
          .camera-frame {
            width: 100%;
            max-width: 520px;
            aspect-ratio: 3 / 2;
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 32px rgba(0,0,0,.35);
            transform: translateZ(0); /* GPU layer to keep video smooth */
          }
          .camera-frame video {
            width: 100%;
            height: 100%;
            object-fit: cover; /* fill nicely on mobile */
            background: #000;
          }
          .overlay-guide {
            position:absolute; inset:6%;
            border: 2px dashed rgba(255,255,255,.5);
            border-radius: 12px;
            pointer-events: none;
          }

          .scan-toolbar {
            padding: .75rem;
            gap: .5rem;
            display:flex;
            align-items:center;
          }

          .scan-toolbar .btn {
            touch-action: manipulation;
          }

          .scan-status { min-width: 160px; text-align:right; }
        </style>

        <div class="scan-wrap">
          <div class="camera-area">
            <div class="camera-frame">
              <video id="ocr-video" playsinline autoplay muted></video>
              <div class="overlay-guide d-none d-sm-block"></div>
              <canvas id="ocr-canvas" class="d-none"></canvas>
            </div>
          </div>

          <div class="scan-toolbar">
            <button class="btn btn-success flex-fill" id="ocr-capture">
              <i class="bi bi-camera"></i> Capture & OCR
            </button>
            <button class="btn btn-outline-secondary" id="ocr-switch" title="Switch Camera">
              <i class="bi bi-arrow-repeat"></i>
            </button>
            <button class="btn btn-outline-dark" id="ocr-torch" title="Toggle Flash">
              <i class="bi bi-lightning-charge"></i>
            </button>
            <div class="ms-auto small scan-status" id="ocr-status"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer py-2">
        <small class="text-muted">Tip: hold the ID flat, fill the frame, use flash in low light.</small>
      </div>
    </div>
  </div>
</div>



@if(session('duplicate_error'))
  <script>Swal.fire({
    icon: 'warning',
    title: 'Oops—Duplicate Entry!',
    text: "{{ session('duplicate_error') }}",
    confirmButtonText: 'Understood',
    confirmButtonColor: '#d33',
    background: '#fff5f5',
    color: '#611a15',
    width: 400,
    showCloseButton: true,
    timer: 4000,
    timerProgressBar: true,
  });</script>
@endif

@endsection

@push('scripts')
  {{-- Load Tesseract first --}}
  <script defer src="{{ asset('vendor/tesseract/tesseract.min.js') }}"></script>
  <script defer src="{{ asset('vendor/tesseract/worker.min.js') }}"></script>
  {{-- Your app scripts (also deferred so Tesseract is guaranteed loaded) --}}
  <script defer src="{{ asset('js/issueTicket.js') }}"></script>
  <script defer src="{{ asset('js/id-scan.js')}}?v={{ filemtime(public_path('js/id-scan.js')) }}"></script>
@endpush