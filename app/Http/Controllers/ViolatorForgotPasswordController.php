<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Violator;
use App\Services\EmailOtp;

class ViolatorForgotPasswordController extends Controller
{
    //
    public function showRequest() {
        return view('violator.password.request');
    }

    public function submitEmail(Request $request)
    {
        $data = $request->validate(['email'=>'required|email|max:191']);
        $user = Violator::where('email',$data['email'])->first();

        if (!$user) {
            // Soft privacy masking: still proceed to a neutral page
            return redirect()->route('violator.password.forgot.request')
                   ->with('status','If that email exists, you can now confirm your identity.');
        }

        // Store candidate in session for confirm step
        session([
            'pwd_reset_vid' => $user->id,
        ]);

        return redirect()->route('violator.password.forgot.confirm');
    }

    public function showConfirm(Request $request)
    {
        $vid = session('pwd_reset_vid');
        if (!$vid) return redirect()->route('violator.password.forgot.request');

        $u = Violator::find($vid);
        if (!$u) return redirect()->route('violator.password.forgot.request');

        return view('violator.password.confirm', [
            'name' => trim(($u->first_name.' '.$u->last_name) ?: $u->name),
            'address' => $u->address,
            'email' => $u->email,
        ]);
    }

    public function sendOtp(Request $request)
    {
        $vid = session('pwd_reset_vid');
        if (!$vid) return redirect()->route('violator.password.forgot.request');

        $u = Violator::find($vid);
        if (!$u || !$u->email) {
            return redirect()->route('violator.password.forgot.request')->withErrors(['email'=>'No email on file.']);
        }

        // Throttle 3/10m
        $sendKey = "violator:{$u->id}:pwd_send";
        if (RateLimiter::tooManyAttempts($sendKey, 3)) {
            return back()->withErrors(['otp'=>'Too many OTP requests. Try later.']);
        }
        RateLimiter::hit($sendKey, 600);

        if (!EmailOtp::issue('pwd_reset', $u->id, $u->email, 'Password Reset Code')) {
            return back()->withErrors(['otp'=>'Failed to send email. Try later.']);
        }

        return redirect()->route('violator.password.forgot.otp')->with('status','We emailed a 6-digit code.');
    }

    public function showEnterOtp(Request $request)
    {
        if (!session('pwd_reset_vid')) return redirect()->route('violator.password.forgot.request');
        return view('violator.password.otp');
    }

    public function verifyOtp(Request $request)
    {
        $vid = session('pwd_reset_vid');
        if (!$vid) return redirect()->route('violator.password.forgot.request');

        $data = $request->validate(['otp'=>'required|digits:6']);
        $attemptKey = "violator:{$vid}:pwd_attempts";
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return back()->withErrors(['otp'=>'Too many attempts. Try later.']);
        }

        if (!EmailOtp::verify('pwd_reset', $vid, $data['otp'])) {
            RateLimiter::hit($attemptKey, 900);
            return back()->withErrors(['otp'=>'Invalid/expired code.']);
        }
        RateLimiter::clear($attemptKey);

        session(['pwd_reset_verified' => true]);
        return redirect()->route('violator.password.forgot.reset');
    }

    public function showReset(Request $request)
    {
        if (!session('pwd_reset_vid') || !session('pwd_reset_verified')) {
            return redirect()->route('violator.password.forgot.request');
        }
        return view('violator.password.reset');
    }

    public function reset(Request $request)
    {
        $vid = session('pwd_reset_vid');
        if (!$vid || !session('pwd_reset_verified')) {
            return redirect()->route('violator.password.forgot.request');
        }

        $data = $request->validate([
            'password' => ['required','string','min:8','confirmed'], // expects password_confirmation
        ]);

        $u = Violator::find($vid);
        if (!$u) {
            return redirect()->route('violator.password.forgot.request')->withErrors(['email'=>'Account not found.']);
        }

        $u->password = Hash::make($data['password']);
        $u->save();

        // cleanup
        session()->forget(['pwd_reset_vid','pwd_reset_verified']);

        return redirect()->route('violator.showLogin')->with('ok','Password updated. Please log in.');
    }
}
