// 2) Re-print (generic browser printing via receipt page)
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.reprint-ticket-btn');
  if (!btn) return;

  e.preventDefault();
  const url = btn.dataset.url; // route('ticket.receipt', $ticket)

  if (!window.Swal) {
    if (confirm('Re-print this ticket and reset the violator portal password?')) {
      window.open(url, '_blank', 'noopener');
    }
    return;
  }

  const { isConfirmed } = await Swal.fire({
    title: 'Re-print ticket and reset portal password?',
    html: `
      <div class="text-start">
        <p class="mb-1">This will:</p>
        <ul class="small mb-0">
          <li>Generate a <strong>new default password</strong> for the violator</li>
          <li>Invalidate their current portal password</li>
          <li>Open a <strong>printable copy</strong> of this ticket</li>
        </ul>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, continue',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
  });

  if (!isConfirmed) return;

  // Just open the receipt page in a new tab – that page will auto-call window.print()
  window.open(url, '_blank', 'noopener');
});
