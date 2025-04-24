<div class="container-fluid py-4">
    <!-- Back Button -->
    <h2 class="mb-3">Add Enforcer</h2>

    <div class="mb-4">
        <button type="button"
                class="btn btn-outline-secondary"
                id="previousBtn"
                data-back="{{ url('/enforcer') }}">
                <i class="bi bi-arrow-left"></i> Back
        </button>
    </div>
  
    <!-- Enforcer Form Card -->
    <div class="card mx-auto shadow" style="max-width: 800px;">
  
      <form action="{{ url('/enforcer') }}" method="POST" id="enforcerForm">
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
              <label for="badge_num" class="form-label">Badge No.</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                <input type="text" id="badge_num" name="badge_num" class="form-control" value="{{ old('badge_num') }}" required>
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="fname" class="form-label">First Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" id="fname" name="fname" class="form-control" value="{{ old('fname') }}" required>
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="mname" class="form-label">Middle Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" id="mname" name="mname" class="form-control" value="{{ old('mname') }}">
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="lname" class="form-label">Last Name</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" id="lname" name="lname" class="form-control" value="{{ old('lname') }}" required>
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="phone" class="form-label">Phone</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                <input type="tel" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" pattern="\d{11}" required>
              </div>
            </div>
  
            <div class="col-md-6">
              <label for="password" class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                <input type="text" id="password" name="password" class="form-control" value="{{ old('password') }}" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">Generate
                    </button>
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer bg-white text-end">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Add Enforcer
          </button>
        </div>
      </form>

      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="{{ asset('js/sweetalert.js') }}"></script>
        <!-- AJAX navigation loader -->
        <script src="{{ asset('js/ajax.js') }}"></script>
    </div>
  </div>
