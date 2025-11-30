// public/js/id-scan.js — mobile-first OCR scanner (improved 2025-11-30-B)
(() => {
  console.log('%cID-SCAN mobile v2025-11-30-B','color:#0a0');

  // ---------- Utils ----------
  const q = (sel, r=document) => r.querySelector(sel);
  const notify = (title, text, icon='info') => {
    if (window.Swal) Swal.fire({ icon, title, text });
    else alert(`${title}\n${text||''}`);
  };
  const confirmScan = async (html) => {
    if (window.Swal) {
      return Swal.fire({
        title: 'Use scanned details?',
        html,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Apply to form',
        cancelButtonText: 'Discard'
      });
    }
    const ok = window.confirm('Use scanned details?\n' + html.replace(/<[^>]+>/g,' '));
    return { isConfirmed: ok };
  };

  async function closeScanModal() {
    const m = q('#scanIdModal');
    try { window.bootstrap?.Modal.getOrCreateInstance(m).hide(); } catch {}
    // fallback cleanup (in case)
    if (m) { m.classList.remove('show'); m.setAttribute('aria-hidden','true'); m.style.display='none'; }
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
  }

  function whenTesseractReady(timeoutMs = 8000) {
    return new Promise((resolve, reject) => {
      const t0 = Date.now();
      (function poll(){
        if (window.Tesseract?.createWorker) return resolve();
        if (Date.now() - t0 > timeoutMs) return reject(new Error('Tesseract not loaded in time'));
        setTimeout(poll, 50);
      })();
    });
  }

  // ---------- Status ----------
  const statusEl = q('#ocr-status');
  const ocrStatus = (msg, kind = 'muted') => {
    if (!statusEl) return;
    statusEl.innerHTML = msg
      ? `<span class="text-${kind}">${msg}</span>`
      : '';
  };

  // ---------- Tesseract config ----------
  const TESS = {
    workerPath: '/vendor/tesseract/worker.min.js',             // public/vendor/tesseract/worker.min.js
    corePath:  '/wasm/tesseract-core-simd-lstm.wasm.js',       // routed in web.php
    langPath:  '/wasm',                                        // /wasm/eng.traineddata.gz
    gzip:      true,
    workerBlobURL: false                                       // important for self-hosted builds
  };

  let worker = null;
  let workerReady = null;

  async function getWorker() {
    if (worker) return worker;
    if (workerReady) return workerReady;

    if (!window.Tesseract || !Tesseract.createWorker) {
      ocrStatus('Tesseract not loaded', 'danger');
      throw new Error('Tesseract global missing');
    }

    ocrStatus('Loading OCR…');

    // v4/v5 API: createWorker(lang, oem, options)
    workerReady = (async () => {
      try {
        const w = await Tesseract.createWorker(
          'eng',                // language
          1,                    // OEM (LSTM default)
          {
            workerPath: TESS.workerPath,
            corePath:   TESS.corePath,
            langPath:   TESS.langPath,
            gzip:       TESS.gzip,
            logger: m => {
              if (m && m.status != null && typeof m.progress === 'number') {
                ocrStatus(
                  `OCR: ${m.status} ${Math.round(m.progress * 100)}%`
                );
              }
            }
          }
        );

        // tighten recognition: mainly uppercase letters, digits & punctuation
        await w.setParameters({
          tessedit_pageseg_mode: 6, // Assume a block of text
          tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-./,:\\n \''
        });

        worker = w;
        ocrStatus('OCR ready', 'success');
        return worker;
      } catch (e) {
        console.error('Failed to init Tesseract worker', e);
        ocrStatus('OCR init failed', 'danger');
        throw e;
      }
    })();

    return workerReady;
  }

  // ---------- Camera ----------
  let stream = null, devices = [], currentIdx = -1, videoTrack = null, hasTorch = false;

  async function listCameras() {
    try {
      const all = await navigator.mediaDevices.enumerateDevices();
      return all.filter(d => d.kind === 'videoinput');
    } catch { return []; }
  }

  function constraintsFor(deviceId) {
    const video = {
      width:  { ideal: 1920 },
      height: { ideal: 1080 },
      facingMode: { ideal: 'environment' },
      advanced: [{ focusMode: 'continuous' }]
    };
    const c = deviceId ? { audio:false, video:{ ...video, deviceId:{ exact: deviceId } } }
                       : { audio:false, video };
    return c;
  }

  async function startCamera(next=false) {
    await stopCamera();
    if (!devices.length) devices = await listCameras();
    if (devices.length) {
      if (currentIdx === -1) {
        const back = devices.findIndex(d => /back|rear|environment/i.test(d.label));
        currentIdx = back >= 0 ? back : 0;
      } else if (next) {
        currentIdx = (currentIdx + 1) % devices.length;
      }
    }

    const deviceId = devices[currentIdx]?.deviceId;
    const v = q('#ocr-video');
    try {
      stream = await navigator.mediaDevices.getUserMedia(constraintsFor(deviceId));
    } catch (e) {
      console.warn('getUserMedia error, retry generic video', e);
      try { stream = await navigator.mediaDevices.getUserMedia({ audio:false, video:true }); }
      catch (e2) {
        console.warn('Camera still failed', e2);
        notify('Camera error','Check HTTPS, permissions, or browser settings.','warning');
        return;
      }
    }

    v.srcObject = stream;
    await new Promise(res => (v.onloadedmetadata = () => res()));
    await v.play();

    videoTrack = stream.getVideoTracks()[0] || null;
    hasTorch = false;
    try {
      const caps = videoTrack?.getCapabilities?.();
      hasTorch = !!(caps && 'torch' in caps);
      q('#ocr-torch')?.classList.toggle('disabled', !hasTorch);
    } catch { q('#ocr-torch')?.classList.add('disabled'); }
  }

  async function stopCamera() {
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = null; videoTrack = null; hasTorch = false;
  }

  async function toggleTorch() {
    if (!videoTrack || !videoTrack.getCapabilities) return;
    const caps = videoTrack.getCapabilities();
    if (!caps.torch) return;
    const settings = videoTrack.getSettings();
    const on = !settings.torch;
    try { await videoTrack.applyConstraints({ advanced: [{ torch: on }] }); }
    catch (e) { console.warn('Torch toggle failed', e); }
  }

  // ---------- Preprocess ----------
  function preprocess(canvas) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const { width, height } = canvas;

    const maxW = 1600;
    if (width > maxW) {
      const scale = maxW / width;
      const tmp = document.createElement('canvas');
      tmp.width = Math.floor(width * scale);
      tmp.height = Math.floor(height * scale);
      tmp.getContext('2d').drawImage(canvas, 0, 0, tmp.width, tmp.height);
      canvas.width = tmp.width; canvas.height = tmp.height;
      ctx.clearRect(0,0,canvas.width,canvas.height);
      ctx.drawImage(tmp, 0, 0);
    }

    const img = ctx.getImageData(0,0,canvas.width,canvas.height);
    const d = img.data, hist = new Uint32Array(256);

    for (let i=0;i<d.length;i+=4) {
      const g = (0.2126*d[i] + 0.7152*d[i+1] + 0.0722*d[i+2]) | 0;
      d[i]=d[i+1]=d[i+2]=g; hist[g]++;
    }

    // Otsu threshold
    let sum=0,sumB=0,wB=0,wF=0,max=0,th=127,total=d.length/4;
    for (let t=0;t<256;t++) sum+=t*hist[t];
    for (let t=0;t<256;t++){
      wB+=hist[t]; if(!wB) continue;
      wF=total-wB; if(!wF) break;
      sumB+=t*hist[t];
      const mB=sumB/wB, mF=(sum-sumB)/wF, between=wB*wF*(mB-mF)*(mB-mF);
      if (between>max){ max=between; th=t; }
    }
    for (let i=0;i<d.length;i+=4){
      const v = d[i]; const t = v>th ? 255 : 0;
      d[i]=d[i+1]=d[i+2]=t;
    }
    ctx.putImageData(img,0,0);
  }

  // ---------- PH ID parsing ----------
  function parseOCRText(raw) {
    // normalize + whitelist characters to reduce noise
    const normalized = (raw || '')
      .toUpperCase()
      .replace(/\u00A0/g,' ')
      .replace(/[|]/g,' ')
      .replace(/[^A-Z0-9\-.,:\/\n ']/g,' ');
    const lines = normalized
      .split('\n')
      .map(s => s.replace(/\s+/g,' ').trim())
      .filter(Boolean);

    let firstName='', middleName='', lastName='', address='', birthdate='', licenseNumber='', anyId='';

    const grabAfter=(label)=>{
      const re = new RegExp(`^${label}\\b`,'i');
      const i=lines.findIndex(l=>re.test(l));
      if(i>=0) return lines[i].replace(new RegExp(`^${label}\\s*[:#-]*\\s*`,'i'), '').trim();
      return '';
    };

    // label-based extraction where possible
    lastName   = grabAfter('(LAST NAME|SURNAME|APELYIDO)');
    firstName  = grabAfter('(FIRST NAME|GIVEN NAME|GIVEN NAMES|PANGALAN)');
    middleName = grabAfter('(MIDDLE NAME|MIDDLE INITIAL|GITNANG (APELYIDO|PANGALAN)|M\\.I\\.)');

    if (!lastName && !firstName) {
      const nameLine=lines.find(l=>l.includes(',') && /^[A-Z ,.'-]+$/i.test(l));
      if(nameLine){
        const [l,rest]=nameLine.split(',',2);
        lastName=(l||'').trim();
        const t=(rest||'').trim().split(/\s+/);
        firstName=t[0]||''; middleName=t.slice(1).join(' ');
      }
    }

    const dobRaw = grabAfter('(BIRTHDATE|DATE OF BIRTH|DOB|PETSANG KAPANGANAKAN|KAARAWAN|KAPANGANAKAN)');
    const wholeText = lines.join(' ');
    function parseDateCandidate(str){
      if (!str) return '';
      let m;
      m = str.match(/(19|20)\d{2}[-\/.](0[1-9]|1[0-2])[-\/.](0[1-9]|[12]\d|3[01])/);
      if (m) return `${m[1]}-${m[2]}-${m[3]}`;
      m = str.match(/(0?[1-9]|[12]\d|3[01])[-\/.](0?[1-9]|1[0-2])[-\/.](\d{2,4})/);
      if (m) {
        let y=m[3]; if (y.length===2) y = (parseInt(y,10) > 50 ? '19'+y : '20'+y);
        return `${y}-${String(m[2]).padStart(2,'0')}-${String(m[1]).padStart(2,'0')}`;
      }
      m = str.match(/([A-Z]{3,})\s+(\d{1,2}),?\s+((19|20)\d{2})/);
      if (m) {
        const mon={JAN:1,FEB:2,MAR:3,APR:4,MAY:5,JUN:6,JUL:7,AUG:8,SEP:9,OCT:10,NOV:11,DEC:12};
        const mo=mon[m[1].slice(0,3).toUpperCase()]||1;
        return `${m[3]}-${String(mo).padStart(2,'0')}-${String(m[2]).padStart(2,'0')}`;
      }
      return '';
    }
    birthdate = parseDateCandidate(dobRaw) || parseDateCandidate(wholeText);

    const addrIdx=lines.findIndex(l=>/^ADDRESS\b|^TIRAHAN\b/i.test(l));
    if(addrIdx>=0) {
      address=lines.slice(addrIdx,addrIdx+2).join(', ').replace(/^(ADDRESS|TIRAHAN)\s*[:#-]*/i,'').trim();
    } else {
      const guess=lines.find(l=>/\d+.*[A-Z]/.test(l) && !/LICENSE|DL\s*NO|BIRTH|DOB|KAARAWAN/i.test(l));
      if(guess) address=guess;
    }

    for(const l of lines){
      const mDL=l.match(/(LICENSE\s*NO\.?|DL\s*NO\.?|DRIVER'?S\s*LICENSE(?:\s*NO\.?)?)\s*[:#-]*\s*([A-Z0-9\-]{7,20})/i);
      if(mDL){ licenseNumber=mDL[2]; break; }
      const mID=l.match(/(ID\s*NO\.?|PIN|CRN|PSN|PHILSYS(?:\s*NO\.?)?|PHILHEALTH(?:\s*NO\.?)?|UMID|SSS|GSIS|TIN)\s*[:#-]*\s*([A-Z0-9\-]{7,20})/i);
      if(!anyId && mID) anyId=mID[2];
    }
    if(!licenseNumber && anyId) licenseNumber=anyId;

    if(!firstName || !lastName){
      const cands=lines.filter(l=>/^[A-Z .,'-]+$/i.test(l)).sort((a,b)=>b.length-a.length);
      const c=cands[0];
      if(c){ const p=c.split(/\s+/); if(p.length>=3){ firstName=p[0]; middleName=p.slice(1,-1).join(' '); lastName=p.at(-1);} }
    }

    return { firstName, middleName, lastName, address, licenseNumber, birthdate };
  }

  const setValSmart = (id, name, val) => {
    if (val == null) return;
    const v = String(val).trim();
    let el = document.getElementById(id);
    if (!el) el = document.querySelector(`[name="${name}"]`);
    if (el && 'value' in el) el.value = v;
  };
  function fillForm({ firstName, middleName, lastName, address, licenseNumber, birthdate }) {
    setValSmart('first_name','first_name',firstName);
    setValSmart('middle_name','middle_name',middleName);
    setValSmart('last_name','last_name',lastName);
    setValSmart('address','address',address);
    setValSmart('license_num','license_num',licenseNumber);
    if (birthdate) setValSmart('birthdate','birthdate',birthdate);
  }

  // ---------- Capture & OCR ----------
  async function captureAndOCR() {
    const v = q('#ocr-video');
    if (!v || !v.videoWidth) return notify('Hold up','Camera not ready yet.','info');

    const full = q('#ocr-canvas');
    full.width = v.videoWidth; full.height = v.videoHeight;
    const fullCtx = full.getContext('2d', { willReadFrequently: true });
    fullCtx.drawImage(v, 0, 0, full.width, full.height);

    // Crop to central ID area (roughly matches visual overlay)
    const crop = document.createElement('canvas');
    const insetX = full.width * 0.06;
    const insetY = full.height * 0.10;
    const cropW = full.width  - insetX * 2;
    const cropH = full.height - insetY * 2;
    crop.width  = cropW;
    crop.height = cropH;
    const cropCtx = crop.getContext('2d', { willReadFrequently: true });
    cropCtx.drawImage(full, insetX, insetY, cropW, cropH, 0, 0, cropW, cropH);

    preprocess(crop);

    ocrStatus('Reading…');

    let text = '';
    try {
      const w = await getWorker();
      const r = await w.recognize(crop);
      text = r?.data?.text || '';
    } catch (e) {
      console.error('Tesseract error', e);
      ocrStatus('OCR error (see console)', 'danger');
      notify('OCR error','Could not read text. Check Tesseract paths & console.','warning');
      return;
    } finally {
      ocrStatus('');
    }

    const parsed = parseOCRText(text);
    const anyField = parsed.firstName || parsed.lastName || parsed.licenseNumber || parsed.birthdate || parsed.address;
    if (!anyField) {
      notify('No readable text','Try again: fill the frame, steady hands, use flash.','info');
      return;
    }

    const nameLine = [parsed.firstName, parsed.middleName, parsed.lastName].filter(Boolean).join(' ') || '(none)';
    const previewHtml = `
      <div class="text-start">
        <p class="mb-1"><strong>Name:</strong> ${nameLine}</p>
        <p class="mb-1"><strong>Birthdate:</strong> ${parsed.birthdate || '(none)'}</p>
        <p class="mb-1"><strong>License / ID No.:</strong> ${parsed.licenseNumber || '(none)'}</p>
        <p class="mb-0"><strong>Address:</strong> ${parsed.address || '(none)'}</p>
      </div>
    `;

    const { isConfirmed } = await confirmScan(previewHtml);
    if (isConfirmed) {
      fillForm(parsed);
      notify('Captured!','Fields have been auto-filled from the ID.','success');
      await stopCamera();
      await closeScanModal();
    } else {
      notify('Scan discarded','You can adjust fields or try again.','info');
    }
  }

  // ---------- Modal wiring ----------
  const modalEl = q('#scanIdModal');
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', async () => {
      try {
        // Start camera + OCR worker in parallel
        await Promise.all([
          startCamera(),
          getWorker()
        ]);
      } catch (e) {
        console.error(e);
        ocrStatus('OCR init failed', 'danger');
        notify(
          'OCR error',
          'Tesseract failed to initialize. Please check the console for details.',
          'warning'
        );
      }
    });

    modalEl.addEventListener('hidden.bs.modal', async () => {
      await stopCamera();
    });
  } else {
    // Fallback if Bootstrap modal isn’t managing it
    q('#openScanId')?.addEventListener('click', () => {
      startCamera();
      getWorker().catch(err => {
        console.error(err);
        ocrStatus('OCR init failed', 'danger');
      });
    });
    q('#scan-close')?.addEventListener('click', async () => {
      await stopCamera();
      await closeScanModal();
    });
  }
  q('#ocr-capture')?.addEventListener('click', captureAndOCR);
  q('#ocr-switch')?.addEventListener('click', async () => { await startCamera(true); });
  q('#ocr-torch')?.addEventListener('click', async () => { await toggleTorch(); });

  // Debug helper
  window.ocrSmoke = async () => {
    const w = await getWorker();
    const tiny="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAPElEQVQYV2P8z8Dwn4EIwDiQkJAFGJgYjKRDUMiEQg0QW0gGmQYQ0jQYBzYkCqCw0QBrYwBgaQkAE3AigAAbJjB5nJ8l1QAAAABJRU5ErkJggg==";
    const r=await w.recognize(tiny);
    return (r?.data?.text||'').trim();
  };
})();
