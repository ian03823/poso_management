<?php

namespace App\Http\Controllers;

use app\Models\Violator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        try {
            /** @var \App\Models\Violator $user */
            $user = auth('violator')->user();

            $data = $request->validate([
                'phone_number' => [
                    'required','string','max:32',
                    \Illuminate\Validation\Rule::unique('violator','phone_number')->ignore($user->violator_id,'violator_id'),
                    'regex:/^(\+?63|0)9\d{9}$/',
                ],
            ], [
                'phone_number.regex' => 'Enter a valid PH mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).'
            ]);

            $user->phone_number = $data['phone_number'];
            $user->phone_verified_at = null;
            $user->save();

            $res = $this->issueOtp($user, true);
            if (!$res['ok']) {
                return back()->withErrors(['phone_number' => $res['message']])->withInput();
            }

            return back()->with('status', 'OTP sent to your phone.');
        } catch (\Throwable $e) {
            Log::error('submitPhone failed', [
                'err' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['phone_number' => 'Server error while sending OTP. Please try again in a minute.']);
        }
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

    private function issueOtp(Violator $user, bool $respectThrottle = false): array
    {
        try {
            // Rate limit sends: 3 per 10 minutes
            if ($respectThrottle) {
                $sendKey = "violator:{$user->violator_id}:otp_sends";
                if (RateLimiter::tooManyAttempts($sendKey, 3)) {
                    return ['ok' => false, 'message' => 'You requested too many OTPs. Try again later.'];
                }
                RateLimiter::hit($sendKey, 600);
            }

            $otp = random_int(100000, 999999);
            $hash = Hash::make((string)$otp);

            Cache::put(
                "violator:{$user->violator_id}:otp",
                ['hash' => $hash, 'expires_at' => now()->addMinutes(10)],
                now()->addMinutes(10)
            );

            // === SMS send hook ===
            if (app()->environment('production')) {
                // TODO: call your SMS provider here; if it throws, catch below
                Log::info("OTP generated for violator_id={$user->violator_id} (sending via SMS provider).");
            } else {
                Log::debug("[DEV] OTP for violator {$user->violator_id}: {$otp}");
                session()->flash('dev_otp', $otp);
            }

            return ['ok' => true];
        } catch (\Throwable $e) {
            Log::error('OTP send failed', ['err' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'Failed to send OTP. Please try again shortly.'];
        }
    }
}
