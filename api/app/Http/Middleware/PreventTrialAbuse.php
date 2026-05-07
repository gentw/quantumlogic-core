<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Invoice;

class PreventTrialAbuse
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $ip = $request->ip();
        $fingerprint = $request->header('X-Trial-Fingerprint');

        if (!$fingerprint) {
            return response()->json([
                'message' => 'Invalid device fingerprint.'
            ], 403);
        }

        // 🔒 Check existing trial usage
        $trialUsed = Invoice::where(function ($q) use ($user, $ip, $fingerprint) {
            $q->where('user_id', $user->id)
              ->orWhere('trial_fingerprint', $fingerprint)
              ->orWhere('ip_address', $ip);
        })
        ->where('is_trial', true)
        ->where('status', 'paid')
        ->exists();

        if ($trialUsed) {
            return response()->json([
                'message' => 'Trial already used.',
                'trial_blocked' => true
            ], 403);
        }

        return $next($request);
    }
}
