/* issueTicket.js — robust confirm-before-save with offline support + idempotency */

/* ---------------- Dexie (IndexedDB) setup with upgrade ---------------- */
const db = new Dexie('ticketDB');
db.version(1).stores({ tickets: '++id,payload' }); // legacy
// Add an index on client_uuid so we can dedupe queued tickets
db.version(2).stores({ tickets: '++id,client_uuid' }).upgrade(tx =>
  tx.table('tickets').toCollection().modify(t => {
    if (!t.client_uuid) {
      // try to lift from payload or assign
      t.client_uuid = t.payload?.client_uuid || (crypto?.randomUUID?.() || String(Date.now()) + Math.random());
    }
  })
);

/* ---------------- Utility helpers ---------------- */
const sleep = (ms) => new Promise(r => setTimeout(r, ms));

async function isReallyOnline(timeoutMs = 2000) {
  // Fast check first
  if (!navigator.onLine) return false;

  // Better check: ping server (add a /ping route on backend)
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const res = await fetch('/ping', { method: 'GET', cache: 'no-store', signal: ctrl.signal });
    clearTimeout(t);
    return res.ok;
  } catch {
    clearTimeout(t);
    return false;
  }
}

function ensureViolationLookup() {
  if (!window.violationGroups) return {};
  const byCode = {};
  Object.values(window.violationGroups).forEach(list => {
    (list || []).forEach(v => {
      byCode[v.violation_code] = v;
    });
  });
  return byCode;
}

function buildConfirmHtmlFromPayload(payload) {
  const byCode = ensureViolationLookup();
  const full = [payload.first_name, payload.middle_name, payload.last_name].filter(Boolean).join(' ');
  const violHtml = (payload.violations || []).map(code => {
    const v = byCode[code];
    if (!v) return `<li>${code}</li>`;
    const fine = v.fine_amount != null ? ` — ₱${parseFloat(v.fine_amount).toFixed(2)}` : '';
    return `<li>${v.violation_name}${fine}</li>`;
  }).join('');

  return `
    <strong>Violator:</strong> ${full || '—'}<br>
    <strong>License No.:</strong> ${payload.license_number || '—'}<br>
    <strong>Plate:</strong> ${payload.plate_number || '—'}<br>
    <strong>Vehicle Type:</strong> ${payload.vehicle_type || '—'}<br>
    <strong>Owner:</strong> ${payload.is_owner ? 'Yes' : 'No'}<br>
    <strong>Owner Name:</strong> ${payload.owner_name || '—'}<br>
    <strong>Resident:</strong> ${payload.is_resident ? 'Yes' : 'No'}<br>
    <strong>Location:</strong> ${payload.location || '—'}<br>
    <strong>Impounded:</strong> ${payload.is_impounded ? 'Yes' : 'No'}<br>
    <strong>Confiscated:</strong> ${payload.confiscated || '—'}<br>
    <strong>Violations:</strong>
    <ul>${violHtml}</ul>
  `;
}

function makeOfflineTicketNumber(uuid) {
  const tail = (uuid || '').replace(/-/g, '').slice(-6).toUpperCase();
  return `TEMP-${tail || Math.floor(Math.random()*1e6).toString().padStart(6,'0')}`;
}

/* ---------------- Geolocation helpers ---------------- */
function getCurrentPositionOnce(opts = { enableHighAccuracy: true, timeout: 7000, maximumAge: 0 }) {
  return new Promise((resolve, reject) => {
    if (!('geolocation' in navigator)) return reject(new Error('Geolocation not supported'));
    navigator.geolocation.getCurrentPosition(resolve, reject, opts);
  });
}

