// public/js/adminIssueTicket.js — Admin issue flow (no offline)

// ---- safe helpers ----
const SwalOK = (t, m) => Swal.fire(t, m, 'success');
const SwalERR = (t, m) => Swal.fire(t || 'Error', m || 'Something went wrong', 'error');

const byId  = (id) => document.getElementById(id);
const sleep = (ms) => new Promise(r => setTimeout(r, ms));

// Geolocation (optional)
if ('geolocation' in navigator) {
  navigator.geolocation.getCurrentPosition(pos => {
    byId('latitude') && (byId('latitude').value = pos.coords.latitude);
    byId('longitude') && (byId('longitude').value = pos.coords.longitude);
  });
}

// Violations UI
const selectEl    = byId('categorySelect');
const containerEl = byId('violationsContainer');
const selected    = new Set();
function renderCategory() {
  if (!selectEl || !containerEl) return;

  const raw = (selectEl.value || '').trim();
  // Try exact, then case-insensitive fallback
  let list = (window.violationGroups && window.violationGroups[raw]) || null;

  if (!list && window.violationGroups) {
    const keys = Object.keys(window.violationGroups);
    const found = keys.find(k => k.trim().toLowerCase() === raw.toLowerCase());
    if (found) list = window.violationGroups[found];
  }

  containerEl.innerHTML = '';

  if (!list || !Array.isArray(list)) {
    // quick one-time debug to help diagnose in console
    console.warn('[violations]', { selected: raw, keys: Object.keys(window.violationGroups || {}) });
    return;
  }

  list.forEach(v => {
    const wrap = document.createElement('div');
    wrap.className = 'form-check mb-2';
    wrap.innerHTML = `
      <input class="form-check-input"
             type="checkbox"
             name="violations[]"
             id="v-${v.id}"
             value="${v.violation_code}">
      <label class="form-check-label" for="v-${v.id}">
        ${v.violation_name} — ₱${Number(v.fine_amount).toFixed(2)}
      </label>`;
    const chk = wrap.querySelector('input');
    chk.checked = selected.has(v.violation_code);
    chk.addEventListener('change', () => {
      chk.checked ? selected.add(chk.value) : selected.delete(chk.value);
    });
    containerEl.appendChild(wrap);
  });
}

selectEl?.addEventListener('change', renderCategory);

// Auto-fill owner name
const ownerChk = byId('is_owner');
const ownerIn  = byId('owner_name');
const f = byId('first_name'), m = byId('middle_name'), l = byId('last_name');
function syncOwner(){
  if (!ownerChk || !ownerIn) return;
  if (ownerChk.checked){
    let full = (f?.value || '').trim();
    if (m?.value?.trim()) full += ' ' + m.value.trim();
    if (l?.value?.trim()) full += ' ' + l.value.trim();
    ownerIn.value = full; ownerIn.readOnly = true;
  } else { ownerIn.value = ''; ownerIn.readOnly = false; }
}
ownerChk?.addEventListener('change', syncOwner);
[f,m,l].forEach(el => el?.addEventListener('input', () => ownerChk.checked && syncOwner()));
syncOwner();

