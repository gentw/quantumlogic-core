<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayPalWebhookJob;
use App\Services\PayPalGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    /**
     * Public route; verify-webhook-signature is the authentication. Verified
     * events queue and acknowledge — the job layer is replay-safe.
     */
    public function handle(Request $request, PayPalGateway $paypal): JsonResponse
    {
        $event = $request->json()->all();

        $headers = collect($request->headers->all())
            ->map(fn ($values) => $values[0] ?? '')
            ->all();

        if (! $paypal->verifyWebhook($headers, $event)) {
            Log::warning('PayPal webhook rejected', ['event_type' => $event['event_type'] ?? null]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        ProcessPayPalWebhookJob::dispatch(
            (string) ($event['event_type'] ?? ''),
            (array) ($event['resource'] ?? []),
        );

        return response()->json(['received' => true]);
    }
}
