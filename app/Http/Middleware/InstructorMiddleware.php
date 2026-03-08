<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstructorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Only allows users with 'instructor', 'admin', or 'super-admin' role.
     * Admins can access instructor routes for management purposes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Please log in to access this page.');
        }

        $user = auth()->user();

        if (!$user->hasAnyRole(['instructor', 'admin', 'super-admin'])) {
            abort(403, 'Unauthorized. Instructor access required.');
        }

        return $next($request);
    }
}
