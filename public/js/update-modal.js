document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.edit-btn');

    editButtons.forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();

            const id = this.dataset.id;
            document.getElementById('edit_badge_num').value = this.dataset.badge;
            document.getElementById('edit_fname').value = this.dataset.fname;
            document.getElementById('edit_mname').value = this.dataset.mname;
            document.getElementById('edit_lname').value = this.dataset.lname;
            document.getElementById('edit_phone').value = this.dataset.phone;

            document.getElementById('editEnforcerForm').action = `/enforcer/${id}`;
        });
    });

    document.getElementById('editEnforcerForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const url = this.action;
        const formData = new FormData(this);

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) throw response;
            return response.json();
        })
        .then(() => {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

            Swal.fire({
                title: 'Updated!',
                text: 'Enforcer updated successfully.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });

            fetch('/enforcer/partial')
              .then(res => res.text())
              .then(html => {
                  document.querySelector('#enforcer-content').innerHTML = html;
              });
        })
        .catch(() => {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to update. Please try again.',
                icon: 'error'
            });
        });
    });
});
