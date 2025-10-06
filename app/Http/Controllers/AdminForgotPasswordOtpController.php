<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Admin;
use App\Services\EmailOtp;

class AdminForgotPasswordOtpController extends Controller
{
    // Step 1: ask for email
    public function showRequest() {
        return view('admin.password.request'); // blades listed below
    }

    public function submitEmail(Request $request)
    {
        $data = $request->validate(['email'=>'required|email|max:191']);
        $admin = Admin::where('email',$data['email'])->first();

        // privacy: don't reveal if email exists
        if (!$admin) {
            return redirect()->route('admin.password.forgot.request')
                   ->with('status','If that email exists, you can now confirm your identity.');
        }

        session(['adm_pwd_reset_id' => $admin->id]);

        return redirect()->route('admin.password.forgot.confirm');
    }

    // Step 2: show who we found (non-sensitive summary)
    public function showConfirm(Request $request)
    {
        $aid = session('adm_pwd_reset_id');
        if (!$aid) return redirect()->route('admin.password.forgot.request');

        $a = Admin::find($aid);
        if (!$a) return redirect()->route('admin.password.forgot.request');

        return view('admin.password.confirm', [
            'name'  => $a->name,
            'email' => $a->email,
        ]);
    }

    // Step 3: send OTP
    public function sendOtp(Request $request)
    {
        $aid = session('adm_pwd_reset_id');
        if (!$aid) return redirect()->route('admin.password.forgot.request');

        $a = Admin::find($aid);
        if (!$a || !$a->email) {
            return redirect()->route('admin.password.forgot.request')->withErrors(['email'=>'No email on file.']);
        }

        // throttle 3 times per 10 minutes
        $sendKey = "admin:{$a->id}:pwd_send";
        if (RateLimiter::tooManyAttempts($sendKey, 3)) {
            return back()->withErrors(['otp'=>'Too many OTP requests. Try later.']);
        }
        RateLimiter::hit($sendKey, 600);

        if (!EmailOtp::issue('admin_pwd_reset', $a->id, $a->email, 'Admin Password Reset Code')) {
            return back()->withErrors(['otp'=>'Failed to send email. Try later.']);
        }

        return redirect()->route('admin.password.forgot.otp')->with('status','We emailed a 6-digit code.');
    }

    // Step 4: enter OTP
    public function showEnterOtp(Request $request)
    {
        if (!session('adm_pwd_reset_id')) return redirect()->route('admin.password.forgot.request');
        return view('admin.password.otp');
    }

    public function verifyOtp(Request $request)
    {
        $aid = session('adm_pwd_reset_id');
        if (!$aid) return redirect()->route('admin.password.forgot.request');

        $data = $request->validate(['otp'=>'required|digits:6']);

        $attemptKey = "admin:{$aid}:pwd_attempts";
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return back()->withErrors(['otp'=>'Too many attempts. Try later.']);
        }

        if (!EmailOtp::verify('admin_pwd_reset', $aid, $data['otp'])) {
            RateLimiter::hit($attemptKey, 900); // 15m block after too many wrong tries
            return back()->withErrors(['otp'=>'Invalid/expired code.']);
        }
        RateLimiter::clear($attemptKey);

        session(['adm_pwd_reset_verified' => true]);

        return redirect()->route('admin.password.forgot.reset');
    }

    // Step 5: set new password
    public function showReset(Request $request)
    {
        if (!session('adm_pwd_reset_id') || !session('adm_pwd_reset_verified')) {
            return redirect()->route('admin.password.forgot.request');
        }
        return view('admin.password.reset');
    }

    public function reset(Request $request)
    {
        $aid = session('adm_pwd_reset_id');
        if (!$aid || !session('adm_pwd_reset_verified')) {
            return redirect()->route('admin.password.forgot.request');
        }

        $data = $request->validate([
            'password' => ['required','string','min:8','confirmed'],
        ]);

        $a = Admin::find($aid);
        if (!$a) {
            return redirect()->route('admin.password.forgot.request')->withErrors(['email'=>'Account not found.']);
        }

        $a->password = Hash::make($data['password']);
        $a->save();

        // cleanup
        session()->forget(['adm_pwd_reset_id','adm_pwd_reset_verified']);

        return redirect()->route('admin.showLogin')->with('status','Password updated. Please log in.');
    }
}
