// public/js/id-scan.js
(() => {
  // ---- Helpers: SweetAlert2 fallback ----
  const notify = (title, text, icon='info') => {
    if (window.Swal) Swal.fire({ icon, title, text });
    else alert(`${title}\n${text||''}`);
  };

  // ---- Form fillers (adjust IDs if your form uses different IDs) ----
  function fillForm({ firstName, middleName, lastName, address, licenseNumber }) {
    const setVal = (id, val) => {
        if (!val) return;
        const el = document.getElementById(id);
        if (el && 'value' in el) el.value = String(val).trim();
    };

    setVal('first_name', firstName);
    setVal('middle_name', middleName);
    setVal('last_name', lastName);
    setVal('address', address);
    setVal('license_number', licenseNumber);
  }

  // ---- Basic parsing from QR payloads ----
  function parseQRPayload(raw) {
    // Try JSON first
    try {
      const obj = JSON.parse(raw);
      return {
        firstName: obj.first_name || obj.firstname || obj.fn || '',
        middleName: obj.middle_name || obj.middlename || obj.mn || '',
        lastName: obj.last_name || obj.lastname || obj.ln || '',
        address: obj.address || obj.addr || '',
        licenseNumber: obj.license_number || obj.lic_no || obj.license || ''
      };
    } catch (_) {}

    // Try pipe or comma-separated (LAST,FIRST,MID;ADDR;LIC)
    // Example: "LASTNAME,FIRST,MIDDLE|ADDRESS|LIC-123456"
    let parts = raw.split('|');
    if (parts.length >= 2) {
      const name = parts[0].trim();
      const addr = parts[1]?.trim() || '';
      const lic  = parts[2]?.trim() || '';

      let last='', first='', mid='';
      if (name.includes(',')) {
        const [l, rest] = name.split(',', 2);
        last = l?.trim() || '';
        const tokens = (rest || '').split(/\s+/).filter(Boolean);
        first = tokens[0] || '';
        mid = tokens.slice(1).join(' ') || '';
      } else {
        // fallback: FIRST MIDDLE LAST
        const tokens = name.split(/\s+/).filter(Boolean);
        if (tokens.length >= 3) {
          first = tokens[0]; mid = tokens.slice(1, -1).join(' '); last = tokens.at(-1);
        } else if (tokens.length == 2) {
          first = tokens[0]; last = tokens[1];
        }
      }

      return { firstName:first, middleName:mid, lastName:last, address:addr, licenseNumber:lic };
    }

    // Last fallback: guess a license-like token
    const licMatch = raw.match(/[A-Z0-9-]{7,}/i);
    return { firstName:'', middleName:'', lastName:'', address:'', licenseNumber: licMatch ? licMatch[0] : '' };
  }

  // ---- OCR parsing heuristics (very simple; tailor as needed) ----
  function parseOCRText(text) {
    // Normalize lines
    const lines = text
      .split('\n')
      .map(s => s.replace(/\s+/g,' ').trim())
      .filter(Boolean);

    let licenseNumber = '';
    // Find License line like: "LICENSE NO: ABC-123456", "DL No 12345678"
    for (const ln of lines) {
      const m = ln.match(/(LICENSE\s*NO\.?|DL\s*NO\.?|DRIVER'?S\s*LICENSE\s*(NO\.?)?)\s*[:#]?\s*([A-Z0-9\-]{7,})/i);
      if (m && m[3]) { licenseNumber = m[3]; break; }
      const n = ln.match(/\b([A-Z0-9]{3,}-[A-Z0-9]{3,}|[A-Z0-9]{8,})\b/); // generic token
      if (!licenseNumber && n) licenseNumber = n[1] || n[0];
    }

    // Guess name line – often "LAST, FIRST MIDDLE"
    let nameLine = lines.find(l => l.includes(',') && /^[A-Z ,.'-]+$/.test(l));
    let firstName='', middleName='', lastName='';
    if (nameLine) {
      const [last, rest] = nameLine.split(',',2);
      lastName = (last || '').trim();
      const tokens = (rest || '').trim().split(/\s+/);
      firstName = tokens[0] || '';
      middleName = tokens.slice(1).join(' ');
    } else {
      // Else: pick the UPPERCASE longest line that looks like a name (3+ tokens)
      const candidates = lines.filter(l => /^[A-Z .,'-]+$/.test(l));
      candidates.sort((a,b) => b.length - a.length);
      const c = candidates[0];
      if (c) {
        const parts = c.split(/\s+/);
        if (parts.length >= 3) {
          firstName = parts[0]; middleName = parts.slice(1,-1).join(' '); lastName = parts.at(-1);
        }
      }
    }

    // Address – look for a line starting with ADDRESS or following a keyword
    let address = '';
    let addrIdx = lines.findIndex(l => /^ADDRESS\b/i.test(l));
    if (addrIdx >= 0) {
      address = lines.slice(addrIdx, addrIdx+2).join(', ').replace(/^ADDRESS[: ]*/i,'');
    } else {
      // Choose a line with digits + letters (often addresses), but not license
      const guess = lines.find(l => /[0-9].+[A-Z]/i.test(l) && !/LICENSE|DL\s*NO/i.test(l));
      if (guess) address = guess;
    }

    return { firstName, middleName, lastName, address, licenseNumber };
  }

  // ---------------- QR SCAN ----------------
  let qrInstance = null;
  async function startQR() {
    const el = document.getElementById('qr-reader');
    if (!window.Html5Qrcode || !el) return;

    if (qrInstance) await stopQR();

    qrInstance = new Html5Qrcode('qr-reader');
    try {
      await qrInstance.start(
        { facingMode: { exact: "environment" } },
        { fps: 10, qrbox: { width: 230, height: 230 } },
        (decodedText) => {
          const data = parseQRPayload(decodedText || '');
          fillForm(data);
          notify('Scanned!', 'Fields have been auto-filled.', 'success');
          stopQR();
          bootstrap.Modal.getInstance(document.getElementById('scanIdModal'))?.hide();
        },
        (_) => {}
      );
    } catch (err) {
      console.warn('QR start error:', err);
      notify('Camera error', 'Could not start QR scan. Try OCR tab.', 'warning');
    }
  }
  async function stopQR() {
    if (qrInstance) {
      try { await qrInstance.stop(); } catch {}
      try { await qrInstance.clear(); } catch {}
      qrInstance = null;
    }
  }

  // ---------------- OCR SCAN ----------------
  let ocrStream = null;
  let useBackCamera = true;
  let worker = null;
  const ocrStatus = (msg) => document.getElementById('ocr-status').innerHTML = `<small class="text-muted">${msg||''}</small>`;

  async function getWorker() {
    if (worker) return worker;
    worker = await Tesseract.createWorker(
        "eng", 1, // language, OEM
        {
        workerPath: "/vendor/tesseract/worker.min.js",
        corePath: "/vendor/tesseract",   // folder with tesseract-core.wasm
        langPath: "/vendor/tesseract"    // folder with eng.traineddata.gz
        }
    );
    return worker;
  }

  async function startOCRCamera() {
    await stopOCRCamera();
    const constraints = { video: { facingMode: useBackCamera ? 'environment' : 'user' }, audio: false };
    try {
      ocrStream = await navigator.mediaDevices.getUserMedia(constraints);
      const video = document.getElementById('ocr-video');
      video.srcObject = ocrStream;
      await video.play();
    } catch (e) {
      console.warn('OCR camera error:', e);
      notify('Camera error', 'Could not open camera. Check permissions.', 'warning');
    }
  }
  async function stopOCRCamera() {
    if (ocrStream) {
      ocrStream.getTracks().forEach(t => t.stop());
      ocrStream = null;
    }
  }

  async function captureAndOCR() {
    const video = document.getElementById('ocr-video');
    if (!video || !video.videoWidth) return notify('Hold up','Camera not ready yet','info');

    // Draw frame to canvas
    const canvas = document.getElementById('ocr-canvas');
    const w = video.videoWidth, h = video.videoHeight;
    canvas.width = w; canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, w, h);

    // Run OCR
    ocrStatus('Reading…');
    const wkr = await getWorker();
    const { data: { text } } = await wkr.recognize(canvas);
    ocrStatus('');

    // Parse and fill
    const parsed = parseOCRText(text || '');
    fillForm(parsed);
    notify('Captured!', 'Fields have been auto-filled from photo.', 'success');
    bootstrap.Modal.getInstance(document.getElementById('scanIdModal'))?.hide();
  }

  // ---- Modal lifecycle ----
  const modalEl = document.getElementById('scanIdModal');
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', () => {
      // default to QR tab
      startQR();
    });
    modalEl.addEventListener('hidden.bs.modal', async () => {
      await stopQR();
      await stopOCRCamera();
    });
  }

  // ---- Tab switching ----
  document.getElementById('tab-qr')?.addEventListener('click', async () => {
    await stopOCRCamera();
    startQR();
  });
  document.getElementById('tab-ocr')?.addEventListener('click', async () => {
    await stopQR();
    startOCRCamera();
  });

  // ---- Buttons ----
  document.getElementById('qr-stop')?.addEventListener('click', stopQR);
  document.getElementById('ocr-capture')?.addEventListener('click', captureAndOCR);
  document.getElementById('ocr-switch')?.addEventListener('click', async () => {
    useBackCamera = !useBackCamera;
    await startOCRCamera();
  });

  // ---- Configure Tesseract offline paths ----
  // This lets Tesseract.js find the files we placed in /public/vendor/tesseract
  if (window.Tesseract && Tesseract.setLogging) {
    // no-op; logging is already off above
  }
  // Patch fetch paths used by worker (works when all assets are local)
  // NOTE: This depends on your Tesseract.js version. For most builds,
  // putting the files in the same folder is enough.

  // Optional: warm the worker after page load so it's ready when modal opens
  window.addEventListener('load', () => {
    // Lazy warm-up (does not block UI)
    setTimeout(() => { getWorker().catch(()=>{}); }, 1500);
  });

})();
