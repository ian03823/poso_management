<?php

namespace App\Http\Controllers;

use app\Models\Violator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class ViolatorPhoneController extends Controller
{
    //
    public function showPrompt(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();
        return view('violator.phone', [
            'hasPhone' => filled($user->phone_number),
            'isVerified' => filled($user->phone_verified_at),
            'phone' => $user->phone_number
        ]);
    }

    public function submitPhone(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        $data = $request->validate([
            'phone_number' => [
                'required','string','max:32',
                // unique across violator table except self
                Rule::unique('violator','phone_number')->ignore($user->violator_id,'violator_id'),
                // basic PH format example (optional): starts with +63 or 09
                'regex:/^(\+?63|0)9\d{9}$/'
            ],
        ], [
            'phone_number.regex' => 'Enter a valid PH mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).'
        ]);

        $user->phone_number = $data['phone_number'];
        $user->phone_verified_at = null; // force verification if changed
        $user->save();

        $this->issueOtp($user);

        return back()->with('status','OTP sent to your phone.');
    }

    public function resendOtp(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        $this->issueOtp($user, true);
        return back()->with('status','New OTP sent.');
    }

    public function verifyOtp(Request $request)
    {
        /** @var Violator $user */
        $user = auth('violator')->user();

        $request->validate(['otp' => 'required|digits:6']);

        $key = "violator:{$user->violator_id}:otp";
        $attemptKey = "violator:{$user->violator_id}:otp_attempts";

        // Throttle attempts: 5 per 15 minutes
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            return back()->withErrors(['otp' => 'Too many attempts. Please try again later.']);
        }

        $payload = Cache::get($key);
        if (!$payload) {
            RateLimiter::hit($attemptKey, 900);
            return back()->withErrors(['otp' => 'OTP expired. Please resend.']);
        }

        // time check
        if (now()->greaterThan($payload['expires_at'])) {
            Cache::forget($key);
            RateLimiter::hit($attemptKey, 900);
            return back()->withErrors(['otp' => 'OTP expired. Please resend.']);
        }

        if (!Hash::check($request->otp, $payload['hash'])) {
            RateLimiter::hit($attemptKey, 900);
            return back()->withErrors(['otp' => 'Invalid code.']);
        }

        // success
        Cache::forget($key);
        RateLimiter::clear($attemptKey);

        $user->phone_verified_at = now();
        $user->save();

        return redirect()->route('violator.dashboard')->with('ok','Phone verified!');
    }

    private function issueOtp(Violator $user, bool $resend = false): void
    {
        // Rate limit sends: 3 per 10 minutes
        $sendKey = "violator:{$user->violator_id}:otp_sends";
        if (RateLimiter::tooManyAttempts($sendKey, 3)) {
            abort(back()->withErrors(['phone_number' => 'You requested too many OTPs. Try again later.']));
        }
        RateLimiter::hit($sendKey, 600);

        $otp = random_int(100000, 999999);
        $hash = Hash::make((string)$otp);

        Cache::put(
            "violator:{$user->violator_id}:otp",
            ['hash' => $hash, 'expires_at' => now()->addMinutes(10)],
            now()->addMinutes(10)
        );

        // === SEND THE OTP ===
        // Hook your SMS provider here (Twilio/Vonage/Asia gateways).
        // For local/dev, we log it so you can see it safely.
        if (app()->environment('production')) {
            // TODO: integrate SMS SDK call here
            Log::info("OTP sent to {$user->phone_number} (hidden).");
        } else {
            Log::debug("[DEV] OTP for violator {$user->violator_id}: {$otp}");
            session()->flash('dev_otp', $otp);
        }
    }
}
