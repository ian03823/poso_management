<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Violator;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\EmailOtp;

class ViolatorEmailController extends Controller
{
    //
    public function showForm(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();
        return view('violator.email', [
            'email' => $user->email,
            'isVerified' => (bool)$user->email_verified_at,
        ]);
    }

    public function save(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        $data = $request->validate([
            'email' => ['required','email','max:191', Rule::unique('violators','email')->ignore($user->id,'id')],
        ]);

        $user->email = $data['email'];
        $user->email_verified_at = null; // require fresh confirm if they change it
        $user->save();

        // send OTP (throttle 3/10m)
        $sendKey = "violator:{$user->id}:email_send";
        if (RateLimiter::tooManyAttempts($sendKey, 3)) {
            return back()->withErrors(['email'=>'Too many OTP requests. Try again later.']);
        }
        RateLimiter::hit($sendKey, 600);

        if (!EmailOtp::issue('email_confirm', $user->id, $user->email, 'Confirm your email')) {
            return back()->withErrors(['email'=>'Failed to send email. Try later.']);
        }

        return back()->with('status','We sent a 6-digit code to your email.');
    }
    public function resend(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();
        if (!$user->email) return back()->withErrors(['email'=>'Add an email first.']);

        $sendKey = "violator:{$user->id}:email_send";
        if (RateLimiter::tooManyAttempts($sendKey, 3)) {
            return back()->withErrors(['email'=>'Too many OTP requests. Try again later.']);
        }
        RateLimiter::hit($sendKey, 600);

        if (!EmailOtp::issue('email_confirm', $user->id, $user->email, 'Confirm your email')) {
            return back()->withErrors(['email'=>'Failed to send email. Try later.']);
        }
        return back()->with('status','New code sent.');
    }

    public function verify(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        $data = $request->validate(['otp'=>'required|digits:6']);
        $attemptKey = "violator:{$user->id}:email_otp_attempts";
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return back()->withErrors(['otp'=>'Too many attempts, try later.']);
        }

        if (!EmailOtp::verify('email_confirm', $user->id, $data['otp'])) {
            RateLimiter::hit($attemptKey, 900);
            return back()->withErrors(['otp'=>'Invalid/expired code.']);
        }
        RateLimiter::clear($attemptKey);

        $user->email_verified_at = now();
        $user->save();

        return back()->with('ok','Email confirmed.');
    }
}
