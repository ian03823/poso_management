window.pie = window.pie || null;
window.bar = window.bar || null;
window.map = window.map || null;
window.heat = window.heat || null;

function initAnalytics() {
    if (!document.getElementById('pieChart')) return;

    if (window.pie) window.pie.destroy();
    if (window.bar) window.bar.destroy();

    window.pie = new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: ['Paid', 'Unpaid'],
            datasets: [{ data: [0, 0] }]
        }
    });

    window.bar = new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{ label: 'Violations', data: [] }]
        }
    });

    if (!window.map) {
        window.map = L.map('map').setView([window.DEFAULT_LAT, window.DEFAULT_LNG], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(window.map);
        window.heat = L.heatLayer([], { radius: 25 }).addTo(window.map);
    }

    fetchStats();
}

async function fetchStats() {
    const loadingEl = document.getElementById('loadingIndicator');
    if (loadingEl) loadingEl.style.display = 'block';

    try {
        const res = await fetch('/dataAnalytics/latest');
        const e = await res.json();

        window.pie.data.datasets[0].data = [e.paid, e.unpaid];
        window.pie.update();

        window.bar.data.labels = Object.keys(e.monthlyCounts);
        window.bar.data.datasets[0].data = Object.values(e.monthlyCounts);
        window.bar.update();

        const pts = e.hotspots.map(h => [h.latitude, h.longitude, h.c]);
        window.heat.setLatLngs(pts);
    } catch (error) {
        console.error('Fetch failed:', error);
    } finally {
        if (loadingEl) loadingEl.style.display = 'none';
    }
}


document.addEventListener("DOMContentLoaded", initAnalytics);
document.addEventListener("page:loaded", initAnalytics);
