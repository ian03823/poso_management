// public/js/id-scan.js
(() => {
  // ============== Utils ==============
  const notify = (title, text, icon='info') => {
    if (window.Swal) Swal.fire({ icon, title, text });
    else alert(`${title}\n${text||''}`);
  };

  async function closeScanModal() {
    const m = document.getElementById('scanIdModal');
    if (m) bootstrap.Modal.getOrCreateInstance(m).hide();
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
  }

  const setValSmart = (id, name, val) => {
    if (val === undefined || val === null) return;
    const v = String(val).trim();
    let el = document.getElementById(id);
    if (!el) el = document.querySelector(`[name="${name}"]`);
    if (el && 'value' in el) el.value = v;
  };

  function fillForm({ firstName, middleName, lastName, address, licenseNumber }) {
    setValSmart('first_name',   'first_name',   firstName);
    setValSmart('middle_name',  'middle_name',  middleName);
    setValSmart('last_name',    'last_name',    lastName);
    setValSmart('address',      'address',      address);
    // your form field is license_num (not license_number)
    setValSmart('license_num',  'license_num',  licenseNumber);
  }

  // ============== QR parsing ==============
  function parseQRPayload(raw) {
    if (!raw) return { firstName:'', middleName:'', lastName:'', address:'', licenseNumber:'' };

    // 1) JSON (plain text)
    try {
      const o = JSON.parse(raw);
      const pick = (obj, ...keys) => { for (const k of keys) if (obj[k] != null) return String(obj[k]); return ''; };
      return {
        firstName:     pick(o, 'first_name','firstname','firstName','FN','fn'),
        middleName:    pick(o, 'middle_name','middlename','middleName','MN','mn'),
        lastName:      pick(o, 'last_name','lastname','lastName','LN','ln'),
        address:       pick(o, 'address','addr','ADDRESS','Address'),
        licenseNumber: pick(o, 'license_number','license_num','license','lic_no','dlno','DLNO')
      };
    } catch (e) {}

    // 2) URL with query params (works offline)
    try {
      const u = new URL(raw);
      const q = u.searchParams;
      if ([...q.keys()].length) {
        const get = (...keys) => { for (const k of keys) { const v=q.get(k); if (v!=null) return v; } return ''; };
        return {
          firstName:     get('first_name','firstname','fn'),
          middleName:    get('middle_name','middlename','mn'),
          lastName:      get('last_name','lastname','ln'),
          address:       get('address','addr'),
          licenseNumber: get('license_number','license_num','license','lic_no')
        };
      }
    } catch (e) {}

    // 3) key:value chunks or pipe splits
    const kv = {};
    raw.split(/[|;\n]/).forEach(ch => {
      const m = ch.split(/[:=]/);
      if (m.length >= 2) kv[m[0].trim().toLowerCase()] = m.slice(1).join(':').trim();
    });
    if (Object.keys(kv).length) {
      const getkv = (...keys) => { for (const k of keys) if (kv[k]!=null) return kv[k]; return ''; };
      const name = getkv('name','fullname');
      let first='', mid='', last='';
      if (name && name.includes(',')) {
        const [l, rest] = name.split(',',2);
        last = (l||'').trim();
        const t = (rest||'').trim().split(/\s+/);
        first = t[0]||''; mid = t.slice(1).join(' ');
      }
      return {
        firstName:     getkv('first_name','firstname','first') || first,
        middleName:    getkv('middle_name','middlename','middle') || mid,
        lastName:      getkv('last_name','lastname','last') || last,
        address:       getkv('address','addr'),
        licenseNumber: getkv('license_number','license_num','license','lic_no') || (raw.match(/[A-Z0-9-]{7,}/i)?.[0] || '')
      };
    }

    // 4) LAST,FIRST MID | ADDRESS | LIC
    const parts = raw.split('|');
    if (parts.length >= 2) {
      const name = parts[0].trim();
      const addr = parts[1]?.trim() || '';
      const lic  = parts[2]?.trim() || '';
      let last='', first='', mid='';
      if (name.includes(',')) {
        const [l, rest] = name.split(',',2);
        last = l?.trim() || '';
        const t = (rest||'').split(/\s+/).filter(Boolean);
        first = t[0]||''; mid = t.slice(1).join(' ');
      } else {
        const t = name.split(/\s+/).filter(Boolean);
        if (t.length >= 3) { first=t[0]; mid=t.slice(1,-1).join(' '); last=t.at(-1); }
        else if (t.length===2) { first=t[0]; last=t[1]; }
      }
      return { firstName:first, middleName:mid, lastName:last, address:addr, licenseNumber:lic };
    }

    // 5) fallback
    const lic = raw.match(/[A-Z0-9-]{7,}/i)?.[0] || '';
    return { firstName:'', middleName:'', lastName:'', address:'', licenseNumber: lic };
  }

  // ============== OCR parsing ==============
  function parseOCRText(text) {
    const lines = (text||'').split('\n').map(s => s.replace(/\s+/g,' ').trim()).filter(Boolean);

    let licenseNumber = '';
    for (const ln of lines) {
      const m = ln.match(/(LICENSE\s*NO\.?|DL\s*NO\.?|DRIVER'?S\s*LICENSE\s*(NO\.?)?)\s*[:#]?\s*([A-Z0-9\-]{7,})/i);
      if (m && m[3]) { licenseNumber = m[3]; break; }
      const n = ln.match(/\b([A-Z0-9]{3,}-[A-Z0-9]{3,}|[A-Z0-9]{8,})\b/);
      if (!licenseNumber && n) licenseNumber = n[1] || n[0];
    }

    let firstName='', middleName='', lastName='';
    const nameLine = lines.find(l => l.includes(',') && /^[A-Z ,.'-]+$/.test(l));
    if (nameLine) {
      const [last, rest] = nameLine.split(',',2);
      lastName = (last||'').trim();
      const t = (rest||'').trim().split(/\s+/);
      firstName = t[0]||''; middleName = t.slice(1).join(' ');
    } else {
      const cands = lines.filter(l => /^[A-Z .,'-]+$/.test(l)).sort((a,b)=>b.length-a.length);
      const c = cands[0];
      if (c) {
        const p = c.split(/\s+/);
        if (p.length>=3){ firstName=p[0]; middleName=p.slice(1,-1).join(' '); lastName=p.at(-1); }
      }
    }

    let address = '';
    const idx = lines.findIndex(l => /^ADDRESS\b/i.test(l));
    if (idx >= 0) address = lines.slice(idx, idx+2).join(', ').replace(/^ADDRESS[: ]*/i,'');
    else {
      const guess = lines.find(l => /[0-9].+[A-Z]/i.test(l) && !/LICENSE|DL\s*NO/i.test(l));
      if (guess) address = guess;
    }

    return { firstName, middleName, lastName, address, licenseNumber };
  }

  // ============== QR Scan ==============
  let qrInstance = null;

  async function startQR() {
    const el = document.getElementById('qr-reader');
    if (!window.Html5Qrcode || !el) return;

    await stopQR();
    qrInstance = new Html5Qrcode('qr-reader');

    const config = { fps: 10, qrbox: { width: 230, height: 230 } };
    const onScan = async (decodedText) => {
      console.log('[QR raw]', decodedText);
      const data = parseQRPayload(decodedText || '');
      console.log('[QR parsed]', data);
      fillForm(data);

      const probe = {
        first:  document.getElementById('first_name')?.value,
        middle: document.getElementById('middle_name')?.value,
        last:   document.getElementById('last_name')?.value,
        addr:   document.getElementById('address')?.value,
        lic:    document.getElementById('license_num')?.value
      };
      console.log('[QR after fill]', probe);

      if (window.Swal) {
        await Swal.fire({
          icon: 'success',
          title: 'QR Scanned',
          html: `<pre style="text-align:left;white-space:pre-wrap">${JSON.stringify(data,null,2)}</pre>`,
          confirmButtonText: 'OK'
        });
      } else {
        notify('Scanned!', 'Fields have been auto-filled.', 'success');
      }
      await stopQR();
      await closeScanModal();
    };

    try {
      await qrInstance.start({ facingMode: "environment" }, config, onScan);
      return;
    } catch (e1) { console.warn('QR env failed, trying user:', e1); }
    try {
      await qrInstance.start({ facingMode: "user" }, config, onScan);
      return;
    } catch (e2) { console.warn('QR user failed, enumerating cameras:', e2); }
    try {
      const cams = await Html5Qrcode.getCameras();
      if (cams?.length) { await qrInstance.start({ deviceId: { exact: cams[0].id } }, config, onScan); return; }
      throw new Error('No cameras found');
    } catch (e3) {
      console.warn('QR deviceId failed:', e3);
      notify('Camera error', 'QR scanner could not start. Use the OCR tab or check permissions.', 'warning');
    }
  }

  async function stopQR() {
    if (!qrInstance) return;
    try { await qrInstance.stop(); } catch {}
    try { await qrInstance.clear(); } catch {}
    qrInstance = null;
  }

  // ============== OCR Scan ==============
  let ocrStream = null, useBackCamera = true;
  const ocrStatus = (msg) => { const el = document.getElementById('ocr-status'); if (el) el.innerHTML = `<small class="text-muted">${msg||''}</small>`; };

  // Use our Laravel routes so headers are correct
  const TESS = {
    workerPath: "/vendor/tesseract/worker.min.js",
    corePath:   "/vender/tesseract/tesseract-core.wasm", // <-- served with application/wasm
    langPath:   "/wasm",                      // <-- contains eng.traineddata.gz
    gzip:       true
  };

  let worker = null, workerReady = null;

  async function getWorker() {
    if (worker) return worker;
    if (workerReady) return workerReady;

    ocrStatus('Loading OCR…');
    workerReady = (async () => {
      const w = await Tesseract.createWorker({
        workerPath: TESS.workerPath,
        corePath:   TESS.corePath,
        langPath:   TESS.langPath,
        gzip:       TESS.gzip
      });
      await w.loadLanguage('eng');
      await w.initialize('eng');
      ocrStatus('');
      worker = w;
      return worker;
    })();

    return workerReady;
  }

  async function startOCRCamera() {
    await stopOCRCamera();
    const constraints = {
      video: { facingMode: useBackCamera ? 'environment' : 'user', width:{ideal:1280}, height:{ideal:720} },
      audio: false
    };
    try {
      ocrStream = await navigator.mediaDevices.getUserMedia(constraints);
      const v = document.getElementById('ocr-video');
      v.srcObject = ocrStream; await v.play();
    } catch (e) {
      console.warn('OCR camera error:', e);
      notify('Camera error', 'Could not open camera. Check permissions.', 'warning');
    }
  }

  async function stopOCRCamera() {
    if (ocrStream) { ocrStream.getTracks().forEach(t => t.stop()); ocrStream = null; }
  }

  async function captureAndOCR() {
    const v = document.getElementById('ocr-video');
    if (!v || !v.videoWidth) return notify('Hold up','Camera not ready yet','info');

    const c = document.getElementById('ocr-canvas');
    c.width = v.videoWidth; c.height = v.videoHeight;
    const ctx = c.getContext('2d'); ctx.drawImage(v, 0, 0, c.width, c.height);

    ocrStatus('Reading…');
    const wkr = await getWorker();
    let text = '';
    try { const res = await wkr.recognize(c); text = res?.data?.text || ''; }
    catch (err) { console.error('Tesseract recognize error:', err); notify('OCR error','Could not read text', 'warning'); }
    ocrStatus('');

    const dbg = document.getElementById('ocr-debug'); if (dbg) dbg.textContent = text;
    const parsed = parseOCRText(text || '');
    console.log('[OCR parsed]', parsed);
    fillForm(parsed);

    await stopOCRCamera();
    await closeScanModal();
  }

  // ============== Modal & Events ==============
  const modalEl = document.getElementById('scanIdModal');
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', () => { startQR(); });
    modalEl.addEventListener('hidden.bs.modal', async () => { await stopQR(); await stopOCRCamera(); closeScanModal(); });
  }
  document.getElementById('tab-qr')?.addEventListener('click', async () => { await stopOCRCamera(); startQR(); });
  document.getElementById('tab-ocr')?.addEventListener('click', async () => { await stopQR(); startOCRCamera(); });
  document.getElementById('qr-stop')?.addEventListener('click', stopQR);
  document.getElementById('ocr-capture')?.addEventListener('click', captureAndOCR);
  document.getElementById('ocr-switch')?.addEventListener('click', async () => { useBackCamera = !useBackCamera; await startOCRCamera(); });

  // Self-test button
  document.getElementById('fill-self-test')?.addEventListener('click', () => {
    fillForm({ firstName:'JUAN', middleName:'SANTOS', lastName:'DELA CRUZ', address:'San Carlos City, Pangasinan', licenseNumber:'AB1-2345678' });
  });

  // Warm worker
  window.addEventListener('load', () => { setTimeout(() => { getWorker().catch(()=>{}); }, 1200); });

  // TEMP verbose
    window._ocrDiag = async () => {
    console.log('diag: createWorker');
    const w = await Tesseract.createWorker({
        workerPath: "/vendor/tesseract/worker.min.js",
        corePath:   "/wasm/tesseract-core.wasm",
        langPath:   "/wasm",
        gzip:       true
    });
    console.log('diag: loadLanguage');
    await w.loadLanguage('eng');
    console.log('diag: initialize');
    await w.initialize('eng');
    console.log('diag: recognize tiny');
    const tiny = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAPElEQVQYV2P8z8Dwn4EIwDiQkJAFGJgYjKRDUMiEQg0QW0gGmQYQ0jQYBzYkCqCw0QBrYwBgaQkAE3AigAAbJjB5nJ8l1QAAAABJRU5ErkJggg==";
    const r = await w.recognize(tiny);
    console.log('diag: result =', (r?.data?.text || '').trim());
    await w.terminate();
    return (r?.data?.text || '').trim();
    };


  // Console smoke test: run window.ocrSmoke()
  window.ocrSmoke = async () => {
    try {
      const w = await getWorker();
      const tiny = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAPElEQVQYV2P8z8Dwn4EIwDiQkJAFGJgYjKRDUMiEQg0QW0gGmQYQ0jQYBzYkCqCw0QBrYwBgaQkAE3AigAAbJjB5nJ8l1QAAAABJRU5ErkJggg==";
      const r = await w.recognize(tiny);
      console.log('[ocrSmoke] ->', (r?.data?.text || '').trim());
      return (r?.data?.text || '').trim();
    } catch (e) {
      console.error('[ocrSmoke ERROR]', e);
      throw e;
    }
  };
})();
