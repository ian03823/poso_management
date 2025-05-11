
<div class="table-responsive">
  <table class="table table-bordered table-hover table-fixed">
        <thead class="table-light">
          <tr>
            <th class="text-center">Badge </th>
            <th class="text-center">First Name</th>
            <th class="text-center">Middle Name</th>
            <th class="text-center">Last Name</th>
            <th class="text-center">Phone</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($enforcer as $e)
            <tr>
              <td>{{ $e->badge_num }}</td>
              <td>{{ $e->fname }}</td>
              <td>{{ $e->mname }}</td>
              <td>{{ $e->lname }}</td>
              <td>{{ $e->phone }}</td>
              <td class="text-center">  
                <a href="#"
                  class="btn btn-warning btn-sm edit-btn"
                  data-bs-toggle="modal"
                  data-bs-target="#editModal"
                  data-id="{{ $e->id }}"
                  data-badge="{{ $e->badge_num }}"
                  data-fname="{{ $e->fname }}"
                  data-mname="{{ $e->mname }}"
                  data-lname="{{ $e->lname }}"
                  data-phone="{{ $e->phone }}">
                  Edit
                </a>
                <form action="{{ url("enforcer/{$e->id}") }}" method="POST" class="d-inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-sm delete-btn" data-name="{{ $e->fname }} Enforcer">
                    Delete
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center">No enforcers found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>

  <div class="mt-3 justify-content-center d-flex position-sticky">
      {{ $enforcer->links() }}
  </div>
</div>