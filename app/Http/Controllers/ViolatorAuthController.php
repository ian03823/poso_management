<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ViolatorAuthController extends Controller
{
    public function showLogin()
    {
        return view("auth.violatorLogin");
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $maxAttempts   = (int) env('AUTH_MAX_FAILED_LOGINS', 5);
        $baseLockMins  = (int) env('AUTH_LOCKOUT_MINUTES', 15);
        $useExpBackoff = filter_var(env('AUTH_LOCKOUT_EXP_BACKOFF', false), FILTER_VALIDATE_BOOL);
        $maxLockMins   = (int) env('AUTH_LOCKOUT_MAX_MINUTES', 1440);

        // Find violator (include soft-deleted to show proper message if needed)
        $violator = \App\Models\Violator::withTrashed()
            ->where('username', $validated['username'])
            ->first();

        if (! $violator) {
            throw ValidationException::withMessages([
                'credentials' => 'Incorrect username or password.',
            ]);
        }

        if (method_exists($violator, 'trashed') && $violator->trashed()) {
            throw ValidationException::withMessages([
                'credentials' => 'Your account is deactivated. Please contact the Admin.',
            ]);
        }

        // If currently locked
        if ($violator->lockout_until && now()->lt($violator->lockout_until)) {
            $remaining = now()->diffInSeconds($violator->lockout_until);
            session()->flash('lockout_remaining', $remaining);
            throw ValidationException::withMessages([
                'credentials' => 'Account temporarily locked. Try again later.',
            ]);
        }

        $plain = $validated['password'];

        /* ==========================
         * 1) REAL PASSWORD → DASHBOARD
         * ========================== */
        if (Hash::check($plain, $violator->password)) {
            // Success → reset counters/lock
            $violator->failed_attempts = 0;
            $violator->lockout_until   = null;
            $violator->lockouts_count  = 0;
            $violator->save();

            Auth::guard('violator')->login($violator);
            $request->session()->regenerate();

            // clear any forced-change flags from previous flows
            $request->session()->forget([
                'must_change_password',
                'violator_identity_pending',
                'identity_verified',
            ]);

            return redirect()->route('violator.dashboard')->with('login_success', true);
        }

        /* =====================================
         * 2) DEFAULT PASSWORD → IDENTITY PAGE
         * ===================================== */
        if ($violator->defaultPassword && Hash::check($plain, $violator->defaultPassword)) {
            // On default password success, also reset counters/lock
            $violator->failed_attempts = 0;
            $violator->lockout_until   = null;
            $violator->lockouts_count  = 0;
            $violator->save();

            Auth::guard('violator')->login($violator);
            $request->session()->regenerate();

            // flags for forced flow:
            // - must_change_password : this login must end with a new password
            // - violator_identity_pending : must do ID check first
            // - identity_verified : false (not yet)
            $request->session()->put('must_change_password', true);
            $request->session()->put('violator_identity_pending', true);
            $request->session()->put('identity_verified', false);

            // 🔐 go to identity form (name + license), NOT change password directly
            return redirect()->route('violator.identity.show');
        }

        /* ==============================
         * 3) WRONG PASSWORD → increment
         * ============================== */
        $violator->failed_attempts = (int) $violator->failed_attempts + 1;

        if ($violator->failed_attempts >= $maxAttempts) {
            $lockMinutes = $baseLockMins;
            if ($useExpBackoff) {
                $lockMinutes = min($baseLockMins * (2 ** (int) $violator->lockouts_count), $maxLockMins);
            }

            $violator->lockout_until   = now()->addMinutes($lockMinutes);
            $violator->lockouts_count  = (int) $violator->lockouts_count + 1;
            $violator->failed_attempts = 0; // reset streak after locking
            $violator->save();

            session()->flash('lockout_remaining', now()->diffInSeconds($violator->lockout_until));
            throw ValidationException::withMessages([
                'credentials' => "Too many attempts. Account locked for {$lockMinutes} minute(s).",
            ]);
        }

        $violator->save();

        throw ValidationException::withMessages([
            'credentials' => "Incorrect username or password. Attempts: {$violator->failed_attempts} / {$maxAttempts}",
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('violator')->logout();

        // clear flags
        $request->session()->forget([
            'must_change_password',
            'violator_identity_pending',
            'identity_verified',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('violator.showLogin');
    }

    /**
     * Step 2 after default-password login:
     * Show form where violator must confirm license number + full name.
     */
    public function showIdentityForm(Request $request)
    {
        $violator = Auth::guard('violator')->user();
        if (! $violator) {
            return redirect()->route('violator.showLogin');
        }

        // Only allow if this step is pending
        if (! $request->session()->get('violator_identity_pending')) {
            // If identity is not pending, just go dashboard
            return redirect()->route('violator.dashboard');
        }

        return view('auth.violatorVerifyIdentity', compact('violator'));
    }

    /**
     * Handle the identity verification (license + full name).
     */
    public function verifyIdentity(Request $request)
    {
        $violator = Auth::guard('violator')->user();
        if (! $violator) {
            return redirect()->route('violator.showLogin');
        }

        if (! $request->session()->get('violator_identity_pending')) {
            return redirect()->route('violator.dashboard');
        }

        $data = $request->validate([
            'license_number' => 'required|string',
            'full_name'      => 'required|string',
        ]);

        // Normalize license for comparison (ignore dashes/spaces, case-insensitive)
        $inputLicense  = strtoupper(str_replace([' ', '-'], '', $data['license_number']));
        $storedLicense = strtoupper(str_replace([' ', '-'], '', (string) $violator->license_number));

        // Build stored full name: "First Middle Last"
        $parts = array_filter([
            $violator->first_name ?? null,
            $violator->middle_name ?? null,
            $violator->last_name ?? null,
        ]);

        $storedFullName = strtolower(preg_replace('/\s+/', ' ', implode(' ', $parts)));
        $inputFullName  = strtolower(preg_replace('/\s+/', ' ', $data['full_name']));

        $licenseMatches = $inputLicense !== '' && $storedLicense !== '' && hash_equals($storedLicense, $inputLicense);
        $nameMatches    = $storedFullName !== '' && hash_equals($storedFullName, $inputFullName);

        if (! $licenseMatches || ! $nameMatches) {
            throw ValidationException::withMessages([
                'identity' => 'License number or full name does not match our records.',
            ]);
        }

        // ✅ Identity is correct
        $request->session()->forget('violator_identity_pending');
        $request->session()->put('identity_verified', true);

        // still in forced-change flow
        $request->session()->put('must_change_password', true);

        return redirect()
            ->route('violator.password.change')
            ->with('identity_verified', true);
    }

    /**
     * Show the change-password form.
     * - If in forced default-password flow → require identity first.
     * - If normal logged-in violator (no must_change_password) → allow voluntary change.
     */
    public function showChangePasswordForm()
    {
        $violator = Auth::guard('violator')->user();
        if (! $violator) {
            return redirect()->route('violator.showLogin');
        }

        // If this login came from default password flow,
        // enforce identity verification first.
        if (session('must_change_password')) {
            if (session('violator_identity_pending')) {
                return redirect()->route('violator.identity.show');
            }
            // identity_verified == true → ok to show form
        }

        return view('auth.violatorChangePassword');
    }

    /**
     * Handle a change-password submission.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|confirmed|min:8',
        ]);

        $violator = Auth::guard('violator')->user();
        if (! $violator) {
            return redirect()->route('violator.showLogin');
        }

        $violator->password        = Hash::make($request->password);
        $violator->defaultPassword = null; // clear default password
        $violator->save();

        // Clear forced-change flags
        $request->session()->forget([
            'must_change_password',
            'violator_identity_pending',
            'identity_verified',
        ]);

        Auth::guard('violator')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('violator.showLogin')
                         ->with('status', 'Password changed. Please log in with your new password.');
    }
}
