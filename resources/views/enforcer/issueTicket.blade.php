@extends('components.app')

@section('title', 'Issue A Ticket')

@section('body')
  <style>
    /* Ensure page is scrollable */
    html, body {
      overflow: auto !important;
      /* allow full height */
      height: auto !important;
    }
  </style>

  <div class="container py-4">
    {{-- Page Title --}}
    <h1 class="h4 text-center mb-4">Traffic Citation Ticket</h1>

    {{-- Form Card --}}
    <div class="card mx-auto shadow-sm rounded-3" style="max-width: 800px;">
      <div class="card-body p-3 p-sm-4">

        <form id="ticketForm" action="/enforcerCreate" method="POST">
          @csrf
          <div class="row g-3">
            {{-- Name --}}
            <div class="col-12 col-md-6 form-floating">
              <input type="text" class="form-control" id="name" name="name"
                    placeholder="Full name" value="{{ old('name') }}">
              <label for="name">To: Full name</label>
            </div>

            {{-- Address --}}
            <div class="col-12 col-md-6 form-floating">
              <textarea class="form-control" id="address" name="address"
                        placeholder="Full address" style="height: 3rem" required></textarea>
              <label for="address">Address</label>
            </div>

            {{-- Mobile --}}
            <div class="col-12 col-md-6 form-floating">
              <input type="tel" class="form-control" id="phone" name="phone_number"
                    placeholder="11 digit number" pattern="\d{11}" {{ old('phone_number') }}>
              <label for="phone">Mobile No.</label>
            </div>

            {{-- Birthdate --}}
            <div class="col-6 col-md-3 form-floating">
              <input type="date" class="form-control" id="birthdate" name="birthdate"
                    placeholder="Birthdate" required>
              <label for="birthdate">Birthdate</label>
            </div>

            {{-- License No. --}}
            <div class="col-6 col-md-3 form-floating">
              <input type="text" class="form-control" id="license_num" name="license_num"
                    placeholder="License number" autocomplete="off" {{ old('license_num') }}>
              <label for="license_num">License No.</label>
            </div>  
            {{-- Plate No. --}}
            <div class="col-6 col-md-3 form-floating">
              <input type="text" class="form-control" id="plate_num" name="plate_num"
                    placeholder="Plate number" autocomplete="off" {{ old('plate_num') }}>
              <label for="plate_num">Plate No.</label>
            </div>

            {{-- Confiscated --}}
            <div class="col-12 col-md-6 form-floating">
              <select class="form-select" id="confiscated" name="confiscated" required>
                <option value="" disabled selected>Choose…</option>
                <option value="None">None</option>
                <option value="License ID">License ID</option>
                <option value="Plate Number">Plate Number</option>
                <option value="ORCR">ORCR</option>
                <option value="TCT/TOP">TCT/TOP</option>
              </select>
              <label for="confiscated">Confiscated</label>
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

            {{-- Owner Checkbox --}}
            <div class="col-12 col-md-6 d-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_owner"
                      name="is_owner" value="1" checked>
                <label class="form-check-label" for="is_owner">Violator is owner</label>
              </div>
            </div>

            <div class="col-12 col-md-6 d-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_resident" name="is_resident" value="1">
                <label class="form-check-label" for="is_resident">
                  Resident
                </label>
              </div>
            </div>

            {{-- Owner Name --}}
            <div class="col-12 col-md-6 form-floating">
              <input type="text" class="form-control" id="owner_name" name="owner_name"
                    placeholder="Owner name" {{ old('owner_name') }}>
              <label for="owner_name">Owner Name</label>
            </div>
          </div>
          
          {{-- Violations Section --}}
          <div class="mt-4">
            <h5 class="mb-2">Select Violations</h5>
            <div class="accordion accordion-flush" id="violationsAccordion">
              @foreach($violationGroups as $category => $violations)
                @php $slug = Str::slug($category); @endphp
                <div class="accordion-item">
                  <h2 class="accordion-header" id="heading-{{ $slug }}">
                    <button class="accordion-button collapsed py-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapse-{{ $slug }}"
                            aria-expanded="false"
                            aria-controls="collapse-{{ $slug }}">
                      {{ $category }}
                    </button>
                  </h2>
                  <div id="collapse-{{ $slug }}"
                      class="accordion-collapse collapse"
                      aria-labelledby="heading-{{ $slug }}"
                      data-bs-parent="#violationsAccordion">
                    <div class="accordion-body">
                      @foreach($violations as $v)
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox"
                                name="violations[]" id="violation-{{ $v->id }}"
                                value="{{ $v->violation_code }}">
                          <label class="form-check-label" for="violation-{{ $v->id }}">
                            {{ $v->violation_name }} — ₱{{ number_format($v->fine_amount,2) }}
                          </label>
                        </div>
                      @endforeach
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

          {{-- Impounded & Location --}}
          <div class="row g-3 mt-3">
            <div class="col-12 col-md-6 d-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_impounded"
                      name="is_impounded" value="1">
                <label class="form-check-label" for="is_impounded">
                  Vehicle Impounded
                </label>
              </div>
            </div>
            <div class="col-12 col-md-6 form-floating">
              <input type="text" class="form-control" id="location" name="location"
                    placeholder="e.g. Brgy, Street" required>
              <label for="location">Location of Apprehension</label>
            </div>
          </div>

          {{-- Submit --}}
          <div class="mt-4">
            <button type="submit" class="btn btn-primary w-100 btn-lg">
              Save &amp; Print
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>


    <!-- Include SweetAlert2 and your script -->
    <script src="https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    console.log('ticket script loaded')
    document.getElementById('ticketForm')
        .addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);

        try {
            // 1) Create ticket
            const res = await fetch('/enforcerTicket', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': form._token.value,
                'Accept': 'application/json'
            },
            body: data
            });
            if (!res.ok) {
            const msg = await res.text();
            return Swal.fire('Error', msg, 'error');
            }
            const p = await res.json();

            // 2) Show confirmation
            let html = `
            <strong>Enforcer:</strong> ${p.enforcer.name}<br>
            <strong>Violator:</strong> ${p.violator.name}<br>
            <strong>License No.:</strong> ${p.violator.license_number}<br>
            <strong>Plate:</strong> ${p.vehicle.plate_number}<br>
            <strong>Type:</strong> ${p.vehicle.vehicle_type}<br>
            <strong>Owner:</strong> ${p.vehicle.is_owner}<br>
            <strong>Owner Name:</strong> ${p.vehicle.owner_name}<br>
            <strong>Resident:</strong> ${p.ticket.is_resident ? 'Yes' : 'No'}<br>
            <strong>Location:</strong> ${p.ticket.location}<br>
            <strong>Confiscated:</strong> ${p.ticket.confiscated}<br>
            <strong>Impounded?:</strong> ${p.ticket.is_impounded}<br>
            <strong>Last Apprehended:</strong> ${p.last_apprehended_at || 'Never'}<br>
            <strong>Username:</strong> ${p.credentials.username}<br>
            <strong>Password:</strong> ${p.credentials.password}<br>
            <strong>Violations:</strong>
            <ul>`;
            p.violations.forEach(v => {
            html += `<li>${v.name} — Php${v.fine}</li>`;
            });
            html += `</ul>`;

            const { isConfirmed } = await Swal.fire({
            title: 'Confirm & Print',
            html,
            width: 600,
            showCancelButton: true,
            confirmButtonText: 'Save & Print',
            });
            if (!isConfirmed) return;

            // 3) Print two copies (single connection)
            const S = '49535343-fe7d-4ae5-8fa9-9fafd205e455';
            const C = '49535343-8841-43f4-a8d4-ecbe34729bb3';
            const dev = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: [S]
            });
            const srv = await dev.gatt.connect();
            const svc = await srv.getPrimaryService(S);
            const ch  = await svc.getCharacteristic(C);

            // Build ESC/POS text once
            const ESC = '\x1B', GS = '\x1D', NL = '\x0A';
            let txt = '';
            txt += '\tCity of San Carlos' + NL;
            txt += 'Public Order and Safety Office' + NL;
            txt += '\t(POSO)' + NL + NL;
            txt += '\tTraffic Citation Ticket' + NL;
            txt += 'Date issued: ' + p.ticket.issued_at + NL + NL;
            txt += 'Violator: ' + p.violator.name + NL;
            txt += 'Phone: ' + p.violator.phone_number + NL + NL;
            txt += 'Birthdate: ' + p.violator.birthdate + NL;
            txt += 'Address: ' + p.violator.address + NL;
            txt += 'License No.: ' + p.violator.license_number + NL + NL;
            txt += 'Plate: ' + p.vehicle.plate_number + NL;
            txt += 'Type: ' + p.vehicle.vehicle_type + NL;
            txt += 'Owner: ' + p.vehicle.is_owner + NL;
            txt += 'Owner Name: ' + p.vehicle.owner_name + NL + NL;
            txt += 'Violations:' + NL;
            p.violations.forEach(v => {
            txt += `- ${v.name} (Php${v.fine})` + NL;
            });
            txt += NL;
            txt += 'Username: ' + p.credentials.username + NL;
            txt += 'Password: ' + p.credentials.password + NL + NL;
            txt += 'Last Apprehended: ' + (p.last_apprehended_at || 'Never') + NL + NL;
            if (p.ticket.is_impounded === 'true') {
            txt += '*** VEHICLE IMPOUNDED ***' + NL + NL;
            }
            txt += 'Badge No: ' + p.enforcer.badge_num + NL;
            txt += NL + ESC + 'd' + '\x03' + GS + 'V' + '\x00';

            // Encode and send twice
            const dataBytes = new TextEncoder().encode(txt);
            for (let copy = 0; copy < 2; copy++) {
            for (let i = 0; i < dataBytes.length; i += 20) {
                await ch.writeValue(dataBytes.slice(i, i + 20));
                await new Promise(r => setTimeout(r, 50));
            }
            }

            // 4) Show success, then reset form
            await Swal.fire('Success', 'Ticket Submitted.', 'success');
            form.reset();

        } catch (err) {
            console.error(err);
            Swal.fire('Error', err.message || err, 'error');
        }
        });

    // Bluetooth-print helper
    async function printReceipt(p) {
        const S = '49535343-fe7d-4ae5-8fa9-9fafd205e455';
        const C = '49535343-8841-43f4-a8d4-ecbe34729bb3';

        const dev = await navigator.bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: [S]
        });
        const srv = await dev.gatt.connect();
        const svc = await srv.getPrimaryService(S);
        const ch  = await svc.getCharacteristic(C);

        const ESC = '\x1B', GS = '\x1D', NL = '\x0A';
        let txt = '';
        txt += '\tCity of San Carlos' + NL;
        txt += 'Public Order and Safety Office' + NL;
        txt += '\t\t(POSO)' + NL + NL;
        txt += '\tTraffic Citation Ticket' + NL;
        txt += 'Date issued: ' + p.ticket.issued_at + NL + NL;
        txt += 'Violator: ' + p.violator.name + NL;
        txt += 'Phone: ' + p.violator.phone_number + NL + NL;
        txt += 'Birthdate: ' + p.violator.birthdate + NL;
        txt += 'Address: ' + p.violator.address + NL;
        txt += 'Resident: ' + (p.ticket.is_resident ? 'Yes' : 'No') + NL;
        txt += 'License No.: ' + p.violator.license_number + NL + NL;
        txt += 'Plate No.: ' + p.vehicle.plate_number + NL;
        txt += 'Type: ' + p.vehicle.vehicle_type + NL;
        txt += 'Owner: ' + p.vehicle.is_owner + NL;
        txt += 'Owner Name: ' + p.vehicle.owner_name + NL + NL;
        txt += 'Violation(s):' + NL;
        p.violations.forEach(v => {
        txt += `- ${v.name} (Php${v.fine})` + NL;
        });
        txt += NL;
        txt += 'Username: ' + p.credentials.username + NL;
        txt += 'Password: ' + p.credentials.password + NL + NL;
        txt += 'Last Apprehended: ' + (p.last_apprehended_at || 'Never') + NL + NL;
        if (p.ticket.is_impounded === 'true') {
        txt += '*** VEHICLE IMPOUNDED ***' + NL + NL;
        }
        txt += 'Badge No: ' + p.enforcer.badge_num + NL;
        txt += NL + ESC + 'd' + '\x03' + GS + 'V' + '\x00';

        const data = new TextEncoder().encode(txt);
        for (let i = 0; i < data.length; i += 20) {
        await ch.writeValue(data.slice(i, i + 20));
        await new Promise(r => setTimeout(r, 50));
        }
    }


    const isOwnerCheckbox = document.getElementById('is_owner');
    const ownerInput      = document.getElementById('owner_name');
    const violatorName    = document.querySelector('input[name="name"]');
    function syncOwner() {
    if (isOwnerCheckbox.checked) {
      ownerInput.value    = violatorName.value;
      ownerInput.readOnly = true;
    } else {
        ownerInput.value    = '';
        ownerInput.readOnly = false;
        }
    }
    isOwnerCheckbox.addEventListener('change', syncOwner);
    violatorName.addEventListener('input', syncOwner);
    syncOwner();
    </script>

@endsection