async function ensureGpsFields() {
  const latEl = document.getElementById('latitude');
  const lngEl = document.getElementById('longitude');
  if (latEl?.value && lngEl?.value) return true; // already set

  try {
    const pos = await getCurrentPositionOnce();
    latEl.value = pos.coords.latitude;
    lngEl.value = pos.coords.longitude;
    return true;
  } catch (e) {
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

/* ---------------- Network status toasts ---------------- */
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

/* ---------------- Offline queueing with idempotency ---------------- */
async function enqueueTicket(payload) {
  // idempotent: avoid duplicate queue items for same client_uuid
  const exists = await db.tickets.where('client_uuid').equals(payload.client_uuid).first();
  if (!exists) {
    await db.tickets.add({ client_uuid: payload.client_uuid, payload, createdAt: Date.now() });
  }

  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    const reg = await navigator.serviceWorker.ready;
    try { await reg.sync.register('sync-tickets'); } catch {}
  }

  await Swal.fire({
    icon: 'info',
    title: 'Saved Offline',
    html: 'Your ticket was recorded locally and will auto-sync when online.',
    confirmButtonText: 'OK',
    width: 420
  });
}

async function syncOfflineTickets() {
  const all = await db.tickets.orderBy('id').toArray();
  for (const rec of all) {
    try {
      const res = await fetch('/enforcerTicket', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Idempotency-Key': rec.client_uuid || rec.payload?.client_uuid || ''
        },
        body: JSON.stringify(rec.payload)
      });
      if (res.ok) {
        await db.tickets.delete(rec.id);
      }
    } catch (e) {
      // keep it for next time
      console.warn('Sync failed for', rec.id, e);
    }
  }
}

/* ---------------- Bluetooth printing helpers ---------------- */
async function bluetoothWrite(characteristic, bytes) {
  // Write in chunks (20 bytes) with tiny gaps
  for (let i = 0; i < bytes.length; i += 20) {
    await characteristic.writeValue(bytes.slice(i, i + 20));
    await sleep(40);
  }
}

async function getPrinterCharacteristic() {
  const S = '49535343-fe7d-4ae5-8fa9-9fafd205e455';
  const C = '49535343-8841-43f4-a8d4-ecbe34729bb3';
  const dev = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: [S] });
  const srv = await dev.gatt.connect();
  const svc = await srv.getPrimaryService(S);
  return await svc.getCharacteristic(C);
}

function buildQrCommand(data) {
  // ESC/POS QR (Store→Print)
  const storeLen = data.length + 3;
  const pL = storeLen % 256;
  const pH = Math.floor(storeLen / 256);
  const QRModel = '\x1D\x28\x6B\x04\x00\x31\x41\x32\x00';
  const QRSize  = '\x1D\x28\x6B\x03\x00\x31\x43\x06';
  const QRError = '\x1D\x28\x6B\x03\x00\x31\x45\x30';
  const QRStore = '\x1D\x28\x6B' + String.fromCharCode(pL, pH) + '\x31\x50\x30' + data;
  const QRPrint = '\x1D\x28\x6B\x03\x00\x31\x51\x30';
  return QRModel + QRSize + QRError + QRStore + QRPrint;
}

async function printServerTicket(p) {
  const ESC = '\x1B', GS = '\x1D', NL = '\x0A';
  const INIT = ESC + '@', FONT_A = ESC + 'M' + '\x00', FONT_B = ESC + 'M' + '\x01';

  const ch = await getPrinterCharacteristic();

  // QR first (e.g., portal URL)
  const qrCmd = buildQrCommand('https://poso.gov.ph/t');
  await bluetoothWrite(ch, new TextEncoder().encode(qrCmd));

  const lines = (copyLabel) => {
    let t = '';
    t += INIT + FONT_B;
    t += '\tCity of San Carlos' + NL;
    t += 'Public Order and Safety Office' + NL;
    t += '\t(POSO)' + NL + NL;
    t += 'Traffic Citation Ticket' + NL;
    t += `\t${copyLabel}` + NL;
    t += 'Ticket #: ' + p.ticket.ticket_number + NL;
    t += 'Date issued: ' + p.ticket.issued_at + NL + NL;
    t += 'Violator: ' + [p.violator.first_name, p.violator.middle_name, p.violator.last_name].filter(Boolean).join(' ') + NL;
    t += 'Birthdate: ' + (p.violator.birthdate || '') + NL;
    t += 'Address: ' + (p.violator.address || '') + NL;
    t += 'License No.: ' + (p.violator.license_number || '') + NL + NL;
    t += 'Plate: ' + (p.vehicle.plate_number || '') + NL;
    t += 'Type: ' + (p.vehicle.vehicle_type || '') + NL;
    t += 'Owner: ' + (p.vehicle.is_owner ? 'Yes' : 'No') + NL;
    t += 'Owner Name: ' + (p.vehicle.owner_name || '') + NL + NL;
    t += 'Violations:' + NL;
    (p.violations || []).forEach(v => { t += `- ${v.name} (Php${v.fine})` + NL; });
    t += NL;
    t += 'Username: ' + (p.credentials.username || '') + NL;
    t += 'Password: ' + (p.credentials.password || '') + NL + NL;
    t += 'Last Apprehended: ' + (p.last_apprehended_at || 'Never') + NL + NL;
    if (p.ticket.is_impounded) t += '*** VEHICLE IMPOUNDED ***' + NL + NL;
    t += 'Badge No: ' + (p.enforcer.badge_num || '') + NL + NL;
    t += NL + NL + NL + NL;
    t += '__________________________' + NL;
    t += 'Signature of Violator' + NL + NL;
    return t;
  };

  let txt = lines('ENFORCER') + '- - - - - - - - - - - - - - - -' + NL + NL + '\thttps://poso_management.test' + NL
          + NL + NL + lines('VIOLATOR') + FONT_A + ESC + 'd' + '\x03' + GS + 'V' + '\x00';

  await bluetoothWrite(ch, new TextEncoder().encode(txt));
}

