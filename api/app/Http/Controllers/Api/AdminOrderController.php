<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminOrderRequest;
use App\Http\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly ServiceOrderService $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = ServiceOrder::query()
            ->with(['user:id,name,surname,email', 'items'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$term}%")
                        ->orWhere('surname', 'like', "%{$term}%"));
            }))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 10), 100));

        return ServiceOrderResource::collection($orders)->response();
    }

    public function show(ServiceOrder $order): JsonResponse
    {
        $order->load(['user:id,name,surname,email', 'items', 'invoices', 'recurringPlans.service:id,name']);

        return response()->json([
            'order' => new ServiceOrderResource($order),
            'client' => $order->user,
            'deposit_split' => $this->orders->depositSplit($order),
        ]);
    }

    public function store(AdminOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = $this->orders->create(
            User::findOrFail($validated['user_id']),
            $validated['lines'],
            [
                'deposit_percent' => $validated['deposit_percent'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'account_manager_id' => $validated['account_manager_id'] ?? $request->user()->id,
            ],
        );

        return (new ServiceOrderResource($order->load('items')))->response()->setStatusCode(201);
    }

    /** State transitions from the order detail screen. */
    public function transition(Request $request, ServiceOrder $order): ServiceOrderResource
    {
        $validated = $request->validate([
            'action' => ['required', 'in:submit,activate,startDelivery,complete,cancel'],
        ]);

        $order = match ($validated['action']) {
            'submit' => $this->orders->submit($order),
            'activate' => $this->orders->activate($order),
            'startDelivery' => $this->orders->startDelivery($order),
            'complete' => $this->orders->complete($order),
            'cancel' => $this->orders->cancel($order),
        };

        return new ServiceOrderResource($order->load('items'));
    }

    /** Orders of one client, for the create-invoice picker. */
    public function forClient(User $user): JsonResponse
    {
        return ServiceOrderResource::collection(
            $user->serviceOrders()->with('items')->latest()->get()
        )->response();
    }
}
