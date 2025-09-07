/* issueTicket.js — robust confirm-before-save with offline support + idempotency + reliable BLE ESC/POS printing */

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
  const url = `${location.origin}/ping`;
  if (!navigator.onLine) return false;
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
    (list || []).forEach(v => { byCode[v.violation_code] = v; });
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
  const flagsList = (payload.flag_labels || []).map(l => `<li>${l}</li>`).join('');

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
    <strong>Flags:</strong>${flagsList ? `<ul>${flagsList}</ul>` : ' — '}<br>
    <strong>Violations:</strong><ul>${violHtml}</ul>
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
  if (latEl?.value && lngEl?.value) return true;
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
  Swal.fire({ toast: true, icon: 'warning', title: 'You are now offline', text: 'Tickets will be saved locally.', position: 'top-end', timer: 3000, showConfirmButton: false });
});

window.addEventListener('online', () => {
  Swal.fire({ toast: true, icon: 'success', title: 'Back online!', text: 'Offline tickets will now sync.', position: 'top-end', timer: 3000, showConfirmButton: false });
  if (typeof syncOfflineTickets === 'function') syncOfflineTickets();
});

/* ---------------- Offline queueing with idempotency ---------------- */
async function enqueueTicket(payload) {
  const exists = await db.tickets.where('client_uuid').equals(payload.client_uuid).first();
  if (!exists) await db.tickets.add({ client_uuid: payload.client_uuid, payload, createdAt: Date.now() });

  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    const reg = await navigator.serviceWorker.ready;
    try { await reg.sync.register('sync-tickets'); } catch {}
  }

  await Swal.fire({ icon: 'info', title: 'Saved Offline', html: 'Your ticket was recorded locally and will auto-sync when online.', confirmButtonText: 'OK', width: 420 });
}

async function syncOfflineTickets() {
  const all = await db.tickets.orderBy('id').toArray();
  for (const rec of all) {
    try {
      const res = await fetch(`${location.origin}/pwa/sync/ticket`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Idempotency-Key': rec.client_uuid || rec.payload?.client_uuid || ''
        },
        body: JSON.stringify(rec.payload)
      });
      if (res.ok) await db.tickets.delete(rec.id);
    } catch (e) {
      console.warn('Sync failed for', rec.id, e);
    }
  }
}

/* ---------------- Bluetooth: services list ---------------- */
const BLE_CANDIDATES = [
  // Feasycom SPP-like
  { service: '49535343-fe7d-4ae5-8fa9-9fafd205e455', char: '49535343-8841-43f4-a8d4-ecbe34729bb3' },
  // FFxx family
  { service: '0000ff00-0000-1000-8000-00805f9b34fb', char: '0000ff02-0000-1000-8000-00805f9b34fb' },
  { service: '0000ff00-0000-1000-8000-00805f9b34fb', char: '0000ff01-0000-1000-8000-00805f9b34fb' },
  { service: '0000fff0-0000-1000-8000-00805f9b34fb', char: '0000fff2-0000-1000-8000-00805f9b34fb' },
  { service: '0000fff0-0000-1000-8000-00805f9b34fb', char: '0000fff1-0000-1000-8000-00805f9b34fb' },
  // Nordic UART
  { service: '6e400001-b5a3-f393-e0a9-e50e24dcca9e', char: '6e400002-b5a3-f393-e0a9-e50e24dcca9e' },
  // Gprinter AE30 family
  { service: '0000ae30-0000-1000-8000-00805f9b34fb', char: '0000ae01-0000-1000-8000-00805f9b34fb' },
  // Some “18F0/2AF1” variants
  { service: '000018f0-0000-1000-8000-00805f9b34fb', char: '00002af1-0000-1000-8000-00805f9b34fb' },
];

/* ---------------- ESC/POS builder (bytes, not strings) ---------------- */
function u8(...n){ return Uint8Array.from(n); }
function concatBytes(arrays){
  let len = arrays.reduce((a,b)=>a+b.length,0);
  let out = new Uint8Array(len), off = 0;
  arrays.forEach(a => { out.set(a, off); off += a.length; });
  return out;
}
function txt(s){
  // Stick to ASCII to avoid codepage headaches first; replace non-ASCII
  s = (s ?? '').toString().replace(/[^\x00-\x7F]/g,'?');
  return new TextEncoder().encode(s);
}
function line(s=''){ return concatBytes([txt(s), u8(0x0A)]); } // append LF