// ---- BLE printer helpers (robust, mirrors Enforcer) ----
async function printTwoCopies(p) {
  const S_MAIN='49535343-fe7d-4ae5-8fa9-9fafd205e455', C_MAIN='49535343-8841-43f4-a8d4-ecbe34729bb3';
  const S_FFE0='0000ffe0-0000-1000-8000-00805f9b34fb', C_FFE1='0000ffe1-0000-1000-8000-00805f9b34fb';

  const dev = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: [S_MAIN, S_FFE0] });
  const server = await dev.gatt.connect();

  // pick any supported service/characteristic
  let ch;
  try { ch = await (await server.getPrimaryService(S_MAIN)).getCharacteristic(C_MAIN); }
  catch { ch = await (await server.getPrimaryService(S_FFE0)).getCharacteristic(C_FFE1); }

  const enc = new TextEncoder(), NL = '\x0A';
  const ALIGN = (n)=>Uint8Array.of(0x1B,0x61,n);
  const FEED  = (n)=>Uint8Array.of(0x1B,0x64,n);

  const write = async (u8) => {
    if (ch.writeValueWithoutResponse) await ch.writeValueWithoutResponse(u8);
    else await ch.writeValue(u8);
    await sleep(60);
  };
  const writeChunked = async (u8, chunk=20, tries=3) => {
    for (let i=0;i<u8.length;i+=chunk){
      const slice=u8.slice(i,i+chunk);
      let ok=false, attempt=0;
      while(!ok && attempt<tries){
        try {
          if (ch.writeValueWithoutResponse) await ch.writeValueWithoutResponse(slice);
          else await ch.writeValue(slice);
          ok=true;
        } catch(e){ attempt++; if (attempt>=tries) throw e; await sleep(30*attempt); }
      }
      await sleep(10);
    }
  };
  const send = async (s) => {
    const b = enc.encode(s);
    for (let i=0;i<b.length;i+=20) { await write(b.slice(i,i+20)); }
  };
  const safe = (s)=>String(s??'').normalize('NFKD')
    .replace(/[\u0300-\u036f]/g,'')
    .replace(/₱/g,'Php')
    .replace(/[–—]/g,'-')
    .replace(/[“”]/g,'"')
    .replace(/[‘’]/g,"'");
  const L = async (k,v='') => send(k + safe(v) + NL);

  const nameLine = [p.violator.first_name, p.violator.middle_name, p.violator.last_name]
                    .filter(Boolean).join(' ');

  // Header
  await write(Uint8Array.of(0x1B,0x40)); // init
  await write(ALIGN(1));
  await send('City of San Carlos'+NL);
  await send('Public Order and Safety Office'+NL);
  await send('(POSO)'+NL+NL);
  await send('Traffic Citation Ticket'+NL);
  await write(ALIGN(0));

  // --- COPY 1 (Violator) ---
  await L('Ticket #: ', p.ticket.ticket_number);
  await L('Date issued: ', p.ticket.issued_at);
  await write(FEED(1));
  await L('Violator: ', nameLine);
  await L('Birthdate: ', p.violator.birthdate);
  await L('Address: ', p.violator.address);
  await L('License No.: ', p.violator.license_number);
  await write(FEED(1));
  await L('Plate: ', p.vehicle.plate_number);
  await L('Vehicle: ', p.vehicle.vehicle_type);
  await L('Owner: ', p.vehicle.is_owner);
  await L('Owner Name: ', p.vehicle.owner_name);
  await write(FEED(1));
  await send('Violations:'+NL);
  for (const v of (p.violations||[])) await send('- '+safe(v.name)+' (Php'+safe(v.fine)+')'+NL);
  await write(FEED(1));
  await L('Username: ', p.credentials.username);
  await L('Password: ', p.credentials.password);
  await write(FEED(1));
  if (p.ticket.flags?.includes?.('is_impounded')) { await send('*** VEHICLE IMPOUNDED ***'+NL); await write(FEED(1)); }
  await L('Badge No: ', p.enforcer.badge_num);
  await send('*UNOFFICIAL RECEIPT*. Please present this to cashier\'s office at City Hall'+NL);
  await write(FEED(3));

  // --- COPY 2 (Enforcer) ---
  await write(ALIGN(1));
  await send('Issued by POSO Admin'+ NL + NL);
  await send('City of San Carlos'+NL);
  await send('Public Order and Safety Office'+NL);
  await send('(POSO)'+NL+NL);
  await send('Traffic Citation Ticket'+NL);
  await write(ALIGN(0));
  await L('Ticket #: ', p.ticket.ticket_number);
  await L('Date issued: ', p.ticket.issued_at);
  await write(FEED(1));
  await L('Violator: ', nameLine);
  await L('License No.: ', p.violator.license_number);
  await L('Birthdate: ', p.violator.birthdate);
  await L('Address: ', p.violator.address);
  await write(FEED(1));
  await L('Plate: ', p.vehicle.plate_number);
  await L('Vehicle: ', p.vehicle.vehicle_type);
  await L('Owner: ', p.vehicle.is_owner);
  await L('Owner Name: ', p.vehicle.owner_name);
  await write(FEED(1));
  await send('Violations:'+NL);
  for (const v of (p.violations||[])) await send('- '+safe(v.name)+' (Php'+safe(v.fine)+')'+NL);
  await write(FEED(1));
  if (p.ticket.flags?.includes?.('is_impounded')) { await send('*** VEHICLE IMPOUNDED ***'+NL); await write(FEED(1)); }
  await L('Badge No: ', p.enforcer.badge_num);
  await write(FEED(5));

  try { server.device.gatt.disconnect(); } catch {}
}

