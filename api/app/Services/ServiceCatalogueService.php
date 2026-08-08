<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceCoupon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Catalogue CRUD and pricing resolution. Catalogue entries with live orders
 * or plans are deactivated, never hard-deleted — the FK layer enforces the
 * same with restrictOnDelete.
 */
class ServiceCatalogueService
{
    /** @return Collection<int, Service> */
    public function list(bool $includeInactive = false): Collection
    {
        return Service::query()
            ->when(! $includeInactive, fn ($q) => $q->active())
            ->orderBy('sort_order')
            ->get();
    }

    /** The catalogue a logged-out prospect may see and order from. */
    public function publicCatalogue(): Collection
    {
        return Service::query()->publiclyOrderable()->orderBy('sort_order')->get();
    }

    public function create(array $data): Service
    {
        $data['slug'] ??= Str::slug($data['name']);

        return Service::create($data);
    }

    public function update(Service $service, array $data): Service
    {
        $service->update($data);

        return $service->refresh();
    }

    public function deactivate(Service $service): Service
    {
        $service->update(['active' => false, 'is_publicly_orderable' => false]);

        return $service->refresh();
    }

    /** The net unit price for a line: explicit override, else catalogue default. */
    public function priceFor(Service $service, ?float $override = null): float
    {
        return $override ?? (float) $service->default_price_net;
    }

    /** The VAT rate a line starts from: explicit override, else the service's. */
    public function vatRateFor(Service $service, ?float $override = null): float
    {
        return $override ?? (float) $service->vat_rate;
    }

    /**
     * Resolve a coupon code for a service, or null if it cannot be redeemed.
     *
     * Deliberately silent about *why* it failed: unknown, expired, exhausted and
     * wrong-service all return null, so a public form cannot be used to probe
     * which codes exist.
     */
    public function couponFor(?string $code, ?Service $service = null): ?ServiceCoupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $coupon = ServiceCoupon::redeemable()
            ->where('code', mb_strtolower(trim($code)))
            ->first();

        if ($coupon === null) {
            return null;
        }

        return $service === null || $coupon->appliesTo($service) ? $coupon : null;
    }

    /**
     * Count a redemption. Called once the order exists, not at quote time —
     * pricing a basket must not burn a single-use code.
     */
    public function redeemCoupon(ServiceCoupon $coupon): void
    {
        $coupon->increment('used_count');
    }
}
