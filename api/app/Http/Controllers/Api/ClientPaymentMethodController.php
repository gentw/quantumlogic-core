<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientPaymentMethodController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(
            $request->user()->paymentMethods()->latest('is_default')->latest()->get()
        );
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless($paymentMethod->user_id === $request->user()->id, 403);

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        if ($wasDefault) {
            $request->user()->paymentMethods()->first()?->update(['is_default' => true]);
        }

        return response()->json(['deleted' => true]);
    }

    public function setDefault(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        abort_unless($paymentMethod->user_id === $request->user()->id, 403);

        $request->user()->paymentMethods()->where('id', '!=', $paymentMethod->id)->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true]);

        return response()->json(['is_default' => true]);
    }
}
