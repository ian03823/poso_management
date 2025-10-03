<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class EmailOtp
{
    /**
     * Issue an OTP and email it via Brevo API.
     *
     * @param string $purpose  e.g. 'email_confirm' | 'pwd_reset'
     * @param int|string $subjectId the user id
     * @param string $toEmail
     * @param string $title
     * @return bool
     */
    public static function issue(string $purpose, $subjectId, string $toEmail, string $title = 'Your verification code'): bool
    {
        // 1) generate & store
        $code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMin = (int) env('BREVO_OTP_TTL_MINUTES', 10);
        $key    = self::key($purpose, $subjectId);

        Cache::put($key, [
            'code' => $code,
            'tries'=> 0,
        ], now()->addMinutes($ttlMin));

        // 2) email via Brevo API (HTTPS)
        $payload = [
            'sender'      => [
                'email' => config('services.brevo.from_email'),
                'name'  => config('services.brevo.from_name', config('app.name')),
            ],
            'to'          => [['email' => $toEmail]],
            'subject'     => $title,
            'htmlContent' => self::htmlTemplate($title, $code, $ttlMin),
            'tags'        => [$purpose, 'otp'],
        ];

        try {
            Http::withHeaders([
                'api-key'      => config('services.brevo.key'),
                'content-type' => 'application/json',
                'accept'       => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', $payload)->throw();

            return true;
        } catch (\Throwable $e) {
            // Optional: log $e->getMessage()
            return false;
        }
    }

    /**
     * Verify an OTP; returns true if valid and consumes the code.
     */
    public static function verify(string $purpose, $subjectId, string $code): bool
    {
        $key = self::key($purpose, $subjectId);
        $data = Cache::get($key);
        if (!$data || !isset($data['code'])) {
            return false;
        }

        // (optional) restrict wrong attempts
        $maxTries = 5;
        if (($data['tries'] ?? 0) >= $maxTries) {
            Cache::forget($key);
            return false;
        }

        if (hash_equals($data['code'], $code)) {
            Cache::forget($key); // consume on success
            return true;
        }

        // increment tries and keep TTL
        $data['tries'] = (int) ($data['tries'] ?? 0) + 1;
        $ttl = self::remainingTtl($key);
        if ($ttl > 0) {
            Cache::put($key, $data, now()->addSeconds($ttl));
        } else {
            Cache::forget($key);
        }
        return false;
    }

    /* ---------------- helpers ---------------- */

    protected static function key(string $purpose, $subjectId): string
    {
        return "otp:{$purpose}:{$subjectId}";
    }

    protected static function remainingTtl(string $key): int
    {
        // Cache doesn't expose TTL directly; re-store is simplest.
        // Here we just return a small default to keep it simple.
        return 120; // seconds fallback; adequate for incrementing tries
    }

    protected static function htmlTemplate(string $title, string $code, int $ttlMin): string
    {
        $app = e(config('app.name'));
        return <<<HTML
<!doctype html><html><body style="font-family:Arial,sans-serif">
  <h2>{$title}</h2>
  <p>Your {$app} verification code is:</p>
  <p style="font-size:28px;letter-spacing:6px;margin:16px 0;"><strong>{$code}</strong></p>
  <p>This code expires in <strong>{$ttlMin} minute(s)</strong>.</p>
  <p>If you didn’t request this, you can ignore this email.</p>
</body></html>
HTML;
    }
}
