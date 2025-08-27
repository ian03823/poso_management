<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationData;
use Illuminate\Validation\ValidationException;

class ViolatorAuthController extends Controller
{
    //
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

        // 1) Find the violator by username
        $violator = \App\Models\Violator::where('username', $validated['username'])->first();
        if (! $violator) {
            throw ValidationException::withMessages(['credentials' => 'Incorrect username or password.']);
        }

        $plain = $validated['password'];

        // 2) Check real password
        if (Hash::check($plain, $violator->password)) {
            Auth::guard('violator')->login($violator);
            $request->session()->regenerate();
            return redirect()->route('violator.dashboard')->with('login_success', true);
        }

        // 3) Check default password (first-time login)
        if ($violator->defaultPassword && Hash::check($plain, $violator->defaultPassword)) {
            Auth::guard('violator')->login($violator);
            $request->session()->regenerate();
            // Flag that the user must change password
            session()->flash('must_change_password', true);
            return redirect()->route('violator.password.change');
        }

        // 4) Fallback: invalid credentials
        throw ValidationException::withMessages(['credentials' => 'Incorrect username or password.']);
    }
    public function logout(Request $request)
    {
        Auth::guard('violator')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return redirect()->route('violator.showLogin');
    }
    /**
     * Show the change-password form if flagged.
     */
    public function showChangePasswordForm()
    {
        if (! session('must_change_password')) {
            return redirect()->route('violator.dashboard');
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
        $violator->password = Hash::make($request->password);
        $violator->defaultPassword = null;
        $violator->save();

        Auth::guard('violator')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('violator.showLogin')
                         ->with('status', 'Password changed. Please log in with your new password.');
    }


}
