document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('sort_option');
    const container = document.getElementById('enforcerContainer');
    const form = document.getElementById('filterForm');
  
    select.addEventListener('change', () => {
      const url = `${form.action}?sort_option=${select.value}`;
  
      fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(res => {
        if (!res.ok) throw new Error(res.status);
        return res.text();
      })
      .then(html => {
        // Replace only the table+pagination
        container.innerHTML = html;
      })
      .catch(err => {
        console.error('Filter AJAX error:', err);
      });
    });
  });