// public/js/adminDashboard.js
(function ($) {
  if (window.__adminDashInit) return;
  window.__adminDashInit = true;

  function root() {
    return document.getElementById('admin-dashboard');
  }

  function cfg() {
    const r = root();
    return {
      versionUrl:   r?.dataset.versionUrl   || null,
      summaryUrl:   r?.dataset.summaryUrl   || null,
      violatorsUrl: r?.dataset.violatorsUrl || null,
      ticketsUrl:   r?.dataset.ticketsUrl   || null
    };
  }

  function swapHtml($el, html) {
    $el.removeClass('fade-in').addClass('fade-out');
    setTimeout(() => {
      $el.html(html);
      $el.removeClass('fade-out').addClass('fade-in');
    }, 120);
  }

  let lastV    = null;
  let interval = null;

  async function reloadSections() {
    const C = cfg();
    // Only run if we are actually on dashboard
    if (!root()) return;

    if (C.summaryUrl) {
      $.get(C.summaryUrl)
        .done(html => swapHtml($('#dash-summary'), html));
    }

    if (C.violatorsUrl) {
      $.get(C.violatorsUrl)
        .done(html => swapHtml($('#dash-recent-violators'), html));
    }

    if (C.ticketsUrl) {
      $.get(C.ticketsUrl)
        .done(html => swapHtml($('#dash-recent-tickets'), html));
    }
  }

  async function checkVersion() {
    const C = cfg();
    // if not on dashboard or no version URL, do nothing
    if (!root() || !C.versionUrl) return;

    try {
      const res = await $.getJSON(C.versionUrl);
      const v   = res && res.v ? String(res.v) : null;

      // First time: just store version, no reload (already have initial HTML)
      if (!lastV && v) {
        lastV = v;
        return;
      }

      // Subsequent: if changed, reload sections
      if (v && lastV && v !== lastV) {
        lastV = v;
        await reloadSections();
      }
    } catch (e) {
      // optional: console.error(e);
    }
  }

  function start() {
    // Called on DOMContentLoaded and every SPA page:loaded
    const dash = root();

    // If not on the dashboard page, clear any existing interval and exit
    if (!dash) {
      if (interval) {
        clearInterval(interval);
        interval = null;
      }
      return;
    }

    // We ARE on dashboard page:
    // reset lastV so we re-sync with today's version
    lastV = null;

    // Clear previous interval (if any) to avoid duplicates
    if (interval) {
      clearInterval(interval);
      interval = null;
    }

    // Baseline check + then poll
    checkVersion();
    // Poll every 5s (5000ms). You can tweak to 3000 if you want faster.
    interval = setInterval(checkVersion, 3000);
  }

  // Initial full load
  $(document).on('DOMContentLoaded', start);
  // Each time SPA injects a new page
  $(document).on('page:loaded', start);

})(jQuery);
