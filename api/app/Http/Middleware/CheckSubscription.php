<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please login first.'
            ], 401);
        }

        $activeSub = $user->subscriptions()->where('status', 'active')->first();

        if (!$activeSub) {
            return response()->json([
                'message' => 'You need an active subscription or trial to access this feature.',
                'subscription_required' => true
            ], 403);
        }

        return $next($request);
    }
}
