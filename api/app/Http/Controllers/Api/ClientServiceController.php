<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecurringPlanResource;
use App\Http\Resources\ServiceOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The client's "My Services" view: their orders and recurring plans. */
class ClientServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = $user->serviceOrders()
            ->where('status', '!=', ServiceOrderStatus::Draft->value)
            ->with('items')
            ->latest()
            ->get();

        $plans = $user->recurringPlans()
            ->with('service:id,name')
            ->latest()
            ->get();

        return response()->json([
            'orders' => ServiceOrderResource::collection($orders),
            'recurring_plans' => RecurringPlanResource::collection($plans),
        ]);
    }
}
