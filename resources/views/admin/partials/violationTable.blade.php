

<table class="table table-bordered">
    <thead>
      <tr>
        <th class="text-center">Code</th>
        <th class="text-center">Name</th>
        <th class="text-center">Fine Amount</th>
        <th class="text-center">Category</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($violation as $violations)
        <tr>
          <td>{{ $violations->violation_code }}</td>
          <td>{{ $violations->violation_name }}</td>
          <td>₱{{ number_format($violations->fine_amount, 2) }}</td>
          <td>{{ $violations->category }}</td>
          <td class="text-center">
            <a href="{{ route('violation.edit', $violations->id) }}"
               class="btn btn-warning btn-sm edit-btn">Edit
              </a>

            <form action="violation/{{ $violations->id }}" method="POST" class="d-inline">
              @csrf
              @method('DELETE')
              <button type="submit"
                      class="btn btn-danger btn-sm delete-btn" data-name="{{ $violations->violation_name }} Violation">Delete</button>
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

  {{-- ▶ Pagination links --}}

  <nav aria-label="Pages navigation" class="mt-3">
    {{ $violation->links('pagination::bootstrap-5')->withClass('pagination-modern justify-content-center') }}
  </nav>