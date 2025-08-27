;(function(){
  const contentEl = document.getElementById('app-body');

  document.body.addEventListener('click', e => {
    const a = e.target.closest('.sidebar .nav-link, a[data-ajax]');
    if (!a) return;
    if (a.dataset.noAjax !== undefined) return; // let normal submit happen (e.g., logout)
    e.preventDefault();
    loadContent(a.href);
  });

  window.addEventListener('popstate', () => {
    loadContent(location.pathname + location.search, false);
  });

  function loadContent(url, push = true) {
    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.text())
    .then(html => {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newContent = doc.getElementById('app-body').innerHTML;

      contentEl.innerHTML = newContent;

      if (push) history.pushState({}, '', url);

      // Custom event trigger after swapping content
      document.dispatchEvent(new Event('page:loaded'));
    });
  }
})();
