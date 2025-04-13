<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $validated = $request->validate([
            "badge_num" => "required|string",
            "password" => "required|string"
        ]);

        if (Auth::guard('enforcer')->attempt($validated)) {
            $request->session()->regenerate();  // Regenerate session
            return redirect()->route('enforcer.dashboard'); // Redirect to admin dashboard
        }
        throw ValidationException::withMessages([
            'credentials'=> 'Incorrect password or badge number.'
        ]);
    }
    public function logout(Request $request)
    {
        Auth::guard('enforcer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return redirect()->route('enforcer.showLogin');
    }
}
