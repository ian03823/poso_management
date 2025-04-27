{{-- the entire “content” for the Enforcer page --}}
<form id="filterForm" onsubmit="return false;" class="mb-4">
    <div class="row g-2 align-items-center">
      <div class="col-auto">
        <label for="sort_option" class="col-form-label fw-semibold">
          Sort by:
        </label>
      </div>
      <div class="col-auto">
        <select name="sort_option"
                id="sort_option"
                class="form-select">
          <option value="date_desc" {{ $sortOption==='date_desc'?'selected':'' }}>
            Date Modified (Newest First)
          </option>
          <option value="date_asc"  {{ $sortOption==='date_asc' ?'selected':'' }}>
            Date Modified (Oldest First)
          </option>
          <option value="name_asc"  {{ $sortOption==='name_asc' ?'selected':'' }}>
            Name A → Z
          </option>
          <option value="name_desc" {{ $sortOption==='name_desc'?'selected':'' }}>
            Name Z → A
          </option>
        </select>
      </div>
    </div>
  </form>
  
  {{-- include the table wrapper --}}
  @include('admin.partials.enforcerTable')
  