// ESC/POS QR (Model 2)
function buildQR(dataStr, size=6, ecc=49){
  const d = txt(dataStr);
  const store = (pL,pH,cn,fn,payload)=> concatBytes([u8(0x1D,0x28,0x6B,pL,pH,cn,fn), payload]);

  const qr = concatBytes([
    // Select model 2
    store(0x04,0x00,0x31,0x41,u8(0x32,0x00)),
    // Set size 3..16
    store(0x03,0x00,0x31,0x43,u8(size)),
    // Set ECC (48=L,49=M,50=Q,51=H)
    store(0x03,0x00,0x31,0x45,u8(ecc)),
    // Store data
    (() => {
      const len = d.length + 3;
      const pL = len & 0xFF, pH = (len >> 8) & 0xFF;
      return concatBytes([u8(0x1D,0x28,0x6B,pL,pH,0x31,0x50,0x30), d]);
    })(),
    // Print QR
    store(0x03,0x00,0x31,0x51,u8(0x30)),
    u8(0x0A)
  ]);

  return qr;
}

function buildEscPosTicket(ticket, opts = {}){
  const { copies = 1 } = opts;

  // Map fields
  const tnum   = ticket.ref_no || ticket.ticket_no || ticket.reference || 'N/A';
  const when   = ticket.issued_at || ticket.created_at_fmt || ticket.created_at || '';
  const enName = ticket.enforcer_name || `${ticket.enforcer?.fname ?? ''} ${ticket.enforcer?.lname ?? ''}`.trim();
  const badge  = ticket.enforcer_badge || ticket.enforcer?.badge_num || '';
  const vName  = ticket.violator_name || `${ticket.violator?.first_name ?? ''} ${ticket.violator?.last_name ?? ''}`.trim();
  const license= ticket.license_number || ticket.violator?.license_number || '';
  const plate  = ticket.plate_number || ticket.vehicle?.plate_number || '';
  const loc    = ticket.location || '';
  const total  = ticket.total_fine || ticket.amount || '';
  const qrUrl  = ticket.qr_url || ticket.portal_url || ticket.qr || `${location.origin}/ticket/${tnum}`;

  let violations = [];
  if (Array.isArray(ticket.violations)) {
    violations = ticket.violations.map(v => `• ${v.code ?? ''} ${v.name ? '- '+v.name : ''} ${v.amount ? '(Php'+v.amount+')':''}`);
  } else if (ticket.violations_text) {
    violations = ticket.violations_text.split('\n').map(s=>s.trim()).filter(Boolean);
  }

  const init     = u8(0x1B,0x40);               // ESC @ init
  const center   = u8(0x1B,0x61,0x01);          // ESC a 1
  const left     = u8(0x1B,0x61,0x00);          // ESC a 0
  const boldOn   = u8(0x1B,0x45,0x01);          // ESC E 1
  const boldOff  = u8(0x1B,0x45,0x00);          // ESC E 0
  const dblOn    = u8(0x1D,0x21,0x11);          // GS ! 0x11 (2x)
  const dblOff   = u8(0x1D,0x21,0x00);
  const feed4    = u8(0x1B,0x64,0x04);          // ESC d 4 lines
  const cut      = u8(0x1D,0x56,0x42,0x00);     // partial cut (harmless if no cutter)

  const copy = concatBytes([
    init, center, boldOn, dblOn,
    line('POSO Digital Ticket'),
    dblOff, boldOff,
    line('San Carlos City, Pangasinan'),
    line(''),

    left,
    line(`Ticket No: ${tnum}`),
    line(`Date/Time: ${when}`),
    line(`Enforcer: ${enName} ${badge ? '('+badge+')':''}`),
    line(`Violator: ${vName}`),
    line(`License: ${license}`),
    line(`Plate: ${plate}`),
    line(`Location: ${loc}`),
    line(''),

    boldOn, line('Violations:'), boldOff,
    ...violations.map(v => line(v)),
    line(''),

    boldOn, line(`Total Fine: ${total}`), boldOff,
    line(''),

    center, boldOn, line('Scan to View / Verify'), boldOff,
    buildQR(qrUrl, 6, 49),
    left, line('Printed via POSO PWA'),
    center, feed4,
    cut
  ]);

  // Repeat if multiple copies
  const copiesArr = new Array(Math.max(1, copies)).fill(copy);
  return concatBytes(copiesArr);
}

