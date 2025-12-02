// public/js/adminNotify.js
(function () {
  // avoid double-init
  if (window.__adminNotifyInit) return;
  window.__adminNotifyInit = true;

  const VERSION_URL = window.ADMIN_VERSION_URL || '/admin/dashboard/version';
  let lastV = null;
  let timer = null;

  function playTicketNotifySound() {
    try {
      const audio = document.getElementById('ticketNotifySound');
      if (!audio) return;
      audio.currentTime = 0;
      // Some browsers need a user gesture first; if it fails, we just ignore
      audio.play().catch(() => {});
    } catch (_) {}
  }

  function showNewActivityToast() {
    const msg = 'New ticket / violator activity detected today.';
    if (window.Swal) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: msg,
        timer: 2600,
        showConfirmButton: false,
        timerProgressBar: true
      });
    } else {
      alert(msg);
    }
  }

  async function pollVersion() {
    if (!VERSION_URL) return;

    try {
      const res = await fetch(VERSION_URL, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        cache: 'no-store'
      });

      // If not logged in / redirected, just ignore
      if (!res.ok) return;

      const data = await res.json();
      const v = data && data.v ? String(data.v) : null;
      if (!v) return;

      // First run after page load: remember current version but don't alert
      if (!lastV) {
        lastV = v;
        return;
      }

      // On change → new activity today
      if (v !== lastV) {
        lastV = v;
        showNewActivityToast();
        playTicketNotifySound();
      }
    } catch (e) {
      // swallow errors silently; we don't want to annoy the admin
      // console.warn('[adminNotify] version poll failed', e);
    }
  }

  function start() {
    // Only run when SweetAlert is ready (layout already loads it)
    if (timer) clearInterval(timer);

    // Baseline check
    pollVersion();

    // Poll every 5 seconds (you can make this 3000 for faster)
    timer = setInterval(pollVersion, 5000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
