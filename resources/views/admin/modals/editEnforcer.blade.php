<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editEnforcerForm" method="POST" action="">
      @csrf
      @method('PUT')

      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Edit Enforcer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label>Badge Number</label>
            <input type="text" class="form-control" name="badge_num" id="edit_badge_num" readonly>
          </div>
          <div class="mb-3">
            <label>First Name</label>
            <input type="text" class="form-control" name="fname" id="edit_fname">
          </div>
          <div class="mb-3">
            <label>Middle Name</label>
            <input type="text" class="form-control" name="mname" id="edit_mname">
          </div>
          <div class="mb-3">
            <label>Last Name</label>
            <input type="text" class="form-control" name="lname" id="edit_lname">
          </div>
          <div class="mb-3">
            <label>Phone</label>
            <input type="text" class="form-control" name="phone" id="edit_phone">
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
              <input type="text" class="form-control" name="password" id="edit_password" readonly>
              <button type="button"
                      class="btn btn-outline-secondary"
                      onclick="generatePassword('edit_password')">
                Generate
              </button>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">
            Close
          </button>
          <button type="submit" class="btn btn-success">
            Save Changes
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
