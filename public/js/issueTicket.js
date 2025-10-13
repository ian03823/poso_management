// public/js/issueTicket.js — deploy-ready, online+offline with idempotency

// ---- notify helpers (Swal fallback) ----
const Notify = {
  toast(o={}){ if (window.Swal) Swal.fire({toast:true,position:'top-end',timer:o.timer||2000,showConfirmButton:false,...o});
               else alert(o.title||o.text||'Notice'); },
  modal(o={}){ if (window.Swal) return Swal.fire(o);
               const ok = confirm(`${o.title||'Confirm'}\n${o.text||o.html||''}`); return Promise.resolve({isConfirmed:ok}); },
  info(m){this.toast({icon:'info',title:m})}, ok(m){this.toast({icon:'success',title:m})},
  warn(m){this.toast({icon:'warning',title:m})}, err(m){this.toast({icon:'error',title:m})}
};
/* ---------- Input Security: Name Sanitizer + License Mask ---------- */

// allow letters (incl. accents), spaces, dot, apostrophe, hyphen
const NAME_ALLOWED_RE = /[^A-Za-z\u00C0-\u024F\s.'-]+/g;
function sanitizeName(el) {
  const before = el.value;
  let v = before
    .replace(NAME_ALLOWED_RE, '')       // strip disallowed
    .replace(/\s{2,}/g, ' ')            // collapse spaces
    .replace(/^[\s.'-]+/, '');        // no leading punctuation
  // optional: capitalize words
  v = v.replace(/\b([a-zà-öø-ÿ])/g, m => m.toUpperCase());
  if (v !== before) el.value = v;
  // HTML5 pattern still enforces; clear any customValidity we set elsewhere
  el.setCustomValidity('');
}

// Mask to A12-34-567890 as the user types
function maskLicense(el) {
  const raw = (el.value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  let out = '';

  // L
  if (raw.length > 0) {
    if (/[A-Z]/.test(raw[0])) out += raw[0];
    else out += ''; // first must be letter; will fail pattern if not provided
  }
  // D D
  if (raw.length > 1) out += (raw[1] || '');
  if (raw.length > 2) out += (raw[2] || '');
  if (out.length >= 3) out = out.slice(0,3);

  if (out.length >= 3) out += '-';

  // D D
  if (raw.length > 3) out += (raw[3] || '');
  if (raw.length > 4) out += (raw[4] || '');
  if (out.length >= 6) out = out.slice(0,6);

  if (out.length >= 6) out += '-';

  // D D D D D D
  if (raw.length > 5) out += (raw[5] || '');
  if (raw.length > 6) out += (raw[6] || '');
  if (raw.length > 7) out += (raw[7] || '');
  if (raw.length > 8) out += (raw[8] || '');
  if (raw.length > 9) out += (raw[9] || '');
  if (raw.length > 10) out += (raw[10] || '');
  out = out.slice(0, 13); // max length including dashes

  if (el.value !== out) el.value = out;

  // If user leaves the field, validate strictly
  const FULL_RE = /^[A-Z]\d{2}-\d{2}-\d{6}$/;
  if (document.activeElement !== el) {
    if (!FULL_RE.test(out)) {
      el.setCustomValidity('License must match A12-34-567890');
    } else {
      el.setCustomValidity('');
    }
  } else {
    el.setCustomValidity('');
  }
}

(function wireInputSecurity(){
  const first  = document.getElementById('first_name');
  const middle = document.getElementById('middle_name');
  const last   = document.getElementById('last_name');
  const owner  = document.getElementById('owner_name');
  const lic    = document.getElementById('license_num');
  const form   = document.getElementById('ticketForm');

  [first, middle, last, owner].forEach(el=>{
    if (!el) return;
    el.addEventListener('input', ()=> sanitizeName(el));
    el.addEventListener('blur',  ()=> sanitizeName(el));
  });

  if (lic) {
    lic.addEventListener('input', ()=> maskLicense(lic));
    lic.addEventListener('blur',  ()=> maskLicense(lic));
    // normalize once on page load (e.g., old values)
    maskLicense(lic);
  }

  // gate all submits with HTML5 validity first (keeps your offline/online flow)
  if (form) {
    form.addEventListener('submit', (e)=>{
      // run one last sanitize/mask before validate
      [first, middle, last, owner].forEach(el=> el && sanitizeName(el));
      lic && maskLicense(lic);

      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
          firstInvalid.focus({preventScroll:false});
          if (window.Swal) {
            Swal.fire({icon:'error', title:'Please fix invalid fields', timer:1800, showConfirmButton:false});
          }
        } else {
          alert('Please fix invalid fields.');
        }
        return;
      }
      // otherwise allow your existing submit handler below to proceed
    }, {capture:true}); // capture ensures this runs before your submit logic
  }
})();


async function loadImage(url) {
  return new Promise((resolve, reject)=>{
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.crossOrigin = 'anonymous';
    img.src = url;
  });
}
const rand = (n=6)=>crypto.getRandomValues(new Uint32Array(1))[0].toString(36).slice(-n);
const last4 = s => (s||'').replace(/\D/g,'').slice(-4).padStart(4,'0');
const tempTicketNo = ()=>`TEMP-${new Date().toISOString().slice(2,10).replace(/-/g,'')}-${rand(4)}`;
function makeOfflineCreds() {
  const u = 'user' + (Math.floor(1000 + Math.random()*9000));      // user1234
  const rawPwd = 'violator' + (Math.floor(1000 + Math.random()*9000)); // violator5678
  return { username: u, password: rawPwd, default_password: rawPwd };
}

function drawToCanvas(img, maxWidth = 360) {
  const scale = Math.min(1, maxWidth / img.width);
  const w = Math.floor(img.width * scale);
  const h = Math.floor(img.height * scale);
  const c = document.createElement('canvas');
  c.width = w % 8 === 0 ? w : w + (8 - (w % 8));
  c.height = h;
  const ctx = c.getContext('2d');
  ctx.drawImage(img, 0, 0, w, h);
  if (c.width > w) {
    ctx.fillStyle = '#fff';
    ctx.fillRect(w, 0, c.width - w, h);
  }
  return c;
}

// Floyd–Steinberg dithering to 1bpp
function ditherToMono(ctx, w, h) {
  const img = ctx.getImageData(0, 0, w, h);
  const d = img.data;
  const gray = new Float32Array(w*h);
  for (let i=0, j=0; i<d.length; i+=4, j++) gray[j] = (0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2]);
  for (let y=0; y<h; y++) {
    for (let x=0; x<w; x++) {
      const i = y*w + x, old = gray[i], nv = old < 128 ? 0 : 255, err = old - nv;
      gray[i] = nv;
      if (x+1 < w) gray[i+1] += err*7/16;
      if (x-1 >=0 && y+1 < h) gray[i+w-1] += err*3/16;
      if (y+1 < h) gray[i+w] += err*5/16;
      if (x+1 < w && y+1 < h) gray[i+w+1] += err*1/16;
    }
  }
  const bytesPerRow = Math.ceil(w/8), out = new Uint8Array(bytesPerRow * h);
  let p = 0;
  for (let y=0; y<h; y++) {
    for (let bx=0; bx<bytesPerRow; bx++) {
      let byte = 0;
      for (let bit=0; bit<8; bit++) {
        const x = bx*8 + bit;
        const v = (x < w && gray[y*w + x] === 0) ? 1 : 0;
        byte |= (v << (7 - bit));
      }
      out[p++] = byte;
    }
  }
  return { data: out, bytesPerRow };
}

// GS v 0 m xL xH yL yH [data]
function escposRasterBytes(mono, w, h, mode = 0) {
  const bytesPerRow = Math.ceil(w/8);
  const xL = bytesPerRow & 0xFF, xH = (bytesPerRow >> 8) & 0xFF;
  const yL = h & 0xFF, yH = (h >> 8) & 0xFF;
  const header = Uint8Array.of(0x1D, 0x76, 0x30, mode, xL, xH, yL, yH);
  const out = new Uint8Array(header.length + mono.data.length);
  out.set(header, 0); out.set(mono.data, header.length);
  return out;
}

async function printImageUrl(url, writeChunked, ALIGN, maxW = 360) {
  try {
    const img = await loadImage(url);
    const c = drawToCanvas(img, maxW);
    const ctx = c.getContext('2d');
    const { data } = ditherToMono(ctx, c.width, c.height);
    const raster = escposRasterBytes({ data }, c.width, c.height, 0);
    await writeChunked(ALIGN(1)); await writeChunked(raster); await writeChunked(ALIGN(0));
  } catch (e) { console.warn('[printImageUrl] failed:', e); }
}

/* one-time guard */
if (window.__ISSUE_TICKET_WIRED__) {
  console.warn('[issueTicket] duplicate include — skipping');
} else {
  window.__ISSUE_TICKET_WIRED__ = true;

  /* ---- constants ---- */
  const SYNC_ENDPOINT = '/pwa/sync/ticket';
  const FORM_ENDPOINT = '/enforcerTicket';
  const SYNC_TAG      = 'sync-tickets';

  // Dexie (must match SW)
  const DB_NAME = 'ticketDB';
  const DB_VERSION = 221;
  const DB_SCHEMA = { tickets: '++id,client_uuid,created_at' };

  /* ---- Dexie (with LocalStorage fallback) ---- */
  const hasDexie = !!window.Dexie;
  if (hasDexie && !window.ticketDB) {
    try {
      window.ticketDB = new Dexie(DB_NAME);
      window.ticketDB.version(DB_VERSION).stores(DB_SCHEMA).upgrade(()=>{});
    } catch (e) { console.warn('[Dexie init failed]', e); }
  }
  const LS_KEY = 'offline_tickets';
  const lsRead  = ()=>{ try { return JSON.parse(localStorage.getItem(LS_KEY) || '[]'); } catch { return []; } };
  const lsWrite = (a)=>{ try { localStorage.setItem(LS_KEY, JSON.stringify(a)); } catch {} };

  // remove queued item by client_uuid from BOTH Dexie & LocalStorage
  async function removeQueuedByUuid(uuid){
    if (!uuid) return;
    try { await window.ticketDB?.tickets?.where('client_uuid').equals(uuid).delete(); } catch {}
    try {
      const keep = lsRead().filter(r => (r.client_uuid || r.payload?.client_uuid) !== uuid);
      lsWrite(keep);
    } catch {}
  }

  // optional: migrate LS leftovers into Dexie once (no-op if none)
  async function migrateLocalToDexie(){
    if (!window.ticketDB?.tickets) return;
    const ls = lsRead(); if (!ls.length) return;
    for (const rec of ls) {
      const uuid = rec.client_uuid || rec.payload?.client_uuid || String(Date.now());
      const exists = await window.ticketDB.tickets.where('client_uuid').equals(uuid).count();
      if (!exists) {
        await window.ticketDB.tickets.add({
          client_uuid: uuid,
          payload: rec.payload || rec,
          created_at: rec.created_at || Date.now()
        });
      }
    }
    lsWrite([]); // clear LS after migration
  }
  migrateLocalToDexie().catch(()=>{});

  /* ---- small helpers ---- */
  const byId  = (id)=>document.getElementById(id);
  const sleep = (ms)=>new Promise(r=>setTimeout(r,ms));
  const getCSRF = ()=> document.querySelector('meta[name="csrf-token"]')?.content
              || document.querySelector('input[name="_token"]')?.value || '';

  async function isReallyOnline(){
    if (!navigator.onLine) return false;
    try { const res = await fetch('/ping', {cache:'no-store'}); return res.ok; }
    catch { return false; }
  }

  function ensureClientUuid(formEl){
    let hidden = formEl.querySelector('input[name="client_uuid"]');
    if (!hidden) { hidden = document.createElement('input'); hidden.type='hidden'; hidden.name='client_uuid'; formEl.appendChild(hidden); }
    hidden.value = hidden.value || (crypto?.randomUUID?.() || (Date.now()+'-'+Math.random().toString(16).slice(2)));
    return hidden.value;
  }

  function buildJsonPayloadFromFormData(fd){
    const arr = (k)=>fd.getAll(k) || [];
    const val = (k)=>fd.get(k) ?? '';
    return {
      first_name: val('first_name'),
      middle_name: val('middle_name'),
      last_name: val('last_name'),
      address: val('address'),
      birthdate: val('birthdate') || null,
      license_number: val('license_num'),
      plate_number: val('plate_num'),
      vehicle_type: val('vehicle_type'),
      is_owner: !!val('is_owner'),
      owner_name: val('owner_name'),
      violations: arr('violations[]'),
      location: val('location'),
      latitude: val('latitude') || null,
      longitude: val('longitude') || null,
      confiscation_type_id: val('confiscation_type_id') || null,
      enforcer_id: val('enforcer_id') || null,
      client_uuid: val('client_uuid') || null,
    };
  }

  /* ---- badge ---- */
  async function refreshQueueBadge(){
    const el = byId('queueCount');
    if (!el) return;
    let dexieCount = 0, lsCount = 0;
    try { dexieCount = await window.ticketDB?.tickets?.count() ?? 0; } catch {}
    try { lsCount = lsRead().length; } catch {}
    el.textContent = String(dexieCount + lsCount);
  }
  window.refreshQueueBadge = refreshQueueBadge;
  window.addEventListener('DOMContentLoaded', refreshQueueBadge);
  window.addEventListener('online', refreshQueueBadge);

  // listen for SW messages to auto-prune & refresh
  if (navigator.serviceWorker) {
    navigator.serviceWorker.addEventListener('message', (e) => {
      const msg = e.data || {};
      if (msg.type === 'SYNC_TICKET_OK' && msg.client_uuid) {
        removeQueuedByUuid(msg.client_uuid).then(refreshQueueBadge);
      }
      if (msg.type === 'QUEUE_COUNT') {
        const el = document.getElementById('queueCount');
        if (el) el.textContent = String(msg.count || 0);
      }
      if (msg.type === 'SYNC_TICKETS_DONE') {
        refreshQueueBadge();
      }
      if (msg.type === 'SYNC_TICKETS') {
        window.syncOfflineTickets?.();
      }
    });
  }

  /* ---- queue add ---- */
  async function enqueueTicket(payload){
    const uuid = payload.client_uuid;
    if (hasDexie && window.ticketDB?.tickets) {
      const exists = await window.ticketDB.tickets.where('client_uuid').equals(uuid).count();
      if (!exists) await window.ticketDB.tickets.add({ client_uuid: uuid, payload, created_at: Date.now() });
    } else {
      const list = lsRead();
      if (!list.some(r => (r.client_uuid || r.payload?.client_uuid) === uuid)) {
        list.push({ id: Date.now(), client_uuid: uuid, payload, created_at: Date.now() });
        lsWrite(list);
      }
    }
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
      try { const reg = await navigator.serviceWorker.ready; await reg.sync.register(SYNC_TAG); } catch {}
    }
    Notify.info('Saved offline');
    byId('ticketForm')?.reset();
    refreshQueueBadge();
  }

  /* ---- manual / fallback sync ---- */
  window.syncOfflineTickets = async function(){
    if (!navigator.onLine) { Notify.warn('Go online to sync.'); return; }

    if (hasDexie && window.ticketDB?.tickets) {
      const all = await window.ticketDB.tickets.toArray();
      for (const rec of all) {
        try {
          const uuid = rec.client_uuid || rec.payload?.client_uuid || String(Date.now());
          const res = await fetch(SYNC_ENDPOINT, {
            method:'POST', credentials:'omit',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-Idempotency-Key':uuid},
            body: JSON.stringify(rec.payload)
          });
          if (res.ok) {
            await removeQueuedByUuid(uuid);   // << prune both stores
          } else {
            console.warn('[sync] non-OK', res.status, await res.text());
          }
        } catch { /* keep for later */ }
      }
    } else {
      const list = lsRead(); const keep = [];
      for (const rec of list) {
        try {
          const uuid = rec.client_uuid || rec.payload?.client_uuid || String(Date.now());
          const res = await fetch(SYNC_ENDPOINT, {
            method:'POST', credentials:'omit',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-Idempotency-Key':uuid},
            body: JSON.stringify(rec.payload)
          });
          if (res.ok) {
            await removeQueuedByUuid(uuid);  // << prune both stores
          } else {
            keep.push(rec);
          }
        } catch { keep.push(rec); }
      }
      lsWrite(keep);
    }

    await refreshQueueBadge();
    Notify.ok('Offline tickets synced');
  };

  /* ---- wire topbar buttons ---- */
  byId('syncNowBtn')?.addEventListener('click', async ()=>{
    if (!navigator.onLine) return Notify.warn('Offline — connect to sync.');
    await window.syncOfflineTickets();
  });
  byId('queueInfoBtn')?.addEventListener('click', async ()=>{
    const n = await (window.ticketDB?.tickets?.count?.() ?? lsRead().length);
    Notify.modal({icon:'info',title:'Offline queue', html:`${n||0} ticket(s) waiting to sync.`});
  });

  /* ---- network toasts ---- */
  window.addEventListener('offline', ()=> Notify.warn('Offline — tickets will be saved locally.'));
  window.addEventListener('online',  ()=> { Notify.ok('Back online'); window.syncOfflineTickets?.(); });

  /* ---- geolocation autofill ---- */
  if ('geolocation' in navigator) {
    navigator.geolocation.getCurrentPosition(pos=>{
      const lat = byId('latitude'), lng = byId('longitude');
      if (lat) lat.value = pos.coords.latitude;
      if (lng) lng.value = pos.coords.longitude;
    }, err=>console.warn('Geolocation failed:', err?.message), {enableHighAccuracy:true,timeout:5000,maximumAge:0});
  }

  /* ---- violations category render ---- */
  (function(){
    const selectEl = byId('categorySelect');
    const container = byId('violationsContainer');
    const selected = new Set();
    function renderCategory(){
      if (!selectEl || !container) return;
      container.innerHTML = '';
      const list = (window.violationGroups?.[selectEl.value]) || [];
      for (const v of list) {
        const wrap = document.createElement('div');
        wrap.className = 'form-check mb-2';
        wrap.innerHTML = `
          <input class="form-check-input" type="checkbox" name="violations[]" id="v-${v.id}" value="${v.violation_code}" ${selected.has(v.violation_code)?'checked':''}>
          <label class="form-check-label" for="v-${v.id}">${v.violation_name} — ₱${parseFloat(v.fine_amount).toFixed(2)}</label>`;
        const chk = wrap.querySelector('input');
        chk.addEventListener('change', ()=> chk.checked ? selected.add(chk.value) : selected.delete(chk.value));
        container.appendChild(wrap);
      }
    }
    selectEl?.addEventListener('change', renderCategory);
  })();

  /* ---- license duplicate warning ---- */
  (function(){
    const licEl = byId('license_num'); if (!licEl) return;
    const first = byId('first_name'), middle = byId('middle_name'), last = byId('last_name');
    let warnedFor = null;
    const typedFullName = () => [first?.value, middle?.value, last?.value].map(s=>(s||'').trim()).filter(Boolean).join(' ');
    async function warnIfRegistered(license){
      if (!license || !(await isReallyOnline())) return false;
      const base = byId('ticketForm')?.dataset?.checkLicenseUrl || '/violators/check-license';
      try {
        const res = await fetch(`${base}?license=${encodeURIComponent(license)}`, {headers:{'Accept':'application/json'},credentials:'same-origin'});
        if (!res.ok) return false;
        const info = await res.json();
        if (!info.exists) return false;
        if (warnedFor === license) return true;
        warnedFor = license;
        await Notify.modal({icon:'warning', title:'License already registered',
          html:`This license belongs to <b>${info.name}</b>.<br><small>You typed: <b>${typedFullName()||'(none)'}</b></small>`, width:460});
        return true;
      } catch { return false; }
    }
    licEl.addEventListener('blur', ()=> warnIfRegistered((licEl.value||'').trim()));
    byId('ticketForm')?.addEventListener('submit', ()=>{
      const lic = (licEl.value||'').trim(); if (lic && warnedFor !== lic) warnIfRegistered(lic);
    });
  })();

  /* ---- owner name auto-fill ---- */
  (function(){
    const ownerChk = byId('is_owner'), ownerIn = byId('owner_name');
    const f=byId('first_name'), m=byId('middle_name'), l=byId('last_name');
    function syncOwner(){
      if (!ownerChk || !ownerIn) return;
      if (ownerChk.checked){
        let full=(f?.value||'').trim(); if (m?.value?.trim()) full+=' '+m.value.trim(); if (l?.value?.trim()) full+=' '+l.value.trim();
        ownerIn.value=full; ownerIn.readOnly=true;
      } else ownerIn.readOnly=false;
    }
    ownerChk?.addEventListener('change', syncOwner);
    [f,m,l].forEach(el=>el?.addEventListener('input', ()=> ownerChk?.checked && syncOwner()));
    syncOwner();
  })();

  /* ---- form submit: online vs offline ---- */
  byId('ticketForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const form = e.target;
    const fd   = new FormData(form);
    const uuid = ensureClientUuid(form);

    if (!(await isReallyOnline())) {
      const payload = buildJsonPayloadFromFormData(fd);
      if (!payload.client_uuid) payload.client_uuid = uuid;
      await enqueueTicket(payload);
      return;
    }

    try {
      const res = await fetch(FORM_ENDPOINT, { method:'POST', headers:{'X-CSRF-TOKEN':getCSRF(),'Accept':'application/json'}, body:fd });
      if (!res.ok) { return Notify.err(await res.text() || 'Failed to submit'); }
      const p = await res.json();

      let html = `
        <strong>Ticket #:</strong> ${p.ticket.ticket_number}<br>
        <strong>Enforcer:</strong> ${p.enforcer.name}<br>
        <strong>Violator:</strong> ${[p.violator.first_name,p.violator.middle_name,p.violator.last_name].filter(Boolean).join(' ')}<br>
        <strong>License No.:</strong> ${p.violator.license_number||''}<br>
        <strong>Plate:</strong> ${p.vehicle.plate_number||''}<br>
        <strong>Type:</strong> ${p.vehicle.vehicle_type||''}<br>
        <strong>Owner:</strong> ${p.vehicle.is_owner}<br>
        <strong>Owner Name:</strong> ${p.vehicle.owner_name||''}<br>
        <strong>Resident:</strong> ${p.ticket.is_resident?'Yes':'No'}<br>
        <strong>Location:</strong> ${p.ticket.location||''}<br>
        <strong>Confiscated:</strong> ${p.ticket.confiscated||'None'}<br>
        <strong>Impounded:</strong> ${p.ticket.is_impounded?'Yes':'No'}<br>
        <strong>Last Apprehended:</strong> ${p.last_apprehended_at||'Never'}<br>
        <strong>Username:</strong> ${p.credentials.username}<br>
        <strong>Password:</strong> ${p.credentials.password}<br>
        <strong>Violations:</strong><ul>`;
      (p.violations||[]).forEach(v=>{ html+=`<li>${v.name} — Php${v.fine}</li>`; });
      html += `</ul>`;

      const {isConfirmed} = await Notify.modal({ title:'Confirm Details', html, width:600, showCancelButton:true, confirmButtonText:'Save & Print' });
      if (!isConfirmed) return;

      // === BLE printing (unchanged) ===
      await (async function printTwoCopies(p) {
        const S_MAIN='49535343-fe7d-4ae5-8fa9-9fafd205e455', C_MAIN='49535343-8841-43f4-a8d4-ecbe34729bb3';
        const S_FFE0='0000ffe0-0000-1000-8000-00805f9b34fb', C_FFE1='0000ffe1-0000-1000-8000-00805f9b34fb';
        const dev = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: [S_MAIN, S_FFE0] });
        let server = await dev.gatt.connect();
        let svcUUID=S_MAIN, chrUUID=C_MAIN, ch;
        try { ch = await (await server.getPrimaryService(S_MAIN)).getCharacteristic(C_MAIN); }
        catch { svcUUID=S_FFE0; chrUUID=C_FFE1; ch = await (await server.getPrimaryService(S_FFE0)).getCharacteristic(C_FFE1); }

        const enc=new TextEncoder(), NL='\x0A';
        const ALIGN=(n)=>Uint8Array.of(0x1B,0x61,n), FEED=(n)=>Uint8Array.of(0x1B,0x64,n);

        const write = async (u8) => {
          if (ch.writeValueWithoutResponse) await ch.writeValueWithoutResponse(u8);
          else await ch.writeValue(u8);
          await sleep(60);
        };
        const writeChunked = async (u8, chunk = 20, tries = 3) => {
          for (let i = 0; i < u8.length; i += chunk) {
            const slice = u8.slice(i, i + chunk);
            let ok = false, attempt = 0;
            while (!ok && attempt < tries) {
              try {
                if (ch.writeValueWithoutResponse) await ch.writeValueWithoutResponse(slice);
                else await ch.writeValue(slice);
                ok = true;
              } catch (e) {
                attempt++; if (attempt >= tries) throw e; await sleep(30 * attempt);
              }
            }
            await sleep(10);
          }
        };
        const send = async (s) => { const b = enc.encode(s); for (let i=0;i<b.length;i+=20) await write(b.slice(i,i+20)); };
        const safe = (s)=>String(s??'').normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/₱/g,'Php').replace(/[–—]/g,'-').replace(/[“”]/g,'"').replace(/[‘’]/g,"'");
        const L = async (k,v='')=>send(k + safe(v) + NL);
        const nameLine=[p.violator.first_name,p.violator.middle_name,p.violator.last_name].filter(Boolean).join(' ');
        await write(Uint8Array.of(0x1B,0x40));
        await write(ALIGN(1));
        await printImageUrl('/qr.png?v=1', writeChunked, ALIGN, 384);
        await send('https://tinyurl.com/posoVlogin'+NL);
        await write(FEED(1));
        await send('City of San Carlos'+NL);
        await send('Public Order and Safety Office'+NL);
        await send('(POSO)'+NL+NL);
        await send('Traffic Citation Ticket'+NL);
        await write(ALIGN(0));
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
        if (p.ticket.is_impounded) { await send('*** VEHICLE IMPOUNDED ***'+NL); await write(FEED(1)); }
        await L('Badge No: ', p.enforcer.badge_num);
        await send('*UNOFFICIAL RECEIPT*. Please present this to cashiers officer at City Hall'+NL);
        await write(FEED(3));
        await write(ALIGN(1));
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
        if (p.ticket.is_impounded) { await send('*** VEHICLE IMPOUNDED ***'+NL); await write(FEED(1)); }
        await L('Badge No: ', p.enforcer.badge_num);
        await send('_____________________'+NL);
        await send('Signature of violator'+NL);
        await write(FEED(5));
        try { dev.gatt.disconnect(); } catch {}
      })(p);

      Notify.ok('Ticket Submitted.');
      form.reset();
    } catch (err) {
      console.error(err);
      Notify.err(err.message || String(err));
    }
  });
}
