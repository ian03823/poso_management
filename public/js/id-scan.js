// public/js/id-scan.js — mobile-first OCR scanner (2025-10-04)
(() => {
  console.log('%cID-SCAN mobile v2025-10-04','color:#0a0');

  // ---------- Utils ----------
  const q = (sel, r=document) => r.querySelector(sel);
  const notify = (title, text, icon='info') => {
    if (window.Swal) Swal.fire({ icon, title, text });
    else alert(`${title}\n${text||''}`);
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

  // ---------- Status ----------
  const statusEl = q('#ocr-status');
  const ocrStatus = (msg, kind='muted') => {
    if (statusEl) statusEl.innerHTML = `<span class="text-${kind}">${msg||''}</span>`;
  };

  // ---------- Tesseract config ----------
  const TESS = {
    // Load worker from vendor (it references tesseract.min.js already included)
    workerPath: "/vendor/tesseract/worker.min.js",
    // Use the SIMD-LSTM loader for speed; fallback to non-SIMD if needed
    corePathSIMD: "/wasm/tesseract-core-simd-lstm.wasm.js",
    langPath: "/wasm",
    gzip: true
  };

  let worker = null, workerReady = null;
  async function getWorker() {
    if (worker) return worker;
    if (workerReady) return workerReady;
    if (!window.Tesseract) {
      ocrStatus('Tesseract not loaded', 'danger');
      throw new Error('Tesseract global missing');
    }

    ocrStatus('Loading OCR…');
    if (Tesseract.setLogging) Tesseract.setLogging(false);

    workerReady = (async () => {
      // prefer SIMD core; if it fails (older iOS), try fallback
      const tryCreate = async (corePath) => {
        const w = await Tesseract.createWorker({
          workerPath: TESS.workerPath,
          corePath: TESS.corePathSIMD,
          langPath: TESS.langPath,
          gzip: TESS.gzip,
          logger: m => (m?.status ? ocrStatus(`OCR: ${m.status} ${Math.round((m.progress||0)*100)}%`) : null)
        });
        await w.loadLanguage('eng');
        await w.initialize('eng');
        await w.setParameters({
          tessedit_pageseg_mode: '6',          // uniform block of text
          preserve_interword_spaces: '1',
          user_defined_dpi: '300'
        });
        return w;
      };

      try {
        worker = await tryCreate(TESS.corePathSIMD);
      } catch (e) {
        console.warn('SIMD core failed, retrying non-SIMD…', e);
        try {
          worker = await tryCreate(TESS.corePathFallback);
        } catch (e2) {
          ocrStatus('Failed to init OCR (core)', 'danger');
          throw e2;
        }
      }

      ocrStatus('OCR ready', 'success');
      return worker;
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
      // NB: focusMode isn't standard everywhere; some browsers/phones still honor it
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
      console.warn('GUM error, retry generic video', e);
      try { stream = await navigator.mediaDevices.getUserMedia({ audio:false, video:true }); }
      catch (e2) { notify('Camera error','Check HTTPS & permissions.','warning'); return; }
    }

    v.srcObject = stream;
    await new Promise(res => (v.onloadedmetadata = () => res()));
    await v.play();

    // track refs for torch/zoom
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

  // ---------- Preprocess (light but effective) ----------
  function preprocess(canvas) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const { width, height } = canvas;

    // downscale to denoise/speed
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

  // ---------- PH ID parsing (kept from your version) ----------
  function parseOCRText(raw) {
    const text=(raw||'').replace(/\u00A0/g,' ').replace(/[|]/g,' ');
    const lines=text.split('\n').map(s=>s.replace(/\s+/g,' ').trim()).filter(Boolean);

    let firstName='', middleName='', lastName='', address='', birthdate='', licenseNumber='', anyId='';

    const grabAfter=(label)=>{
      const i=lines.findIndex(l=>new RegExp(`^${label}\\b`,'i').test(l));
      if(i>=0) return lines[i].replace(new RegExp(`^${label}\\s*[:#-]*\\s*`,'i'), '').trim();
      return '';
    };

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
    if (dobRaw){
      const m1=dobRaw.match(/(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/);
      const m2=dobRaw.match(/(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})/);
      const m3=dobRaw.match(/([A-Za-z]{3,})\s+(\d{1,2}),?\s+(\d{4})/);
      if(m1){ const[_,y,mo,d]=m1; birthdate=`${y}-${String(mo).padStart(2,'0')}-${String(d).padStart(2,'0')}`; }
      else if(m2){ let[_,d,mo,y]=m2; if(y.length===2) y=`20${y}`; birthdate=`${y}-${String(mo).padStart(2,'0')}-${String(d).padStart(2,'0')}`; }
      else if(m3){ const mon={jan:1,feb:2,mar:3,apr:4,may:5,jun:6,jul:7,aug:8,sep:9,oct:10,nov:11,dec:12};
                   const mo=mon[m3[1].slice(0,3).toLowerCase()]||1; birthdate=`${m3[3]}-${String(mo).padStart(2,'0')}-${String(m3[2]).padStart(2,'0')}`; }
    }

    const addrIdx=lines.findIndex(l=>/^ADDRESS\b|^TIRAHAN\b/i.test(l));
    if(addrIdx>=0) address=lines.slice(addrIdx,addrIdx+2).join(', ').replace(/^(ADDRESS|TIRAHAN)\s*[:#-]*/i,'').trim();
    else {
      const guess=lines.find(l=>/\d+.*[A-Za-z]/.test(l) && !/LICENSE|DL\s*NO|BIRTH|DOB|KAARAWAN/i.test(l));
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

    const c = q('#ocr-canvas');
    c.width = v.videoWidth; c.height = v.videoHeight;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(v, 0, 0, c.width, c.height);
    preprocess(c);

    ocrStatus('Reading…');
    let text = '';
    try {
      const w = await getWorker();
      const r = await w.recognize(c);
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
    fillForm(parsed);

    const anyField = parsed.firstName || parsed.lastName || parsed.licenseNumber || parsed.birthdate || parsed.address;
    if (anyField) {
      notify('Captured!','Fields have been auto-filled from the ID.','success');
      await stopCamera();
      await closeScanModal();
    } else {
      notify('No readable text','Try again: fill the frame, steady hands, use flash.','info');
    }
  }

  // ---------- Modal wiring ----------
  const modalEl = q('#scanIdModal');
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', () => { startCamera(); getWorker().catch(()=>{}); });
    modalEl.addEventListener('hidden.bs.modal', async () => { await stopCamera(); });
  } else {
    // Fallback if BS isn't present for some reason
    q('#openScanId')?.addEventListener('click', () => { startCamera(); getWorker().catch(()=>{}); });
    q('#scan-close')?.addEventListener('click', async () => { await stopCamera(); closeScanModal(); });
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
