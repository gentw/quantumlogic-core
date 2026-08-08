<?php

namespace App\Models;

use App\Enums\BillingType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'billing_type',
        'default_price_net',
        'default_billing_interval',
        'vat_rate',
        'supports_deposit',
        'default_deposit_percent',
        'is_publicly_orderable',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'billing_type' => BillingType::class,
        'default_price_net' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'default_deposit_percent' => 'decimal:2',
        'supports_deposit' => 'boolean',
        'is_publicly_orderable' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function orderItems()
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function coupons()
    {
        return $this->hasMany(ServiceCoupon::class);
    }

    public function recurringPlans()
    {
        return $this->hasMany(RecurringPlan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopePubliclyOrderable($query)
    {
        return $query->where('active', true)->where('is_publicly_orderable', true);
    }
}
