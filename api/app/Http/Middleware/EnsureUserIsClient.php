<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsClient
{
    public function handle($request, Closure $next)
    {
        // Check if the authenticated user is an agent
        if (Auth::check() && Auth::user()->role === 'client') {
            return $next($request);
        }

        // If not, return an unauthorized response
        return response()->json(['error' => 'Unauthorized'], 403);
    }
}
