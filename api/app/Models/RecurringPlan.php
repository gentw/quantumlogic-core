<?php

namespace App\Models;

use App\Enums\RecurringPlanState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_order_id',
        'service_id',
        'payment_method_id',
        'interval',
        'amount_net',
        'vat_rate',
        'currency',
        'state',
        'current_period_start',
        'current_period_end',
        'next_charge_at',
        'failure_count',
        'last_failure_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'state' => RecurringPlanState::class,
        'amount_net' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'next_charge_at' => 'datetime',
        'failure_count' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
