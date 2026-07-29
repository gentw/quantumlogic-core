<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecurringPlanResource;
use App\Models\RecurringPlan;
use App\Models\ServiceOrder;
use App\Services\RecurringBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRecurringPlanController extends Controller
{
    public function __construct(
        private readonly RecurringBillingService $recurring,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = RecurringPlan::query()
            ->with(['service:id,name', 'user:id,name,surname,email'])
            ->when($request->query('state'), fn ($q, $state) => $q->where('state', $state))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return RecurringPlanResource::collection($plans)->response();
    }

    /** Start recurring revenue off an order line (hosting, retainers). */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_order_id' => ['required', 'integer', 'exists:service_orders,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'interval' => ['required', 'in:monthly,yearly'],
            'amount_net' => ['required', 'numeric', 'min:0.01'],
            'vat_rate' => ['required', 'numeric', 'between:0,100'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'first_charge_at' => ['nullable', 'date'],
        ]);

        $order = ServiceOrder::findOrFail($validated['service_order_id']);

        $plan = $order->recurringPlans()->create([
            'user_id' => $order->user_id,
            'service_id' => $validated['service_id'],
            'payment_method_id' => $validated['payment_method_id'] ?? null,
            'interval' => $validated['interval'],
            'amount_net' => $validated['amount_net'],
            'vat_rate' => $validated['vat_rate'],
            'currency' => $order->currency ?? 'EUR',
            'state' => \App\Enums\RecurringPlanState::Active,
            'next_charge_at' => $validated['first_charge_at'] ?? now(),
        ]);

        return (new RecurringPlanResource($plan->load('service')))->response()->setStatusCode(201);
    }

    public function manage(Request $request, RecurringPlan $plan): RecurringPlanResource
    {
        $validated = $request->validate([
            'action' => ['required', 'in:pause,resume,cancel'],
        ]);

        $plan = match ($validated['action']) {
            'pause' => $this->recurring->pause($plan),
            'resume' => $this->recurring->resume($plan),
            'cancel' => $this->recurring->cancel($plan),
        };

        return new RecurringPlanResource($plan->load('service'));
    }

    /** New price applies from the next cycle. */
    public function changePrice(Request $request, RecurringPlan $plan): RecurringPlanResource
    {
        $validated = $request->validate([
            'amount_net' => ['required', 'numeric', 'min:0.01'],
        ]);

        return new RecurringPlanResource(
            $this->recurring->changePrice($plan, (float) $validated['amount_net'])->load('service')
        );
    }
}
