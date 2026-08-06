<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
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
