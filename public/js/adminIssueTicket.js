// public/js/adminIssueTicket.js — Admin issue flow (no offline)

console.log('adminIssueTicket.js - script loaded 03');

// ---- safe helpers ----
const SwalOK  = (t, m) => Swal.fire(t, m, 'success');
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
const f = byId('first_name'),
      m = byId('middle_name'),
      l = byId('last_name');

function syncOwner(){
  if (!ownerChk || !ownerIn) return;
  if (ownerChk.checked){
    let full = (f?.value || '').trim();
    if (m?.value?.trim()) full += ' ' + m.value.trim();
    if (l?.value?.trim()) full += ' ' + l.value.trim();
    ownerIn.value   = full;
    ownerIn.readOnly = true;
  } else {
    ownerIn.value   = '';
    ownerIn.readOnly = false;
  }
}
ownerChk?.addEventListener('change', syncOwner);
[f,m,l].forEach(el => el?.addEventListener('input', () => ownerChk.checked && syncOwner()));
syncOwner();

/* ---------- Generic masking helper (copied from Enforcer) ---------- */

function maskForPrint(kind, a, b = '', c = '') {
  switch (kind) {
    case 'license': {
      if (!a) return '';
      const raw = String(a).toUpperCase();
      const chars = raw.split('');
      const alnumIdx = [];

      for (let i = 0; i < chars.length; i++) {
        if (/[A-Z0-9]/.test(chars[i])) alnumIdx.push(i);
      }
      if (!alnumIdx.length) return raw;

      // keep last 4 alphanumeric chars
      const keep = new Set(alnumIdx.slice(-4));

      return chars
        .map((ch, idx) => {
          if (!/[A-Z0-9]/.test(ch)) return ch;    // keep dashes/spaces
          return keep.has(idx) ? ch : '*';
        })
        .join('');
    }

    case 'name': {
      const parts = [a, b, c]
        .map(s => (s || '').trim())
        .filter(Boolean);

      if (!parts.length) return '';

      const maskWord = (w) => {
        if (!w) return '';
        if (w.length === 1) return w + '*';
        // J***, D***, C*** style
        return w[0] + '*'.repeat(Math.min(w.length - 1, 3));
      };

      return parts.map(maskWord).join(' ');
    }

    case 'address': {
      if (!a) return '';
      const str = String(a);
      const visible = 6; // number of alphanumeric chars to keep
      let count = 0;
      let out = '';

      for (let i = 0; i < str.length; i++) {
        const ch = str[i];
        if (/[A-Za-z0-9]/.test(ch)) {
          if (count < visible) {
            out += ch;
            count++;
          } else {
            out += '*';
          }
        } else {
          out += ch; // keep spaces/commas etc.
        }
      }

      return out;
    }

    case 'birthdate': {
      if (!a) return '';
      const str = String(a);
      const visibleDigits = 4; // keep year digits, mask rest
      let seen = 0;
      let out = '';

      for (let i = 0; i < str.length; i++) {
        const ch = str[i];
        if (/[0-9]/.test(ch)) {
          if (seen < visibleDigits) {
            out += ch;
            seen++;
          } else {
            out += '*';
          }
        } else {
          // keep '-', '/', spaces, etc.
          out += ch;
        }
      }

      // Example: 1999-01-23 -> 1999-**-**
      return out;
    }

    default:
      return String(a ?? '');
  }
}

/* ---------- BLE printer helpers (mirrors Enforcer masking) ---------- */

