<div class="table-responsive">
  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th class="text-center">Badge</th>
        <th class="text-center">First Name</th>
        <th class="text-center">Middle Name</th>
        <th class="text-center">Last Name</th>
        <th class="text-center">Phone</th>
        <th class="text-center">Ticket Range</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($enforcer as $e)
        <tr class="{{ $e->trashed() ? 'table-secondary' : '' }}">
          <td>{{ $e->badge_num }}</td>
          <td>{{ $e->fname }}</td>
          <td>{{ $e->mname }}</td>
          <td>{{ $e->lname }}</td>
          <td>{{ $e->phone }}</td>
          <td>{{ $e->ticket_start }} - {{ $e->ticket_end }}</td>
          <td class="text-center">
            {{-- Edit always --}}
            <a href="#"
               class="btn btn-warning btn-sm edit-btn"
               data-bs-toggle="modal"
               data-bs-target="#editModal"
               data-id="{{ $e->id }}"
               {{-- …other data-attributes… --}}
            >Edit</a>

            @if(!$e->trashed())
              {{-- Deactivate --}}
              <form action="{{ route('enforcer.destroy',$e) }}" method="POST" class="d-inline">
                @csrf @method('DELETE')
                <button type="button"
                        class="btn btn-danger btn-sm status-btn"
                        data-action="{{ route('enforcer.destroy',$e) }}"
                        data-method="DELETE">
                  Inactivate
                </button>
              </form>
            @else
              {{-- Activate --}}
              <form action="{{ route('enforcer.restore',$e) }}" method="POST" class="d-inline">
                @csrf
                <button type="button"
                        class="btn btn-success btn-sm status-btn"
                        data-action="{{ route('enforcer.restore',$e) }}"
                        data-method="POST">
                  Activate
                </button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center">No enforcers found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="d-flex justify-content-center mt-3">
    {{ $enforcer->links() }}
  </div>
</div>
