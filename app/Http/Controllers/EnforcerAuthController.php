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
    public function login(Request $request)
    {
        $credentials = $request->validate([
            "badge_num" => "required|string",
            "password" => "required|string"
        ]);
        // 1) Find by badge_num (or whatever you use)
        $user = Enforcer::withTrashed()
            ->where('badge_num', $credentials['badge_num'])
            ->first();

        if ($user && method_exists($user,'trashed') && $user->trashed()) {
            return back()->withErrors([
                'badge_num' => 'Your account is deactivated. Please contact the Admin or go to Admin Office.',
            ])->withInput();
        }

        // 3) Normal auth attempt (Eloquent provider won’t return soft-deleted anyway)
        if (Auth::guard('enforcer')->attempt([
            'badge_num' => $credentials['badge_num'],
            'password'  => $credentials['password'],
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/enforcerTicket'); // or your dashboard route
        }

        return back()->withErrors([
            'badge_num' => 'Invalid badge number or password.',
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
