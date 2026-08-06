<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        // Plan-tier subscriptions are retired (docs/modules/subscriptions/README.md).
        // The alias and the 403 JSON shape below stay untouched so re-enabling is
        // just the flag — the SPA still keys its /client/pricing redirect on them.
        if (! config('features.subscription_plans')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated. Please login first.',
            ], 401);
        }

        $entitlingStates = array_map(fn ($s) => $s->value, SubscriptionState::entitled());

        $subscription = $user->subscriptions()
            ->whereIn('state', $entitlingStates)
            ->orderByDesc('end_date')
            ->first();

        if (! $subscription || ! $subscription->isEntitled()) {
            return response()->json([
                'message' => 'You need an active subscription or trial to access this feature.',
                'subscription_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