/* ---------------- Bluetooth connect + safe chunking ---------------- */
let _bleDevice = null;
let _printerChar = null;

async function getPrinterCharacteristic() {
  if (!('bluetooth' in navigator)) {
    throw new Error('Web Bluetooth not supported in this browser/context.');
  }

  // Reuse if connected
  if (_printerChar && _bleDevice?.gatt?.connected) return _printerChar;

  const uniqueServices = [...new Set(BLE_CANDIDATES.map(c => c.service))];
  const device = await navigator.bluetooth.requestDevice({
    acceptAllDevices: true,
    optionalServices: uniqueServices
  });
  _bleDevice = device;

  const server = await device.gatt.connect();

  for (const c of BLE_CANDIDATES) {
    try {
      const svc = await server.getPrimaryService(c.service);
      const ch  = await svc.getCharacteristic(c.char);
      console.log('Using BLE service/char:', c.service, c.char);
      _printerChar = ch;
      return ch;
    } catch (e) { /* keep trying */ }
  }
  throw new Error('No compatible printer characteristic found. Try power-cycling the printer and select it again.');
}

async function bleWriteAll(characteristic, bytes){
  // Start optimistic (180); fallback to 20 on error
  let chunkSize = window._bleChunkSize || 180;

  for (let i = 0; i < bytes.length; ) {
    const end = Math.min(i + chunkSize, bytes.length);
    const slice = bytes.slice(i, end);
    try {
      if (characteristic.writeValueWithoutResponse) {
        await characteristic.writeValueWithoutResponse(slice);
      } else {
        await characteristic.writeValue(slice);
      }
      i = end;
      await sleep(12);
    } catch (e) {
      // fallback once to 20 if larger chunk fails
      if (chunkSize > 20) {
        console.warn('BLE write failed at chunkSize', chunkSize, '— falling back to 20 bytes.');
        chunkSize = 20;
        window._bleChunkSize = 20;
        await sleep(60);
        continue; // retry same index with smaller chunk
      }
      throw e;
    }
  }
}

/* ---------------- High-level print calls ---------------- */
async function printTicketBLE(characteristic, ticket, opts={ copies: 2 }){
  const payload = buildEscPosTicket(ticket, opts);
  await bleWriteAll(characteristic, payload);
}

// Map your server response → receipt fields and print
async function printServerTicket(p){
  try {
    const ch = await getPrinterCharacteristic();

    const violations = (p.violations || []).map(v => ({
      code: v.violation_code || v.code || '',
      name: v.name || v.violation_name || '',
      amount: v.fine || v.fine_amount || ''
    }));

    const ticket = {
      ref_no: p.ticket?.ticket_number || p.ticket?.reference || 'N/A',
      issued_at: p.ticket?.issued_at || p.ticket?.created_at_fmt || p.ticket?.created_at || '',
      enforcer_name: [p.enforcer?.fname, p.enforcer?.lname].filter(Boolean).join(' '),
      enforcer_badge: p.enforcer?.badge_num || '',
      violator_name: [p.violator?.first_name, p.violator?.middle_name, p.violator?.last_name].filter(Boolean).join(' '),
      license_number: p.violator?.license_number || '',
      plate_number: p.vehicle?.plate_number || '',
      location: p.ticket?.location || '',
      total_fine: (() => {
        if (p.ticket?.total_fine) return p.ticket.total_fine;
        const sum = (p.violations || []).reduce((s,v)=> s + (+v.fine || +v.fine_amount || 0), 0);
        return sum ? `Php${sum.toFixed(2)}` : '';
      })(),
      violations,
      qr_url: p.qr_url || p.portal_url || (location.origin + '/violator/login?ref=' + (p.ticket?.ticket_number || ''))
    };

    await printTicketBLE(ch, ticket, { copies: 2 });
  } catch (e) {
    console.error('Bluetooth print failed:', e);
    await Swal.fire('Printer error', e.message || String(e), 'error');
  }
}

