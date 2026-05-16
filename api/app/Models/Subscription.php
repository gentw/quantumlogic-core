<?php

namespace App\Models;

use App\Enums\SubscriptionState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'status',
        'state',
        'start_date',
        'end_date',
        'grace_period_ends_at',
        'cancelled_at',
        'billing_cycle',
        'recurring_payment_method',
        'cc_payment_id',
        'paypal_payment_id',
        'bank_transfer_payment_id',
        'payment_method_token',
        'payment_method_brand',
        'last_payment_status',
        'renewal_failure_count',
        'trial_used',
        'trial_started_at',
        'trial_used_at',
        'trial_ip',
        'trial_device_hash',
        'auto_renew',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'trial_started_at' => 'datetime',
        'trial_used_at' => 'datetime',
        'state' => SubscriptionState::class,
        'auto_renew' => 'boolean',
        'trial_used' => 'boolean',
        'renewal_failure_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    // should th euser has access
    public function isEntitled(): bool
    {
        if (! $this->state instanceof SubscriptionState || ! $this->state->entitlesAccess()) {
            return false;
        }

        return $this->end_date && $this->end_date->isFuture();
    }

    public function isPaymentDue(): bool
    {
        return $this->state === SubscriptionState::Active
            && $this->end_date
            && Carbon::now()->gte($this->end_date);
    }

    public function hasRecurringPayment(): bool
    {
        return in_array($this->recurring_payment_method, ['cc', 'paypal', 'bank_transfer'], true);
    }

    public function recurringPaymentMethod(): string
    {
        return match ($this->recurring_payment_method) {
            'cc' => 'Credit Card',
            'paypal' => 'PayPal',
            'bank_transfer' => 'Bank Transfer',
            default => 'None',
        };
    }
}
