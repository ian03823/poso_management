@extends('components.app')

@section('title', 'POSO Enforcer Dashboard')

@section('body')
    
  
  <!-- Main Body -->
  <div class="bg-white shadow-sm py-3">
    <div class="container position-relative">
      <div class="input-group mx-auto" style="max-width: 600px;">
        <input
          id="violator-search"
          type="text"
          class="form-control border-0"
          placeholder="Search by name, license, or plate…"
          aria-label="Search"
          autocomplete="off"
        >
        <button class="btn btn-success" type="button" id="search-clear" disabled>
          <i class="bi bi-search"></i>
        </button>
      </div>
      <p class="text-sm muted p-3">Search by name, plate number, license number.</p>

      {{-- Suggestions dropdown --}}
      <div
        id="suggestions"
        class="list-group position-absolute w-100"
        style="z-index:1000; top: 4.5rem;"
      ></div>
    </div>
  </div>

  <div class="my-5 text-center">
    <a href="/enforcerTicket/create" class="btn btn-lg btn-success rounded-pill shadow-sm">
      <i class="bi bi-ticket-perforated-fill me-2"></i> Issue a Ticket
    </a>
  </div>
  @push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
      const input        = document.getElementById('violator-search');
      const suggestions  = document.getElementById('suggestions');
      const clearBtn     = document.getElementById('search-clear');
      const suggestionsUrl = "{{ route('enforcer.violators.suggestions') }}";
      let timer;

      clearBtn.addEventListener('click', () => {
        input.value = '';
        suggestions.innerHTML = '';
        input.focus();
      });

      input.addEventListener('input', () => {
        clearTimeout(timer);
        const term = input.value.trim();
        if (term.length < 2) {
          suggestions.innerHTML = '';
          return;
        }
        timer = setTimeout(() => {
          fetch(`${suggestionsUrl}?q=${encodeURIComponent(term)}`, {
            credentials: 'same-origin'
          })
            .then(res => {
              if (!res.ok) throw new Error(res.status);
              return res.json();
            })
            .then(data => {
              suggestions.innerHTML = '';
              data.forEach(v => {
                const a = document.createElement('a');
                a.href      = `/violators/${v.id}`;
                a.className = 'list-group-item list-group-item-action';
                a.innerHTML = `<strong>${v.name}</strong><br>
                               <small class="text-muted">
                                 ${v.license_number} &middot; ${v.plate_number || '—'}
                               </small>`;
                suggestions.appendChild(a);
              });
            })
            .catch(err => {
              suggestions.innerHTML = 
                `<div class="list-group-item text-danger">
                   Error fetching results (${err.message})
                 </div>`;
            });
        }, 300);
      });

      document.addEventListener('click', e => {
        if (!suggestions.contains(e.target) && e.target !== input) {
          suggestions.innerHTML = '';
        }
      });
    });
  </script>
@endpush
  
@endsection