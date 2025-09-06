/* ajax.js — SPA-like navigation for #app-body with active nav + spinner loader */
(() => {
  const CONTAINER_SEL = '#app-body';
  const NAV_LINK_SEL  = '.sidebar .nav-link';
  const AJAX_LINK_SEL = 'a[data-ajax]:not([data-no-ajax])';

  const container = document.querySelector(CONTAINER_SEL);
  if (!container) return;

  /* ---------- loader overlay (spinner) ---------- */
  const loader = document.createElement('div');
  loader.id = 'page-loader';
  loader.setAttribute('aria-hidden', 'true');
  loader.innerHTML = `
    <div class="loader-box" role="status" aria-live="polite" aria-label="Loading">
      <div class="spinner"></div>
      <div class="loader-text">Loading…</div>
    </div>
  `;
  loader.style.display = 'none';
  document.body.appendChild(loader);
  const showLoader = () => (loader.style.display = 'grid');
  const hideLoader = () => (loader.style.display = 'none');

  function normalizePath(u) {
    try { return new URL(u, location.origin).pathname.replace(/\/+$/, '') || '/'; }
    catch { return '/'; }
  }

  function setActiveFromURL() {
    const path = normalizePath(location.pathname);
    let best = null, bestLen = -1;
    document.querySelectorAll(NAV_LINK_SEL).forEach(a => {
      const href = a.getAttribute('href') || a.href || '';
      if (!href) return;
      const hrefPath = normalizePath(href);
      if (path === hrefPath || (path.startsWith(hrefPath) && hrefPath.length > bestLen)) {
        best = a; bestLen = hrefPath.length;
      }
    });
    document.querySelectorAll(`${NAV_LINK_SEL}.active,[aria-current="page"]`)
      .forEach(a => { a.classList.remove('active'); a.removeAttribute('aria-current'); });
    if (best) { best.classList.add('active'); best.setAttribute('aria-current','page'); }
  }

  function applyLayoutFlags() {
    // If the new content includes the marker, hide the sidebar
    const hide = !!container.querySelector('[data-hide-sidebar]');
    document.body.classList.toggle('no-sidebar', hide);
  }

  async function executeScripts(scope) {
    const scripts = Array.from(scope.querySelectorAll('script'));
    for (const s of scripts) {
      const n = document.createElement('script');
      for (const { name, value } of Array.from(s.attributes)) n.setAttribute(name, value);
      if (s.src) {
        const already = Array.from(document.scripts).some(tag => tag.src === s.src);
        if (already) continue;
        await new Promise((res, rej) => {
          n.onload = res; n.onerror = rej; n.async = false; n.src = s.src; document.head.appendChild(n);
        }).catch(()=>{});
      } else {
        n.textContent = s.textContent; document.head.appendChild(n);
        setTimeout(() => n.remove(), 0);
      }
    }
  }

  async function ajaxNavigate(url, { push=true, replace=false } = {}) {
    showLoader();
    try {
      const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' });
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newMain = doc.querySelector(CONTAINER_SEL);
      if (!newMain) { location.href = url; return; }

      container.innerHTML = newMain.innerHTML;

      const titleEl = doc.querySelector('title');
      if (titleEl) document.title = titleEl.textContent;

      if (replace) history.replaceState({ url }, '', url);
      else if (push) history.pushState({ url }, '', url);

      setActiveFromURL();
      applyLayoutFlags(); // 👈 toggle sidebar based on marker

      container.scrollTo({ top: 0, behavior: 'instant' });
      const firstHeading = container.querySelector('h1,h2,h3,[role="heading"]');
      if (firstHeading) { firstHeading.tabIndex = -1; firstHeading.focus({ preventScroll: true }); }

      await executeScripts(container);
      document.dispatchEvent(new CustomEvent('page:loaded', { detail:{ url } }));
    } catch (e) {
      location.href = url;
    } finally {
      hideLoader();
    }
  }

  document.addEventListener('click', e => {
    const a = e.target.closest(`${NAV_LINK_SEL}, ${AJAX_LINK_SEL}`);
    if (!a) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || a.target === '_blank') return;
    if (a.hasAttribute('data-no-ajax')) return;

    const href = a.getAttribute('href') || a.href;
    if (!href || href.startsWith('#')) return;

    e.preventDefault();
    ajaxNavigate(href, { push:true });
  });

  window.addEventListener('popstate', e => {
    const url = (e.state && e.state.url) ? e.state.url : location.href;
    ajaxNavigate(url, { replace:true });
  });

  // Initial pass (in case first page already has the marker)
  document.addEventListener('DOMContentLoaded', () => {
    setActiveFromURL();
    applyLayoutFlags();
  });
})();
