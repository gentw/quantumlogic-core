<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'code',
        'label',
        'discount_percent',
        'active',
        'expires_at',
        'max_uses',
        'used_count',
        'created_by_user_id',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /** Codes are matched case-insensitively; stored lowercase. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = mb_strtolower(trim($value));
    }

    /** Null service = applies to anything publicly orderable. */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Usable in principle — whether it applies to a given service is separate. */
    public function isRedeemable(): bool
    {
        return $this->active && ! $this->isExpired() && ! $this->isExhausted();
    }

    public function appliesTo(Service $service): bool
    {
        return $this->service_id === null || $this->service_id === $service->id;
    }

    public function scopeRedeemable($query)
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }
}