// ---- Submit + confirm + print ----
byId('ticketForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);

  try {
    const res = await fetch(form.action, {
      method: form.method,
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form._token?.value || '',
        'Accept': 'application/json'
      },
      body: data
    });

    if (!res.ok) {
      // show server message when available
      let msg = '';
      try { msg = (await res.json()).message || ''; } catch { msg = await res.text(); }
      return SwalERR('Validation error', msg || 'Please check the inputs.');
    }

    const p = await res.json();
    // Confirmation summary
    let html = `
      <strong>Ticket #:</strong> ${p.ticket.ticket_number}<br>
      <strong>Enforcer:</strong> ${p.enforcer.name} (${p.enforcer.badge_num})<br>
      <strong>Violator:</strong> ${[p.violator.first_name,p.violator.middle_name,p.violator.last_name].filter(Boolean).join(' ')}<br>
      <strong>License No.:</strong> ${p.violator.license_number || ''}<br>
      <strong>Plate:</strong> ${p.vehicle.plate_number}<br>
      <strong>Type:</strong> ${p.vehicle.vehicle_type}<br>
      <strong>Owner:</strong> ${p.vehicle.is_owner}<br>
      <strong>Owner Name:</strong> ${p.vehicle.owner_name || ''}<br>
      <strong>Location:</strong> ${p.ticket.location || ''}<br>
      <strong>Confiscated:</strong> ${p.ticket.confiscated}<br>
      <strong>Impounded:</strong> ${p.ticket.flags?.includes('is_impounded') ? 'Yes' : 'No'}<br>
      <strong>Resident:</strong> ${p.ticket.flags?.includes('is_resident') ? 'Yes' : 'No'}<br>
      <strong>Last Apprehended:</strong> ${p.last_apprehended_at || 'Never'}<br>
      <strong>Username:</strong> ${p.credentials.username}<br>
      <strong>Password:</strong> ${p.credentials.password}<br>
      <strong>Violations:</strong><ul>`;
    (p.violations || []).forEach(v => { html += `<li>${v.name} — Php${v.fine}</li>`; });
    html += `</ul>`;

    const { isConfirmed } = await Swal.fire({
      title: 'Confirm Details',
      html,
      width: 600,
      showCancelButton: true,
      confirmButtonText: 'Save & Print',
    });
    if (!isConfirmed) return;

    const { isConfirm } = await Swal.fire({
      icon: 'success',
      title: 'Ticket Submitted',
      text: 'Do you want to issue another ticket?',
      confirmButtonText: 'Yes, issue another',
      cancelButtonText: 'Go to list',
      showCancelButton: true,
      reverseButtons: true,
    });

    if (isConfirm) {
      // stay on page and reset for next entry
      form.reset();
      selected.clear?.();
      if (containerEl) containerEl.innerHTML = '';   // clear violations
      // re-sync owner name checkbox behavior
      if (typeof syncOwner === 'function') syncOwner();
      // optionally scroll to top for faster next entry
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      // go back to table (resource index)
      const indexUrl = form.dataset.indexUrl || '/ticket';
      window.location.assign(indexUrl);
    }
  } catch (err) {
    console.error(err);
    SwalERR('Error', err.message || String(err));
  }
});
