@extends('components.app')

@section('title', 'POSO Enforcer Management')

@section('body')
  <style>
    /* Ensure page is scrollable */
    html, body {
      overflow: auto !important;
      height: auto !important;
    }
  </style>
  @php
    $allFlags = \App\Models\Flag::whereIn('key',['is_resident','is_impounded'])->get();
    $old      = old('flags', []);
  @endphp

  @php $v = $violator; @endphp

  <div class="container py-4">
    {{-- Page Title --}}
    <h1 class="h4 text-center mb-4">Traffic Citation Ticket</h1>
    <button type="button" class="btn btn-outline-success mb-3" data-bs-toggle="modal" data-bs-target="#scanIdModal" id="openScanId">
      <i class="bi bi-camera"></i> Scan ID
    </button>
    {{-- Form Card --}}
    <div class="card mx-auto shadow-sm rounded-3 ticket-card" style="max-width: 800px;">
      <div class="card-body p-3 p-sm-4">
        <form id="ticketForm" action="/enforcerTicket" method="POST">
          @csrf
          <div class="row g-3">
            {{-- Name --}}
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="first_name" name="first_name"
                     placeholder="First Name" value="{{ $v->first_name ?? old('first_name') }}">
              <label for="name">First Name</label>
            </div>
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="middle_name" name="middle_name"
                     placeholder="Middle Name" value="{{ $v->middle_name ?? old('middle_name') }}">
              <label for="name">Middle Name</label>
            </div>
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="last_name" name="last_name"
                     placeholder="Last Name" value="{{ $v->last_name ?? old('last_name') }}">
              <label for="name">Last Name</label>
            </div>
            {{-- License No. --}}
            <div class="col-6 col-md-6 form-floating">
              <input type="text" class="form-control" id="license_num" name="license_num"
                     placeholder="License number" autocomplete="off"
                     value="{{ $v->license_number ?? old('license_num') }}">
              <label for="license_num">License No.</label>
            </div>
            {{-- Address --}}
            <div class="col-12 col-md-6 form-floating">
              <textarea class="form-control" id="address" name="address"
                        placeholder="Full address" style="height: 3rem">{{ $v->address ?? old('address') }}</textarea>
              <label for="address">Address</label>
            </div>
            {{-- Birthdate --}}
            <div class="col-6 col-md-3 form-floating">
              <input type="date" class="form-control" id="birthdate" name="birthdate"
                     placeholder="Birthdate" value="{{ $v->birthdate ?? old('birthdate') }}">
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
                  <option value="{{ $type->id }}"
                          {{ old('confiscation_type_id') == $type->id ? 'selected' : '' }}>
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
            {{-- Owner & Resident --}}
            <div class="col-12 col-md-6 d-flex justify-content-evenly align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_owner" name="is_owner" value="1" checked>
                <label class="form-check-label" for="is_owner">Violator is owner</label>
              </div>
              <!-- <div class="form-check">
                <input type="hidden" name="is_resident" value="0">
                <input class="form-check-input" type="checkbox" id="is_resident" name="is_resident" value="1"
                       {{ old('is_resident', true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_resident">Resident</label>
              </div> -->
            </div>
            {{-- Owner Name --}}
            <div class="col-12 col-md-6 form-floating">
              <input type="text" class="form-control" id="owner_name" name="owner_name"
                     placeholder="Owner name" value="{{ old('owner_name') }}">
              <label for="owner_name">Owner Name</label>
            </div>
          </div>

          {{-- Violations --}}
          <div class="mt-4">
            <h5 class="mb-2">Select Violations</h5>
            <div class="border rounded p-3">
              <div class="form-floating mb-3">
                <select class="form-select" id="categorySelect" aria-label="Select violation category">
                  <option value="" disabled selected>Choose category…</option>
                  @foreach($violationGroups->keys() as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                  @endforeach
                </select>
                <label for="categorySelect">Category</label>
              </div>
              <div id="violationsContainer" class="px-1 overflow-auto" style="max-height:250px;"></div>
            </div>
          </div>

          {{-- Impounded & Location --}}
          <div class="row g-3 mt-3">
            <!-- <div class="col-12 col-md-6 d-flex align-items-center">
              <div class="form-check">
                <input type="hidden" name="is_impounded" value="0">
                <input class="form-check-input" type="checkbox" id="is_impounded" name="is_impounded" value="1"
                       {{ old('is_impounded', false) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_impounded">Vehicle Impounded</label>
              </div>
            </div> -->
            @foreach($allFlags as $flag)
              <div class="form-check">
                <input 
                  class="form-check-input" 
                  type="checkbox" 
                  id="{{ $flag->key }}" 
                  name="flags[]" 
                  value="{{ $flag->id }}"
                  {{ in_array($flag->id, $old) ? 'checked' : '' }}
                >
                <label class="form-check-label" for="{{ $flag->key }}">
                  {{ $flag->label }}
                </label>
              </div>
            @endforeach
            <div class="col-12 col-md-6 form-floating">
              <input type="text" class="form-control" id="location" name="location"
                     placeholder="e.g. Brgy, Sitio" required>
              <label for="location">Location of Apprehension</label>
            </div>
          </div>

          <input type="hidden" name="latitude" id="latitude">
          <input type="hidden" name="longitude" id="longitude">

          {{-- Submit --}}
          <div class="mt-4">
            <button type="submit" class="btn btn-primary w-100 btn-lg">Save &amp; Print</button>
          </div>
        </form>

        {{-- Pass PHP data into a JS global --}}
        <script> 
          window.violationGroups = @json($violationGroups->toArray()); 
          window.flagsLookup = @json(
            $allFlags->mapWithKeys(fn($f)=>[$f->id => ['key'=>$f->key,'label'=>$f->label]])
          );
        </script>
      </div>
    </div>
  </div>


  


  <div class="modal fade" id="scanIdModal" tabindex="-1" aria-labelledby="scanIdModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-semibold" id="scanIdModalLabel">Scan Violator ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="scan-close"></button>
      </div>

      <div class="modal-body">
        <!-- Tabs: QR (default) and OCR fallback -->
        <ul class="nav nav-pills mb-3" id="scanTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-qr" data-bs-toggle="pill" data-bs-target="#pane-qr" type="button" role="tab">QR Scan</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-ocr" data-bs-toggle="pill" data-bs-target="#pane-ocr" type="button" role="tab">OCR (Photo)</button>
          </li>
        </ul>

        <div class="tab-content">
          <!-- QR MODE -->
          <div class="tab-pane fade show active" id="pane-qr" role="tabpanel" aria-labelledby="tab-qr">
            <div class="rounded border p-2">
              <div id="qr-reader" style="width:100%; max-width:420px; margin:auto;"></div>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-secondary" id="qr-stop">Stop</button>
              <small class="text-muted ms-auto">Tip: point at the QR of the ID</small>
            </div>
          </div>

          <!-- OCR MODE -->
          <div class="tab-pane fade" id="pane-ocr" role="tabpanel" aria-labelledby="tab-ocr">
            <div class="rounded border p-2">
              <video id="ocr-video" playsinline autoplay muted style="width:100%; max-width:420px;"></video>
              <canvas id="ocr-canvas" class="d-none"></canvas>
            </div>
            <div class="d-flex gap-2 mt-3 align-items-center">
              <button class="btn btn-success" id="ocr-capture">Capture & OCR</button>
              <button class="btn btn-outline-secondary" id="ocr-switch">Switch Camera</button>
              <div class="ms-auto" id="ocr-status" style="min-width: 140px;"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <small class="text-muted">Camera runs on *https* only. This works offline once cached.</small>
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
      });
    </>
    @endif

  {{-- External scripts --}}
  <script src="https://cdn.jsdelivr.net/npm/dexie/dist/dexie.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="{{ asset('js/issueTicket.js') }}"></script>
  <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
  <script src="{{ asset('vendor/tesseract/tesseract.min.js') }}"></script>
  <script src="{{ asset('js/id-scan.js') }}"></script>


@endsection