async function printTwoCopies(p) {
  const S_MAIN='49535343-fe7d-4ae5-8fa9-9fafd205e455', C_MAIN='49535343-8841-43f4-a8d4-ecbe34729bb3';
  const S_FFE0='0000ffe0-0000-1000-8000-00805f9b34fb', C_FFE1='0000ffe1-0000-1000-8000-00805f9b34fb';

  const dev    = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: [S_MAIN, S_FFE0] });
  const server = await dev.gatt.connect();

  // pick any supported service/characteristic
  let ch;
  try { ch = await (await server.getPrimaryService(S_MAIN)).getCharacteristic(C_MAIN); }
  catch { ch = await (await server.getPrimaryService(S_FFE0)).getCharacteristic(C_FFE1); }

  const enc   = new TextEncoder(),
        NL    = '\x0A';
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
        } catch(e){
          attempt++;
          if (attempt>=tries) throw e;
          await sleep(30*attempt);
        }
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

  // 🔒 MASKED VALUES (same rules as Enforcer)
  const maskedName      = maskForPrint('name',     p.violator.first_name, p.violator.middle_name, p.violator.last_name);
  const maskedBirthdate = maskForPrint('birthdate', p.violator.birthdate);
  const maskedAddress   = maskForPrint('address',  p.violator.address);
  const maskedLicense   = maskForPrint('license',  p.violator.license_number);

  console.log('[ADMIN BLE] masked values:', {
    fullName : [p.violator.first_name, p.violator.middle_name, p.violator.last_name].join(' '),
    maskedName,
    maskedBirthdate,
    maskedAddress,
    maskedLicense
  });

  // Header
  await write(Uint8Array.of(0x1B,0x40)); // init
  await write(ALIGN(1));
  await send('City of San Carlos'+NL);
  await send('Public Order and Safety Office'+NL);
  await send('(POSO)'+NL+NL);
  await send('Traffic Citation Ticket'+NL);
  await write(ALIGN(0));

  // --- COPY 1 (Violator copy) — MASKED ---
  await L('Ticket #: ', p.ticket.ticket_number);
  await L('Date issued: ', p.ticket.issued_at);
  await write(FEED(1));
  await L('Violator: ', maskedName);
  await L('Birthdate: ', maskedBirthdate);
  await L('Address: ', maskedAddress);
  await L('License No.: ', maskedLicense);
  await write(FEED(1));
  await L('Plate: ', p.vehicle.plate_number);
  await L('Vehicle: ', p.vehicle.vehicle_type);
  await L('Owner: ', p.vehicle.is_owner);
  await L('Owner Name: ', p.vehicle.owner_name);
  await write(FEED(1));
  await send('Violations:'+NL);
  for (const v of (p.violations||[])) {
    await send('- '+safe(v.name)+' (Php'+safe(v.fine)+')'+NL);
  }
  await write(FEED(1));
  await L('Username: ', p.credentials.username);
  await L('Password: ', p.credentials.password);
  await write(FEED(1));
  if (p.ticket.flags?.includes?.('is_impounded')) {
    await send('*** VEHICLE IMPOUNDED ***'+NL);
    await write(FEED(1));
  }
  await L('Badge No: ', p.enforcer.badge_num);
  await send('*UNOFFICIAL RECEIPT*. Please present this to cashier\'s office at City Hall'+NL);
  await write(FEED(3));

  // --- COPY 2 (Admin/Enforcer file copy) — ALSO MASKED ---
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
  await L('Violator: ', maskedName);
  await L('License No.: ', maskedLicense);
  await L('Birthdate: ', maskedBirthdate);
  await L('Address: ', maskedAddress);
  await write(FEED(1));
  await L('Plate: ', p.vehicle.plate_number);
  await L('Vehicle: ', p.vehicle.vehicle_type);
  await L('Owner: ', p.vehicle.is_owner);
  await L('Owner Name: ', p.vehicle.owner_name);
  await write(FEED(1));
  await send('Violations:'+NL);
  for (const v of (p.violations||[])) {
    await send('- '+safe(v.name)+' (Php'+safe(v.fine)+')'+NL);
  }
  await write(FEED(1));
  if (p.ticket.flags?.includes?.('is_impounded')) {
    await send('*** VEHICLE IMPOUNDED ***'+NL);
    await write(FEED(1));
  }
  await L('Badge No: ', p.enforcer.badge_num);
  await write(FEED(5));

  try { server.device.gatt.disconnect(); } catch {}
}

/* ---------- Submit + confirm + print ---------- */

byId('ticketForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);

  // ===== PRE-SUBMIT CONFIRMATION (names, not codes) =====
  try {
    const violatorName = [data.get('first_name'), data.get('middle_name'), data.get('last_name')]
      .map(s => (s||'').trim()).filter(Boolean).join(' ') || '(n/a)';
    const checked = Array.from(document.querySelectorAll('input[name="violations[]"]:checked'));
    const listItems = checked.map(chk => {
      const labelText = chk.nextElementSibling?.textContent?.trim() || chk.value;
      const nameOnly = labelText.split(' — ')[0]; // strip " — ₱123.45"
      return `<li>${nameOnly}</li>`;
    }).join(',') || '<li>(none)</li>';

    const preHtml = `
      <strong>Violator:</strong> ${violatorName}<br>
      <strong>Address.:</strong> ${data.get('address')||''}<br>
      <strong>License No.:</strong> ${data.get('license_num')||''}<br>
      <strong>Vehicle:</strong> ${data.get('vehicle_type')||''}<br>
      <strong>Plate:</strong> ${data.get('plate_num')||''}<br>
      <strong>Owner:</strong> ${data.get('is_owner') ? 'Yes' : 'No'}<br>
      <strong>Owner Name:</strong> ${data.get('owner_name')||''}<br>
      <strong>Location:</strong> ${data.get('location')||''}<br>
      <strong>Violations:</strong><ul>${listItems}</ul>
    `;

    const { isConfirmed } = await Swal.fire({
      title: 'Confirm Details',
      html: preHtml,
      width: 600,
      showCancelButton: true,
      confirmButtonText: 'Save & Print',
      cancelButtonText: 'Review'
    });
    if (!isConfirmed) {
      await Swal.fire({ icon:'info', title:'Submission cancelled', timer:1300, showConfirmButton:false });
      return; // <- DO NOT submit on cancel
    }
  } catch (errPreview) {
    console.warn('pre-submit preview failed:', errPreview);
  }

  // ===== ACTUAL SUBMIT TO SERVER =====
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
      let msg = '';
      try { msg = (await res.json()).message || ''; } catch { msg = await res.text(); }
      return SwalERR('Validation error', msg || 'Please check the inputs.');
    }

    const p = await res.json();

    // ===== PRINT RIGHT AFTER SUCCESS SAVE (masked) =====
    try {
      await printTwoCopies(p);
    } catch (printErr) {
      console.warn('Printing failed:', printErr);
      await Swal.fire({ icon:'warning', title:'Saved but printing failed', text:String(printErr).slice(0,300) });
    }

    // ===== AFTER-SUBMIT FLOW =====
    const { isConfirmed: isConfirm } = await Swal.fire({
      icon: 'success',
      title: 'Ticket Submitted',
      text: 'Do you want to issue another ticket?',
      confirmButtonText: 'Yes, issue another',
      cancelButtonText: 'Go to list',
      showCancelButton: true,
      reverseButtons: true,
    });

    if (isConfirm) {
      form.reset();
      selected.clear?.();
      if (containerEl) containerEl.innerHTML = '';
      if (typeof syncOwner === 'function') syncOwner();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      const indexUrl = form.dataset.indexUrl || '/ticket';
      window.location.assign(indexUrl);
    }
  } catch (err) {
    console.error(err);
    SwalERR('Error', err.message || String(err));
  }
});
