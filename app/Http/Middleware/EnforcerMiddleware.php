<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnforcerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('enforcer')->check()) {
            // Redirect to the enforcer login page if not authenticated
            return $next($request);
           
        }

        // Proceed with the request if authenticated
        return redirect()->route('enforcer.showLogin')->with('error', 'You must be logged in to access this page.');
    }
}
