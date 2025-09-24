<?php

namespace App\Http\Controllers;

use App\Models\Violator;
use App\Services\EmailOtp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class ViolatorEmailController extends Controller
{
    public function showForm(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        return view('violator.email', [
            'email'       => $user->email,
            'isVerified'  => (bool) $user->email_verified_at,
        ]);
    }

    public function save(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        // ── 1) validate (soft; no DNS) ───────────────────────────────────────────
        $data = $request->validate([
            'email' => [
                'required', 'email', 'max:30',
                // allow same email for this user; enforce DB-level unique otherwise
                Rule::unique('violators', 'email')->ignore($user->id, 'id'),
            ],
        ]);

        // ── 2) persist safely (handle DB unique race) ───────────────────────────
        $user->email = $data['email'];
        $user->email_verified_at = null; // require fresh confirm when changed
        try {
            $user->save();
        } catch (QueryException $e) {
            // 23000 = duplicate key (unique index)
            Log::error('violator.email duplicate', ['err' => $e->getMessage()]);
            return back()->withErrors(['email' => 'That email is already in use. Try another.'])->withInput();
        } catch (\Throwable $e) {
            Log::error('violator.email save failed', ['err' => $e->getMessage()]);
            return back()->withErrors(['email' => 'Could not save email. Please try again.'])->withInput();
        }

        // ── 3) send OTP via Gmail WebApp (or your configured driver) ───────────
        try {
            $ok = EmailOtp::issue('email_confirm', $user->id, $user->email, 'Confirm your email');
            if (!$ok) {
                return back()->withErrors(['email' => 'Failed to send email. Try again later.']);
            }
        } catch (\Throwable $e) {
            Log::error('email otp send failed', ['err' => $e->getMessage()]);
            return back()->withErrors(['email' => 'Failed to send email. Try again later.']);
        }

        return back()->with('status', 'We sent a 6-digit code to your email.');
    }

    public function resend(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        if (!$user->email) {
            return back()->withErrors(['email' => 'Add an email first.'])->withInput();
        }

        $ok = EmailOtp::issue('email_confirm', $user->id, $user->email, 'Confirm your email');
        if (!$ok) {
            return back()->withErrors(['email' => 'Failed to send email. Try again later.']);
        }

        return back()->with('status', 'New code sent.');
    }

    public function verify(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        $data = $request->validate(['otp' => 'required|digits:6']);

        if (!EmailOtp::verify('email_confirm', $user->id, $data['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired code.']);
        }

        $user->email_verified_at = now();
        $user->save();

        return back()->with('ok', 'Email confirmed.');
    }
}
