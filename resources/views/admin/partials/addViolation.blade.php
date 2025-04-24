<div class="container-fluid py-4">
    <!-- Back Button -->
    <h2 class="mb-3">Add Violation</h2>
    <div class="mb-4">
        <button type="button"
                class="btn btn-outline-secondary"
                id="previousBtn"
                data-back="{{ url('/violation') }}">
                <i class="bi bi-arrow-left"></i> Back
        </button>
    </div>
  
    <!-- Violation Form Card -->
    <div class="card mx-auto shadow" style="max-width: 800px;">
  
      <form action="{{ url('/violation') }}" method="POST" id="enforcerForm">
        @csrf
        <div class="card-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
  
          <div class="row g-3">
            <div class="col-md-6">
              <label for="violation_code" class="form-label">Violation Code</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                <input type="text" id="violation_code" name="violation_code" class="form-control" value="{{ old('violation_code') }}">
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="violation_name" class="form-label">Violation Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" id="violation_name" name="violation_name" class="form-control" value="{{ old('violation_name') }}">
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="fine_amount" class="form-label">Fine Amount</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="number" id="fine_amount" name="fine_amount" step="0.01" class="form-control" value="{{ old('fine_amount') }}">
              </div>
            </div>
  
  
            <div class="col-md-6">
              <label for="description" class="form-label">Description</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                <textarea class="form-control" name="description" id="description" rows="2"></textarea>
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="category" class="form-label">Category</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>

                    <select class="form-select" name="category" id="category">
                        <option value="" disabled selected>Choose category…</option>
                        <option value="Moving Violations">Moving Violations</option>
                        <option value="Non-Moving Violations">Non-Moving Violations</option>
                        <option value="Safety Violations">Safety Violations</option>
                        <option value="Parking Violations">Parking Violations</option>
                    </select>
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer bg-white text-end">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Add Violation
          </button>
        </div>
      </form>

      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="{{ asset('js/sweetalert.js') }}"></script>
        <!-- AJAX navigation loader -->
        <script src="{{ asset('js/ajax.js') }}"></script>
    </div>
  </div>
