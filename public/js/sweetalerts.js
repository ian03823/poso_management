document.addEventListener('DOMContentLoaded', () => {
    // Delete confirmation
    document.body.addEventListener('click', e => {
      const btn = e.target.closest('.delete-btn');
      if (!btn) return;
      e.preventDefault();
      const form = btn.closest('form') || (() => {
        // fallback: wrap button in a form tag
        let f = document.createElement('form');
        f.method = 'POST';
        f.action = btn.dataset.action || window.location.href;
        f.appendChild(btn);
        return f;
      })();
      const name = btn.dataset.name || 'this item';
  
      Swal.fire({
        title: `Delete ${name}?`,
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
      }).then(res => {
        if (res.isConfirmed) form.submit();
      });
    });
    //Generate Password
    window.generatePassword = function() {
        const prefix = 'posoenforcer_';
        const rnd    = Math.floor(100 + Math.random() * 900);
        const pwEl   = document.getElementById('password');
        if (pwEl) pwEl.value = prefix + rnd;
      };
  
    // Back-button confirmation
    document.body.addEventListener('click', e => {
      const btn = e.target.closest('#previousBtn');
      if (!btn) return;
      e.preventDefault();
  
      const inputs = document.querySelectorAll('#enforcerForm input');
      const empty  = Array.from(inputs).filter(i => !i.value.trim());
      empty.forEach(i=> i.classList.add('border','border-danger'));
  
      const swalOpts = empty.length === inputs.length
        ? {
            title: 'All fields are empty!',
            text: 'Please fill out the form or click Leave.',
          }
        : {
            title: 'Form is not complete!',
            text: 'Some fields are still empty. Leave anyway?',
          };
  
      Swal.fire(Object.assign({
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, leave',
        cancelButtonText: 'Stay'
      }, swalOpts)).then(res => {
        if (res.isConfirmed) {
          // go back via AJAX
          loadContent(btn.dataset.back);
        }
      });
    });
  });
  