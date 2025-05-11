<div id="table-container" class="table-responsive">
<table class="table table-bordered table-hover ">
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
            <a href=""
                class="btn btn-warning btn-sm edit-btn"
                data-bs-toggle="modal"
                data-bs-target="#editModal"
                data-id="{{ $violations->id }}"
                data-violation_code="{{ $violations->violation_code }}"
                data-violation_name="{{ $violations->violation_name }}"
                data-fine_amount="{{ $violations->fine_amount }}"
                data-category="{{ $violations->category }}">
                Edit
              </a>

            <form action="violation/{{ $violations->id }}" method="POST" class="d-inline">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-danger btn-sm delete-btn" data-name="{{ $violations->violation_name }} Violation">
                Delete</button>
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
    {{ $violation->links() }}
  </div>
</div>