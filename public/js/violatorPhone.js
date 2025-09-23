// public/js/violatorPhone.js
(function () {
  if (window.__violatorPhoneWired) return;
  window.__violatorPhoneWired = true;

  const $ = (sel, ctx=document) => ctx.querySelector(sel);

  const phoneForm = $('#phone-form');
  const otpForm   = $('#otp-form');

  function toast(icon, title, ms=2200){
    if (!window.Swal) return alert(title);
    Swal.fire({toast:true,icon,title,position:'top-end',timer:ms,showConfirmButton:false});
  }

  if (phoneForm) {
    phoneForm.addEventListener('submit', function (e) {
      if (!window.Swal) return; // graceful
      e.preventDefault();

      const formData = new FormData(phoneForm);
      const phone = formData.get('phone_number');

      Swal.fire({
        title: 'Save phone number?',
        html: `<div class="text-start">We will send a 6-digit OTP to <b>${phone}</b>.</div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, send OTP',
      }).then(res => {
        if (res.isConfirmed) phoneForm.submit();
      });
    });
  }

  if (otpForm) {
    otpForm.addEventListener('submit', function (e) {
      if (!window.Swal) return; // graceful
      const otpEl = otpForm.querySelector('input[name="otp"]');
      const code = (otpEl.value||'').trim();
      if (code.length !== 6) {
        e.preventDefault();
        toast('warning','Enter the 6-digit code.');
      }
    });
  }
})();
