// public/js/enforcer.js
(() => {
      // ── 2) “Back” button on create/edit forms ───────────────────────────────
        // Confirm‐back with SweetAlert on any <button class="confirm-back" data-back="…">
        document.body.addEventListener('click', e => {
        const btn = e.target.closest('button.confirm-back');
        if (!btn) return;
        e.preventDefault();

        // highlight any empty inputs
        const inputs = document.querySelectorAll('#enforcerForm input');
        const empty  = Array.from(inputs).filter(i => !i.value.trim());
        empty.forEach(i => i.classList.add('border','border-danger'));

        // decide message
        const swalOpts = empty.length === inputs.length
            ? { title: 'All fields are empty!', text: 'Fill the form or leave.' }
            : { title: 'Form not complete!',   text: 'Some fields are empty. Leave anyway?' };

        Swal.fire(Object.assign({
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, leave',
            cancelButtonText: 'Stay'
        }, swalOpts)).then(res => {
            if (!res.isConfirmed) return;

            const backUrl = btn.dataset.back;
            if (typeof loadContent === 'function') {
                loadContent(backUrl);
            } else {
                window.location.href = backUrl;
            }
        });
    });

  // ── 1) Status toggle (Activate / Inactivate) ──────────────────────────
  document.body.addEventListener('click', e => {
    const btn = e.target.closest('.status-btn');
    if (!btn) return;
    e.preventDefault();

    // SweetAlert password prompt + confirmation
    Swal.fire({
      title: btn.textContent.trim() + ' this enforcer?',
      input: 'password',
      inputLabel: 'Enter your admin password',
      inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      preConfirm: pwd => {
        if (!pwd) Swal.showValidationMessage('Password is required');
        return pwd;
      }
    }).then(result => {
      if (!result.isConfirmed) return;

      // attach admin_password to the form and submit
      const form = btn.closest('form');
      let input = form.querySelector('input[name="admin_password"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'admin_password';
        form.appendChild(input);
      }
      input.value = result.value;
      form.submit();
    });
  });




  // ── 3) Edit-Enforcer modal population & submission ──────────────────────
  document.addEventListener('show.bs.modal', event => {
    if (event.target.id !== 'editModal') return;
    const btn  = event.relatedTarget;
    const form = document.getElementById('editEnforcerForm');
    if (!btn || !form) return;

    form.action = `/enforcer/${btn.dataset.id}`;   // make sure this matches your route

    const map = { badge:'badge_num', fname:'fname', mname:'mname', lname:'lname', phone:'phone' };
    Object.entries(map).forEach(([k,f]) => {
      const val = btn.getAttribute(`data-${k}`) || '';
      const input = form.querySelector(`#edit_${f}`) || form.querySelector(`[name="${f}"]`);
      if (input) input.value = val;
    });

    window.generatePassword = inputId => {
      const rnd = Math.floor(100 + Math.random()*900);
      const el  = document.getElementById(inputId);
      if (el) el.value = `posoenforcer_${rnd}`;
    };
  });

  document.body.addEventListener('submit', async e => {
    const form = e.target;
    if (form.id !== 'editEnforcerForm') return;
    e.preventDefault();

    const modalEl = document.getElementById('editModal');
    const instance = bootstrap.Modal.getInstance(modalEl);

    // clear previous errors
    form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(fb => fb.remove());

    try {
      const resp = await fetch(form.action, {
        method: 'POST',
        headers: {
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: new FormData(form)
      });
      const json = await resp.json();

      const title = json.raw_password
        ? `New password: ${json.raw_password}`
        : 'Updated successfully';

      await Swal.fire({ toast:true, position:'top-end', icon:'success', title, showConfirmButton:false, timer:3000 });
    } catch (err) {
      if (err instanceof Object && err.errors) {
        for (let [field, msgs] of Object.entries(err.errors)) {
          const input = form.querySelector(`[name="${field}"]`);
          if (!input) continue;
          input.classList.add('is-invalid');
          let fb = document.createElement('div');
          fb.classList.add('invalid-feedback');
          fb.textContent = msgs[0];
          input.after(fb);
        }
      } else {
        Swal.fire({ icon:'error', title:'Update failed', text: err.message||err });
      }
    }

    instance.hide();
    modalEl.addEventListener('hidden.bs.modal', () => {
      instance.dispose();
      document.querySelectorAll('.modal-backdrop').forEach(x=>x.remove());
      if (typeof loadContent === 'function') loadContent(window.location.pathname);
      else window.location.reload();
    }, { once:true });
  });


  // ── 4) SPA navigation + table reload (search/sort/pagination) ──────────
  (function($){
    const container      = $('#enforcerContainer');
    const tableContainer = $('#table-container');
    const partialRoute   = '/enforcer/partial';

    // — any <a data-ajax> becomes an AJAX page swap
    $(document).on('click','a[data-ajax]', function(e){
      e.preventDefault();
      const url = $(this).attr('href');
      $.get(url, html => {
        container.html(html);
        history.pushState(null,'',url);
      });
    });

    // — popstate (Back/Forward buttons)
    window.addEventListener('popstate', () => {
      const url = location.pathname + location.search;
      $.get(url, html => container.html(html));
    });
    history.replaceState(null,'',location.pathname+location.search);

    // — form submit for Create
    $(document).on('submit','#enforcerForm', function(e){
      e.preventDefault();
      const $f = $(this);
      $.post($f.attr('action'), $f.serialize(), tableHtml => {
        Swal.fire({
          icon: 'success',
          title: 'Enforcer added!',
          text: 'Add another?',
          showCancelButton: true,
          confirmButtonText: 'Yes',
          cancelButtonText: 'No'
        }).then(res => {
          if (res.isConfirmed) $(`a[href$="/enforcer/create"]`).click();
          else {
            tableContainer.html(tableHtml);
            history.pushState(null,'','/enforcer');
          }
        });
      }).fail(xhr => {
        const errs = (xhr.status===422 && xhr.responseJSON.errors)
                   ? xhr.responseJSON.errors
                   : { general:['Something went wrong'] };
        let list = '<ul class="text-start">';
        $.each(errs,(k,v)=> list+=`<li>${v[0]||v}</li>`);
        list += '</ul>';
        Swal.fire({ icon:'error', title:'Error', html:list });
      });
    });

    // — table loader
    window.loadTable = (page='1', push=true) => {
      const sort   = $('#sort_table').val()     || 'date_desc';
      const search = ($('#search_input').val()||'').trim();
      $.ajax({
        url: partialRoute,
        data: { sort_option: sort, search, page },
        headers: { 'X-Requested-With':'XMLHttpRequest' },
        success(html) {
          tableContainer.html(html);
          if (push) {
            const p = new URLSearchParams();
            if (sort!=='date_desc') p.set('sort_option', sort);
            if (search!=='')       p.set('search', search);
            if (page!=='1')        p.set('page', page);
            history.pushState(null,'',`${window.location.pathname}?${p}`);
          }
        },
        error(err) { console.error('Enforcer table load error', err); }
      });
    };

    // — initial load & controls
    $(function(){
      const params = new URLSearchParams(window.location.search);
      $('#sort_table').val(params.get('sort_option')||'date_desc');
      $('#search_input').val(params.get('search')     ||'');
      loadTable(params.get('page')||'1', false);
    });
    $(document).on('change','#sort_table', () => loadTable('1'));
    $(document).on('click','#search_btn', () => loadTable('1'));
    $(document).on('keypress','#search_input', e => {
      if (e.which===13) { e.preventDefault(); loadTable('1'); }
    });
    $(document).on('click','#table-container .pagination a', function(e){
      e.preventDefault();
      const pg = new URL(this.href).searchParams.get('page')||'1';
      loadTable(pg);
    });

  })(jQuery);

})();