async function printOfflineReceipt(payload) {
  const ESC = '\x1B', GS = '\x1D', NL = '\x0A';
  const INIT = ESC + '@', FONT_B = ESC + 'M' + '\x01', FONT_A = ESC + 'M' + '\x00';
  const ch = await getPrinterCharacteristic();

  const full = [payload.first_name, payload.middle_name, payload.last_name].filter(Boolean).join(' ');
  const tempNum = makeOfflineTicketNumber(payload.client_uuid);

  let t = '';
  t += INIT + FONT_B;
  t += '*** OFFLINE RECEIPT (Pending Sync) ***' + NL + NL;
  t += 'Temp Ticket #: ' + tempNum + NL;
  t += 'Date issued: ' + (new Date()).toLocaleString() + NL + NL;
  t += 'Violator: ' + full + NL;
  t += 'License No.: ' + (payload.license_number || '') + NL + NL;
  t += 'Plate: ' + (payload.plate_number || '') + NL;
  t += 'Type: ' + (payload.vehicle_type || '') + NL;
  t += 'Owner: ' + (payload.is_owner ? 'Yes' : 'No') + NL;
  t += 'Owner Name: ' + (payload.owner_name || '') + NL + NL;
  t += 'Violations:' + NL;
  (payload.violations || []).forEach(c => { t += `- ${c}` + NL; });
  t += NL + 'This ticket will be assigned an official number after sync.' + NL + NL;
  t += FONT_A + ESC + 'd' + '\x03' + GS + 'V' + '\x00';

  await bluetoothWrite(ch, new TextEncoder().encode(t));
}

/* ---------------- Category rendering (unchanged) ---------------- */
const selectEl    = document.getElementById('categorySelect');
const containerEl = document.getElementById('violationsContainer');
const selected    = new Set();

