<?php

namespace App\Http\Middleware;

use App\Exceptions\TrialAbuseException;
use App\Services\TrialService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventTrialAbuse
{
    public function __construct(private readonly TrialService $trials) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $this->trials->assertEligible(
                $user,
                $request->ip(),
                $request->header('X-Trial-Fingerprint'),
            );
        } catch (TrialAbuseException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'reason' => $e->reason,
                'trial_blocked' => true,
            ], 403);
        }

        return $next($request);
    }
}
