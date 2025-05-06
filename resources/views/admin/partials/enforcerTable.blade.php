
<table class="table table-bordered table-hover">
      <thead class="table-light">
        <tr>
          <th>Badge No.</th>
          <th>First Name</th>
          <th>Middle Name</th>
          <th>Last Name</th>
          <th>Phone</th>
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
            <td colspan="6" class="text-center py-4">No enforcers found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>

<div class="mt-4">
    {{ $enforcer->links('pagination::bootstrap-5') }}
</div>