function renderCategory() {
  containerEl.innerHTML = '';
  const list = (window.violationGroups?.[selectEl.value]) || [];
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
selectEl?.addEventListener('change', renderCategory);

/* ---------------- Form submission flow ---------------- */
document.getElementById('ticketForm')?.addEventListener('submit', async function (e) {
  e.preventDefault();
  const form = e.target;

  // Flatten FormData → payload
  const fd = new FormData(form);
  const payload = {};
  fd.forEach((v, k) => {
    if (k.endsWith('[]')) {
      const key = k.replace('[]','');
      (payload[key] ||= []).push(v);
    } else {
      payload[k] = v;
    }
  });

  // idempotency key
  payload.client_uuid = payload.client_uuid || (crypto?.randomUUID?.() || String(Date.now()) + Math.random());

  // Ensure GPS before confirm (optional but recommended)
  const okGps = await ensureGpsFields();
  if (!okGps) return;

  // Show PRE-CONFIRM (no saving yet)
  const pre = await Swal.fire({
    title: 'Confirm Details',
    html: buildConfirmHtmlFromPayload(payload),
    width: 600,
    showCancelButton: true,
    confirmButtonText: 'Issue Ticket',
    cancelButtonText: 'Cancel'
  });
  if (!pre.isConfirmed) return; // ← nothing saved

  // Re-check connectivity right before saving
  const online = await isReallyOnline();

  // If offline → queue & offer printing an offline slip
  if (!online) {
    await enqueueTicket(payload);
    const askPrint = await Swal.fire({
      title: 'Print now?',
      text: 'You are offline. Print an offline receipt for the violator?',
      showCancelButton: true,
      confirmButtonText: 'Print',
      cancelButtonText: 'Skip'
    });
    if (askPrint.isConfirmed) {
      try { await printOfflineReceipt(payload); } catch (e) { console.error(e); }
    }
    form.reset();
    return;
  }

  // Online attempt: append client_uuid to FormData and POST
  fd.set('client_uuid', payload.client_uuid);

  try {
    const res = await fetch('/enforcerTicket', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': form._token.value, 'Accept': 'application/json' },
      body: fd
    });

    // If server is unhappy, fall back to offline queue
    if (!res.ok) {
      await enqueueTicket(payload);
      await Swal.fire('Saved Offline', 'Server rejected/failed. Ticket queued for sync.', 'info');
      const ask = await Swal.fire({ title: 'Print now?', showCancelButton: true, confirmButtonText: 'Print' });
      if (ask.isConfirmed) { try { await printOfflineReceipt(payload); } catch (e) { console.error(e); } }
      form.reset();
      return;
    }

    const p = await res.json(); // server ticket payload (includes ticket_number, etc.)

    // Ask to print (this no longer cancels the save)
    const printAsk = await Swal.fire({
      title: 'Ticket Issued',
      html: `
        <strong>Ticket #:</strong> ${p.ticket.ticket_number}<br>
        <strong>Username:</strong> ${p.credentials.username}<br>
        <strong>Password:</strong> ${p.credentials.password}<br>
        <em>Print two copies now?</em>
      `,
      showCancelButton: true,
      confirmButtonText: 'Print now',
      cancelButtonText: 'Skip'
    });

    if (printAsk.isConfirmed) {
      try { await printServerTicket(p); } catch (e) { console.error(e); }
    }

    await Swal.fire('Success', 'Ticket submitted.', 'success');
    form.reset();

  } catch (err) {
    // Network dropped during POST → queue offline
    console.error(err);
    await enqueueTicket(payload);
    await Swal.fire('Saved Offline', 'Network error. Ticket queued for sync.', 'info');
    const ask = await Swal.fire({ title: 'Print now?', showCancelButton: true, confirmButtonText: 'Print' });
    if (ask.isConfirmed) { try { await printOfflineReceipt(payload); } catch (e) { console.error(e); } }
    form.reset();
  }
});

/* ---------------- Auto-fill owner name (unchanged) ---------------- */
const ownerChk = document.getElementById('is_owner');
const ownerIn  = document.getElementById('owner_name');
const violFirst = document.getElementById('first_name');
const violMiddle = document.getElementById('middle_name');
const violLast = document.getElementById('last_name');

function syncOwner() {
  if (!ownerChk || !ownerIn) return;
  if (ownerChk.checked) {
    let full = (violFirst?.value || '').trim();
    if (violMiddle?.value?.trim()) full += ' ' + violMiddle.value.trim();
    if (violLast?.value?.trim())  full += ' ' + violLast.value.trim();
    ownerIn.value    = full;
    ownerIn.readOnly = true;
  } else {
    ownerIn.value    = '';
    ownerIn.readOnly = false;
  }
}
ownerChk?.addEventListener('change', syncOwner);
[violFirst, violMiddle, violLast].forEach(el => el?.addEventListener('input', () => ownerChk?.checked && syncOwner()));
syncOwner();

/* ---------------- Optional: get GPS immediately on page load ---------------- */
if ('geolocation' in navigator) {
  navigator.geolocation.getCurrentPosition(pos => {
    const lat = document.getElementById('latitude');
    const lng = document.getElementById('longitude');
    if (lat) lat.value = pos.coords.latitude;
    if (lng) lng.value = pos.coords.longitude;
    console.log('Location captured:', pos.coords.latitude, pos.coords.longitude);
  }, err => {
    console.warn('Geolocation failed:', err.message);
  }, { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 });
} else {
  console.warn('Geolocation not supported by this browser.');
}