// Offline slip using available form data
async function printOfflineReceipt(payload){
  try {
    const ch = await getPrinterCharacteristic();

    const byCode = ensureViolationLookup();
    const tempNum = makeOfflineTicketNumber(payload.client_uuid);
    const fullViolator = [payload.first_name, payload.middle_name, payload.last_name].filter(Boolean).join(' ');

    const violations = (payload.violations || []).map(code => {
      const v = byCode[code];
      return { code, name: v?.violation_name || code, amount: v?.fine_amount ? Number(v.fine_amount).toFixed(2) : '' };
    });

    const ticket = {
      ref_no: tempNum,
      issued_at: new Date().toLocaleString(),
      enforcer_name: (payload._enforcer_name || ''), // if you have it
      enforcer_badge: (payload._enforcer_badge || ''),
      violator_name: fullViolator,
      license_number: payload.license_number || '',
      plate_number: payload.plate_number || '',
      location: payload.location || '',
      total_fine: '', // unknown offline unless you compute it on client
      violations,
      qr_url: `${location.origin}/offline/${encodeURIComponent(tempNum)}`
    };

    await printTicketBLE(ch, ticket, { copies: 1 });
  } catch (e) {
    console.error('Bluetooth print failed:', e);
    await Swal.fire('Printer error', e.message || String(e), 'error');
  }
}

/* ---------------- Category rendering ---------------- */
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
      <input class="form-check-input" type="checkbox" name="violations[]" id="v-${v.id}" value="${v.violation_code}" ${selected.has(v.violation_code)?'checked':''}>
      <label class="form-check-label" for="v-${v.id}">
        ${v.violation_name} — ₱${parseFloat(v.fine_amount).toFixed(2)}
      </label>`;
    const chk = wrapper.querySelector('input');
    chk.addEventListener('change', () => { chk.checked ? selected.add(chk.value) : selected.delete(chk.value); });
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

  // --- normalize names so online & offline match server expectations ---
  if (payload.license_num) { payload.license_number = payload.license_num; delete payload.license_num; }
  if (payload.plate_num)   { payload.plate_number   = payload.plate_num;   delete payload.plate_num; }
  if (payload.confiscation_type_id) {
    const sel = document.getElementById('confiscation_type_id');
    const opt = sel?.selectedOptions?.[0];
    payload.confiscated = opt ? opt.textContent.trim() : '';
  }
  if (Array.isArray(payload.flags)) {
    const keys = payload.flags.map(id => window.flagsLookup?.[id]?.key).filter(Boolean);
    payload.is_impounded = keys.includes('is_impounded');
    payload.is_resident  = keys.includes('is_resident');
    payload.flag_labels  = payload.flags.map(id => window.flagsLookup?.[id]?.label).filter(Boolean);
  }

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
  if (!pre.isConfirmed) return;

  // Re-check connectivity right before saving
  const online = await isReallyOnline();

  // If offline → queue & offer printing an offline slip
  if (!online) {
    await enqueueTicket(payload);
    const askPrint = await Swal.fire({ title: 'Print now?', text: 'You are offline. Print an offline receipt for the violator?', showCancelButton: true, confirmButtonText: 'Print', cancelButtonText: 'Skip' });
    if (askPrint.isConfirmed) { try { await printOfflineReceipt(payload); } catch (e) { console.error(e); } }
    form.reset();
    return;
  }

  // Online attempt: append client_uuid to FormData and POST
  fd.set('client_uuid', payload.client_uuid);

  try {
    const res = await fetch('/enforcerTicket', { method: 'POST', headers: { 'X-CSRF-TOKEN': form._token.value, 'Accept': 'application/json' }, body: fd });

    if (!res.ok) {
      const errText = await res.text();
      console.error('Server error body:', errText);
      await enqueueTicket(payload);
      await Swal.fire('Saved Offline', 'Server rejected/failed. Ticket queued for sync.', 'info');
      const ask = await Swal.fire({ title: 'Print now?', showCancelButton: true, confirmButtonText: 'Print' });
      if (ask.isConfirmed) { try { await printOfflineReceipt(payload); } catch (e) { console.error(e); } }
      form.reset();
      return;
    }

    const p = await res.json(); // server ticket payload (includes ticket_number, etc.)

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

    if (printAsk.isConfirmed) { try { await printServerTicket(p); } catch (e) { console.error(e); } }

    await Swal.fire('Success', 'Ticket submitted.', 'success');
    form.reset();

  } catch (err) {
    console.error(err);
    await enqueueTicket(payload);
    await Swal.fire('Saved Offline', 'Network error. Ticket queued for sync.', 'info');
    const ask = await Swal.fire({ title: 'Print now?', showCancelButton: true, confirmButtonText: 'Print' });
    if (ask.isConfirmed) { try { await printOfflineReceipt(payload); } catch (e) { console.error(e); } }
    form.reset();
  }
});

/* ---------------- Auto-fill owner name ---------------- */
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
