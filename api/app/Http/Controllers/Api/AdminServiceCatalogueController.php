<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCoupon;
use App\Services\ServiceCatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServiceCatalogueController extends Controller
{
    public function __construct(
        private readonly ServiceCatalogueService $catalogue,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogue->list(includeInactive: (bool) $request->query('all', false)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(
            ['data' => $this->catalogue->create($this->validated($request))],
            201
        );
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogue->update($service, $this->validated($request, $service)),
        ]);
    }

    public function deactivate(Service $service): JsonResponse
    {
        return response()->json(['data' => $this->catalogue->deactivate($service)]);
    }

    /** Discount codes for this service's personalised order links. */
    public function coupons(Service $service): JsonResponse
    {
        return response()->json([
            'data' => $service->coupons()->latest('id')->get()->map($this->shapeCoupon(...)),
        ]);
    }

    public function storeCoupon(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:service_coupons,code'],
            'label' => ['nullable', 'string', 'max:255'],
            'discount_percent' => ['required', 'numeric', 'between:0.01,100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ]);

        $coupon = $service->coupons()->create($validated + [
            'active' => true,
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->shapeCoupon($coupon)], 201);
    }

    public function updateCoupon(Request $request, ServiceCoupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'discount_percent' => ['sometimes', 'numeric', 'between:0.01,100'],
            'expires_at' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'active' => ['boolean'],
        ]);

        $coupon->update($validated);

        return response()->json(['data' => $this->shapeCoupon($coupon->refresh())]);
    }

    /**
     * Codes are deactivated rather than deleted: a redeemed one is the record
     * of why an order carries the discount it does.
     */
    public function deactivateCoupon(ServiceCoupon $coupon): JsonResponse
    {
        $coupon->update(['active' => false]);

        return response()->json(['data' => $this->shapeCoupon($coupon->refresh())]);
    }

    /** @return array<string, mixed> */
    private function shapeCoupon(ServiceCoupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'label' => $coupon->label,
            'discount_percent' => (float) $coupon->discount_percent,
            'active' => $coupon->active,
            'expires_at' => $coupon->expires_at,
            'max_uses' => $coupon->max_uses,
            'used_count' => $coupon->used_count,
            'redeemable' => $coupon->isRedeemable(),
            'order_url' => rtrim(config('app.frontend_url'), '/')
                .'/order/'.$coupon->service?->slug.'?coupon='.$coupon->code,
        ];
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'name' => [$service ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:services,slug'.($service ? ','.$service->id : '')],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:64'],
            'billing_type' => [$service ? 'sometimes' : 'required', 'in:one_off,recurring,milestone'],
            'default_price_net' => ['nullable', 'numeric', 'min:0'],
            'default_billing_interval' => ['nullable', 'in:monthly,yearly'],
            'vat_rate' => ['nullable', 'numeric', 'between:0,100'],
            'supports_deposit' => ['boolean'],
            'default_deposit_percent' => ['nullable', 'numeric', 'between:1,100'],
            'is_publicly_orderable' => ['boolean'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
