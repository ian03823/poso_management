<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureViolatorPhoneVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('violator')->user();
        if (!$user) return redirect()->route('violator.showLogin');

        $needsPhone = empty($user->phone_number);
        $needsVerify = !$user->phone_verified_at;

        // Allow the phone/otp routes themselves to pass through
        if ($request->routeIs([
            'violator.phone.prompt',
            'violator.phone.submit',
            'violator.otp.verify',
            'violator.otp.resend',
        ])) {
            return $next($request);
        }

        if ($needsPhone || $needsVerify) {
            return redirect()->route('violator.phone.prompt');
        }

        return $next($request);
    }
}
