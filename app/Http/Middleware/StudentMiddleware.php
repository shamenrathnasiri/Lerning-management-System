<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Only allows authenticated users with 'student', 'instructor', 'admin', or 'super-admin' role.
     * This is effectively an "enrolled user" check — every role can access student-level resources.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Please log in to access this page.');
        }

        $user = auth()->user();

        if (!$user->hasAnyRole(['student', 'instructor', 'admin', 'super-admin'])) {
            abort(403, 'Unauthorized. You must have a valid role to access this page.');
        }

        return $next($request);
    }
}
