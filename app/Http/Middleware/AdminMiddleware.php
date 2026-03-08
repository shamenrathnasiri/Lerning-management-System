<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Only allows users with 'admin' or 'super-admin' role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Please log in to access this page.');
        }

        $user = auth()->user();

        if (!$user->hasAnyRole(['admin', 'super-admin'])) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        return $next($request);
    }
}
