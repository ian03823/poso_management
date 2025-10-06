<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailOtp
{
    /**
     * Issue an OTP and email it via Brevo API.
     *
     * @param string            $purpose    e.g. 'email_confirm' | 'pwd_reset'
     * @param int|string        $subjectId  user id (violator id)
     * @param string            $toEmail
     * @param string            $title
     * @return bool
     */
    public static function issue(string $purpose, $subjectId, string $toEmail, string $title = 'Your verification code'): bool
    {
        // 1) generate & store in cache with TTL
        $code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMin = (int) env('BREVO_OTP_TTL_MINUTES', 10);
        $key    = self::key($purpose, $subjectId);

        Cache::put($key, ['code' => $code, 'tries' => 0], now()->addMinutes($ttlMin));

        // 2) build Brevo payload
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
            $res = Http::withHeaders([
                'api-key'      => config('services.brevo.key'),
                'content-type' => 'application/json',
                'accept'       => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', $payload);

            if ($res->failed()) {
                Log::error('Brevo send failed', [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                    'to'     => $toEmail,
                    'sender' => config('services.brevo.from_email'),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Brevo send exception', [
                'error'  => $e->getMessage(),
                'to'     => $toEmail,
                'sender' => config('services.brevo.from_email'),
            ]);
            return false;
        }
    }

    /**
     * Verify an OTP; returns true if valid and consumes the code.
     */
    public static function verify(string $purpose, $subjectId, string $code): bool
    {
        $key  = self::key($purpose, $subjectId);
        $data = Cache::get($key);
        if (!$data || !isset($data['code'])) {
            return false;
        }

        $maxTries = 5;
        if (($data['tries'] ?? 0) >= $maxTries) {
            Cache::forget($key);
            return false;
        }

        if (hash_equals($data['code'], $code)) {
            Cache::forget($key); // consume on success
            return true;
        }

        // wrong code → increment tries, keep TTL if any
        $data['tries'] = (int) ($data['tries'] ?? 0) + 1;
        // we cannot easily read remaining TTL from Cache; keep a short grace so repeated wrongs are limited
        Cache::put($key, $data, now()->addSeconds(120));

        return false;
    }

    /* ---------------- helpers ---------------- */

    protected static function key(string $purpose, $subjectId): string
    {
        return "otp:{$purpose}:{$subjectId}";
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
        <p>If you didn’t request this, please ignore this email.</p>
        </body></html>
        HTML;
    }
}
