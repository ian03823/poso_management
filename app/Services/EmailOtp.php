<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class EmailOtp
{
    public static function issue(string $purposeKey, int $violatorId, string $email, string $subject = 'Verification Code'): bool
    {
        try {
            $otp = random_int(100000, 999999);
            $hash = Hash::make((string)$otp);

            $cacheKey = "violator:{$violatorId}:{$purposeKey}:otp";
            Cache::put($cacheKey, ['hash'=>$hash,'expires_at'=>now()->addMinutes(10)], now()->addMinutes(10));

            Mail::to($email)->send(new OtpMail($subject, (string)$otp));
            return true;
        } catch (\Throwable $e) {
            Log::error('Email OTP send failed', ['err'=>$e->getMessage()]);
            return false;
        }
    }

    public static function verify(string $purposeKey, int $violatorId, string $otp): bool
    {
        $cacheKey = "violator:{$violatorId}:{$purposeKey}:otp";
        $payload = Cache::get($cacheKey);
        if (!$payload || now()->greaterThan($payload['expires_at'])) {
            Cache::forget($cacheKey);
            return false;
        }
        $ok = Hash::check($otp, $payload['hash']);
        if ($ok) Cache::forget($cacheKey);
        return $ok;
    }
}
