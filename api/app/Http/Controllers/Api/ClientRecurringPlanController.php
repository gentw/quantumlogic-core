<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecurringPlanResource;
use App\Models\RecurringPlan;
use App\Services\RecurringBillingService;
use Illuminate\Http\Request;

class ClientRecurringPlanController extends Controller
{
    public function __construct(
        private readonly RecurringBillingService $recurring,
    ) {}

    public function pause(Request $request, RecurringPlan $plan): RecurringPlanResource
    {
        abort_unless($plan->user_id === $request->user()->id, 403);

        return new RecurringPlanResource($this->recurring->pause($plan)->load('service'));
    }

    public function cancel(Request $request, RecurringPlan $plan): RecurringPlanResource
    {
        abort_unless($plan->user_id === $request->user()->id, 403);

        return new RecurringPlanResource($this->recurring->cancel($plan)->load('service'));
    }
}
