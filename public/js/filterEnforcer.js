// enforcer-filter.js

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('sort_table');
  
    sel.addEventListener('change', () => {
      const sort = sel.value;
      const url  = `/enforcer/partial?sort_option=${encodeURIComponent(sort)}`;
  
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(html => {
          document
            .getElementById('enforcer-table-container')
            .outerHTML = html;
        })
        .catch(console.error);
    });
  
    // Intercept pagination links inside the table container
    document.body.addEventListener('click', e => {
      const a = e.target.closest('#enforcer-table-container .pagination a');
      if (!a) return;
      e.preventDefault();
  
      fetch(a.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(html => {
          document
            .getElementById('enforcer-table-container')
            .outerHTML = html;
        })
        .catch(console.error);
    });
  });
  