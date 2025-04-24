(function(){
    const contentEl = document.getElementById('app-body');
  
    function loadContent(url, push=true) {
      fetch(url, { headers:{ 'X-Requested-With':'XMLHttpRequest' } })
        .then(r => r.text())
        .then(html => {
          const doc = new DOMParser()
                        .parseFromString(html,'text/html');
          const wrapper = doc.getElementById('app-body');
          contentEl.innerHTML = wrapper
            ? wrapper.innerHTML
            : html;
          if (push) history.pushState(null,'',url);
        })
        .catch(console.error);
    }
  
    // expose globally for sweetalert.js
    window.loadContent = loadContent;
  
    // 1) Sidebar nav
    document.querySelectorAll('.sidebar .nav-link').forEach(a=>{
      a.addEventListener('click', e=>{
        e.preventDefault();
        loadContent(a.href);
      });
    });
  
    // 2) In-content AJAX links
    document.body.addEventListener('click', e=>{
      const a = e.target.closest('a[data-ajax]');
      if (!a) return;
      e.preventDefault();
      loadContent(a.href);
    });
  
    // 3) Back button handled in sweetalert.js
  
    // 4) Browser back/forward
    window.addEventListener('popstate', ()=>{
      loadContent(location.pathname,false);
    });
  })();
  