<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Enforcer;
use Illuminate\Validation\ValidationData;
use Illuminate\Validation\ValidationException;

class EnforcerAuthController extends Controller
{
    //
    public function showLogin()
    {
        return view("auth.enforcerLogin");
    }
    //
    public function login(Request $request)
    {
        $credentials = $request->validate([
            "badge_num" => "required|string",
            "password"  => "required|string",
        ]);

        $maxAttempts   = (int) env('AUTH_MAX_FAILED_LOGINS', 5);
        $baseLockMins  = (int) env('AUTH_LOCKOUT_MINUTES', 15);
        $useExpBackoff = filter_var(env('AUTH_LOCKOUT_EXP_BACKOFF', false), FILTER_VALIDATE_BOOL);
        $maxLockMins   = (int) env('AUTH_LOCKOUT_MAX_MINUTES', 1440);

        // Find by badge number (include soft-deleted to show proper message)
        $user = \App\Models\Enforcer::withTrashed()
            ->where('badge_num', $credentials['badge_num'])
            ->first();

        if (! $user) {
            return back()->withErrors([
                'badge_num' => 'Invalid badge number or password.',
            ])->withInput();
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            return back()->withErrors([
                'badge_num' => 'Your account is deactivated. Please contact the Admin or go to Admin Office.',
            ])->withInput();
        }

        // If currently locked, block and show remaining time
        if ($user->lockout_until && now()->lt($user->lockout_until)) {
            $remaining = now()->diffInSeconds($user->lockout_until);
            return back()
                ->withErrors(['badge_num' => 'Account temporarily locked. Try again later.'])
                ->with('lockout_remaining', $remaining)
                ->withInput();
        }

        // Check password directly against the found user
        $passwordOk = \Illuminate\Support\Facades\Hash::check($credentials['password'], $user->password);

        if ($passwordOk) {
            // Success → reset counters/lock
            $user->failed_attempts = 0;
            $user->lockout_until   = null;
            $user->lockouts_count  = 0;
            $user->save();

            \Illuminate\Support\Facades\Auth::guard('enforcer')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended('/enforcerTicket');
        }

        // Wrong password → increment and possibly lock
        $user->failed_attempts = (int) $user->failed_attempts + 1;

        if ($user->failed_attempts >= $maxAttempts) {
            $lockMinutes = $baseLockMins;
            if ($useExpBackoff) {
                $lockMinutes = min($baseLockMins * (2 ** (int) $user->lockouts_count), $maxLockMins);
            }

            $user->lockout_until   = now()->addMinutes($lockMinutes);
            $user->lockouts_count  = (int) $user->lockouts_count + 1;
            $user->failed_attempts = 0; // reset streak after locking
            $user->save();

            return back()
                ->withErrors(['badge_num' => "Too many attempts. Account locked for {$lockMinutes} minute(s)."])
                ->with('lockout_remaining', now()->diffInSeconds($user->lockout_until));
        }

        $user->save();

        return back()->withErrors([
            'badge_num' => "Invalid badge number or password. Attempts: {$user->failed_attempts} / {$maxAttempts}",
        ])->withInput();
    }
    public function logout(Request $request)
    {
        Auth::guard('enforcer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('enforcer.showLogin');
    }
     public function showChangePassword()
    {

        return view('auth.enforcerChangePassword');
    }
    public function changePassword(Request $request)
    {
        $request->validate([
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ]);

        $enforcer = Auth::guard('enforcer')->user();

        // 1) Store the new “real” password
        $enforcer->password = Hash::make($request->password);

        // 2) Clear the default_password so it no longer matches
        $enforcer->defaultPassword = null;

        $enforcer->save();

        // 3) Log them out so they must log in again
        Auth::guard('enforcer')->logout();

        return redirect()
            ->route('enforcer.showLogin')
            ->with('status', 'Password successfully changed. Please log in again.');
    }
}
