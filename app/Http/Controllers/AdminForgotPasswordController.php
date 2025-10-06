<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\EmailOtp;
use App\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminForgotPasswordController extends Controller
{
    //
    public function showRequest()
    {
        return view('admin.password.forgot');
    }
    // POST /admin/password/forgot
    public function submitEmail(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:191']);

        // Generic success either way (privacy)
        $generic = redirect()
            ->route('admin.password.forgot.request')
            ->with('status', 'If that email exists, a reset link has been sent.');

        $admin = Admin::where('email', $data['email'])->first();
        if (!$admin) return $generic;

        // Create broker token for admins
        $token = app('auth.password.broker')->getRepository()->create($admin);

        // Build reset URL (absolute)
        $resetUrl = route('admin.password.reset.view', [
            'token' => $token,
            'email' => $admin->email,
        ], true);
        // Send via Brevo (EmailOtp helper): (subject=null, appName=config('app.name'))
        EmailOtp::sendResetLink($admin->email, $resetUrl, null, config('app.name'));

        return $generic;
    }

    // GET /admin/password/reset
    public function showReset(Request $request)
    {
        // expects ?token=...&email=...
        if (!$request->has(['token','email'])) {
            return redirect()->route('admin.password.forgot.request')
                ->withErrors(['email' => 'Missing reset token or email.']);
        }
        return view('admin.password.reset', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }

    // POST /admin/password/reset
    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($admin) use ($request) {
                $admin->forceFill([
                    'password'       => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('admin.showLogin')->with('status', 'Password updated. Please log in.');
        }

        throw ValidationException::withMessages(['email' => [__($status)]]);
    }
}
