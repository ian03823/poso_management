// -------- IndexedDB setup --------
const db = new Dexie('ticketDB');
db.version(1).stores({ tickets: '++id,payload' });

// -------- Geolocation auto-fill --------
if ('geolocation' in navigator) {
  navigator.geolocation.getCurrentPosition(position => {
    document.getElementById('latitude').value = position.coords.latitude;
    document.getElementById('longitude').value = position.coords.longitude;
    console.log('Location captured:', position.coords.latitude, position.coords.longitude);
  }, error => {
    console.warn('Geolocation failed:', error.message);
  }, {
    enableHighAccuracy: true,
    timeout: 5000,
    maximumAge: 0
  });
} else {
  console.warn('Geolocation not supported by this browser.');
}

async function enqueueTicket(payload) {
  await db.tickets.add({ payload });
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    const reg = await navigator.serviceWorker.ready;
    await reg.sync.register('sync-tickets');
  }
  Swal.fire({
    icon: 'info',
    title: 'Ticket Saved Offline',
    html: 'Your citation was recorded locally.<br>It will auto-sync when back online.',
    confirmButtonText: 'OK',
    width: 400
  });
  document.getElementById('ticketForm').reset();
}

// -------- Network status toasts --------
window.addEventListener('offline', () => {
  Swal.fire({
    toast: true,
    icon: 'warning',
    title: 'You are now offline',
    text: 'Tickets will be saved locally.',
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false,
  });
});

window.addEventListener('online', () => {
  Swal.fire({
    toast: true,
    icon: 'success',
    title: 'Back online!',
    text: 'Offline tickets will now sync.',
    position: 'top-end',
    timer: 3000,
    showConfirmButton: false,
  });
  if (typeof syncOfflineTickets === 'function') syncOfflineTickets();
});


// -------- Violations category & checklist --------
const selectEl    = document.getElementById('categorySelect');
const containerEl = document.getElementById('violationsContainer');
const selected    = new Set();

function renderCategory() {
  containerEl.innerHTML = '';
  const list = window.violationGroups[selectEl.value] || [];
  list.forEach(v => {
    const wrapper = document.createElement('div');
    wrapper.className = 'form-check mb-2';
    wrapper.innerHTML = `
      <input class="form-check-input"
             type="checkbox"
             name="violations[]"
             id="v-${v.id}"
             value="${v.violation_code}"
             ${selected.has(v.violation_code)?'checked':''}>
      <label class="form-check-label" for="v-${v.id}">
        ${v.violation_name} — ₱${parseFloat(v.fine_amount).toFixed(2)}
      </label>`;
    const chk = wrapper.querySelector('input');
    chk.addEventListener('change', () => {
      chk.checked ? selected.add(chk.value) : selected.delete(chk.value);
    });
    containerEl.appendChild(wrapper);
  });
}
selectEl.addEventListener('change', renderCategory);

// -------- Sync function --------
async function syncOfflineTickets() {
  const all = await db.tickets.toArray();
  for (const rec of all) {
    try {
      let res = await fetch('/enforcerTicket', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(rec.payload)
      });
      if (res.ok) await db.tickets.delete(rec.id);
    } catch(e) {
      console.error('Sync failed for', rec.id, e);
    }
  }
}
function getCurrentPositionOnce(opts = { enableHighAccuracy: true, timeout: 7000, maximumAge: 0 }) {
  return new Promise((resolve, reject) => {
    if (!('geolocation' in navigator)) return reject(new Error('Geolocation not supported'));
    navigator.geolocation.getCurrentPosition(resolve, reject, opts);
  });
}
async function ensureGpsFields() {
  const latEl = document.getElementById('latitude');
  const lngEl = document.getElementById('longitude');
  if (latEl.value && lngEl.value) return true; // already set

  try {
    const pos = await getCurrentPositionOnce();
    latEl.value = pos.coords.latitude;
    lngEl.value = pos.coords.longitude;
    return true;
  } catch (e) {
    // Let the enforcer choose: continue without GPS or cancel
    const { isConfirmed } = await Swal.fire({
      icon: 'warning',
      title: 'GPS not available',
      html: 'Allow location or move to an open area. Continue without coordinates?',
      showCancelButton: true,
      confirmButtonText: 'Continue',
      cancelButtonText: 'Cancel'
    });
    return isConfirmed;
  }
}


