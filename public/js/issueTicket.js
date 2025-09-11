// ===== One-time guard (prevents double wiring) =====
if (window.__ISSUE_TICKET_WIRED__) {
  console.warn('[issueTicket] duplicate include — skipping second execution');
} else {
  window.__ISSUE_TICKET_WIRED__ = true;

  // ===== Dexie singleton WITHOUT top-level let/const =====
  if (!window.ticketsDB) {
    window.ticketsDB = new Dexie('ticketDB');
    window.ticketsDB.version(1).stores({ tickets: '++id,payload' });
  }

  // -------- Geolocation auto-fill --------
  if ('geolocation' in navigator) {
    navigator.geolocation.getCurrentPosition(position => {
      const latEl = document.getElementById('latitude');
      const lngEl = document.getElementById('longitude');
      if (latEl) latEl.value = position.coords.latitude;
      if (lngEl) lngEl.value = position.coords.longitude;
      console.log('Location captured:', position.coords.latitude, position.coords.longitude);
    }, error => {
      console.warn('Geolocation failed:', error.message);
    }, { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 });
  } else {
    console.warn('Geolocation not supported by this browser.');
  }

  async function enqueueTicket(payload) {
    await window.ticketsDB.tickets.add({ payload });
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
    document.getElementById('ticketForm')?.reset();
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
    if (!containerEl || !selectEl) return;
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

  // -------- Sync function --------
  async function syncOfflineTickets() {
  const all = await window.ticketsDB.tickets.toArray();
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value;
  for (const rec of all) {
    try {
        const res = await fetch('/enforcerTicket', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {})
          },
          body: JSON.stringify(rec.payload)
        });
        if (res.ok) await window.ticketsDB.tickets.delete(rec.id);
      } catch (e) { console.error('Sync failed for', rec.id, e); }
    }
  }

  // -------- Form submission (online/offline) --------
  document.getElementById('ticketForm')
    ?.addEventListener('submit', async function(e) {
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
        p.violations.forEach(v => { html += `<li>${v.name} — Php${v.fine}</li>`; });
        html += `</ul>`;

        const { isConfirmed } = await Swal.fire({
          title: 'Confirm Details',
          html,
          width: 600,
          showCancelButton: true,
          confirmButtonText: 'Save & Print',
        });
        if (!isConfirmed) return;

        // 3) Print two copies (single connection) — your original working logic
        const S_MAIN = '49535343-fe7d-4ae5-8fa9-9fafd205e455';
        const C_MAIN = '49535343-8841-43f4-a8d4-ecbe34729bb3';
        const S_FFE0 = '0000ffe0-0000-1000-8000-00805f9b34fb';
        const C_FFE1 = '0000ffe1-0000-1000-8000-00805f9b34fb';
        const dev = await navigator.bluetooth.requestDevice({
          acceptAllDevices: true,
          optionalServices: [S_MAIN, S_FFE0]
        });
        let server = await dev.gatt.connect();
        let serviceUUID = S_MAIN, charUUID = C_MAIN, ch;
        try {
          ch = await (await server.getPrimaryService(S_MAIN)).getCharacteristic(C_MAIN);
        } catch {
          serviceUUID = S_FFE0; charUUID = C_FFE1;
          ch = await (await server.getPrimaryService(S_FFE0)).getCharacteristic(C_FFE1);
        }

        // ---- robust write helpers ----
        const enc = new TextEncoder();
        const z = (ms)=>new Promise(r=>setTimeout(r,ms));
        const BLE_CHUNK = 20;
        const BLE_DELAY = 60;

        async function reconnectOnce() {
          try { if (dev.gatt.connected) return; } catch {}
          try { dev.gatt.disconnect(); } catch {}
          await z(150);
          server = await dev.gatt.connect();
          const svc = await server.getPrimaryService(serviceUUID);
          ch = await svc.getCharacteristic(charUUID);
        }

        async function writeCmd(u8) {
          // try writeWithoutResponse → fallback to write → reconnect once and retry
          for (let attempt = 0; attempt < 2; attempt++) {
            try {
              if (ch.writeValueWithoutResponse) {
                await ch.writeValueWithoutResponse(u8);
              } else {
                await ch.writeValue(u8);
              }
              return;
            } catch (e) {
              // fallback path if printer rejects this mode
              if (e.name === 'NotSupportedError' && ch.writeValue) {
                await z(80);
                await ch.writeValue(u8);
                return;
              }
              // reconnect once on transient failures
              if ((e.name === 'NetworkError' || e.name === 'NotSupportedError') && attempt === 0) {
                await reconnectOnce();
                await z(120);
                continue;
              }
              throw e;
            }
          }
        }

        async function sendStr(str) {
          const bytes = enc.encode(str);
          for (let i = 0; i < bytes.length; i += BLE_CHUNK) {
            await writeCmd(bytes.slice(i, i + BLE_CHUNK));
            await z(BLE_DELAY);
          }
        }


        // helpers
        const ESC = '\x1B', GS = '\x1D', NL = '\x0A';
        const FONT_A = ESC + 'M' + '\x00';
        const FONT_B = ESC + 'M' + '\x01';
        const ALIGN  = (n)=>Uint8Array.of(0x1B,0x61,n);  // 0 left, 1 center, 2 right
        const FEED   = (n)=>Uint8Array.of(0x1B,0x64,n);  // feed n lines

        function safe(s){
          return String(s ?? '')
            .normalize('NFKD').replace(/[\u0300-\u036f]/g,'')
            .replace(/₱/g,'Php')
            .replace(/[–—]/g,'-')
            .replace(/[“”]/g,'"')
            .replace(/[‘’]/g,"'");
        }

        // ---- wake & init (LF + CP437) ----
        await writeCmd(enc.encode('\n\n'));       await z(120);
        await writeCmd(Uint8Array.of(0x1B,0x40)); await z(60);   // ESC @
        await writeCmd(Uint8Array.of(0x1B,0x32)); await z(40);   // ESC 2 default line spacing
        await writeCmd(Uint8Array.of(0x1B,0x74,0x00)); await z(40); // ESC t 0 (CP437)

        await writeCmd(ALIGN(1));
        await sendStr('City of San Carlos'+NL);
        await sendStr('Public Order and Safety Office'+NL);
        await sendStr('(POSO)'+NL+NL);
        await sendStr('Traffic Citation Ticket'+NL);
        await writeCmd(ALIGN(0));

        // small helpers
        const L = async (label, val='') => { await sendStr(label + safe(val) + NL); };
        const nameLine = [p.violator.first_name, p.violator.middle_name, p.violator.last_name].map(safe).join(' ');

        // ===== Copy 1: ENFORCER (send ESC codes and each line separately)
        await sendStr(FONT_B);                 // ESC M 1 (small font)
        await sendStr('ENFORCER' + NL);
        await sendStr(FONT_A);                 // back to default
        await L('Ticket #: ',    p.ticket.ticket_number);
        await L('Date issued: ', p.ticket.issued_at);
        await writeCmd(FEED(1));

        await L('Violator: ',    nameLine);
        await L('Birthdate: ',   p.violator.birthdate);
        await L('Address: ',     p.violator.address);
        await L('License No.: ', p.violator.license_number);
        await writeCmd(FEED(1));

        await L('Plate: ',       p.vehicle.plate_number);
        await L('Type: ',        p.vehicle.vehicle_type);
        await L('Owner: ',       (p.vehicle.is_owner ? 'Yes' : 'No'));
        await L('Owner Name: ',  p.vehicle.owner_name);
        await writeCmd(FEED(1));

        await sendStr('Violations:' + NL);
        for (const v of (p.violations || [])) {
          await sendStr('- ' + safe(v.name) + ' (Php' + safe(v.fine) + ')' + NL);
        }
        await writeCmd(FEED(1));

        await L('Username: ', p.credentials.username);
        await L('Password: ', p.credentials.password);
        await writeCmd(FEED(1));
        await L('Last Apprehended: ', p.last_apprehended_at || 'Never');
        if (p.ticket.is_impounded) {
          await sendStr('*** VEHICLE IMPOUNDED ***' + NL);
          await writeCmd(FEED(1));
        }
        await L('Badge No: ', p.enforcer.badge_num);

        await writeCmd(FEED(2));
        await sendStr('__________________________' + NL);
        await sendStr('Signature of Violator' + NL);
        await writeCmd(FEED(2));
        await sendStr('--------------------------------' + NL);
        await writeCmd(FEED(2));

        // ===== Copy 2: VIOLATOR (header centered again)
        await writeCmd(ALIGN(1));
        await sendStr('City of San Carlos'+NL);
        await sendStr('Public Order and Safety Office'+NL);
        await sendStr('(POSO)'+NL+NL);
        await sendStr('Traffic Citation Ticket'+NL);
        await writeCmd(ALIGN(0));

        await sendStr(FONT_B);
        await sendStr('VIOLATOR' + NL);
        await sendStr(FONT_A);
        await L('Ticket #: ',    p.ticket.ticket_number);
        await L('Date issued: ', p.ticket.issued_at);
        await writeCmd(FEED(1));

        await L('Violator: ',    nameLine);
        await L('Birthdate: ',   p.violator.birthdate);
        await L('Address: ',     p.violator.address);
        await L('License No.: ', p.violator.license_number);
        await writeCmd(FEED(1));

        await L('Plate: ',       p.vehicle.plate_number);
        await L('Type: ',        p.vehicle.vehicle_type);
        await L('Owner: ',       (p.vehicle.is_owner ? 'Yes' : 'No'));
        await L('Owner Name: ',  p.vehicle.owner_name);
        await writeCmd(FEED(1));

        await sendStr('Violations:' + NL);
        for (const v of (p.violations || [])) {
          await sendStr('- ' + safe(v.name) + ' (Php' + safe(v.fine) + ')' + NL);
        }
        await writeCmd(FEED(1));

        await L('Username: ', p.credentials.username);
        await L('Password: ', p.credentials.password);

        await writeCmd(FEED(5));      // finish with paper out (no cutter)
        await z(120);
        try { dev.gatt.disconnect(); } catch {}

        // 4) Show success, then reset form
        await Swal.fire('Success', 'Ticket Submitted.', 'success');
        form.reset();

      } catch (err) {
        console.error(err);
        Swal.fire('Error', err.message || err, 'error');
      }
    });
  // -------- License number duplicate check (SweetAlert notice) --------
  (function () {
    const licEl = document.getElementById('license_num');
    if (!licEl) return;

    async function checkLicenseDuplicate() {
      const license = (licEl.value || '').trim();
      if (!license) return;
      if (!navigator.onLine) return; // skip when offline

      try {
        const url = `/violators/check-license?license=${encodeURIComponent(license)}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;

        const info = await res.json(); // { exists, id, name }
        if (!info.exists) return;

        // If a violator is preloaded, don't warn if it's the same one
        const currentIdEl = document.getElementById('current_violator_id');
        const samePerson =
          currentIdEl && String(info.id) === String(currentIdEl.value);

        if (!samePerson) {
          await Swal.fire({
            icon: 'warning',
            title: 'License already registered',
            html: `This license number belongs to <b>${info.name}</b>.`,
            confirmButtonText: 'OK',
            width: 420
          });
        }
      } catch (e) {
        console.warn('[checkLicenseDuplicate] failed:', e);
      }
    }

    document.addEventListener('focusout', (e) => {
      if (e.target && e.target.id === 'license_num') {
        const license = (e.target.value || '').trim();
        warnIfRegistered(license);
      }
    });
  })();
/* ====== PASTE THIS BLOCK HERE (just above Auto-fill owner name) ====== */
  (function () {
    const licEl = document.getElementById('license_num');
    if (!licEl) return;

    const first  = document.getElementById('first_name');
    const middle = document.getElementById('middle_name');
    const last   = document.getElementById('last_name');

    let warnedFor = null;

    function typedFullName() {
      return [first?.value, middle?.value, last?.value]
        .map(v => (v || '').trim())
        .filter(Boolean)
        .join(' ');
    }

    async function warnIfRegistered(license) {
      if (!license || !navigator.onLine) return false;

      const form = document.getElementById('ticketForm');
      const base = form?.dataset?.checkLicenseUrl || '/violators/check-license';
      const url  = `${base}?license=${encodeURIComponent(license)}`;

      try {
        const res = await fetch(url, {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        });
        if (!res.ok) return false;

        const info = await res.json(); // { exists, id, name }
        if (!info.exists) return false;

        if (warnedFor === license) return true;
        warnedFor = license;

        const typed = typedFullName() || '<i>(no name typed yet)</i>';
        await Swal.fire({
          icon: 'warning',
          title: 'License number already registered',
          html: `This license is registered to <b>${info.name}</b>.<br><br>
                 <small>You typed: <b>${typed}</b></small>`,
          confirmButtonText: 'OK',
          width: 460
        });
        return true;
      } catch (e) {
        console.warn('[check-license] failed:', e);
        return false;
      }
    }

    // Warn when leaving the license field
    licEl.addEventListener('blur', () => {
      const license = (licEl.value || '').trim();
      warnIfRegistered(license);
    });

    // Also warn once on submit if blur was skipped (doesn't block)
    const form = document.getElementById('ticketForm');
    form?.addEventListener('submit', () => {
      const license = (licEl.value || '').trim();
      if (license && navigator.onLine) {
        if (warnedFor !== license) warnIfRegistered(license);
      }
    });
  })();
  /* ==================== END OF NEW BLOCK ==================== */

  // -------- Auto-fill owner name --------
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
      ownerIn.value     = full;
      ownerIn.readOnly  = true;
    } else {
      ownerIn.value     = '';
      ownerIn.readOnly  = false;
    }
  }
  ownerChk?.addEventListener('change', syncOwner);
  [violFirst, violMiddle, violLast].forEach(el =>
      el?.addEventListener('input', () => ownerChk.checked && syncOwner())
  );
  syncOwner();

} // end one-time guard
