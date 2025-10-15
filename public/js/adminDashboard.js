(function ($) {
  if (window.__adminDashInit) return;
  window.__adminDashInit = true;

  function root() { return document.getElementById('admin-dashboard'); }
  function cfg() {
    const r = root();
    return {
      versionUrl:  r?.dataset.versionUrl || null,
      summaryUrl:  r?.dataset.summaryUrl || null,
      violatorsUrl:r?.dataset.violatorsUrl || null,
      ticketsUrl:  r?.dataset.ticketsUrl || null
    };
  }

  function swapHtml($el, html) {
    $el.removeClass('fade-in').addClass('fade-out');
    setTimeout(() => {
      $el.html(html);
      $el.removeClass('fade-out').addClass('fade-in');
    }, 120);
  }

  let lastV = null;
  async function reloadSections() {
    const C = cfg();
    if (C.summaryUrl)   $.get(C.summaryUrl).done(html => swapHtml($('#dash-summary'), html));
    if (C.violatorsUrl) $.get(C.violatorsUrl).done(html => swapHtml($('#dash-recent-violators'), html));
    if (C.ticketsUrl)   $.get(C.ticketsUrl).done(html => swapHtml($('#dash-recent-tickets'), html));
  }

  async function checkVersion() {
    const C = cfg();
    if (!C.versionUrl) return;
    try {
      const res = await $.getJSON(C.versionUrl);
      const v = res && res.v ? String(res.v) : null;
      if (v && lastV && v !== lastV) {
        await reloadSections();
      }
      if (v) lastV = v;
    } catch (e) { /* ignore */ }
  }

  function start() {
    // baseline immediately, then poll
    checkVersion();
    setInterval(checkVersion, 1000); // 5s; tweak if you want faster
  }

  $(document).on('DOMContentLoaded page:loaded', start);
})(jQuery);
