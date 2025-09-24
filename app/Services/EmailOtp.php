<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\OtpMail;

class EmailOtp
{
    public static function issue(string $purposeKey, int $violatorId, string $email, string $subject = 'Verification Code'): bool
    {
        try {
            $otp = random_int(100000, 999999);
            $hash = \Illuminate\Support\Facades\Hash::make((string)$otp);

            $cacheKey = "violator:{$violatorId}:{$purposeKey}:otp";
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'hash' => $hash,
                'expires_at' => now()->addMinutes(10)
            ], now()->addMinutes(10));

            $driver = config('otp.driver', 'log');

            if ($driver === 'gmail_webapp') {
                $url    = config('otp.gmail_webapp.url');
                $secret = config('otp.gmail_webapp.secret');
                if (!$url || !$secret) {
                    Log::error('gmail_webapp missing url/secret');
                    return false;
                }
                $html = view('emails.otp', ['otp' => (string)$otp])->render();
                $res = \Illuminate\Support\Facades\Http::timeout(10)->asJson()->post($url, [
                    'secret'  => $secret,
                    'to'      => $email,
                    'subject' => $subject,
                    'html'    => $html,
                    'text'    => "Your verification code is {$otp}. It expires in 10 minutes."
                ]);
                if (!$res->ok() || trim((string)$res->body()) !== 'OK') {
                    Log::error('gmail_webapp send failed', ['status'=>$res->status(), 'body'=>$res->body()]);
                    return false;
                }
                return true;
            }

            // Fallbacks (existing code)
            // Use normal Laravel Mail if you later fix SMTP, or 'log' while testing
            Mail::to($email)->send(new \App\Mail\OtpMail($subject, (string)$otp));
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
