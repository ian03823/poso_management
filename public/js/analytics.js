// public/js/analytics.js — redesigned + insights text (Swal drill-down)
(function () {
  let pie, bar, map, heat, markers;

  function $(sel, root = document) { return root.querySelector(sel); }
  function $all(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

  function getRoot() { return $('#analyticsRoot'); }
  function getEndpoints() {
    const r = getRoot(); if (!r) return {};
    return {
      latest:  r.dataset.latestEndpoint  || '/dataAnalytics/latest',
      hotspot: r.dataset.hotspotEndpoint || '/dataAnalytics/hotspotTickets'
    };
  }

  function currentFilters() {
    const from = $('#fltFrom')?.value || '';
    const to   = $('#fltTo')?.value   || '';
    const status = $('input[name="fltStatus"]:checked')?.value || '';
    const vio = $all('.vio-opt:checked').map(cb => cb.value);
    return { from, to, status, violations: vio };
  }

  function buildQuery(obj) {
    const usp = new URLSearchParams();
    if (obj.from) usp.set('from', obj.from);
    if (obj.to) usp.set('to', obj.to);
    if (obj.status) usp.set('status', obj.status);
    (obj.violations || []).forEach(v => usp.append('violations[]', v));
    return usp.toString();
  }

  function initDefaultMonths() {
    const to = new Date();
    const from = new Date(to.getFullYear(), to.getMonth() - 2, 1);
    if ($('#fltFrom')) $('#fltFrom').value = `${from.getFullYear()}-${String(from.getMonth()+1).padStart(2,'0')}`;
    if ($('#fltTo'))   $('#fltTo').value   = `${to.getFullYear()}-${String(to.getMonth()+1).padStart(2,'0')}`;
  }

  // ---- Charts ----
  function ensureCharts() {
    if (pie) try { pie.destroy(); } catch {}
    if (bar) try { bar.destroy(); } catch {}
    const ctxPie = $('#chartPie')?.getContext('2d');
    const ctxBar = $('#chartBar')?.getContext('2d');
    if (!ctxPie || !ctxBar || typeof Chart === 'undefined') return;

    pie = new Chart(ctxPie, {
      type: 'doughnut',
      data: { labels: ['Paid','Unpaid'], datasets: [{ data: [0,0] }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
      }
    });

    bar = new Chart(ctxBar, {
      type: 'bar',
      data: { labels: [], datasets: [{ label:'Tickets', data: [] }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } }
      }
    });
  }

  // ---- Map ----
  function ensureMap() {
    if (!$('#map') || typeof L === 'undefined') return;
    if (map) { try { map.remove(); } catch {} map = null; heat = null; }
    const root = getRoot();
    const lat = Number(root?.dataset.defaultLat ?? 15.9285);
    const lng = Number(root?.dataset.defaultLng ?? 120.3487);

    map = L.map('map', { zoomControl:true }).setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    heat = L.heatLayer([], { radius: 25, blur: 15, maxZoom: 17 }).addTo(map);
    markers = L.layerGroup().addTo(map);
    setTimeout(()=> map.invalidateSize(), 200);
  }

  // ---- Fetch + Render ----
  async function fetchStats() {
    const { latest } = getEndpoints();
    const q = buildQuery(currentFilters());
    $('#spinPie')?.classList.remove('d-none');
    $('#spinBar')?.classList.remove('d-none');

    try {
      const res = await fetch(`${latest}?${q}`, { headers:{ 'Accept':'application/json' }});
      const data = await res.json();

      const paid   = Number(data.paid || 0);
      const unpaid = Number(data.unpaid || 0);
      const months = Object.keys(data.monthlyCounts || {});
      const totals = Object.values(data.monthlyCounts || {}).map(x=>Number(x));

      // KPIs
      if ($('#kpiPaid'))   $('#kpiPaid').textContent = paid;
      if ($('#kpiUnpaid')) $('#kpiUnpaid').textContent = unpaid;
      if ($('#kpiTotal'))  $('#kpiTotal').textContent = paid + unpaid;

      // Pie
      if (pie) {
        pie.data.datasets[0].data = [paid, unpaid];
        pie.update();
      }
      if ($('#pieEmpty')) $('#pieEmpty').style.display = (paid + unpaid) ? 'none' : '';

      // Bar
      if (bar) {
        bar.data.labels = months;
        bar.data.datasets[0].data = totals;
        bar.update();
      }
      if ($('#barEmpty')) $('#barEmpty').style.display = totals.some(v=>v>0) ? 'none' : '';

      // Heat + markers
      if (heat && markers) {
        const pts = (data.hotspots || []).map(h => [Number(h.latitude), Number(h.longitude), Number(h.c)]);
        heat.setLatLngs(pts);

        markers.clearLayers();
        [...(data.hotspots || [])].slice(0, 12).forEach(h => {
          const m = L.circleMarker([h.latitude, h.longitude], {
            radius: Math.min(60, 18 + Number(h.c) * 1.6),
            weight: 1, opacity: 0.9, fillOpacity: 0.35
          }).bindTooltip(`${h.area ?? 'Hotspot'}: ${h.c} tickets`);
          m.on('click', ()=> openHotspot(h.latitude, h.longitude, h.area));
          markers.addLayer(m);
        });
      }

      // Insights text (array from server)
      if (Array.isArray(data.insights)) {
        const text = data.insights.map((s,i)=>`${i+1}. ${s}`).join('\n');
        if ($('#insightsBox')) $('#insightsBox').value = text;
      } else if (typeof data.insights_text === 'string') {
        if ($('#insightsBox')) $('#insightsBox').value = data.insights_text;
      }

    } catch (e) {
      console.error('Analytics fetch failed', e);
      if (window.Swal) Swal.fire('Error','Failed to load analytics data.','error');
    } finally {
      $('#spinPie')?.classList.add('d-none');
      $('#spinBar')?.classList.add('d-none');
      setTimeout(()=> map && map.invalidateSize(), 150);
      updateViolationButtonText();
      updateExportLinks();
      killBackdrops(); // just in case any old backdrops exist
    }
  }

  function updateViolationButtonText(){
    const boxes = $all('.vio-opt:checked');
    const btn = $('#vioBtnText');
    if (!btn) return;
    if (boxes.length === 0) btn.textContent = 'All Violations';
    else if (boxes.length === 1) btn.textContent = boxes[0].nextElementSibling?.textContent?.trim() || '1 selected';
    else btn.textContent = `${boxes.length} selected`;
  }

  function updateExportLinks(){
    const q = buildQuery(currentFilters());
    const x = $('#btnExportXlsx'); const d = $('#btnExportDocx');
    if (x) x.href = `${x.href.split('?')[0]}?${q}`;
    if (d) d.href = `${d.href.split('?')[0]}?${q}`;
  }


  // ---- SweetAlert drill-down (ticket id, name, vehicle, issued at, status)
window.__AN_SWAL_VERSION = 'v06-grid';

async function openHotspot(lat, lng, area){
  const { hotspot } = getEndpoints();
  const q = buildQuery(currentFilters());
  const title = `Hotspot Tickets — ${area ?? 'Location'}`;

  await Swal.fire({
    title,
    html: `
      <div id="hotspotBody" class="text-center text-muted py-4">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div class="mt-2">Loading… ${window.__AN_SWAL_VERSION}</div>
      </div>
    `,
    width: 1000,
    showConfirmButton: true,
    confirmButtonText: 'Close',
    focusConfirm: false,
    allowOutsideClick: true,
    didOpen: async () => {
      const body = document.getElementById('hotspotBody');
      try {
        const url = `${hotspot}?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}&${q}`;
        const res = await fetch(url, { headers:{ 'Accept':'application/json' }, credentials:'same-origin' });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          body.innerHTML = `<div class="text-danger">Unexpected response (not JSON). Are you logged in?</div>`;
          return;
        }

        const json = await res.json();
        const rows = Array.isArray(json.tickets) ? json.tickets : [];
        console.debug('[analytics v06-grid] rows=', rows.length, rows);

        if (!rows.length) {
          body.innerHTML = `<div class="text-center text-muted py-4">No tickets for this spot in the selected range.</div>`;
          return;
        }

        // clear placeholder
        body.classList.remove('text-muted','py-4');
        body.textContent = '';

        // found-count
        const found = document.createElement('div');
        found.className = 'mb-2 small text-muted';
        found.textContent = `Found ${rows.length} ticket(s)`;
        body.appendChild(found);

        // grid wrapper
        const wrap = document.createElement('div');
        wrap.className = 'swal-gridwrap';

        // header
        const header = document.createElement('div');
        header.className = 'swal-grid-header';
        ['Ticket ID','Name','Vehicle','Issued At','Status'].forEach(txt => {
          const d = document.createElement('div');
          d.textContent = txt;
          header.appendChild(d);
        });
        wrap.appendChild(header);

        // rows
        rows.forEach(r => {
          const row = document.createElement('div');
          row.className = 'swal-grid-row';

          const cId = document.createElement('div');
          cId.className = 'swal-grid-id swal-grid-wrap-nowrap';
          cId.textContent = `#${r.id ?? ''}`;

          const cName = document.createElement('div');
          cName.className = 'swal-grid-wrap-normal';
          cName.textContent = r.name || 'Unknown';

          const cVeh = document.createElement('div');
          cVeh.className = 'swal-grid-wrap-normal';
          cVeh.textContent = r.vehicle || '';

          const cIssued = document.createElement('div');
          cIssued.className = 'swal-grid-issued swal-grid-wrap-nowrap';
          cIssued.textContent = r.issued_at || '';

          const cStatus = document.createElement('div');
          cStatus.className = 'swal-grid-status';
          const badge = document.createElement('span');
          badge.className = `badge ${r.status === 'Paid' ? 'bg-success' : 'bg-warning text-dark'}`;
          badge.textContent = r.status || '';
          cStatus.appendChild(badge);

          row.appendChild(cId);
          row.appendChild(cName);
          row.appendChild(cVeh);
          row.appendChild(cIssued);
          row.appendChild(cStatus);

          wrap.appendChild(row);
        });

        body.appendChild(wrap);

      } catch (e) {
        console.error('hotspotTickets error', e);
        body.innerHTML = `<div class="text-danger">Failed to load tickets.</div>`;
      }
    },
    didClose: killBackdrops
  });
}
  function escapeHtml(s){ return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  // Remove any stale Bootstrap backdrops/body classes (SPA safety)
  function killBackdrops() {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
  }

  function bindEvents(){
    $('#btnApply')?.addEventListener('click', fetchStats);
    $('#btnReset')?.addEventListener('click', () => {
      initDefaultMonths();
      $all('.vio-opt').forEach(cb => cb.checked = false);
      $('#st_all')?.click();
      fetchStats();
    });
    $all('.vio-opt').forEach(cb => cb.addEventListener('change', updateViolationButtonText));

    $('#btnCopyInsights')?.addEventListener('click', () => {
      const ta = $('#insightsBox'); if (!ta) return;
      ta.select();
      document.execCommand('copy');
      if (window.Swal) Swal.fire('Copied','Insights copied to clipboard.','success');
    });

    // SPA engine hook: after any page swap, clear backdrops if any
    document.addEventListener('page:loaded', killBackdrops);
  }

  function init(){
    if (!getRoot()) return; // not on this page
    ensureCharts();
    ensureMap();
    bindEvents();
    initDefaultMonths();
    fetchStats();
  }

  // Run on normal load + SPA swap
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
  document.addEventListener('page:loaded', init);
})();