// -------- Form submission (online/offline) --------
document.getElementById('ticketForm')
  .addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);

    // flatten FormData into an object
    const payload = {};
    data.forEach((v,k) => {
      if (k.endsWith('[]')) {
        const key = k.replace('[]','');
        payload[key] = payload[key]||[];
        payload[key].push(v);
      } else {
        payload[k] = v;
      }
    });

    if (!navigator.onLine) {
      return enqueueTicket(payload);
    }

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
            console.log('DEBUG ticket data →', p.ticket);

            // 2) Show confirmation
            let html = `
            <strong>Ticket #:</strong> ${p.ticket.ticket_number}<br>
            <strong>Enforcer:</strong> ${p.enforcer.name}<br>
            <strong>Violator:</strong> ${p.violator.first_name + p.violator.middle_name + p.violator.last_name}<br>
            <strong>License No.:</strong> ${p.violator.license_number}<br>
            <strong>Plate:</strong> ${p.vehicle.plate_number}<br>
            <strong>Type:</strong> ${p.vehicle.vehicle_type}<br>
            <strong>Owner:</strong> ${p.vehicle.is_owner}<br>
            <strong>Owner Name:</strong> ${p.vehicle.owner_name}<br>
            <strong>Resident:</strong> ${p.ticket.is_resident ? 'Yes' : 'No'}<br>
            <strong>Location:</strong> ${p.ticket.location}<br>
            <strong>Confiscated:</strong> ${p.ticket.confiscated}<br>
            <strong>Impounded:</strong> ${p.ticket.is_impounded ? 'Yes' : 'No'}<br> 
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
              title: 'Confirm Details',
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

            const ESC    = '\x1B',
            GS     = '\x1D',
            NL     = '\x0A';

             // Initialize & font selects
            const INIT   = ESC + '@';         // reset
            const FONT_A = ESC + 'M' + '\x00';// Font A = default
            const FONT_B = ESC + 'M' + '\x01';// Font B = smaller

            function getQrCodeCommand(data) {
              const storeLen = data.length + 3;
              const pL = storeLen % 256;
              const pH = Math.floor(storeLen / 256);

              const QRModel = '\x1D\x28\x6B\x04\x00\x31\x41\x32\x00';  // Model 2
              const QRSize  = '\x1D\x28\x6B\x03\x00\x31\x43\x06';      // Size 6
              const QRError = '\x1D\x28\x6B\x03\x00\x31\x45\x30';      // Error correction L
              const QRStore = '\x1D\x28\x6B' + String.fromCharCode(pL, pH) + '\x31\x50\x30' + data;
              const QRPrint = '\x1D\x28\x6B\x03\x00\x31\x51\x30';      // Print command

              return QRModel + QRSize + QRError + QRStore + QRPrint;
            }

            const qrCommand = getQrCodeCommand('https://poso.gov.ph/t');

            // Print QR code first
            const qrBytes = new TextEncoder().encode(qrCommand);
            for (let i = 0; i < qrBytes.length; i += 20) {
              await ch.writeValue(qrBytes.slice(i, i + 20));
              await new Promise(r => setTimeout(r, 50));
            }


            // Build ESC/POS text once
            let txt = '';
            txt += INIT + FONT_B;
            txt += '\tCity of San Carlos' + NL;
            txt += 'Public Order and Safety Office' + NL;
            txt += '\t(POSO)' + NL + NL;
            txt += 'Traffic Citation Ticket' + NL;
            txt += '\tENFORCER' + NL;
            txt += 'Ticket #: '+ p.ticket.ticket_number + NL;
            txt += 'Date issued: ' + p.ticket.issued_at + NL + NL;
            txt += 'Violator: ' + p.violator.first_name +' '+ p.violator.middle_name +' '+ p.violator.last_name + NL;
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
            if (p.ticket.is_impounded) {
            txt += '*** VEHICLE IMPOUNDED ***' + NL + NL;
            }
            txt += 'Badge No: ' + p.enforcer.badge_num + NL + NL;
            txt += NL + NL + NL + NL;
            txt += '__________________________' + NL;
            txt += 'Signature of Violator' + NL + NL;
            txt += '- - - - - - - - - - - - - - - -' + NL + NL;
            txt += '\thttps://poso_management.test' + NL;
            txt += NL + NL + NL + NL;
            txt += '\tCity of San Carlos' + NL;
            txt += 'Public Order and Safety Office' + NL;
            txt += '\t(POSO)' + NL + NL;
            txt += 'Traffic Citation Ticket' + NL;
            txt += '\tVIOLATOR' + NL;
            txt += 'Ticket #: '      + p.ticket.ticket_number        + NL;
            txt += 'Date issued: ' + p.ticket.issued_at + NL + NL;
            txt += 'Violator: ' + p.violator.first_name + ' '+ p.violator.middle_name +' '+ p.violator.last_name + NL;
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
            if (p.ticket.is_impounded) {
            txt += '*** VEHICLE IMPOUNDED ***' + NL + NL;
            }
            txt += 'Badge No: ' + p.enforcer.badge_num + NL;
            txt += FONT_A;
            txt += ESC + 'd' + '\x03';    // feed 3 lines
            txt += GS  + 'V' + '\x00';    // full cut
             
            
            const dataBytes = new TextEncoder().encode(txt);
            for (let i = 0; i < dataBytes.length; i += 20) {
                await ch.writeValue(dataBytes.slice(i, i + 20));
                await new Promise(r => setTimeout(r, 50));
            }

            // 4) Show success, then reset form
            await Swal.fire('Success', 'Ticket Submitted.', 'success');
            form.reset();

        } catch (err) {
            console.error(err);
            Swal.fire('Error', err.message || err, 'error');
        }
  });

// -------- Auto-fill owner name --------
const ownerChk = document.getElementById('is_owner');
const ownerIn  = document.getElementById('owner_name');
const violFirst = document.getElementById('first_name');
const violMiddle = document.getElementById('middle_name');
const violLast = document.getElementById('last_name');

function syncOwner() {
  if (ownerChk.checked) {
      let full = violFirst.value.trim();
      if (violMiddle.value.trim()) full += ' ' + violMiddle.value.trim();
      if (violLast.value.trim())  full += ' ' + violLast.value.trim();
      ownerIn.value     = full;
      ownerIn.readOnly  = true;
    } else {
      ownerIn.value     = '';
      ownerIn.readOnly  = false;
    }
}
ownerChk.addEventListener('change', syncOwner);
[violFirst, violMiddle, violLast].forEach(el =>
    el.addEventListener('input', () => ownerChk.checked && syncOwner())
);
syncOwner();