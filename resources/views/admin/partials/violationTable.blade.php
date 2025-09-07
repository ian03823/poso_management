<div id="table-container" class="table-responsive">
  <table class="table table-hover align-middle vio-table">
    <thead class="table-light">
      <tr>
        <th class="text-center">Code</th>
        <th class="text-center">Name</th>
        <th class="text-center col-md-only">Fine Amount</th>
        <th class="text-center">Category</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($violation as $v)
        <tr>
          <td class="text-center">{{ $v->violation_code }}</td>
          <td>{{ $v->violation_name }}</td>
          <td class="text-center col-md-only">₱{{ number_format($v->fine_amount, 2) }}</td>
          <td class="text-center">
            <span class="badge-cat">{{ $v->category }}</span>
          </td>
          <td class="text-center table-actions">
            <a href="#"
               class="btn btn-warning btn-sm edit-btn"
               data-bs-toggle="modal"
               data-bs-target="#editModal"
               data-id="{{ $v->id }}"
               data-url="{{ route('violation.update',$v) }}"
               data-code="{{ $v->violation_code }}"
               data-name="{{ $v->violation_name }}"
               data-fine="{{ $v->fine_amount }}"
               data-category="{{ $v->category }}">
              Edit
            </a>

            <form action="{{ route('violation.destroy', $v->id) }}" method="POST" class="d-inline">
              @csrf @method('DELETE')
              <button type="button"
                      class="btn btn-danger btn-sm archive-btn"
                      data-name="{{ $v->violation_name }}"
                      data-action="{{ route('violation.destroy', $v->id) }}">
                <i class="bi bi-archive"></i> Archive
              </button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center">No records found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="mt-3 justify-content-center d-flex position-sticky">
    {{ $violation->appends(['category'=>$categoryFilter ?? request('category'),'search'=>$search ?? request('search')])->links() }}
  </div>
</div>
