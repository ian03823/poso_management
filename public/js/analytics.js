// public/js/analytics.js — redesigned + insights text
(function () {
  let pie, bar, map, heat, markers, modal;

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
    $('#fltFrom').value = `${from.getFullYear()}-${String(from.getMonth()+1).padStart(2,'0')}`;
    $('#fltTo').value   = `${to.getFullYear()}-${String(to.getMonth()+1).padStart(2,'0')}`;
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
      options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
    });

    bar = new Chart(ctxBar, {
      type: 'bar',
      data: { labels: [], datasets: [{ label:'Tickets', data: [] }] },
      options: {
        responsive:true, maintainAspectRatio:false,
        scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } },
        plugins:{ legend:{ display:false } }
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
      $('#kpiPaid').textContent = paid;
      $('#kpiUnpaid').textContent = unpaid;
      $('#kpiTotal').textContent = paid + unpaid;

      // Pie
      if (pie) {
        pie.data.datasets[0].data = [paid, unpaid];
        pie.update();
      }
      $('#pieEmpty').style.display = (paid + unpaid) ? 'none' : '';

      // Bar
      if (bar) {
        bar.data.labels = months;
        bar.data.datasets[0].data = totals;
        bar.update();
      }
      $('#barEmpty').style.display = totals.some(v=>v>0) ? 'none' : '';

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
        $('#insightsBox').value = text;
      } else if (typeof data.insights_text === 'string') {
        $('#insightsBox').value = data.insights_text;
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

  async function openHotspot(lat, lng, area){
    const { hotspot } = getEndpoints();
    $('#hotspotBody').innerHTML = `<div class="text-center text-muted py-4">Loading…</div>`;
    $('#hotspotModalLabel').textContent = `Hotspot Tickets — ${area ?? 'Location'}`;
    modal = modal || (typeof bootstrap !== 'undefined' ? new bootstrap.Modal($('#hotspotModal')) : null);

    try{
      const q = buildQuery(currentFilters());
      const res = await fetch(`${hotspot}?lat=${lat}&lng=${lng}&${q}`);
      const json = await res.json();
      const rows = json.tickets || [];
      if (!rows.length){
        $('#hotspotBody').innerHTML = `<div class="text-center text-muted py-4">No tickets for this spot in the selected range.</div>`;
      } else {
        $('#hotspotBody').innerHTML = `
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr><th>ID</th><th>Issued At</th><th>Location</th><th>Status</th></tr>
              </thead>
              <tbody>
                ${rows.map(r => `
                  <tr>
                    <td>${r.id}</td>
                    <td>${r.issued_at}</td>
                    <td>${escapeHtml(r.location || '')}</td>
                    <td>
                      <span class="badge ${r.status==='Paid'?'bg-success':'bg-warning text-dark'}">${r.status}</span>
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>`;
      }
    } catch(e){
      $('#hotspotBody').innerHTML = `<div class="text-danger">Failed to load tickets.</div>`;
    }
    modal && modal.show();
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

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
      const ta = $('#insightsBox');
      if (!ta) return;
      ta.select();
      document.execCommand('copy');
      if (window.Swal) Swal.fire('Copied','Insights copied to clipboard.','success');
    });
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
