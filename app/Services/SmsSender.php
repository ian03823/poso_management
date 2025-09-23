<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SmsSender
{
    public static function send(string $to, string $message): bool
    {
        $driver = config('otp.driver', 'log'); // log | semaphore | twilio

        // Always normalize PH numbers to +63 format
        $to = self::normalizePH($to);

        try {
            if ($driver === 'semaphore') {
                // Semaphore PH
                $apikey = config('otp.semaphore.key');
                $sender = config('otp.semaphore.sender', 'POSO');
                $res = Http::asForm()->post('https://api.semaphore.co/api/v4/messages', [
                    'apikey'  => $apikey,
                    'number'  => $to,
                    'message' => $message,
                    'sendername' => $sender,
                ]);
                if ($res->successful()) return true;
                Log::error('Semaphore send failed', ['status' => $res->status(), 'body' => $res->body()]);
                return false;

            } elseif ($driver === 'twilio') {
                // Twilio
                $sid   = config('otp.twilio.sid');
                $token = config('otp.twilio.token');
                $from  = config('otp.twilio.from');
                $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
                $res = Http::withBasicAuth($sid, $token)->asForm()->post($url, [
                    'From' => $from,
                    'To'   => $to,
                    'Body' => $message,
                ]);
                if ($res->successful()) return true;
                Log::error('Twilio send failed', ['status' => $res->status(), 'body' => $res->body()]);
                return false;

            } else {
                // log-only fallback (good for testing on Railway)
                Log::info("[SMS LOG] to={$to} msg={$message}");
                return true;
            }
        } catch (\Throwable $e) {
            Log::error('SMS exception', ['err' => $e->getMessage()]);
            return false;
        }
    }

    private static function normalizePH(string $raw): string
    {
        $n = preg_replace('/\D+/', '', $raw ?? '');
        if (str_starts_with($n, '09')) return '+63' . substr($n, 1);
        if (str_starts_with($n, '9') && strlen($n) === 10) return '+63' . $n;
        if (str_starts_with($n, '63')) return '+' . $n;
        if (str_starts_with($n, '0'))  return '+63' . substr($n, 1);
        if (str_starts_with($n, '+'))  return $n;
        return '+63' . $n;
    }
}
