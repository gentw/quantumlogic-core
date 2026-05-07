<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','package_id','status','start_date','end_date','billing_cycle','recurring_payment_method','raiffeisen_payment_id','paypal_payment_id','last_payment_status',
    'trial_used', 'auto_renew'
    ];

    protected $dates = ['start_date', 'end_date'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function package() { 
        return $this->belongsTo(Package::class); 
    }

    public function invoices() { 
        return $this->hasMany(Invoice::class); 
    }

    // Helper to check if payment is due
    public function isPaymentDue()
    {
        return $this->status === 'active' && Carbon::now()->gte($this->end_date);
    }

    /**
     * Check if this subscription uses recurring payments.
     *
     * @return bool
     */
    public function hasRecurringPayment()
    {
        // User opted-in for auto-renewal
        return in_array($this->recurring_payment_method, ['cc', 'paypal', 'bank_transfer']);
    }

    /**
     * Get a human-readable description of the recurring payment method.
     *
     * @return string
     */
    public function recurringPaymentMethod()
    {
        return match ($this->recurring_payment_method) {
            'cc' => 'Credit Card',
            'paypal' => 'PayPal',
            'bank_transfer' => 'Bank Transfer',
            default => 'None',
        };
    }
}
