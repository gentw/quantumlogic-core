<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStripeWebhookJob;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    /**
     * Public route, signature is the authentication. Verify synchronously
     * (cheap), queue the work, acknowledge fast — Stripe retries handlers
     * that dawdle, and the job layer is replay-safe anyway.
     */
    public function handle(Request $request, StripeGateway $stripe): JsonResponse
    {
        try {
            $event = $stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature')
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Stripe webhook rejected', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        ProcessStripeWebhookJob::dispatch($event->type, $event->data->object->toArray());

        return response()->json(['received' => true]);
    }
}
