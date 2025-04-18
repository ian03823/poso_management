<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            "username" => "required|string",
            "password" => "required|string"
        ]);

        if (Auth::guard('violator')->attempt($validated)) {
            $request->session()->regenerate();  // Regenerate session
            return redirect()->route('violator.dashboard'); // Redirect to admin dashboard
        }
        throw ValidationException::withMessages([
            'credentials'=> 'Incorrect password or username.'
        ]);
    }
    public function logout(Request $request)
    {
        Auth::guard('violator')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return redirect()->route('violator.showLogin');
    }



}
