<?php

namespace App\Models;

use App\Enums\ServiceOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'account_manager_id',
        'currency',
        'subtotal_net',
        'discount_total',
        'vat_total',
        'total_gross',
        'deposit_percent',
        'notes',
        'reverse_charge',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => ServiceOrderStatus::class,
        'subtotal_net' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'vat_total' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'deposit_percent' => 'decimal:2',
        'reverse_charge' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function items()
    {
        return $this->hasMany(ServiceOrderItem::class)->orderBy('sort_order');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function recurringPlans()
    {
        return $this->hasMany(RecurringPlan::class);
    }
}
